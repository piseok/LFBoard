<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Board;
use App\Models\BoardCategory;
use App\Models\Comment;
use App\Models\Inquiry;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Policy;
use App\Models\Popup;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 프론트엔드 화면 확인용 더미 콘텐츠 시더.
 * 설치 시 기본 시딩(DatabaseSeeder)과 분리되어 있으며, 필요할 때 수동으로 실행한다:
 *   php artisan db:seed --class=DummyContentSeeder
 * 재실행해도 게시글/배너/팝업/문의가 중복 생성되지 않도록 개수 기준으로 가드한다.
 */
class DummyContentSeeder extends Seeder
{
    public function run(): void
    {
        $users = $this->seedUsers();
        $pages = $this->seedPages();
        $this->seedLocalizedTestPages();
        $boards = $this->seedBoards();
        $this->seedLocalizedTestBoard();
        $this->seedMenus($pages, $boards);
        $this->seedLocalizedTestMenu();
        $this->seedLocalizedTestPolicies();

        if (Post::count() === 0) {
            $this->seedPostsAndComments($boards, $users);
        }

        if (Banner::count() === 0) {
            $this->seedBanners();
        }

        if (Popup::count() === 0) {
            $this->seedPopups();
        }

        if (Inquiry::count() === 0) {
            $this->seedInquiries($users);
        }
    }

    /** @return array<string, User> */
    private function seedUsers(): array
    {
        $members = [
            ['name' => '김민준', 'nickname' => '민준이', 'email' => 'minjun@example.com'],
            ['name' => '이서연', 'nickname' => '서연맘', 'email' => 'seoyeon@example.com'],
            ['name' => '박도윤', 'nickname' => '도윤파파', 'email' => 'doyoon@example.com'],
        ];

        $result = [];

        foreach ($members as $m) {
            $result[$m['email']] = User::firstOrCreate(
                ['email' => $m['email']],
                [
                    'name' => $m['name'],
                    'nickname' => $m['nickname'],
                    'password' => Hash::make('password1234'),
                    'level' => User::LEVEL_MEMBER,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'unsubscribe_token' => Str::random(32),
                ]
            );
        }

        return $result;
    }

