<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\ServerStorageWidget;
use ReflectionMethod;
use Tests\TestCase;

// 업로드 용량 위젯의 설명 문구 — public/ 폴더가 실제로 있는 로컬 개발 환경과, public/이 저장소
// 루트로 평탄화된 배포 환경(client-deploy 브랜치들)에서 서로 다른 실제 경로를 정확히 보여줘야
// 한다(bootstrap/app.php의 usePublicPath() 판단 조건과 반드시 같은 기준을 써야 함).
class ServerStorageWidgetTest extends TestCase
{
    public function test_uploads_folder_label_matches_the_real_public_path_structure(): void
    {
        $method = new ReflectionMethod(ServerStorageWidget::class, 'uploadsFolderLabel');
        $method->setAccessible(true);

        $expected = is_dir(base_path('public')) ? 'public/uploads' : 'uploads';

        $this->assertSame($expected, $method->invoke(new ServerStorageWidget()));
    }
}
