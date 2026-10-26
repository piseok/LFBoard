#!/usr/bin/env bash
# dev 브랜치의 최신 내용을 main(vendor 포함 배포 브랜치)에 반영하고, 로컬 dev 작업 환경도
# 원래대로 복원하는 스크립트입니다. Git Bash(Windows) 또는 WSL/Linux에서 실행하세요.
#
# 왜 필요한가: main은 소스코드 + composer 프로덕션 vendor/를 함께 커밋해 둔 브랜치로,
# 카페24 등 SSH 가능한 공유호스팅에 composer 없이 바로 올릴 수 있게 만든 배포용 브랜치입니다.
# dev에서 코드가 바뀔 때마다(특히 composer.json/composer.lock이 바뀔 때) 이 스크립트로
# main을 다시 만들어야 배포용 vendor가 최신 상태로 유지됩니다.
#
# 사용법: scripts/build-deploy-branch.sh
# (저장소 루트에서 실행. dev에 커밋 안 된 변경사항이 있으면 중단합니다.)
#
# Windows Docker Desktop에서 vendor처럼 파일 수가 많은 디렉토리를 바인드 마운트 경로에
# 직접 composer install 하면 심각하게 느려지거나 멈추는 문제가 있어(알려진 이슈), 컨테이너의
# 네이티브 파일시스템(/tmp)에서 빌드한 뒤 결과물만 docker cp로 꺼내오는 방식을 씁니다.

set -euo pipefail

# Windows Git Bash(MSYS)는 앞에 슬래시가 붙은 인자(/tmp/... 등)를 자동으로 Windows 경로로
# 바꿔버려서 컨테이너 안 docker exec 명령에 엉뚱한 경로가 전달되는 문제가 있다. 이 변수를 켜두면
# 그 자동 변환을 끈다(Linux/WSL에서는 존재하지 않는 변수라 그냥 무시되어 문제 없음).
export MSYS_NO_PATHCONV=1

CONTAINER="${DEPLOY_CONTAINER:-cms-admin-laravel.test-1}"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="/tmp/deploy_build"

cd "$REPO_ROOT"

if [ -n "$(git status --porcelain)" ]; then
  echo "오류: 작업 디렉토리에 커밋 안 된 변경사항이 있습니다. 먼저 커밋하거나 정리하세요." >&2
  git status --short >&2
  exit 1
fi

current_branch="$(git rev-parse --abbrev-ref HEAD)"
if [ "$current_branch" != "dev" ]; then
  echo "오류: dev 브랜치에서 실행해야 합니다(현재: $current_branch)." >&2
  exit 1
fi

echo "== 1/6: main으로 전환하고 dev의 새 커밋을 cherry-pick합니다 =="
git checkout main

# 2026-10-26: dev 의 vendor/(dev 에서는 .gitignore 대상)가 디스크에 남은 채로 main 으로 오면, main 이 추적하지 않는 파일은 '미추적'으로 남는다
# (예: Filament 업그레이드로 dev vendor 에만 생긴 새 파일). 이어지는 cherry-pick 이 그 경로를 추가하려 하면 git 이
# "untracked working tree files would be overwritten" 로 cherry-pick 을 시작도 못 하고 실패하고, 아래 `--skip` 이
# "no cherry-pick in progress" 로 죽는다(Filament 4.15.3 모의 실행 2회차에서 재현). vendor 는 4단계에서 어차피 통째로 다시 만들고
# 6단계에서 dev 용으로 복원하므로, 여기서 미추적 vendor 파일만 먼저 치운다(main 이 추적하는 파일은 건드리지 않음).
git clean -fdq -- vendor