    /** @return array<string, Page> */
    private function seedPages(): array
    {
        // 'history'(연혁)는 원래 단순 <ul> 목록이었으나, corporate/hospital 디자인 레퍼런스 공통
        // DNA(세로 타임라인)를 미리보기로 보여주기 위해 components/vertical-timeline.blade.php와
        // 정확히 같은 구조("같은 클래스명")의 구운 HTML로 교체했다. 'organization'(조직도)은 이번에
        // 신규 추가된 페이지로, components/org-chart-tree.blade.php와 짝을 이룬다. 두 컴포넌트
        // 파일 상단의 "구운 HTML 드리프트" 경고 그대로: 컴포넌트 마크업을 바꾸면 이 문자열도 함께
        // 손으로 맞춰야 한다(재시딩 자동화 없음, 사용자 결정). 형제 페이지들과 동일하게 locale은
        // 의도적으로 'ko'만 시딩한다 — 다국어(D섹션) 파일럿과 무관한 데모 콘텐츠라 en/ja 버전은
        // 처음부터 범위 밖이다(누락이 아니라 의도적 보류).
        $pages = [
            'about' => ['title' => '인사말', 'content' => '<h2>안녕하세요, 저희 사이트를 찾아주셔서 감사합니다.</h2><p>고객과의 신뢰를 최우선 가치로 삼고, 더 나은 서비스를 위해 항상 노력하겠습니다.</p><p>앞으로도 변함없는 관심과 성원 부탁드립니다.</p>'],
            'history' => ['title' => '연혁', 'content' => '<ul class="vertical-timeline">'.
                '<li class="vertical-timeline__item"><p class="vertical-timeline__year">2024</p><ul class="vertical-timeline__list"><li>01월 — 법인 설립</li><li>06월 — 서비스 정식 오픈</li></ul></li>'.
                '<li class="vertical-timeline__item"><p class="vertical-timeline__year">2025</p><ul class="vertical-timeline__list"><li>03월 — 누적 이용자 10,000명 달성</li></ul></li>'.
                '<li class="vertical-timeline__item"><p class="vertical-timeline__year">2026</p><ul class="vertical-timeline__list"><li>01월 — 신규 서비스 확장</li></ul></li>'.
                '</ul>',
            ],
            'organization' => ['title' => '조직도', 'content' => '<div class="org-chart-tree">'.
                '<div class="org-chart-tree__ceo">대표이사</div>'.
                '<div class="org-chart-tree__connector" aria-hidden="true"></div>'.
                '<div class="org-chart-tree__depts">'.
                '<div class="org-chart-tree__dept-group"><div class="org-chart-tree__dept">경영지원본부</div><ul class="org-chart-tree__teams"><li class="org-chart-tree__team">총무팀</li><li class="org-chart-tree__team">인사팀</li><li class="org-chart-tree__team">재무팀</li></ul></div>'.
                '<div class="org-chart-tree__dept-group"><div class="org-chart-tree__dept">사업본부</div><ul class="org-chart-tree__teams"><li class="org-chart-tree__team">영업팀</li><li class="org-chart-tree__team">마케팅팀</li></ul></div>'.
                '<div class="org-chart-tree__dept-group"><div class="org-chart-tree__dept">기술본부</div><ul class="org-chart-tree__teams"><li class="org-chart-tree__team">개발팀</li><li class="org-chart-tree__team">인프라팀</li></ul></div>'.
                '</div>'.
                '</div>',
            ],
            'location' => ['title' => '오시는길', 'content' => '<p>주소: 서울특별시 강남구 테헤란로 123, 4층</p><p>지하철: 2호선 강남역 3번 출구 도보 5분</p><p>주차: 건물 지하 1~3층 이용 가능(2시간 무료)</p>'],
            'support-info' => ['title' => '고객지원 안내', 'content' => '<p>궁금하신 사항은 아래 메뉴를 통해 확인하실 수 있습니다.</p><ul><li>공지사항 — 서비스 관련 주요 안내사항</li><li>자료실 — 각종 서식 및 이용 가이드</li><li>1:1 문의 — 개별 문의사항 접수</li></ul><p>운영시간: 평일 09:00~18:00 (주말·공휴일 휴무)</p>'],
            'archive-guide' => ['title' => '자료실 이용안내', 'content' => '<p>자료실에는 서비스 이용에 필요한 각종 서식과 매뉴얼이 등록되어 있습니다.</p><p>파일 다운로드는 로그인 후 이용 가능하며, 자료 요청은 1:1 문의를 통해 접수해 주세요.</p>'],
            'faq' => ['title' => 'FAQ', 'content' => '<p><strong>Q. 비밀번호를 잊어버렸어요.</strong><br>A. 로그인 화면의 [비밀번호 찾기]를 통해 재설정하실 수 있습니다.</p><p><strong>Q. 회원 탈퇴는 어떻게 하나요?</strong><br>A. 마이페이지에서 직접 처리하시거나 1:1 문의로 요청해 주세요.</p>'],
        ];

        $result = [];

        foreach ($pages as $slug => $data) {
            $result[$slug] = Page::updateOrCreate(
                ['slug' => $slug, 'locale' => 'ko'],
                [
                    'title' => $data['title'],
                    'content_type' => 'editor',
                    'content' => $data['content'],
                    'is_active' => true,
                    'min_level' => 0,
                ]
            );
        }

        return $result;
    }

    // 다국어(D 섹션) 파일럿 테스트용 — 'about' 페이지의 영어/일본어 버전을 같은 슬러그로 추가.
    // 영어는 오버라이드 뷰가 없어 기본 레이아웃 그대로 쓰이고, 일본어는 콘텐츠 자체가 다국어임을
    // 확인하기 위한 더미 데이터(사용자 요청 — "영어랑 일본어 두개로 해주고" 검증용).
    private function seedLocalizedTestPages(): void
    {
        Page::updateOrCreate(
            ['slug' => 'about', 'locale' => 'en'],
            [
                'title' => 'Greetings',
                'content_type' => 'editor',
                'content' => '<h2>Hello, thank you for visiting our site.</h2><p>We value trust with our customers above all else and always strive to provide better service.</p><p>We appreciate your continued interest and support.</p>',
                'is_active' => true,
                'min_level' => 0,
            ]
        );

        Page::updateOrCreate(
            ['slug' => 'about', 'locale' => 'ja'],
            [
                'title' => 'ご挨拶',
                'content_type' => 'editor',
                'content' => '<h2>こんにちは、当サイトをご覧いただきありがとうございます。</h2><p>お客様との信頼を何よりも大切にし、より良いサービスのために常に努力いたします。</p><p>今後とも変わらぬご関心とご声援をお願いいたします。</p>',
                'is_active' => true,
                'min_level' => 0,
            ]
        );
    }

