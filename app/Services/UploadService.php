<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UploadService
{
    // 이미지: 최대 5MB, 문서: 최대 20MB, HTML: 최대 2MB
    private const MAX_SIZE_KB = [
        'image' => 5 * 1024,
        'document' => 20 * 1024,
        'html' => 2 * 1024,
    ];

    // svg는 이미지 확장자지만 XML 안에 <script>를 담을 수 있어(embedded XSS) 허용하지 않는다.
    // 업로드된 파일은 public/uploads 아래 정적 파일로 그대로 서빙되고(Content-Disposition:
    // attachment를 강제하는 서버 설정에 의존할 수 없는 공유호스팅 환경 전제), 게시글 첨부파일
    // 링크는 <a download>일 뿐 실제 다운로드를 강제하지 않아, svg를 직접 열면 그 안의 스크립트가
    // 이 사이트 origin에서 그대로 실행된다(로그인 회원/비회원 누구나 업로드 가능한 경로라 위험).
    private const ALLOWED_EXTENSIONS = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp', 'txt'],
        'html' => ['html'],
    ];

    // 실행 파일 확장자는 어떤 유형이든 절대 허용하지 않는다.
    private const BLOCKED_EXTENSIONS = ['php', 'php3', 'php4', 'php5', 'phtml', 'asp', 'aspx', 'exe', 'sh', 'cgi'];

    /**
     * 파일 업로드.
     *
     * @param  string  $type  업로드 디렉토리(uploads/{type}/{Y}/{m}/) 및 검증 카테고리 결정용 (images/files/pages/policies)
     * @return string 저장된 상대 경로 (public/ 기준, 예: uploads/images/2026/07/abc123.png)
     */
    public function upload(UploadedFile $file, string $type): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw new RuntimeException(__('허용되지 않는 파일 형식입니다.'));
        }

        $category = $this->resolveCategory($type, $extension);

        if (! in_array($extension, self::ALLOWED_EXTENSIONS[$category], true)) {
            throw new RuntimeException(__('허용되지 않는 파일 형식입니다.'));
        }

        $maxKb = self::MAX_SIZE_KB[$category];
        if ($file->getSize() > $maxKb * 1024) {
            throw new RuntimeException(__('파일 크기가 제한을 초과했습니다.'));
        }

        // MIME 타입도 함께 검증 (확장자 위조 대응)
        if (! $this->mimeMatchesCategory($file, $category)) {
            throw new RuntimeException(__('허용되지 않는 파일 형식입니다.'));
        }

        $storedName = uniqid().'.'.$extension;
        $relativeDir = 'uploads/'.$type.'/'.date('Y').'/'.date('m');

        // 'uploads' 디스크는 throw=>false라 권한 문제 등으로 실제 쓰기가 실패해도 예외 없이
        // false만 반환한다. 반환값을 확인하지 않으면 DB에는 경로가 저장되는데 실제 파일은
        // 서버에 생성되지 않는 "유령 경로"가 생긴다(공유호스팅에서 uploads/ 폴더 쓰기 권한이
        // 없을 때 실제로 겪은 문제) — 반드시 성공 여부를 확인해서 실패를 눈에 보이게 한다.
        if (! Storage::disk('uploads')->putFileAs($relativeDir, $file, $storedName)) {
            throw new RuntimeException(__('파일을 서버에 저장하지 못했습니다. uploads 폴더의 쓰기 권한을 확인해 주세요.'));
        }

        return $relativeDir.'/'.$storedName;
    }

    public function delete(string $path): void
    {
        Storage::disk('uploads')->delete($path);
    }

    // AI가 생성해 로컬 임시경로에 내려받은 이미지처럼, HTTP 업로드가 아니라 서버 자체에서
    // 만들어진 파일을 uploads 디스크에 저장할 때 사용한다. upload()와 동일한 확장자
    // 화이트리스트를 통과시켜, AI 응답이 예상 밖 형식을 반환해도 그대로 저장되지 않게 한다.
    public function uploadFromPath(string $sourcePath, string $type, string $extension): string
    {
        $extension = strtolower($extension);

        if (! in_array($extension, self::ALLOWED_EXTENSIONS['image'], true)) {
            throw new RuntimeException(__('허용되지 않는 파일 형식입니다.'));
        }

        $detectedMime = mime_content_type($sourcePath) ?: '';
        if (! str_starts_with($detectedMime, 'image/')) {
            throw new RuntimeException(__('허용되지 않는 파일 형식입니다.'));
        }

        $storedName = uniqid().'.'.$extension;
        $relativeDir = 'uploads/'.$type.'/'.date('Y').'/'.date('m');

        if (! Storage::disk('uploads')->put($relativeDir.'/'.$storedName, file_get_contents($sourcePath))) {
            throw new RuntimeException(__('파일을 서버에 저장하지 못했습니다. uploads 폴더의 쓰기 권한을 확인해 주세요.'));
        }

        return $relativeDir.'/'.$storedName;
    }

    public function url(string $path): string
    {
        return url($path);
    }

    // 배너/팝업/페이지 등을 복제할 때, 복제본이 원본과 같은 파일을 그대로 가리키게 두면 둘 중
    // 하나만 지워도(deleteUploadedFileUsing) 실제 파일이 사라져 나머지 하나도 이미지가 깨진다.
    // 복제본은 항상 물리적으로 별도의 파일을 가져야 안전하다 — 같은 디렉토리에 새 파일명으로
    // 복사만 하고(재검증 없이, 원본이 이미 통과한 파일이므로), 새 상대 경로를 반환한다.
    public function duplicate(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $directory = pathinfo($path, PATHINFO_DIRNAME);
        $newPath = $directory.'/'.uniqid().($extension !== '' ? '.'.$extension : '');

        if (! Storage::disk('uploads')->copy($path, $newPath)) {
            throw new RuntimeException(__('파일을 복제하지 못했습니다.'));
        }

        return $newPath;
    }

    // pages 타입은 html 전용, images 타입은 이미지 전용. 그 외(files, media 등)는
    // 확장자를 보고 이미지/문서 중 어느 카테고리에 속하는지 자동 판별한다.
    private function resolveCategory(string $type, string $extension): string
    {
        if ($type === 'pages' || $type === 'policies') {
            return 'html';
        }

        if ($type === 'images') {
            return 'image';
        }

        return in_array($extension, self::ALLOWED_EXTENSIONS['image'], true) ? 'image' : 'document';
    }

    private function mimeMatchesCategory(UploadedFile $file, string $category): bool
    {
        $mime = $file->getMimeType() ?: '';

        return match ($category) {
            'image' => str_starts_with($mime, 'image/'),
            'html' => in_array($mime, ['text/html', 'text/plain'], true),
            'document' => true, // 문서 MIME은 확장자별로 매우 다양하므로 확장자 화이트리스트로 1차 검증됨
            default => false,
        };
    }
}
