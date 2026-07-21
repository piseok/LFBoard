<?php
/**
 * LFboard 설치 마법사
 *
 * SSH 없이 웹 브라우저에서 초기 설치를 진행합니다.
 * 설치가 완료되면 반드시 이 파일을 삭제하거나 이름을 변경하세요.
 */

declare(strict_types=1);

// index.php와 동일한 이유로 self-detect — public/ 하위(로컬 개발)와 저장소 루트(main 배포)
// 양쪽에서 이 파일이 동작해야 한다.
$rootPath = is_dir(__DIR__.'/vendor') ? __DIR__ : dirname(__DIR__);
$envPath = $rootPath.'/.env';
$lockPath = $rootPath.'/storage/installed.lock';
$uploadsPath = __DIR__.'/uploads';

session_start();

// 이미 설치된 경우 즉시 접근 차단한다. 단, 방금 설치를 완료한 세션이 완료 화면
// (Step 5, install.php 자동 삭제 버튼)을 볼 수 있도록 해당 세션만 예외로 허용한다.
if (file_exists($lockPath) && empty($_SESSION['install_done'])) {
    http_response_code(403);
    echo installLayout('설치 완료됨', 0, '<div class="alert alert-info"><p>이 사이트는 이미 설치가 완료되었습니다.</p>'
        .'<p>재설치가 필요하다면 서버에서 <code>storage/installed.lock</code> 파일을 삭제한 뒤 다시 시도하세요.</p>'
        .'<p>보안을 위해 <code>install.php</code> 파일이 아직 남아있다면 즉시 삭제하세요.</p></div>');
    exit;
}

if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
if (! isset($_SESSION['install_max_step'])) {
    $_SESSION['install_max_step'] = 1;
}
if (! isset($_SESSION['install_db'])) {
    $_SESSION['install_db'] = [];
}
if (! isset($_SESSION['install_site'])) {
    $_SESSION['install_site'] = [];
}

// ---------------------------------------------------------------------
// 유틸리티 함수
// ---------------------------------------------------------------------

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function verifyCsrf(): bool
{
    $token = $_POST['_csrf'] ?? '';

    return is_string($token) && hash_equals($_SESSION['install_csrf'], $token);
}

function ensureWritableDir(string $path): bool
{
    // mkdir()의 두 번째 인자(0775)는 서버의 umask에 의해 그대로 안 먹힐 수 있다 — 흔한 022
    // umask에서는 0775가 실제로 0755로 만들어져 group-write 비트가 빠진다(PHP-FPM이 소유자가
    // 아니라 그룹으로만 걸쳐 있는 이 서버 환경에서는 이게 곧 "쓰기 안 됨"으로 이어진다).
    // mkdir 직후(혹은 이미 존재하지만 안 써지는 디렉터리에도) chmod로 명시적으로 강제한다.
    if (! is_dir($path)) {
        @mkdir($path, 0775, true);
    }

    if (is_dir($path) && ! is_writable($path)) {
        @chmod($path, 0775);
    }

    return is_dir($path) && is_writable($path);
}

// ensureWritableDir()의 단순 성공/실패만으로는 uploads 실패 원인을 알 수 없다는 피드백을 받아
// 추가한 진단용 함수 — 실패한 경로별로 현재 존재 여부/권한/소유자/PHP 실행 계정을 보여준다.
function diagnoseUnwritableDir(string $path): ?string
{
    if (! is_dir($path)) {
        return "{$path} — 디렉터리가 존재하지 않고 생성도 실패했습니다.";
    }

    if (is_writable($path)) {
        return null;
    }

    $perms = substr(sprintf('%o', fileperms($path)), -4);
    $ownerUid = fileowner($path);
    $ownerName = function_exists('posix_getpwuid') ? (posix_getpwuid($ownerUid)['name'] ?? null) : null;
    $currentUser = function_exists('posix_getpwuid') && function_exists('posix_geteuid')
        ? (posix_getpwuid(posix_geteuid())['name'] ?? null)
        : get_current_user();

    return "{$path} — 현재 권한: {$perms}, 소유자: ".($ownerName ?? $ownerUid).", PHP 실행 계정: {$currentUser} (권한 또는 소유자가 일치하지 않아 쓰기 불가)";
}

// PHP_BINARY는 "현재 이 스크립트를 실행 중인 SAPI의 실행파일"을 가리킨다. CLI로 실행 중이면
// php 바이너리가 맞지만, 이 서버처럼 PHP-FPM으로 돌고 있으면 PHP_BINARY가 php-fpm 바이너리
// 자체를 가리켜서 `php-fpm artisan migrate` 같은 명령이 되어버린다(php-fpm은 artisan을 인식
// 못 하고 자기 사용법만 출력) — CLI 바이너리를 별도로 찾아야 한다.
function resolvePhpCliBinary(): string
{
    if (PHP_SAPI === 'cli') {
        return PHP_BINARY;
    }

    $candidates = [
        PHP_BINDIR.'/php',
        '/usr/bin/php',
        '/usr/local/bin/php',
        '/usr/bin/php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
        '/usr/local/php/bin/php',
    ];

    foreach ($candidates as $candidate) {
        if (is_executable($candidate)) {
            return $candidate;
        }
    }

    if (function_exists('shell_exec')) {
        $which = trim((string) @shell_exec('command -v php 2>/dev/null'));
        if ($which !== '' && is_executable($which)) {
            return $which;
        }
    }

    // 다 실패하면 원래 값이라도 반환 — 최소한 실행 로그에 실제로 뭘 시도했는지는 남는다.
    return PHP_BINARY;
}