    /** @return array<string, Board> */
    private function seedBoards(): array
    {
        $notice = Board::where('slug', 'notice')->where('locale', 'ko')->first() ?? Board::create([
            'name' => '공지사항', 'slug' => 'notice', 'locale' => 'ko', 'skin' => 'default', 'layout' => 'list', 'use_editor' => true,
            'allow_comment' => true, 'allow_reply' => true, 'allow_file' => true, 'allow_anonymous' => false,
            'allow_image_upload' => true, 'use_captcha' => false, 'min_read_level' => 1, 'min_write_level' => 9,
            'min_comment_level' => 2, 'files_per_post' => 3, 'per_page' => 15, 'order_by' => 'latest',
            'description' => '서비스 관련 주요 소식을 안내합니다.', 'is_active' => true, 'sort_order' => 1,
        ]);

        $free = Board::where('slug', 'free')->where('locale', 'ko')->first() ?? Board::create([
            'name' => '자유게시판', 'slug' => 'free', 'locale' => 'ko', 'skin' => 'default', 'layout' => 'list', 'use_editor' => true,
            'allow_comment' => true, 'allow_reply' => true, 'allow_file' => true, 'allow_anonymous' => true,
            'allow_image_upload' => true, 'use_captcha' => false, 'min_read_level' => 1, 'min_write_level' => 2,
            'min_comment_level' => 1, 'files_per_post' => 3, 'per_page' => 15, 'order_by' => 'latest',
            'description' => '자유롭게 이야기를 나누는 공간입니다.', 'is_active' => true, 'sort_order' => 2,
        ]);

        $archive = Board::updateOrCreate(
            ['slug' => 'archive', 'locale' => 'ko'],
            [
                'name' => '자료실', 'skin' => 'default', 'layout' => 'list', 'use_editor' => true,
                'allow_comment' => false, 'allow_reply' => false, 'allow_file' => true, 'allow_anonymous' => false,
                'allow_image_upload' => false, 'use_captcha' => false, 'min_read_level' => 1, 'min_write_level' => 9,
                'min_comment_level' => 2, 'files_per_post' => 5, 'per_page' => 15, 'order_by' => 'latest',
                'description' => '각종 서식 및 이용 가이드를 제공합니다.', 'is_active' => true, 'sort_order' => 3,
            ]
        );

        $gallery = Board::updateOrCreate(
            ['slug' => 'gallery', 'locale' => 'ko'],
            [
                'name' => '갤러리', 'skin' => 'default', 'layout' => 'gallery', 'use_editor' => true,
                'allow_comment' => true, 'allow_reply' => false, 'allow_file' => true, 'allow_anonymous' => false,
                'allow_image_upload' => true, 'use_captcha' => false, 'min_read_level' => 1, 'min_write_level' => 2,
                'min_comment_level' => 1, 'files_per_post' => 5, 'per_page' => 12, 'order_by' => 'latest',
                'description' => '행사 및 활동 사진을 공유합니다.', 'is_active' => true, 'sort_order' => 4,
            ]
        );

        // 본인인증 후 개인정보 이용 동의를 거쳐야 글쓰기가 가능한 예시 게시판(민원게시판).
        // 비회원 작성을 허용하지 않고(로그인 필요), 실제 익명 민원 남용을 막기 위해 본인인증을 요구한다.
        $complaint = Board::updateOrCreate(
            ['slug' => 'complaint', 'locale' => 'ko'],
            [
                'name' => '민원게시판', 'skin' => 'default', 'layout' => 'list', 'use_editor' => true,
                'allow_comment' => false, 'allow_reply' => false, 'allow_file' => true, 'allow_anonymous' => false,
                'allow_image_upload' => false, 'use_captcha' => false, 'requires_identity_verification' => true,
                'identity_verification_consent_text' => '민원 처리를 위해 이름, 연락처, 본인인증 결과(CI/DI)를 수집하며, '.
                    '수집된 정보는 민원 처리 및 결과 안내 목적으로만 이용하고 처리 완료 후 관련 법령에 따른 보관 기간이 '.
                    '지나면 파기합니다. 위 내용에 동의합니다.',
                'min_read_level' => 2, 'min_write_level' => 2, 'min_comment_level' => 2, 'files_per_post' => 3,
                'per_page' => 15, 'order_by' => 'latest', 'description' => '본인인증 후 민원을 접수할 수 있는 게시판입니다.',
                'is_active' => true, 'sort_order' => 5,
            ]
        );

        // 채용/모집 공고 예시 게시판. 모집 시작/종료일은 게시판이 아니라 게시글별로 설정한다(한
        // 게시판 안에 마감일이 서로 다른 공고 여러 건이 올라오는 게 일반적이라서) — 순수 정보
        // 표시용(예정/기간중/마감 배지)이며 글쓰기 자체를 막지는 않는다. 실제 지원 접수는 이 게시판
        // 밖(향후 별도 addon)에서 이루어진다.
        $recruitment = Board::updateOrCreate(
            ['slug' => 'recruitment', 'locale' => 'ko'],
            [
                'name' => '채용공고', 'skin' => 'default', 'layout' => 'list', 'use_editor' => true,
                'allow_comment' => false, 'allow_reply' => false, 'allow_file' => true, 'allow_anonymous' => false,
                'allow_image_upload' => true, 'use_captcha' => false, 'min_read_level' => 1, 'min_write_level' => 9,
                'min_comment_level' => 1, 'files_per_post' => 3, 'per_page' => 15, 'order_by' => 'latest',
                'description' => '채용/모집 공고를 안내합니다.', 'is_active' => true, 'sort_order' => 6,
            ]
        );

        foreach ([
            $notice->id => ['일반', '이벤트'],
            $free->id => ['자유', '질문'],
            $archive->id => ['서식', '매뉴얼'],
            $gallery->id => ['행사', '스냅'],
        ] as $boardId => $names) {
            foreach ($names as $i => $name) {
                BoardCategory::firstOrCreate(['board_id' => $boardId, 'name' => $name], ['sort_order' => $i + 1]);
            }
        }

        return compact('notice', 'free', 'archive', 'gallery', 'complaint', 'recruitment');
    }