# 2026-09-02 — main과 dev는 git 상 완전히 무관한 히스토리다(git merge-base main dev가 아무것도
# 반환하지 않음 — 과거 어느 시점에 LFBoard 저장소가 dev와 진짜 조상 관계 없이 별도로 구성된
# 것으로 추정). 그래서 예전의 `git merge dev`는 이제 항상 "refusing to merge unrelated
# histories"로 실패한다. 병합 대신, main 루트의 마커 파일에 "마지막으로 main에 반영한 dev 커밋"을
# 기록해두고 그 이후 dev 커밋만 순서대로 cherry-pick하는 방식으로 바꾼다.
DEPLOY_MARKER_FILE="${REPO_ROOT}/.deploy-source-commit"

if [ ! -f "$DEPLOY_MARKER_FILE" ]; then
  echo "오류: $DEPLOY_MARKER_FILE이 main에 없습니다." >&2
  echo "  main/dev가 unrelated-histories라 '어디까지 이미 포팅됐는지' 자동으로 알 수 없습니다." >&2
  echo "  최초 1회만 수동으로 시드하세요(이미 main에 반영된 마지막 dev 커밋의 SHA를 적어야 함):" >&2
  echo "    echo '<커밋 SHA>' > $DEPLOY_MARKER_FILE && git add .deploy-source-commit && git commit -m 'Seed deploy marker'" >&2
  exit 1
fi

last_synced="$(tr -d '[:space:]' < "$DEPLOY_MARKER_FILE")"

if ! git cat-file -e "${last_synced}^{commit}" 2>/dev/null; then
  echo "오류: $DEPLOY_MARKER_FILE에 적힌 커밋(${last_synced})을 찾을 수 없습니다." >&2
  exit 1
fi

if ! git merge-base --is-ancestor "$last_synced" dev; then
  echo "오류: ${last_synced}이 dev 히스토리의 조상이 아닙니다 — $DEPLOY_MARKER_FILE 값을 확인하세요." >&2
  exit 1
fi

# 2026-10-21: 병합 커밋(협업자 PR 머지 등)은 cherry-pick 이 "-m 없이는 병합 커밋을 고를 수 없다"며 즉시 실패하고(진행 중 cherry-pick 이 없어 --skip 도 실패 → set -e 로 스크립트 중단)
# 병합이 가져온 변경은 범위 안의 일반 커밋들로 이미 들어 있으므로 --no-merges 로 제외한다.
pick_list="$(git log --reverse --no-merges --format=%H "${last_synced}..dev")"

if [ -z "$pick_list" ]; then
  echo "dev에 새 커밋이 없습니다 — cherry-pick할 것이 없습니다."