function runArtisan(string $rootPath, array $args): array
{
    if (! function_exists('exec')) {
        return [false, 'exec() 함수가 비활성화되어 있어 artisan 명령을 실행할 수 없습니다. 서버 관리자에게 문의하세요.'];
    }

    $cmd = escapeshellarg(resolvePhpCliBinary()).' '.escapeshellarg($rootPath.'/artisan').' '
        .implode(' ', array_map('escapeshellarg', $args)).' 2>&1';

    exec($cmd, $outputLines, $returnVar);

    return [$returnVar === 0, implode("\n", $outputLines)];
}

// 호스팅사마다 실제 서버가 MySQL 8, MySQL 5.x, MariaDB 등으로 다르다. 접속 테스트 시점에
// 서버가 스스로 알려주는 버전 문자열(@@version)로 종류/버전을 감지해서, 관리자가 직접
// DB_CONNECTION 값을 알 필요 없이 .env에 올바른 드라이버(mysql/mariadb)를 자동으로 써준다.
// 이 프로젝트는 JSON 컬럼(admin_permissions 등)을 쓰므로 네이티브 JSON 타입이 필요하다
// (MySQL 5.7.8+ / MariaDB 10.2+). 그보다 낮으면 설치를 막고 명확히 안내한다.
function detectDbServer(PDO $pdo): array
{
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $isMariaDb = stripos($version, 'mariadb') !== false;

    if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $m)) {
        [$full, $major, $minor, $patch] = $m;
    } else {
        $major = $minor = $patch = 0;
    }

    if ($isMariaDb) {
        $driver = 'mariadb';
        $versionOk = ((int) $major > 10) || ((int) $major === 10 && (int) $minor >= 2);
        $minRequired = 'MariaDB 10.2';
    } else {
        $driver = 'mysql';
        $versionOk = ((int) $major > 5) || ((int) $major === 5 && (int) $minor >= 7);
        $minRequired = 'MySQL 5.7';
    }

    return [
        'driver' => $driver,
        'version' => $version,
        'version_ok' => $versionOk,
        'min_required' => $minRequired,
    ];
}

function testDbConnection(string $host, string $port, string $database, string $username, string $password): array
{
    // DB 이름은 반드시 이 형식이어야 한다 — 백틱만 제거하는 정도로는 부족하고(설치 마법사
    // 단계라 이미 진짜 DB 자격증명을 가진 사람만 도달 가능한 낮은 위험이긴 하지만, CREATE
    // DATABASE 구문에 그대로 이어붙이는 값이라 안전한 식별자 형식으로 확실히 제한해 둔다).
    if (! preg_match('/^[a-zA-Z0-9_$]+$/', $database)) {
        return [false, 'DB 이름은 영문/숫자/밑줄(_)/달러($)만 사용할 수 있습니다.', null];
    }

    try {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_TIMEOUT => 5]);
        $server = detectDbServer($pdo);

        if (! $server['version_ok']) {
            return [false, "감지된 서버: {$server['version']} — 이 버전은 지원 최소 사양({$server['min_required']} 이상) 미만입니다. 호스팅사에 DB 버전을 문의하세요.", null];
        }

        $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        $label = $server['driver'] === 'mariadb' ? 'MariaDB' : 'MySQL';

        return [true, "데이터베이스 연결에 성공했습니다. (감지된 서버: {$label} {$server['version']} — DB_CONNECTION={$server['driver']}로 자동 설정됩니다)", $server];
    } catch (PDOException $e) {
        return [false, '연결 실패: '.$e->getMessage(), null];
    }
}