    // 다국어(D 섹션) 파일럿 테스트용 — '공지사항' 게시판의 영어/일본어 버전을 같은 슬러그로 추가.
    // 스킨은 언어와 무관하다는 설계를 그대로 보여주기 위해 한국어와 동일한 'default' 스킨을 그대로 사용.
    private function seedLocalizedTestBoard(): void
    {
        Board::updateOrCreate(
            ['slug' => 'notice', 'locale' => 'en'],
            [
                'name' => 'Notice', 'skin' => 'default', 'layout' => 'list', 'use_editor' => true,
                'allow_comment' => true, 'allow_reply' => true, 'allow_file' => true, 'allow_anonymous' => false,
                'allow_image_upload' => true, 'use_captcha' => false, 'min_read_level' => 1, 'min_write_level' => 9,
                'min_comment_level' => 2, 'files_per_post' => 3, 'per_page' => 15, 'order_by' => 'latest',
                'description' => 'Announcements about our service.', 'is_active' => true, 'sort_order' => 1,
            ]
        );

        Board::updateOrCreate(
            ['slug' => 'notice', 'locale' => 'ja'],
            [
                'name' => 'お知らせ', 'skin' => 'default', 'layout' => 'list', 'use_editor' => true,
                'allow_comment' => true, 'allow_reply' => true, 'allow_file' => true, 'allow_anonymous' => false,
                'allow_image_upload' => true, 'use_captcha' => false, 'min_read_level' => 1, 'min_write_level' => 9,
                'min_comment_level' => 2, 'files_per_post' => 3, 'per_page' => 15, 'order_by' => 'latest',
                'description' => 'サービスに関する主なお知らせです。', 'is_active' => true, 'sort_order' => 1,
            ]
        );
    }

