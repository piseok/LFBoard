<?php

namespace Tests\Feature\Auth;

use App\Mail\TemplateMail;
use App\Models\User;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// "아이디 찾기" — 이름+이메일이 일치하는 계정이 있으면 find_id 템플릿으로 메일을 보낸다.
// 계정 존재 여부를 화면에 드러내면 열거(enumeration) 공격에 악용될 수 있어, 일치 여부와
// 무관하게 항상 같은 안내 문구를 보여준다(FindIdController 참고).
class FindIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_id_page_is_reachable(): void
    {
        $this->get(front_route('find-id'))->assertOk();
    }

    public function test_sends_the_registered_email_as_the_login_id_when_login_type_is_email(): void
    {
        Mail::fake();
        app(SiteSettingService::class)->set('email_find_id_enabled', '1', 'mail');

        $user = User::create([
            'name' => '홍길동', 'email' => 'hong@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        $response = $this->post(front_route('find-id.submit'), ['name' => '홍길동', 'email' => 'hong@test.local']);

        $response->assertRedirect();
        Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail) => str_contains($mail->mailBody, 'hong@test.local'));
    }

    public function test_sends_the_username_as_the_login_id_when_login_type_is_username(): void
    {
        Mail::fake();
        $settings = app(SiteSettingService::class);
        $settings->set('email_find_id_enabled', '1', 'mail');
        $settings->set('login_type', 'username', 'member');

        User::create([
            'name' => '홍길동', 'username' => 'gildong', 'email' => 'hong@test.local',
            'password' => bcrypt('password'), 'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        $this->post(front_route('find-id.submit'), ['name' => '홍길동', 'email' => 'hong@test.local']);

        Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail) => str_contains($mail->mailBody, 'gildong')
            && ! str_contains($mail->mailBody, 'hong@test.local'));
    }

    public function test_shows_the_same_generic_message_whether_or_not_an_account_matches(): void
    {
        Mail::fake();
        app(SiteSettingService::class)->set('email_find_id_enabled', '1', 'mail');

        $noMatchResponse = $this->post(front_route('find-id.submit'), ['name' => '없는사람', 'email' => 'nobody@test.local']);
        $noMatchStatus = $noMatchResponse->getSession()->get('status');

        Mail::assertNothingSent();
        $this->assertNotEmpty($noMatchStatus);

        User::create([
            'name' => '홍길동', 'email' => 'hong@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        $matchResponse = $this->post(front_route('find-id.submit'), ['name' => '홍길동', 'email' => 'hong@test.local']);
        $matchStatus = $matchResponse->getSession()->get('status');

        Mail::assertSent(TemplateMail::class);
        $this->assertSame($noMatchStatus, $matchStatus);
    }
}