else
  echo "다음 커밋을 main에 순서대로 cherry-pick합니다:"
  git log --reverse --no-merges --oneline "${last_synced}..dev"

  # main엔 원래부터 없어야 하는 dev 전용 경로(하네스/문서) — cherry-pick 중 이 경로에서만
  # modify/delete 충돌이 나면 "삭제 유지"로 자동 해결한다. 그 외 경로 충돌은 사람이 봐야 한다.
  dev_only_pattern='^(\.claude/|\.github/|CLAUDE\.md$|PROJECT\.md$|claude_code_prompts\.md$)'

  for sha in $pick_list; do
    if ! git cherry-pick "$sha" >/tmp/cherry_pick_output_$$.log 2>&1; then
      unresolvable=""
      for f in $(git diff --name-only --diff-filter=U); do
        if printf '%s\n' "$f" | grep -qE "$dev_only_pattern"; then
          git rm -f --ignore-unmatch -- "$f" >/dev/null 2>&1 || rm -f -- "$f"
        else
          # 2026-10-21: main 은 public/ 을 평탄화해 둔 브랜치라 dev 의 public/… 경로와 어긋난 "추가/삭제" 충돌이
          # 나는 경우가 있다(js/home-banner-tabs.js 추가·삭제, install.php 삭제). 이 두 모양은 dev 쪽 의도를
          # 그대로 따르면 되므로 자동으로 푼다(임시 worktree 모의 실행으로 26커밋이 끝까지 적용됨을 확인):
          #   UA/AA/AU/DU(그 커밋이 파일을 추가했거나 우리 쪽에 없음) → 그 커밋의 버전을 채택
          #   UD(그 커밋이 파일을 삭제함) → 삭제를 수용
          # 둘 다 아닌 내용 충돌(UU 등)은 여전히 사람이 본다.
          case "$(git status --short -- "$f" | cut -c1-2)" in
            UA|AA|AU|DU)
              if git checkout --theirs -- "$f" 2>/dev/null && git add -- "$f"; then
                echo "  (자동 해결: $f → 이 커밋의 버전 채택)"
              else
                unresolvable="$unresolvable $f"
              fi
              ;;
            UD)
              if git rm -f -- "$f" >/dev/null 2>&1; then
                echo "  (자동 해결: $f → 이 커밋이 삭제한 파일, 삭제 수용)"
              else
                unresolvable="$unresolvable $f"
              fi
              ;;
            *)
              unresolvable="$unresolvable $f"
              ;;
          esac
        fi
      done
      if [ -n "$unresolvable" ]; then
        echo "오류: ${sha} cherry-pick 중 자동으로 해결할 수 없는 충돌이 발생했습니다:" >&2
        echo "$unresolvable" >&2
        cat "/tmp/cherry_pick_output_$$.log" >&2
        echo "수동으로 해결한 뒤 'git cherry-pick --continue', 그 다음 이 스크립트를 다시 실행하세요." >&2
        rm -f "/tmp/cherry_pick_output_$$.log"
        exit 1
      fi
      if git diff --cached --quiet; then
        # 이 커밋의 변경사항이 전부 dev 전용 경로였던 경우 — main엔 반영할 내용이 없음
        git cherry-pick --skip
      else
        git cherry-pick --continue --no-edit
      fi
    fi
    rm -f "/tmp/cherry_pick_output_$$.log"
  done
fi

echo "== deploy marker 갱신 =="
new_dev_head="$(git rev-parse dev)"
echo "$new_dev_head" > "$DEPLOY_MARKER_FILE"
git add .deploy-source-commit
if git diff --cached --quiet; then
  echo "마커 변경 없음(이미 최신)."
else
  git commit -m "Update deploy source marker to ${new_dev_head:0:8}"
fi