    /**
     * @param  array<string, Page>  $pages
     * @param  array<string, Board>  $boards
     */
    private function seedMenus(array $pages, array $boards): void
    {
        // 1단계에서 시딩된 타깃 없는(깨진) 메뉴를 정리하고 실제 콘텐츠에 연결된 메뉴 트리로 재구성한다.
        Menu::query()->delete();

        // 1뎁스: 회사소개 (그룹형 — 자체 페이지 없이 하위 메뉴만 보유)
        // '조직도'는 이번(2026-08)에 추가된 메뉴 — 인사말/연혁 다음, 오시는길 앞에 배치해
        // "회사 소개 → 조직 구성 → 오시는 길" 순서가 자연스럽게 읽히도록 했다.
        $top1 = Menu::create(['title' => '회사소개', 'type' => 'none', 'target' => '_self', 'is_active' => true, 'sort_order' => 1, 'min_level' => 0]);
        Menu::create(['parent_id' => $top1->id, 'title' => '인사말', 'type' => 'page', 'target_id' => $pages['about']->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 1, 'min_level' => 0]);
        Menu::create(['parent_id' => $top1->id, 'title' => '연혁', 'type' => 'page', 'target_id' => $pages['history']->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 2, 'min_level' => 0]);
        Menu::create(['parent_id' => $top1->id, 'title' => '조직도', 'type' => 'page', 'target_id' => $pages['organization']->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 3, 'min_level' => 0]);
        Menu::create(['parent_id' => $top1->id, 'title' => '오시는길', 'type' => 'page', 'target_id' => $pages['location']->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 4, 'min_level' => 0]);
        Menu::create(['parent_id' => $top1->id, 'title' => '채용공고', 'type' => 'board', 'target_id' => $boards['recruitment']->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 5, 'min_level' => 0]);

        // 1뎁스: 고객지원 (자체 페이지이면서 동시에 하위 메뉴도 보유하는 혼합형)
        $top2 = Menu::create(['title' => '고객지원', 'type' => 'page', 'target_id' => $pages['support-info']->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 2, 'min_level' => 0]);
        Menu::create(['parent_id' => $top2->id, 'title' => '공지사항', 'type' => 'board', 'target_id' => $boards['notice']->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 1, 'min_level' => 0]);

        // 2뎁스: 자료실 (2뎁스 자신도 게시판 링크이면서 3뎁스 하위 메뉴도 보유하는 혼합형)
        $archiveMenu = Menu::create(['parent_id' => $top2->id, 'title' => '자료실', 'type' => 'board', 'target_id' => $boards['archive']->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 2, 'min_level' => 0]);
        Menu::create(['parent_id' => $archiveMenu->id, 'title' => '자료실 이용안내', 'type' => 'page', 'target_id' => $pages['archive-guide']->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 1, 'min_level' => 0]);
        Menu::create(['parent_id' => $archiveMenu->id, 'title' => 'FAQ', 'type' => 'page', 'target_id' => $pages['faq']->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 2, 'min_level' => 0]);

        Menu::create(['parent_id' => $top2->id, 'title' => '1:1 문의', 'type' => 'url', 'url' => '/inquiry', 'target' => '_self', 'is_active' => true, 'sort_order' => 3, 'min_level' => 0]);
        Menu::create(['parent_id' => $top2->id, 'title' => '민원게시판', 'type' => 'board', 'target_id' => $boards['complaint']->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 4, 'min_level' => 0]);

        // 1뎁스: 커뮤니티 (그룹형)
        $top3 = Menu::create(['title' => '커뮤니티', 'type' => 'none', 'target' => '_self', 'is_active' => true, 'sort_order' => 3, 'min_level' => 0]);
        Menu::create(['parent_id' => $top3->id, 'title' => '자유게시판', 'type' => 'board', 'target_id' => $boards['free']->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 1, 'min_level' => 0]);
        Menu::create(['parent_id' => $top3->id, 'title' => '갤러리', 'type' => 'board', 'target_id' => $boards['gallery']->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 2, 'min_level' => 0]);
    }

    // 다국어(D 섹션) 파일럿 테스트용 — 영어/일본어 메뉴 트리에 '공지사항' 게시판(다국어 버전) 링크 1개씩 추가.
    // MenuService::resolveUrl()이 현재 언어에 맞는 라우트 이름으로 링크를 생성하는지 확인하기 위한 더미 데이터.
    private function seedLocalizedTestMenu(): void
    {
        $enNotice = Board::where('slug', 'notice')->where('locale', 'en')->first();
        $jaNotice = Board::where('slug', 'notice')->where('locale', 'ja')->first();

        if ($enNotice) {
            Menu::updateOrCreate(
                ['title' => 'Notice', 'locale' => 'en'],
                ['type' => 'board', 'target_id' => $enNotice->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 1, 'min_level' => 0]
            );
        }

        if ($jaNotice) {
            Menu::updateOrCreate(
                ['title' => 'お知らせ', 'locale' => 'ja'],
                ['type' => 'board', 'target_id' => $jaNotice->id, 'target' => '_self', 'is_active' => true, 'sort_order' => 1, 'min_level' => 0]
            );
        }
    }

    // 다국어(10번) 테스트용 — 영어/일본어 약관 3종(이용약관/개인정보처리방침/마케팅 수신동의).
    // 한국어 버전은 PolicySeeder에서 이미 시딩됨(기본 언어, 폴백 대상).
    private function seedLocalizedTestPolicies(): void
    {
        $en = [
            ['type' => 'terms', 'title' => 'Terms of Service', 'content' => '[Enter Terms of Service content here]', 'is_required' => true],
            ['type' => 'privacy', 'title' => 'Privacy Policy', 'content' => '[Enter Privacy Policy content here]', 'is_required' => true],
            ['type' => 'marketing', 'title' => 'Marketing Consent', 'content' => '[Enter marketing consent content here]', 'is_required' => false],
        ];
        $ja = [
            ['type' => 'terms', 'title' => '利用規約', 'content' => '[利用規約の内容を入力してください]', 'is_required' => true],
            ['type' => 'privacy', 'title' => 'プライバシーポリシー', 'content' => '[プライバシーポリシーの内容を入力してください]', 'is_required' => true],
            ['type' => 'marketing', 'title' => 'マーケティング受信同意', 'content' => '[マーケティング受信同意の内容を入力してください]', 'is_required' => false],
        ];

        foreach (['en' => $en, 'ja' => $ja] as $locale => $policies) {
            foreach ($policies as $policy) {
                Policy::updateOrCreate(
                    ['type' => $policy['type'], 'locale' => $locale],
                    $policy + ['locale' => $locale, 'is_active' => true, 'version' => now()->format('Y.m.d')]
                );
            }
        }
    }