function installLayout(string $title, int $step, string $content): string
{
    $steps = ['환경 체크', 'DB 연결 설정', '사이트 기본 설정', '설치 실행', '완료'];
    $stepIndicator = '';

    if ($step >= 1 && $step <= 5) {
        $items = '';
        foreach ($steps as $i => $label) {
            $n = $i + 1;
            $cls = $n === $step ? 'step-current' : ($n < $step ? 'step-done' : 'step-pending');
            $items .= '<li class="'.$cls.'"><span class="step-num">'.$n.'</span><span class="step-label">'.h($label).'</span></li>';
        }
        $stepIndicator = '<ol class="steps">'.$items.'</ol><p class="step-caption">Step '.$step.' / 5</p>';
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title} - LFboard 설치</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        padding: 40px 16px;
        background: #f3f4f6;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Malgun Gothic", Arial, sans-serif;
        color: #1f2937;
        line-height: 1.6;
    }
    .wrap { max-width: 640px; margin: 0 auto; }
    .card {
        background: #ffffff;
        border-radius: 10px;
        padding: 32px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    h1 { font-size: 20px; margin: 0 0 4px; }
    h2 { font-size: 16px; margin: 24px 0 8px; }
    p { margin: 8px 0; }
    .subtitle { color: #6b7280; font-size: 14px; margin-bottom: 24px; }
    .steps { list-style: none; display: flex; padding: 0; margin: 0 0 8px; gap: 4px; }
    .steps li {
        flex: 1;
        text-align: center;
        font-size: 12px;
        color: #9ca3af;
        border-top: 3px solid #e5e7eb;
        padding-top: 8px;
    }
    .steps li .step-num {
        display: inline-block;
        width: 18px; height: 18px;
        border-radius: 50%;
        background: #e5e7eb;
        color: #6b7280;
        font-size: 11px;
        line-height: 18px;
        margin-right: 4px;
    }
    .steps li.step-current { color: #92400e; border-top-color: #f59e0b; font-weight: bold; }
    .steps li.step-current .step-num { background: #f59e0b; color: #fff; }
    .steps li.step-done { color: #065f46; border-top-color: #10b981; }
    .steps li.step-done .step-num { background: #10b981; color: #fff; }
    .step-caption { text-align: right; font-size: 12px; color: #9ca3af; margin: 0 0 24px; }
    table.checklist { width: 100%; border-collapse: collapse; margin: 12px 0; }
    table.checklist td { padding: 8px 4px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
    table.checklist td.status { width: 70px; text-align: center; font-weight: bold; }
    .ok { color: #059669; }
    .fail { color: #dc2626; }
    label { display: block; font-size: 13px; font-weight: 600; margin: 14px 0 4px; color: #374151; }
    input[type=text], input[type=password], input[type=email], input[type=number], select, textarea {
        width: 100%;
        padding: 9px 10px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        font-family: inherit;
    }
    .hint { font-size: 12px; color: #6b7280; margin-top: 4px; }
    .row2 { display: flex; gap: 12px; }
    .row2 > div { flex: 1; }
    .actions { margin-top: 28px; display: flex; justify-content: space-between; gap: 8px; }
    button, .btn {
        display: inline-block;
        border: none;
        border-radius: 6px;
        padding: 10px 20px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
    }
    .btn-primary { background: #f59e0b; color: #fff; }
    .btn-primary:hover { background: #d97706; }
    .btn-secondary { background: #e5e7eb; color: #374151; }
    .btn-secondary:hover { background: #d1d5db; }
    .btn-danger { background: #dc2626; color: #fff; }
    .btn:disabled, button:disabled { background: #d1d5db; color: #9ca3af; cursor: not-allowed; }
    .alert { padding: 12px 14px; border-radius: 6px; margin: 14px 0; font-size: 14px; }
    .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    .alert-warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    code { background: #f3f4f6; padding: 1px 5px; border-radius: 4px; font-size: 13px; }
    pre.log { background: #111827; color: #d1fae5; padding: 12px; border-radius: 6px; font-size: 12px; overflow-x: auto; white-space: pre-wrap; }
    @media (max-width: 480px) {
        .row2 { flex-direction: column; }
        .steps li .step-label { display: none; }
        .card { padding: 20px; }
    }
</style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>LFboard 설치 마법사</h1>
        <p class="subtitle">Laravel 12 + Filament 4 기반 LFboard 초기 설치</p>
        {$stepIndicator}
        {$content}
    </div>
</div>
</body>
</html>
HTML;
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="'.h($_SESSION['install_csrf']).'">';
}

// ---------------------------------------------------------------------
// 현재 단계 결정 (세션의 max_step을 넘어서는 이동은 차단)
// ---------------------------------------------------------------------

$requestedStep = isset($_GET['step']) ? (int) $_GET['step'] : $_SESSION['install_max_step'];
$currentStep = max(1, min(5, $requestedStep));
if ($currentStep > $_SESSION['install_max_step']) {
    $currentStep = $_SESSION['install_max_step'];
}
// 설치가 이미 완료된 세션은 이전 단계(재설치)로 되돌아갈 수 없고 완료 화면만 볼 수 있다.
if (! empty($_SESSION['install_done'])) {
    $currentStep = 5;
}

$errors = [];
$notice = '';

// ---------------------------------------------------------------------
// STEP 1 — 환경 체크
// ---------------------------------------------------------------------

if ($currentStep === 1) {
    $requiredExtensions = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo'];

    $checks = [];
    $checks[] = [
        'label' => 'PHP 버전 (8.3 이상)',
        'ok' => version_compare(PHP_VERSION, '8.3.0', '>='),
        'detail' => 'v'.PHP_VERSION,
    ];

    foreach ($requiredExtensions as $ext) {
        $checks[] = [
            'label' => "PHP 확장: {$ext}",
            'ok' => extension_loaded($ext),
            'detail' => extension_loaded($ext) ? '설치됨' : '설치되지 않음',
        ];
    }

    $checks[] = [
        'label' => 'uploads 디렉토리 쓰기 권한',
        'ok' => ensureWritableDir($uploadsPath),
        'detail' => $uploadsPath,
    ];
    $checks[] = [
        'label' => 'storage/framework 쓰기 권한',
        'ok' => ensureWritableDir($rootPath.'/storage/framework/sessions')
            && ensureWritableDir($rootPath.'/storage/framework/views')
            && ensureWritableDir($rootPath.'/storage/framework/cache/data'),
        'detail' => 'storage/framework/*',
    ];
    $checks[] = [
        'label' => 'storage/logs 쓰기 권한',
        'ok' => ensureWritableDir($rootPath.'/storage/logs'),
        'detail' => 'storage/logs',
    ];
    $checks[] = [
        'label' => 'bootstrap/cache 쓰기 권한',
        'ok' => ensureWritableDir($rootPath.'/bootstrap/cache'),
        'detail' => 'bootstrap/cache',
    ];
    // js/css/fonts는 `php artisan filament:assets`가 Filament 패키지의 컴파일된 파일을 새로
    // 쓰는 대상이다 — FTP로 처음 올릴 때 소유자가 PHP 실행 계정과 달라서 쓰기 권한이 없으면
    // 관리자 화면의 캐시/에셋 재생성 기능이 "Permission denied"로 실패한다(실제로 겪은 문제).
    $checks[] = [
        'label' => 'js/css/fonts 쓰기 권한 (Filament 에셋 재생성용)',
        'ok' => ensureWritableDir($rootPath.'/js')
            && ensureWritableDir($rootPath.'/css')
            && ensureWritableDir($rootPath.'/fonts'),
        'detail' => 'js, css, fonts',
    ];
    $checks[] = [
        'label' => '.env 파일 쓰기 권한',
        'ok' => file_exists($envPath) ? is_writable($envPath) : is_writable($rootPath),
        'detail' => '.env',
    ];

    // 저장소 루트 평탄화 배포(main)에서는 vendor/가 이 파일과 같은 위치에 있어야 정상인데,
    // 그 옆에 예전 배포 방식의 public/ 폴더가 남아있으면 bootstrap/app.php의 자체판별
    // (is_dir(base_path('public')))이 "평탄화 안 된 구조"로 착각해서 public_path()가
    // 저장소 루트가 아니라 그 잔재 폴더를 가리키게 된다 — 그 결과 업로드 파일이
    // public/uploads 밑에 생기는데 실제 서비스되는 uploads/ 경로와는 안 맞아 이미지가
    // 깨진다(실제로 겪은 문제). 새 파일을 덮어쓰기만 하는 FTP 배포라 이전 배포의
    // 잔재가 안 지워진 채로 남는 경우가 있다.
    $legacyPublicDir = $rootPath.'/public';
    if ($rootPath === __DIR__ && is_dir($legacyPublicDir)) {
        $checks[] = [
            'label' => '레거시 public/ 폴더 잔재 확인',
            'ok' => false,
            'detail' => "{$legacyPublicDir} 폴더가 남아있습니다 — 예전 배포 방식(또는 이전 설치)의 잔재로 보입니다. "
                .'이 폴더가 있으면 업로드 경로 판별이 꼬여 이미지가 깨질 수 있습니다. FTP로 이 폴더를 삭제한 뒤 다시 확인하세요.',
        ];
    }
    $checks[] = [
        'label' => 'exec() 함수 사용 가능 (마이그레이션 자동 실행용)',
        'ok' => function_exists('exec') && ! in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true),
        'detail' => '자동 설치에 필요합니다',
    ];

    $allOk = true;
    $rows = '';
    foreach ($checks as $check) {
        if (! $check['ok']) {
            $allOk = false;
        }
        $status = $check['ok'] ? '<span class="ok">✔ 정상</span>' : '<span class="fail">✘ 실패</span>';
        $rows .= '<tr><td>'.h($check['label']).'</td><td class="status">'.$status.'</td></tr>';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['action']) && $_POST['action'] === 'next') {
        if ($allOk) {
            $_SESSION['install_max_step'] = max($_SESSION['install_max_step'], 2);
            header('Location: install.php?step=2');
            exit;
        }
        $errors[] = '모든 환경 체크 항목을 통과해야 다음 단계로 진행할 수 있습니다.';
    }

    $errorHtml = $errors ? '<div class="alert alert-error">'.implode('<br>', array_map('h', $errors)).'</div>' : '';
    $csrfFieldHtml = csrfField();
    $nextDisabledAttr = $allOk ? '' : ' disabled';

    $content = <<<HTML
<h2>1. 서버 환경 체크</h2>
<p class="hint">설치를 진행하기 전에 서버 환경이 요구사항을 충족하는지 확인합니다.</p>
{$errorHtml}
<table class="checklist">{$rows}</table>
<form method="post">
    {$csrfFieldHtml}
    <input type="hidden" name="action" value="next">
    <div class="actions">
        <span></span>
        <button type="submit" class="btn-primary"{$nextDisabledAttr}>다음 단계 →</button>
    </div>
</form>
HTML;

    echo installLayout('환경 체크', 1, $content);
    exit;
}

// ---------------------------------------------------------------------
// STEP 2 — DB 연결 설정
// ---------------------------------------------------------------------

if ($currentStep === 2) {
    $db = $_SESSION['install_db'] + [
        'host' => 'localhost',
        'port' => '3306',
        'database' => '',
        'username' => '',
        'password' => '',
        'prefix' => '',
        'driver' => 'mysql',
    ];

    $testResult = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
        $db['host'] = trim((string) ($_POST['host'] ?? ''));
        $db['port'] = trim((string) ($_POST['port'] ?? '3306'));
        $db['database'] = trim((string) ($_POST['database'] ?? ''));
        $db['username'] = trim((string) ($_POST['username'] ?? ''));
        $db['password'] = (string) ($_POST['password'] ?? '');
        $db['prefix'] = trim((string) ($_POST['prefix'] ?? ''));
        $_SESSION['install_db'] = $db;

        if ($db['host'] === '' || $db['database'] === '' || $db['username'] === '') {
            $errors[] = 'DB 호스트, 이름, 사용자명은 필수 입력 항목입니다.';
        } else {
            [$ok, $message, $server] = testDbConnection($db['host'], $db['port'], $db['database'], $db['username'], $db['password']);
            $testResult = ['ok' => $ok, 'message' => $message];
            $_SESSION['install_db_tested'] = $ok;
            if ($ok && $server) {
                $db['driver'] = $server['driver'];
                $_SESSION['install_db'] = $db;
            }

            if (($_POST['action'] ?? '') === 'next' && $ok) {
                $_SESSION['install_max_step'] = max($_SESSION['install_max_step'], 3);
                header('Location: install.php?step=3');
                exit;
            }
            if (($_POST['action'] ?? '') === 'next' && ! $ok) {
                $errors[] = '연결 테스트를 먼저 통과해야 다음 단계로 진행할 수 있습니다.';
            }
        }
    }

    $errorHtml = $errors ? '<div class="alert alert-error">'.implode('<br>', array_map('h', $errors)).'</div>' : '';
    $resultHtml = '';
    if ($testResult) {
        $resultHtml = '<div class="alert '.($testResult['ok'] ? 'alert-success' : 'alert-error').'">'.h($testResult['message']).'</div>';
    }

    $csrfFieldHtml = csrfField();
    $hostVal = h($db['host']);
    $portVal = h($db['port']);
    $dbVal = h($db['database']);
    $userVal = h($db['username']);
    $passVal = h($db['password']);
    $prefixVal = h($db['prefix']);

    $content = <<<HTML
<h2>2. 데이터베이스 연결 설정</h2>
{$errorHtml}
{$resultHtml}
<form method="post">
    {$csrfFieldHtml}
    <div class="row2">
        <div>
            <label>DB 호스트</label>
            <input type="text" name="host" value="{$hostVal}" required>
        </div>
        <div>
            <label>DB 포트</label>
            <input type="text" name="port" value="{$portVal}" required>
        </div>
    </div>
    <label>DB 이름</label>
    <input type="text" name="database" value="{$dbVal}" required>
    <label>DB 사용자명</label>
    <input type="text" name="username" value="{$userVal}" required>
    <label>DB 비밀번호</label>
    <input type="password" name="password" value="{$passVal}">
    <label>테이블 접두사 (선택)</label>
    <input type="text" name="prefix" value="{$prefixVal}" placeholder="예: cms_">
    <div class="actions">
        <button type="submit" name="action" value="test" class="btn-secondary">연결 테스트</button>
        <button type="submit" name="action" value="next" class="btn-primary">다음 단계 →</button>
    </div>
</form>
HTML;

    echo installLayout('DB 연결 설정', 2, $content);
    exit;
}

// ---------------------------------------------------------------------
// STEP 3 — 사이트 기본 설정
// ---------------------------------------------------------------------

if ($currentStep === 3) {
    $site = $_SESSION['install_site'] + [
        'site_name' => 'LFboard',
        // 'admin@admin.com'은 시더가 내부적으로 쓰는 placeholder라, 여기 기본값으로 넣어두면
        // 세션이 어떤 이유로든 비어버렸을 때 폼에 그 값이 그대로 채워진 채로 보이고, 사용자가
        // 못 알아채고 그대로 제출하면 실제 관리자 이메일이 placeholder로 저장되는 사고가 난다
        // (실제로 발생했음). 기본값을 비워둬서 매번 직접 입력하게 만든다.
        'admin_email' => '',
        'admin_password' => '',
        'admin_password_confirm' => '',
        'timezone' => 'Asia/Seoul',
        'locale' => 'ko',
        'admin_path' => 'admin',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
        $site['site_name'] = trim((string) ($_POST['site_name'] ?? ''));
        $site['admin_email'] = trim((string) ($_POST['admin_email'] ?? ''));
        $site['admin_password'] = (string) ($_POST['admin_password'] ?? '');
        $site['admin_password_confirm'] = (string) ($_POST['admin_password_confirm'] ?? '');
        $site['timezone'] = trim((string) ($_POST['timezone'] ?? 'Asia/Seoul'));
        $site['locale'] = in_array($_POST['locale'] ?? 'ko', ['ko', 'en'], true) ? $_POST['locale'] : 'ko';
        $site['admin_path'] = trim((string) ($_POST['admin_path'] ?? 'admin'));
        $_SESSION['install_site'] = $site;

        if ($site['site_name'] === '') {
            $errors[] = '사이트명을 입력하세요.';
        }
        if (! filter_var($site['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = '올바른 관리자 이메일 주소를 입력하세요.';
        } elseif ($site['admin_email'] === 'admin@admin.com') {
            // 시더 내부 placeholder를 실제 관리자 이메일로 쓰지 못하게 막는다 — 이 값으로
            // 설치하면 나중에 재설치/재시딩 시 어떤 계정이 "진짜"인지 구분이 안 되는 문제가
            // 생긴다(실제로 이 값이 세션 기본값으로 잘못 제출된 사고가 있었음).
            $errors[] = "admin@admin.com은 내부적으로 예약된 주소라 관리자 이메일로 쓸 수 없습니다. 다른 이메일을 입력하세요.";
        }
        if (strlen($site['admin_password']) < 8) {
            $errors[] = '관리자 비밀번호는 최소 8자 이상이어야 합니다.';
        }
        if ($site['admin_password'] !== $site['admin_password_confirm']) {
            $errors[] = '비밀번호 확인이 일치하지 않습니다.';
        }
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $site['admin_path']) || $site['admin_path'] === '') {
            $errors[] = '관리자 경로는 영문, 숫자, -, _ 조합으로 입력하세요.';
        }

        if (! $errors) {
            $_SESSION['install_max_step'] = max($_SESSION['install_max_step'], 4);
            header('Location: install.php?step=4');
            exit;
        }
    }

    $errorHtml = $errors ? '<div class="alert alert-error">'.implode('<br>', array_map('h', $errors)).'</div>' : '';
    $csrfFieldHtml = csrfField();
    $siteNameVal = h($site['site_name']);
    $adminEmailVal = h($site['admin_email']);
    $timezoneVal = h($site['timezone']);
    $adminPathVal = h($site['admin_path']);
    $koSelected = $site['locale'] === 'ko' ? ' selected' : '';
    $enSelected = $site['locale'] === 'en' ? ' selected' : '';

    $content = <<<HTML
<h2>3. 사이트 기본 설정</h2>
{$errorHtml}
<form method="post">
    {$csrfFieldHtml}
    <label>사이트명</label>
    <input type="text" name="site_name" value="{$siteNameVal}" maxlength="100" required>

    <label>관리자 이메일</label>
    <input type="email" name="admin_email" value="{$adminEmailVal}" required>

    <label>관리자 비밀번호</label>
    <input type="password" name="admin_password" required>
    <p class="hint">최소 8자 이상 (관리자 계정은 보안을 위해 12자 이상 권장)</p>

    <label>관리자 비밀번호 확인</label>
    <input type="password" name="admin_password_confirm" required>

    <div class="row2">
        <div>
            <label>타임존</label>
            <input type="text" name="timezone" value="{$timezoneVal}">
        </div>
        <div>
            <label>기본 언어</label>
            <select name="locale">
                <option value="ko"{$koSelected}>한국어 (ko)</option>
                <option value="en"{$enSelected}>English (en)</option>
            </select>
        </div>
    </div>

    <label>관리자 접속 경로</label>
    <input type="text" name="admin_path" value="{$adminPathVal}" required>
    <p class="hint">기본값은 admin이며, 예: /{$adminPathVal} 로 접속합니다. 보안을 위해 변경을 권장합니다.</p>

    <div class="actions">
        <a href="install.php?step=2" class="btn-secondary btn">← 이전</a>
        <button type="submit" class="btn-primary">다음 단계 →</button>
    </div>
</form>
HTML;

    echo installLayout('사이트 기본 설정', 3, $content);
    exit;
}

// ---------------------------------------------------------------------
// STEP 4 — 설치 실행
// ---------------------------------------------------------------------

if ($currentStep === 4) {
    $log = [];
    $installOk = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && ($_POST['action'] ?? '') === 'install') {
        $db = $_SESSION['install_db'];
        $site = $_SESSION['install_site'];
        $installOk = true;

        // 세션에 담긴 관리자 비밀번호가 비어있거나(3단계를 건너뛰었거나, 뒤로가기 후 재제출 등
        // 어떤 경로로든) 너무 짧으면 여기서 그대로 진행하지 않는다 — 예전에는 이 값으로 그냥
        // password_hash('')를 만들어 관리자 계정 비밀번호를 빈 문자열 해시로 덮어써버리는
        // 사고가 있었다. 3단계 검증 규칙과 동일한 기준으로 다시 확인해서, 못 미치면 3단계로
        // 돌려보내 사용자가 비밀번호를 다시 입력하게 한다.
        if (strlen($site['admin_password'] ?? '') < 8) {
            header('Location: install.php?step=3');
            exit;
        }

        // 1. .env 파일 생성
        $appKey = 'base64:'.base64_encode(random_bytes(32));
        $envContent = buildEnvContent($appKey, $db, $site);
        if (@file_put_contents($envPath, $envContent) !== false) {
            $log[] = ['label' => '.env 파일 생성', 'ok' => true];
        } else {
            $log[] = ['label' => '.env 파일 생성', 'ok' => false, 'detail' => '파일 쓰기에 실패했습니다.'];
            $installOk = false;
        }

        // 2. APP_KEY는 위 .env 생성 시 함께 기록됨
        $log[] = ['label' => 'APP_KEY 생성', 'ok' => true];

        // 3. 설정 캐시 초기화 + 마이그레이션 실행
        if ($installOk) {
            runArtisan($rootPath, ['config:clear']);
            [$ok, $out] = runArtisan($rootPath, ['migrate', '--force']);
            $log[] = ['label' => 'DB 마이그레이션 실행', 'ok' => $ok, 'detail' => $out];
            if (! $ok) {
                $installOk = false;
            }
        }

        // 4. 기본 시더 실행 (관리자 계정 + site_settings 기본값 등)
        if ($installOk) {
            [$ok, $out] = runArtisan($rootPath, ['db:seed', '--force']);
            $log[] = ['label' => '기본 데이터 시딩', 'ok' => $ok, 'detail' => $out];
            if (! $ok) {
                $installOk = false;
            }
        }

        // 시딩 후 위자드에서 입력한 관리자 계정 정보 및 사이트 설정 반영
        if ($installOk) {
            try {
                $prefix = $db['prefix'] ?? '';
                $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4";
                $pdo = new PDO($dsn, $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

                $hash = password_hash($site['admin_password'], PASSWORD_BCRYPT, ['cost' => 12]);

                // 이전 시도에서 이미 admin@admin.com을 원하는 이메일로 바꿔놓은 상태에서 "설치
                // 시작"을 다시 누르면, db:seed --force가 AdminUserSeeder를 다시 실행해 매칭되는
                // admin@admin.com 행이 없으니 그 placeholder를 새로 하나 더 만들어버린다. 항상
                // "지금 위자드에서 입력한 이메일"을 기준으로 이미 존재하는지부터 확인해서(더 이상
                // admin@admin.com이라고 가정하지 않는다) 재시도를 안전하게 만들고, db:seed가 다시
                // 만들어낸 admin@admin.com(비밀번호가 기본값 admin1234로 남는 계정)은 매번
                // 무조건 정리해서 보안 구멍이 남지 않게 한다.
                $stmt = $pdo->prepare("SELECT id FROM `{$prefix}users` WHERE email = ? LIMIT 1");
                $stmt->execute([$site['admin_email']]);

                if ($stmt->fetch()) {
                    $pdo->prepare("UPDATE `{$prefix}users` SET password = ? WHERE email = ?")
                        ->execute([$hash, $site['admin_email']]);
                } else {
                    $pdo->prepare("UPDATE `{$prefix}users` SET email = ?, password = ? WHERE email = 'admin@admin.com'")
                        ->execute([$site['admin_email'], $hash]);
                }

                if ($site['admin_email'] !== 'admin@admin.com') {
                    $pdo->prepare("DELETE FROM `{$prefix}users` WHERE email = 'admin@admin.com'")->execute();
                }

                $settingsStmt = $pdo->prepare("UPDATE `{$prefix}site_settings` SET value = ? WHERE `key` = ?");
                $settingsStmt->execute([$site['site_name'], 'site_name']);
                $settingsStmt->execute([$site['admin_email'], 'admin_email']);
                $settingsStmt->execute([$site['admin_path'], 'admin_path']);

                $log[] = ['label' => '관리자 계정 및 사이트 정보 반영', 'ok' => true];
            } catch (PDOException $e) {
                // 위에서 이미 "먼저 조회 후 분기" 방식으로 처리하지만, 그래도 유니크 제약 충돌이
                // 나면(예: 다른 회원이 이미 같은 이메일을 쓰고 있는 경우) 원문 SQL 에러 대신
                // 무엇을 확인해야 하는지 바로 알 수 있는 메시지를 보여준다.
                $detail = str_contains($e->getMessage(), '1062')
                    ? "입력하신 관리자 이메일({$site['admin_email']})이 이미 다른 계정에서 사용 중입니다. 3단계로 돌아가 다른 이메일을 사용하거나, 재설치가 맞다면 DB에서 해당 계정 상태를 확인하세요."
                    : $e->getMessage();
                $log[] = ['label' => '관리자 계정 및 사이트 정보 반영', 'ok' => false, 'detail' => $detail];
                $installOk = false;
            } catch (Throwable $e) {
                $log[] = ['label' => '관리자 계정 및 사이트 정보 반영', 'ok' => false, 'detail' => $e->getMessage()];
                $installOk = false;
            }
        }

        // 5. uploads 디렉토리 구조 생성
        // .htaccess는 하위 폴더가 아니라 uploads 폴더 자체에 바로 쓴다 — 그런데 하위 폴더들만
        // ensureWritableDir()로 권한을 보정하고 uploads 폴더 자신은 아무도 보정하지 않아서,
        // uploads가 이미 (배포/FTP 업로드로) 존재하되 group-write가 없는 상태면 하위 폴더
        // 생성은 성공해도 .htaccess 쓰기만 계속 실패하는 상황이 된다.
        $uploadOk = ensureWritableDir($uploadsPath)
            && ensureWritableDir($uploadsPath.'/images')
            && ensureWritableDir($uploadsPath.'/files')
            && ensureWritableDir($uploadsPath.'/pages');
        // uploads/.htaccess는 배포 시점에 이미 저장소에서 그대로 딸려오는 파일이라(main 빌드에
        // 포함되어 있음) 대부분의 경우 이 시점엔 이미 존재한다. 이미 있으면(내용까지 같을 필요도
        // 없이 "보안 설정 자체가 있다"는 사실만 확인) 다시 쓸 필요가 없다 — 일부 호스팅은 PHP가
        // .htaccess 파일을 새로 만드는 것 자체를 보안상 막아둬서, 이미 있는데도 쓰기를 시도하면
        // 파일 내용은 멀쩡한데 "쓰기 실패"로만 잘못 보고되는 문제가 있었다.
        if (file_exists($uploadsPath.'/.htaccess')) {
            $htaccessOk = true;
        } else {
            $htaccess = <<<HTACCESS
Options -Indexes
<FilesMatch "\.(php|php3|php4|php5|phtml|asp|aspx|cgi|sh)$">
    Deny from all
</FilesMatch>
HTACCESS;
            $htaccessOk = @file_put_contents($uploadsPath.'/.htaccess', $htaccess) !== false;
        }

        $uploadDiagnostics = array_filter([
            diagnoseUnwritableDir($uploadsPath),
            diagnoseUnwritableDir($uploadsPath.'/images'),
            diagnoseUnwritableDir($uploadsPath.'/files'),
            diagnoseUnwritableDir($uploadsPath.'/pages'),
            ! $htaccessOk ? "{$uploadsPath}/.htaccess — 파일 쓰기 실패(디렉터리는 존재/쓰기 가능하지만 파일 생성이 거부됨)" : null,
        ]);

        $log[] = [
            'label' => 'uploads 디렉토리 및 보안 설정 생성',
            'ok' => $uploadOk && $htaccessOk,
            'detail' => implode("\n", $uploadDiagnostics),
        ];
        if (! ($uploadOk && $htaccessOk)) {
            $installOk = false;
        }

        // 6. storage 디렉토리 확인
        $storageOk = ensureWritableDir($rootPath.'/storage/framework/sessions')
            && ensureWritableDir($rootPath.'/storage/framework/views')
            && ensureWritableDir($rootPath.'/storage/framework/cache/data');
        $log[] = ['label' => 'storage/framework 디렉토리 확인', 'ok' => $storageOk];
        if (! $storageOk) {
            $installOk = false;
        }

        // 7. 설치 완료 플래그 생성
        if ($installOk) {
            $lockOk = @file_put_contents($lockPath, 'installed_at='.date('c')) !== false;
            $log[] = ['label' => '설치 완료 플래그 생성 (storage/installed.lock)', 'ok' => $lockOk];
            $installOk = $lockOk;
        }

        if ($installOk) {
            // DB 비밀번호는 설치 완료 즉시 세션에서 제거
            unset($_SESSION['install_db']['password']);
            $_SESSION['install_max_step'] = 5;
            $_SESSION['install_done'] = true;
        }

        $_SESSION['install_log'] = $log;
        $_SESSION['install_ok'] = $installOk;
    }

    $log = $_SESSION['install_log'] ?? [];
    $installOk = $_SESSION['install_ok'] ?? null;

    $rows = '';
    foreach ($log as $item) {
        $status = $item['ok'] ? '<span class="ok">✔ 완료</span>' : '<span class="fail">✘ 실패</span>';
        $detail = ! empty($item['detail']) ? '<pre class="log">'.h($item['detail']).'</pre>' : '';
        $rows .= '<tr><td>'.h($item['label']).$detail.'</td><td class="status">'.$status.'</td></tr>';
    }

    if ($installOk === true) {
        header('Location: install.php?step=5');
        exit;
    }

    $resultBanner = '';
    if ($installOk === false) {
        $resultBanner = '<div class="alert alert-error">설치 중 오류가 발생했습니다. 아래 로그를 확인한 후 다시 시도하세요.</div>';
    }

    $tableHtml = $rows ? '<table class="checklist">'.$rows.'</table>' : '';
    $csrfFieldHtml = csrfField();

    $content = <<<HTML
<h2>4. 설치 실행</h2>
<p class="hint">아래 버튼을 클릭하면 .env 생성, 마이그레이션, 기본 데이터 시딩, 업로드 디렉토리 생성이 순서대로 진행됩니다.</p>
{$resultBanner}
{$tableHtml}
<form method="post">
    {$csrfFieldHtml}
    <input type="hidden" name="action" value="install">
    <div class="actions">
        <a href="install.php?step=3" class="btn-secondary btn">← 이전</a>
        <button type="submit" class="btn-primary">설치 시작</button>
    </div>
</form>
HTML;

    echo installLayout('설치 실행', 4, $content);
    exit;
}

// ---------------------------------------------------------------------
// STEP 5 — 완료
// ---------------------------------------------------------------------

if ($currentStep === 5) {
    if (empty($_SESSION['install_done'])) {
        header('Location: install.php?step='.$_SESSION['install_max_step']);
        exit;
    }

    $site = $_SESSION['install_site'];
    $adminPath = $site['admin_path'] ?? 'admin';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && ($_POST['action'] ?? '') === 'delete_self') {
        @unlink(__FILE__);
        echo installLayout('삭제 완료', 0, '<div class="alert alert-success"><p>install.php 파일이 삭제되었습니다.</p>'
            .'<p><a class="btn btn-primary" href="/'.h($adminPath).'">관리자 페이지로 이동</a></p></div>');
        exit;
    }

    $csrfFieldHtml = csrfField();
    $adminPathVal = h($adminPath);

    $content = <<<HTML
<h2>5. 설치 완료</h2>
<div class="alert alert-success">설치가 성공적으로 완료되었습니다.</div>
<p><a class="btn btn-primary" href="/{$adminPathVal}">관리자 패널로 이동 (/{$adminPathVal})</a></p>

<div class="alert alert-warn">
    <p><strong>보안 안내</strong></p>
    <p>설치가 끝났으므로 install.php 파일을 즉시 삭제하거나 이름을 변경하세요.
    이 파일이 남아있으면 보안에 위협이 될 수 있습니다.</p>
</div>

<form method="post" onsubmit="return confirm('install.php 파일을 삭제하시겠습니까?');">
    {$csrfFieldHtml}
    <input type="hidden" name="action" value="delete_self">
    <div class="actions">
        <span></span>
        <button type="submit" class="btn-danger">install.php 자동 삭제</button>
    </div>
</form>
HTML;

    echo installLayout('설치 완료', 5, $content);
    exit;
}

// ---------------------------------------------------------------------
// .env 생성 헬퍼
// ---------------------------------------------------------------------

function buildEnvContent(string $appKey, array $db, array $site): string
{
    $appName = addslashes($site['site_name'] ?? 'LFboard');
    $timezone = $site['timezone'] ?: 'Asia/Seoul';
    $locale = $site['locale'] ?: 'ko';
    $adminPath = $site['admin_path'] ?: 'admin';
    // Step 2 연결 테스트에서 감지한 실제 서버 종류(MySQL/MariaDB)를 그대로 쓴다.
    $dbConnection = in_array($db['driver'] ?? 'mysql', ['mysql', 'mariadb'], true) ? $db['driver'] : 'mysql';

    $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $appUrl = $scheme.'://'.$host;
    // 설치를 진행 중인 접속 프로토콜이 HTTPS이면 세션 쿠키도 secure로 강제해 평문 전송을 막는다.
    $sessionSecureCookie = $scheme === 'https' ? 'true' : 'false';

    return <<<ENV
APP_NAME="{$appName}"
APP_ENV=production
APP_KEY={$appKey}
APP_DEBUG=false
APP_URL={$appUrl}
APP_TIMEZONE={$timezone}
ADMIN_PATH={$adminPath}

APP_LOCALE={$locale}
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=ko_KR

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DAILY_DAYS=7
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION={$dbConnection}
DB_HOST={$db['host']}
DB_PORT={$db['port']}
DB_DATABASE={$db['database']}
DB_USERNAME={$db['username']}
DB_PASSWORD={$db['password']}
DB_PREFIX={$db['prefix']}

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE={$sessionSecureCookie}
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=file

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=587
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="{$site['admin_email']}"
MAIL_FROM_NAME="{$appName}"

VITE_APP_NAME="{$appName}"

ENV;
}