# dev를 병합할 때마다 dev 전용 .github/workflows(CI)와 dev용 전체 .gitignore가 그대로
# 딸려 들어온다. main은 다운받아서 그대로 서버에 올리는 배포 스냅샷이라 CI 워크플로우가
# 필요 없고, .gitignore도 dev 브랜치 설명 주석/개발 도구 항목 없이 안전 관련 항목(.env,
# storage/installed.lock 등 — 없으면 나중에 실수로 git add -A 했을 때 비밀값이 커밋될 위험이
# 있어 완전히 없애지는 않음)만 남긴 최소 버전으로 매번 다시 정리한다.
echo "== main 전용 정리: CI 워크플로우 제거, .gitignore 최소화 =="
# --ignore-unmatch: .github가 이미 지워져 있는 상태(다음 실행부터는 항상 이 상태)에서도
# "pathspec이 아무것도 안 맞는다"는 이유로 실패하지 않게 한다(rm -rf + git add -A 조합은
# 이 경우 실패했었음 — 처음 정리할 때는 통과했지만 두 번째 실행부터 이 문제가 드러났다).
git rm -rf --ignore-unmatch --quiet .github >/dev/null 2>&1 || true
rm -rf .github
# 2026-10-21: dev 전용 경로(.claude 문서·하네스 등)가 '섞인 커밋'(코드+문서)으로 cherry-pick 돼 main 에 들어오는 일이 있었다
# (충돌 자동 해결은 dev 전용 경로만 바뀐 경우에만 동작). main 은 배포 스냅샷이라 이 경로가 필요 없으므로 매번 제거한다.
git rm -rf --ignore-unmatch --quiet .claude CLAUDE.md PROJECT.md claude_code_prompts.md >/dev/null 2>&1 || true
rm -rf CLAUDE.md PROJECT.md claude_code_prompts.md
cat > .gitignore <<'GITIGNORE_EOF'
.env
.env.backup
.env.production
/storage/installed.lock
/storage/*.key
/storage/logs/*.log
/storage/framework/cache/data/*
/storage/framework/cache/purifier/*
/storage/framework/sessions/*
/storage/framework/views/*
!storage/**/.gitignore
!storage/**/.gitkeep
/public/uploads/**/*
!/public/uploads/**/.gitkeep
!/public/uploads/.htaccess
/*-deploy.zip
GITIGNORE_EOF
git add .gitignore
if git diff --cached --quiet -- .github .claude CLAUDE.md PROJECT.md claude_code_prompts.md .gitignore; then
  echo "정리할 내용이 없습니다(이미 정리된 상태)."
else
  git commit -m "Remove CI workflow and use minimal .gitignore on main"
fi

# 2026-10-26: dev 의 public/{css,js,fonts}/filament 는 `php artisan filament:assets` 가 vendor/filament/*/dist 에서
# 복사해 낸 '생성물'이다. main 은 public/ 을 평탄화하므로 같은 생성물이 루트의 {css,js,fonts}/filament 에 있고,
# 아래 3b 단계가 새 vendor 로부터 그 루트 쪽을 통째로 다시 만든다. 문제는 cherry-pick 이다: dev 의 이 경로 변경을 옮길 때
# git 의 rename 탐지가 '내용이 같은' vendor/filament/*/dist 로 잘못 짝지어(Filament 4.11.7→4.15.3 때 실제로 발생)
# 변경이 vendor 쪽에만 반영되고 평탄화된 루트 에셋은 옛 버전으로 남았다. (vendor PHP 는 신버전·JS/CSS 는 구버전 → repeater/
# builder/toggle-buttons 같은 컴포넌트 파손 위험.) 짝지어지지 않은 채 public/…/filament 가 main 에 새로 생기면 평탄화의
# git mv 가 js/js 처럼 중첩시킬 수 있으므로, 평탄화 전에 이 경로를 미리 제거한다(dev 쪽 원본은 영향 없음).
echo "== main 전용 정리: dev 의 Filament 생성 에셋(public/{css,js,fonts}/filament) 제거 =="
if [ -n "$(git ls-files -- public/css/filament public/js/filament public/fonts/filament)" ]; then
  git rm -rf --ignore-unmatch --quiet -- public/css/filament public/js/filament public/fonts/filament
  git commit -m "Drop dev's public/*/filament assets from main (regenerated from vendor in the build)"
else
  echo "제거할 내용이 없습니다(이미 정리된 상태)."
fi
rm -rf public/css/filament public/js/filament public/fonts/filament