    /**
     * @param  array<string, Board>  $boards
     * @param  array<string, User>  $users
     */
    private function seedPostsAndComments(array $boards, array $users): void
    {
        $memberList = array_values($users);

        // 공지사항: 전체공지 1건 + 게시판공지 1건 + 일반 3건
        $noticeTitles = [
            ['title' => '[전체공지] 시스템 정기점검 안내', 'notice' => 'global'],
            ['title' => '[공지] 개인정보처리방침 개정 안내', 'notice' => 'board'],
            ['title' => '2026년 상반기 서비스 업데이트 안내', 'notice' => null],
            ['title' => '설 연휴 고객센터 운영 안내', 'notice' => null],
            ['title' => '신규 회원 대상 이벤트 안내', 'notice' => null],
        ];
        foreach ($noticeTitles as $i => $item) {
            Post::create([
                'board_id' => $boards['notice']->id,
                'user_id' => null,
                'title' => $item['title'],
                'content' => '<p>안녕하세요. 관련 세부 안내사항을 아래와 같이 전달드립니다.</p><p>이용에 참고 부탁드립니다. 감사합니다.</p>',
                'is_global_notice' => $item['notice'] === 'global',
                'is_notice' => $item['notice'] === 'board',
                'is_active' => true,
                'views' => rand(20, 300),
                'created_at' => now()->subDays(20 - $i * 3),
                'updated_at' => now()->subDays(20 - $i * 3),
            ]);
        }

        // 자유게시판: 회원 글 + 비회원(익명) 글 혼합, 일부에 댓글/답글
        $freeTitles = [
            '오늘 날씨 정말 좋네요', '다들 주말에 뭐하세요?', '이 서비스 진짜 편하네요 추천합니다',
            '질문있습니다! 답변 부탁드려요', '자유게시판 첫 이용 인사드립니다', '요즘 관심있는 취미 공유해요',
        ];
        $freePosts = [];
        foreach ($freeTitles as $i => $title) {
            $author = $memberList[$i % count($memberList)] ?? null;
            $freePosts[] = Post::create([
                'board_id' => $boards['free']->id,
                'user_id' => $i % 3 === 0 ? null : $author?->id,
                'author_name' => $i % 3 === 0 ? '익명회원' : null,
                'author_password' => $i % 3 === 0 ? Hash::make('guest1234') : null,
                'title' => $title,
                'content' => '<p>'.$title.'에 대한 이야기를 나눠봐요.</p><p>편하게 댓글 남겨주세요!</p>',
                'is_active' => true,
                'views' => rand(5, 150),
                'created_at' => now()->subDays(15 - $i * 2),
                'updated_at' => now()->subDays(15 - $i * 2),
            ]);
        }

        $commentAuthor1 = $memberList[0] ?? null;
        $commentAuthor2 = $memberList[1] ?? null;
        if ($commentAuthor1 && isset($freePosts[0])) {
            $c1 = Comment::create(['post_id' => $freePosts[0]->id, 'user_id' => $commentAuthor1->id, 'content' => '저도 동감이에요!']);
            Comment::create(['post_id' => $freePosts[0]->id, 'parent_id' => $c1->id, 'depth' => 1, 'user_id' => $commentAuthor2?->id, 'content' => '맞아요 오늘 진짜 화창하네요.']);
        }
        if ($commentAuthor2 && isset($freePosts[2])) {
            Comment::create(['post_id' => $freePosts[2]->id, 'user_id' => $commentAuthor2->id, 'content' => '저도 잘 쓰고 있어요~ 다음 업데이트도 기대됩니다.']);
        }

        // 자료실: 서식/매뉴얼 안내 게시글
        $archiveTitles = ['회원가입 신청서 서식', '서비스 이용 매뉴얼 v1.2', '개인정보 수집·이용 동의서 서식', '자주 묻는 질문 모음집'];
        foreach ($archiveTitles as $i => $title) {
            Post::create([
                'board_id' => $boards['archive']->id,
                'user_id' => null,
                'title' => $title,
                'content' => '<p>'.$title.' 관련 자료입니다.</p><p>필요 시 1:1 문의를 통해 최신 버전을 요청해 주세요.</p>',
                'is_active' => true,
                'views' => rand(10, 80),
                'created_at' => now()->subDays(10 - $i),
                'updated_at' => now()->subDays(10 - $i),
            ]);
        }

        // 갤러리: placehold.co 이미지 첨부
        $galleryItems = [
            ['title' => '2026 상반기 워크숍 스냅', 'color' => '2563eb/ffffff', 'text' => 'Workshop'],
            ['title' => '창립 기념일 행사 사진', 'color' => 'b45309/ffffff', 'text' => 'Anniversary'],
            ['title' => '고객 감사 이벤트 현장', 'color' => '059669/ffffff', 'text' => 'Event'],
            ['title' => '신제품 런칭 쇼케이스', 'color' => 'dc2626/ffffff', 'text' => 'Launch'],
            ['title' => '봄맞이 사무실 스냅', 'color' => '7c3aed/ffffff', 'text' => 'Spring'],
            ['title' => '팀 빌딩 액티비티', 'color' => '0891b2/ffffff', 'text' => 'Team+Building'],
        ];
        foreach ($galleryItems as $i => $item) {
            $post = Post::create([
                'board_id' => $boards['gallery']->id,
                'user_id' => $memberList[$i % count($memberList)]?->id,
                'title' => $item['title'],
                'content' => '<p>'.$item['title'].'</p>',
                'is_active' => true,
                'views' => rand(15, 200),
                'created_at' => now()->subDays(12 - $i * 2),
                'updated_at' => now()->subDays(12 - $i * 2),
            ]);
            $imageUrl = 'https://placehold.co/600x400/'.$item['color'].'?text='.$item['text'];
            $post->files()->create([
                'original_name' => $item['text'].'.png',
                'stored_name' => Str::uuid().'.png',
                'file_path' => $imageUrl,
                'file_size' => 51200,
                'mime_type' => 'image/png',
                'sort_order' => 0,
            ]);
        }

        if ($commentAuthor1) {
            $galleryFirst = Post::where('board_id', $boards['gallery']->id)->first();
            if ($galleryFirst) {
                Comment::create(['post_id' => $galleryFirst->id, 'user_id' => $commentAuthor1->id, 'content' => '사진 정말 잘 나왔네요!']);
            }
        }

        // 채용공고: 예정/기간중/마감 배지를 각각 보여주는 공고 3건(게시글별 모집 기간).
        $recruitmentPosts = [
            ['title' => '2026년 하반기 신입사원 공개채용', 'start' => now()->addWeek(), 'end' => now()->addMonth()],
            ['title' => '백엔드 개발자 경력직 채용', 'start' => now()->subWeek(), 'end' => now()->addWeeks(2)],
            ['title' => '2025년 상반기 인턴 채용', 'start' => now()->subMonths(2), 'end' => now()->subMonth()],
        ];
        foreach ($recruitmentPosts as $i => $item) {
            Post::create([
                'board_id' => $boards['recruitment']->id,
                'user_id' => null,
                'title' => $item['title'],
                'content' => '<p>'.$item['title'].' 관련 상세 내용을 안내드립니다.</p>',
                'is_active' => true,
                'views' => rand(10, 120),
                'recruitment_start_at' => $item['start'],
                'recruitment_end_at' => $item['end'],
                'created_at' => now()->subDays(5 - $i),
                'updated_at' => now()->subDays(5 - $i),
            ]);
        }
    }

    private function seedBanners(): void
    {
        $banners = [
            ['title' => '2026 상반기 프로모션', 'color' => 'b45309/ffffff', 'text' => 'Promotion'],
            ['title' => '신규 회원 혜택 안내', 'color' => '2563eb/ffffff', 'text' => 'Welcome+Benefit'],
            ['title' => '서비스 업데이트 소식', 'color' => '059669/ffffff', 'text' => 'Update'],
        ];

        foreach ($banners as $i => $b) {
            Banner::create([
                'group_key' => 'main_top',
                'locale' => 'ko',
                'title' => $b['title'],
                'image_path' => 'https://placehold.co/1200x300/'.$b['color'].'?text='.$b['text'],
                'link_url' => null,
                'link_target' => '_self',
                'alt_text' => $b['title'],
                'is_active' => true,
                'click_count' => 0,
                'sort_order' => $i + 1,
            ]);
        }

        $this->seedLocalizedTestBanners();
    }

    // 다국어(7번) 테스트용 — 영어/일본어 각 1개씩, 프론트 홈 배너 쿼리가 locale로 필터링되는지 확인용
    private function seedLocalizedTestBanners(): void
    {
        Banner::create([
            'group_key' => 'main_top',
            'locale' => 'en',
            'title' => '2026 Summer Promotion',
            'image_path' => 'https://placehold.co/1200x300/b45309/ffffff?text=Promotion+(EN)',
            'link_url' => null,
            'link_target' => '_self',
            'alt_text' => '2026 Summer Promotion',
            'is_active' => true,
            'click_count' => 0,
            'sort_order' => 1,
        ]);

        Banner::create([
            'group_key' => 'main_top',
            'locale' => 'ja',
            'title' => '2026年上半期プロモーション',
            'image_path' => 'https://placehold.co/1200x300/b45309/ffffff?text=Promotion+(JA)',
            'link_url' => null,
            'link_target' => '_self',
            'alt_text' => '2026年上半期プロモーション',
            'is_active' => true,
            'click_count' => 0,
            'sort_order' => 1,
        ]);
    }