# 도큐먼트 루트를 public/ 하위 폴더로 지정할 수 없는(또는 서버 담당자에게 요청해야 하는)
# 공유호스팅 대응 — main은 저장소 전체를 그대로 웹 루트(예: public_html)에 올리는 배포
# 스냅샷이므로 public/ 안의 공개 파일들을 저장소 루트로 평탄화한다. index.php/install.php/
# bootstrap/app.php는 이미 vendor/ 위치로 자기가 public/ 밑인지 저장소 루트인지 스스로
# 판별하도록 만들어져 있어(self-detect), 여기서는 파일 내용을 건드릴 필요 없이 물리적으로
# 옮기기만 하면 된다. public/이 이미 없으면(두 번째 실행부터) 그대로 건너뛴다.
echo "== main 전용 정리: public/ 평탄화(도큐먼트 루트 하위 폴더 지정이 불가능한 호스팅 대응) =="
if [ -d public ]; then
  # dev 컨테이너에서 tinker로 테스트 파일을 업로드했다가 지우면(UploadService::delete()는
  # 파일만 지우고 이제 빈 부모 디렉토리는 남긴다) public/uploads/... 같은 빈 디렉토리가 로컬에
  # 계속 남는다. .gitignore 대상이라 git엔 안 잡히지만 파일시스템엔 그대로 있어서, 예전 방식
  # (find + git mv 일괄 실행)은 이미 평탄화된 동명 디렉토리(uploads/, images/ 등)와 부딪혀
  # "destination already exists"로 매번 죽었다(이 세션에서만 3번). git이 추적하는 항목만
  # git mv하고, 안 추적하는데 이미 평탄화된 위치가 있으면 찌꺼기로 보고 지운다.
  # git add -A는 절대 쓰지 않는다 — 저장소 루트에 있을 수 있는 무관한 untracked 파일(윈도우
  # 예약 장치명 "nul", .idea/ 등)까지 통째로 인덱싱하려다 "error: unable to index file 'nul'"로
  # 전체가 실패한 적이 있다(git mv/rmdir까지는 다 되고 이 한 줄에서만 죽는 상황). 그래서 여기서
  # 실제로 옮긴 경로만 개별적으로 git add한다(git mv는 이미 자동으로 스테이징해줌).
  # dotglob 없이는 public/*가 .htaccess 같은 숨김파일을 건너뛴다 — 그러면 아래 rmdir이 "폴더가 안
  # 비어있어서" 조용히 실패하고(|| true), .htaccess는 영영 루트로 못 올라간 채 public/ 통째로
  # 잔재로 남는다(실제로 겪은 문제 — 루트 .htaccess가 없어서 rewrite가 전혀 안 먹혀 "/"만 빼고
  # 전부 404가 났었음). 이 루프 안에서만 켰다가 바로 끈다.
  shopt -s dotglob
  for item in public/*; do
    [ -e "$item" ] || continue
    name="$(basename "$item")"
    if git ls-files --error-unmatch "$item" > /dev/null 2>&1; then
      git mv "$item" "$name"
    elif [ -e "$name" ]; then
      echo "  (git 미추적 + 이미 평탄화된 위치 있음 — 로컬 테스트 잔재로 보고 삭제: $item)"
      rm -rf "$item"
    else
      mv "$item" "$name"
      git add -- "$name"
    fi
  done
  shopt -u dotglob
  rmdir public 2>/dev/null || true
  if git diff --cached --quiet; then
    echo "평탄화할 내용이 없습니다."
  else
    git commit -m "Flatten public/ into repo root for hosts without subfolder document root"
  fi
fi

echo "== 2/6: 컨테이너 네이티브 파일시스템에 소스를 복사합니다 (vendor/.git/node_modules 제외) =="
docker exec "$CONTAINER" rm -rf "$BUILD_DIR"
docker exec "$CONTAINER" mkdir -p "$BUILD_DIR"
docker exec "$CONTAINER" sh -c "
  cd /var/www/html && tar \
    --exclude='./vendor' --exclude='./.git' --exclude='./node_modules' \
    --exclude='./storage/framework/cache' --exclude='./storage/framework/sessions' \
    --exclude='./storage/framework/views' --exclude='./storage/logs' \
    -cf - . | (cd $BUILD_DIR && tar -xf -)
"

echo "== 3/6: 프로덕션 vendor를 빌드합니다 (--no-dev --optimize-autoloader) =="
docker exec "$CONTAINER" sh -c "cd $BUILD_DIR && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts"

# 2026-10-26: Filament 의 js/css/fonts 에셋은 vendor/filament/*/dist 의 '복사본'이라 vendor 와 항상 같은 버전이어야 한다.
# main 은 public/ 이 없으니 BUILD_DIR(= main 의 평탄화된 사본)에서 public_path() 가 저장소 루트(bootstrap/app.php 의
# usePublicPath)를 가리키고, 여기서 `filament:assets` 를 돌리면 루트의 js|css|fonts/filament 가 새 vendor 기준으로 만들어진다.
# cherry-pick 에 맡기지 않고 매번 통째로 다시 만드는 이유: dev 의 public/…/filament 변경을 git 이 vendor/…/dist 로 잘못 짝지어
# 평탄화된 루트 에셋이 옛 버전으로 남은 사고가 있었다(위 '생성 에셋 제거' 주석). 항상 지우고 재생성하므로 멱등이고, 이전 버전의
# 해시 이름 폰트 같은 잔재도 남지 않는다. .env 는 지워서 개발자 환경(DB·캐시 설정)과 무관하게 같은 결과가 나오게 한다.
# BUILD_DIR 에는 storage/framework/{cache,sessions,views}·storage/logs 가 없어(2단계 tar 에서 제외) 뷰 컴파일러가 부팅 중 죽으므로 만들어 준다.
# bootstrap/cache 의 packages.php·services.php 는 dev(개발 의존성 포함) 기준으로 만들어진 복사본이라, --no-dev vendor 에 없는 Pail/Sail/Collision
# 프로바이더를 가리켜 부팅이 죽을 수 있으므로 지운다(없으면 Laravel 이 새 vendor 기준으로 다시 만든다 — BUILD_DIR 안에서만).
echo "== 3b/6: Filament 에셋(js/css/fonts)을 새 vendor 기준으로 재생성합니다 (filament:assets) =="
docker exec "$CONTAINER" sh -c "
  cd $BUILD_DIR &&
  rm -f .env &&
  find bootstrap/cache -type f ! -name '.gitignore' -delete &&
  rm -rf js/filament css/filament fonts/filament &&
  mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views &&
  php artisan filament:assets --no-interaction >/tmp/filament_assets_\$\$.log 2>&1 || { cat /tmp/filament_assets_\$\$.log; exit 1; }
  rm -f /tmp/filament_assets_\$\$.log
  for d in js css fonts; do
    [ -d $BUILD_DIR/\$d/filament ] || { echo \"오류: \$d/filament 가 생성되지 않았습니다(public_path()가 평탄화 위치를 가리키는지 확인)\" >&2; exit 1; }
  done
"

echo "== 4/6: 빌드된 vendor와 Filament 에셋을 main 작업 디렉토리로 복사합니다 =="
rm -rf "$REPO_ROOT/vendor"
# docker cp의 목적지는 (컨테이너 내부 경로와 달리) 실제 Windows 호스트 경로라서 여기서만
# MSYS_NO_PATHCONV을 잠깐 풀어 정상적인 /c/... → C:\... 변환이 되게 한다. 컨테이너 쪽 소스 경로
# (container:/tmp/...)는 콜론 뒤에 오는 경로라 애초에 이 변환 대상이 아니므로 영향 없다.
env -u MSYS_NO_PATHCONV docker cp "$CONTAINER:$BUILD_DIR/vendor" "$REPO_ROOT/vendor"
# 목적지가 이미 있으면 docker cp 가 그 안쪽(filament/filament)에 중첩 복사하므로 먼저 지운다.
rm -rf "$REPO_ROOT/js/filament" "$REPO_ROOT/css/filament" "$REPO_ROOT/fonts/filament"
for asset_dir in js css fonts; do
  mkdir -p "$REPO_ROOT/$asset_dir"
  env -u MSYS_NO_PATHCONV docker cp "$CONTAINER:$BUILD_DIR/$asset_dir/filament" "$REPO_ROOT/$asset_dir/filament"
done

echo "== 5/6: main에 커밋하고 푸시합니다 =="
git add vendor/
if git diff --cached --quiet; then
  echo "vendor에 변경사항이 없습니다(이미 최신 상태)."
else
  git commit -m "Rebuild deploy vendor from dev ($(date +%Y-%m-%d))"
fi
# 경로를 지정한 git add 는 삭제(옛 버전 해시 이름 폰트 등)도 함께 스테이징한다. 저장소 루트 전체를 add -A 하지 않는 이유는 위 평탄화 주석 참고.
git add -- js/filament css/filament fonts/filament
if git diff --cached --quiet; then
  echo "Filament 에셋에 변경사항이 없습니다(이미 최신 상태)."
else
  git commit -m "Regenerate Filament assets (filament:assets) from rebuilt vendor ($(date +%Y-%m-%d))"
fi

# 2026-10-26 점검: 푸시 전에 main 의 상태를 스스로 검증한다. 하나라도 어긋나면 푸시하지 않고 중단한다
# (main 이 체크아웃된 채 남으므로 확인 뒤 `git checkout dev`).
echo "== 점검: Filament 에셋 == vendor dist, dev 전용 경로 미혼입 =="
verify_failed=0
# (1) 루트의 js|css|fonts/filament 의 모든 파일은 vendor/filament/*/dist 안의 어떤 파일과 '바이트까지 같아야' 한다
#     (filament:assets 는 dist 파일을 그대로 복사한다). 에셋이 옛 버전으로 남으면 새 dist 에는 같은 내용이 없어 여기서 걸린다.
#     커밋된 상태(git blob)를 비교하므로 작업 디렉토리 줄바꿈 변환과 무관하다.
dist_blobs_file="$(mktemp)"
git ls-tree -r HEAD -- vendor/filament | awk -F'[ \t]' '$4 ~ /\/dist\//{print $3}' | sort -u > "$dist_blobs_file"
asset_count=0
stale_assets=""
while IFS=$'\t' read -r blob path; do
  asset_count=$((asset_count + 1))
  if ! grep -qxF "$blob" "$dist_blobs_file"; then
    stale_assets="$stale_assets
  $path"
  fi
done < <(git ls-tree -r HEAD -- js/filament css/filament fonts/filament | awk -F'[ \t]' '{print $3 "\t" $4}')
rm -f "$dist_blobs_file"
if [ "$asset_count" -eq 0 ]; then
  echo "점검 실패: main 에 js|css|fonts/filament 에셋이 하나도 없습니다." >&2
  verify_failed=1
elif [ -n "$stale_assets" ]; then
  echo "점검 실패: vendor/filament/*/dist 와 내용이 다른(옛 버전) 에셋이 있습니다:$stale_assets" >&2
  verify_failed=1
else
  echo "  OK: Filament 에셋 ${asset_count}개 모두 vendor dist 와 일치"
fi
# (1b) 내용이 dist 와 같아도 '파일이 빠지는' 경우(filament:assets 는 복사 실패를 무시하고 성공을 반환한다, 신규 컴포넌트 누락 등)는
#      위 점검이 못 잡는다. dev 가 추적하는 public/{css,js,fonts}/filament 의 파일 경로 목록과 main 루트의 목록이 같아야 한다.
dev_asset_paths="$(mktemp)"; main_asset_paths="$(mktemp)"
git ls-tree -r --name-only dev -- public/css/filament public/js/filament public/fonts/filament | sed 's#^public/##' | sort > "$dev_asset_paths"
git ls-tree -r --name-only HEAD -- css/filament js/filament fonts/filament | sort > "$main_asset_paths"
if ! cmp -s "$dev_asset_paths" "$main_asset_paths"; then
  echo "점검 실패: dev 의 public/…/filament 와 main 루트 …/filament 의 파일 목록이 다릅니다('<' dev에만, '>' main에만, 상위 10개):" >&2
  diff "$dev_asset_paths" "$main_asset_paths" | grep '^[<>]' | head -10 >&2 || true
  verify_failed=1
else
  echo "  OK: dev 와 main 의 Filament 에셋 파일 목록 일치($(wc -l < "$dev_asset_paths")개)"
fi
rm -f "$dev_asset_paths" "$main_asset_paths"
# (2) main 에 있으면 안 되는 dev 전용 경로(앞에서 지우거나 평탄화한 것들)가 추적되고 있지 않은지.
leaked_paths="$(git ls-tree -r --name-only HEAD -- .claude .github CLAUDE.md PROJECT.md claude_code_prompts.md public | head -5 || true)"
if [ -n "$leaked_paths" ]; then
  echo "점검 실패: main 에 있으면 안 되는 경로가 추적되고 있습니다(상위 5개):" >&2
  echo "$leaked_paths" >&2
  verify_failed=1
else
  echo "  OK: dev 전용 경로(.claude/.github/CLAUDE.md/PROJECT.md/claude_code_prompts.md/public) 없음"
fi
if [ "$verify_failed" -ne 0 ]; then
  echo "오류: 점검에 실패해 푸시하지 않고 중단합니다(현재 main 이 체크아웃된 상태 — 원인 확인 후 'git checkout dev')." >&2
  exit 1
fi
# 2026-08-19에 origin이 lfboard-dev로 바뀌고 main 브랜치가 없어져(실제 배포 저장소는
# 별도 lfboard 리모트, https://github.com/piseok/LFBoard.git) 이 줄이 stale해졌었는데
# 스크립트 자체는 그때 안 고쳐져 있었다(2026-09-02 발견, 여기서 수정) — origin이 아니라
# lfboard 리모트로 push해야 실제 LFBoard 저장소에 반영된다.
# SKIP_PUSH=1 이면 푸시를 건너뛴다(2026-10-21: 결과를 눈으로 확인한 뒤 직접 `git push lfboard main`·`git push origin dev`).
if [ -n "${SKIP_PUSH:-}" ]; then
  echo "SKIP_PUSH 설정됨 — lfboard main 푸시를 건너뜁니다."
else
  git push lfboard main
fi

echo "== 6/6: dev로 돌아가서 개발용 vendor(dev 의존성 포함)를 복원합니다 =="
git checkout dev

# 이 스크립트는 그동안 main만 원격에 푸시하고 dev는 로컬 커밋만 남겨둬서, dev 커밋들이
# 이 컴퓨터에만 쌓이고 GitHub에는 전혀 반영되지 않는 문제가 있었다(디스크가 날아가면
# 작업 내역이 통째로 사라지는 위험). main 배포 때마다 dev도 함께 백업되도록 항상 푸시한다.
echo "== dev 브랜치도 원격에 푸시합니다 (백업) =="
if [ -n "${SKIP_PUSH:-}" ]; then
  echo "SKIP_PUSH 설정됨 — origin dev 푸시를 건너뜁니다."
else
  git push origin dev
fi

docker exec "$CONTAINER" rm -rf "$BUILD_DIR"
docker exec "$CONTAINER" mkdir -p "$BUILD_DIR"
docker exec "$CONTAINER" sh -c "
  cd /var/www/html && tar \
    --exclude='./vendor' --exclude='./.git' --exclude='./node_modules' \
    --exclude='./storage/framework/cache' --exclude='./storage/framework/sessions' \
    --exclude='./storage/framework/views' --exclude='./storage/logs' \
    -cf - . | (cd $BUILD_DIR && tar -xf -)
"
docker exec "$CONTAINER" sh -c "cd $BUILD_DIR && composer install --optimize-autoloader --no-interaction --no-scripts"
rm -rf "$REPO_ROOT/vendor"
env -u MSYS_NO_PATHCONV docker cp "$CONTAINER:$BUILD_DIR/vendor" "$REPO_ROOT/vendor"
docker exec "$CONTAINER" php artisan package:discover --ansi
docker exec "$CONTAINER" rm -rf "$BUILD_DIR"

echo "완료: main이 최신 소스+vendor로 갱신되어 푸시되었고, dev 개발 환경도 정상 복원되었습니다."