    private function seedPopups(): void
    {
        Popup::create([
            'title' => '2026 상반기 이벤트 안내',
            'locale' => 'ko',
            'content_type' => 'image',
            'image_path' => 'https://placehold.co/400x500/dc2626/ffffff?text=Event',
            'position' => 'center',
            'width' => 400,
            'height' => 500,
            'is_active' => true,
            'sort_order' => 1,
            'started_at' => now()->subDay(),
            'ended_at' => now()->addMonth(),
        ]);

        Popup::create([
            'title' => '시스템 점검 안내',
            'locale' => 'ko',
            'content_type' => 'html',
            'html_content' => '<p style="margin:0;"><strong>정기 점검 안내</strong></p><p>매주 화요일 새벽 2시~4시 시스템 점검이 진행됩니다.</p>',
            'position' => 'bottom-right',
            'width' => 320,
            'height' => 200,
            'is_active' => true,
            'sort_order' => 2,
            'started_at' => now()->subDay(),
            'ended_at' => now()->addMonth(),
        ]);

        $this->seedLocalizedTestPopups();
    }

    // 다국어(8번) 테스트용 — 영어/일본어 각 1개씩, PopupService::getActive()가 locale로 필터링되는지 확인용
    private function seedLocalizedTestPopups(): void
    {
        Popup::create([
            'title' => '2026 Event Notice',
            'locale' => 'en',
            'content_type' => 'image',
            'image_path' => 'https://placehold.co/400x500/dc2626/ffffff?text=Event+(EN)',
            'position' => 'center',
            'width' => 400,
            'height' => 500,
            'is_active' => true,
            'sort_order' => 1,
            'started_at' => now()->subDay(),
            'ended_at' => now()->addMonth(),
        ]);

        Popup::create([
            'title' => 'イベントのお知らせ',
            'locale' => 'ja',
            'content_type' => 'image',
            'image_path' => 'https://placehold.co/400x500/dc2626/ffffff?text=Event+(JA)',
            'position' => 'center',
            'width' => 400,
            'height' => 500,
            'is_active' => true,
            'sort_order' => 1,
            'started_at' => now()->subDay(),
            'ended_at' => now()->addMonth(),
        ]);
    }

    /** @param array<string, User> $users */
    private function seedInquiries(array $users): void
    {
        $memberList = array_values($users);

        Inquiry::create([
            'user_id' => $memberList[0]?->id,
            'type' => 'general', 'category' => '일반',
            'name' => $memberList[0]?->name ?? '문의자', 'email' => $memberList[0]?->email,
            'title' => '결제 관련 문의드립니다', 'content' => '결제 후 영수증을 어디서 확인할 수 있을까요?',
            'status' => 'done', 'admin_reply' => '마이페이지 > 결제내역에서 영수증 확인이 가능합니다. 감사합니다.',
            'replied_at' => now()->subDays(2), 'is_active' => true,
        ]);

        Inquiry::create([
            'user_id' => null,
            'type' => 'footer', 'category' => '기술지원',
            'name' => '홍길동', 'phone' => '010-1234-5678',
            'author_password' => Hash::make('guest1234'),
            'title' => '로그인이 안 됩니다', 'content' => '비밀번호를 여러 번 틀렸더니 로그인이 안 됩니다. 확인 부탁드립니다.',
            'status' => 'processing', 'is_active' => true,
        ]);

        Inquiry::create([
            'user_id' => $memberList[1]?->id,
            'type' => 'general', 'category' => '제휴문의',
            'name' => $memberList[1]?->name ?? '문의자', 'email' => $memberList[1]?->email,
            'title' => '제휴 제안드립니다', 'content' => '저희 회사와의 제휴를 제안드리고 싶습니다. 담당자 연락 부탁드립니다.',
            'status' => 'pending', 'is_active' => true,
        ]);
    }
}
