<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * 기본 템플릿 정의. Resource의 "기본값으로 초기화" 액션에서도 재사용한다.
     *
     * @return array<int, array<string, string>>
     */
    public static function definitions(): array
    {
        return [
            [
                'type' => 'welcome',
                'name' => '회원가입 환영 메일',
                'subject' => '[{{site_name}}] 회원가입을 환영합니다',
                'body' => <<<'HTML'
                <p>안녕하세요, {{user_name}}님.</p>
                <p>{{site_name}}에 가입해 주셔서 감사합니다.</p>
                <p>가입하신 이메일 주소: {{user_email}}</p>
                <p>앞으로 다양한 소식과 서비스를 이용하실 수 있습니다.</p>
                HTML,
            ],
            [
                'type' => 'email_verification',
                'name' => '이메일 인증',
                'subject' => '[{{site_name}}] 이메일 주소를 인증해주세요',
                'body' => <<<'HTML'
                <p>안녕하세요, {{user_name}}님.</p>
                <p>아래 버튼을 클릭하여 이메일 인증을 완료해 주세요.</p>
                <p><a href="{{verification_url}}">이메일 인증하기</a></p>
                <p>본 링크는 발송 시점으로부터 24시간 동안 유효합니다.</p>
                HTML,
            ],
            [
                'type' => 'inquiry_received_admin',
                'name' => '상담 접수 알림 (관리자)',
                'subject' => '[{{site_name}}] 새 상담이 접수되었습니다 - {{inquiry_title}}',
                'body' => <<<'HTML'
                <p>새로운 상담이 접수되었습니다.</p>
                <ul>
                    <li>이름: {{inquiry_name}}</li>
                    <li>이메일: {{inquiry_email}}</li>
                    <li>연락처: {{inquiry_phone}}</li>
                    <li>카테고리: {{inquiry_category}}</li>
                    <li>유형: {{inquiry_type}}</li>
                    <li>제목: {{inquiry_title}}</li>
                </ul>
                <p>내용: {{inquiry_content}}</p>
                <p><a href="{{admin_url}}">관리자 페이지에서 확인하기</a></p>
                HTML,
            ],
            [
                'type' => 'inquiry_received_user',
                'name' => '상담 접수 확인 (문의자)',
                'subject' => '[{{site_name}}] 상담이 접수되었습니다',
                'body' => <<<'HTML'
                <p>{{inquiry_name}}님, 상담 접수가 완료되었습니다.</p>
                <p>제목: {{inquiry_title}}</p>
                <p>카테고리: {{inquiry_category}}</p>
                <p>담당자 확인 후 순차적으로 답변 드리겠습니다.</p>
                HTML,
            ],
            [
                'type' => 'inquiry_reply',
                'name' => '상담 답변 완료',
                'subject' => '[{{site_name}}] 상담 답변이 등록되었습니다 - {{inquiry_title}}',
                'body' => <<<'HTML'
                <p>{{inquiry_name}}님, 문의하신 상담에 답변이 등록되었습니다.</p>
                <p>제목: {{inquiry_title}}</p>
                <p>답변 내용:</p>
                <div>{{reply_content}}</div>
                HTML,
            ],
            [
                'type' => 'password_reset',
                'name' => '비밀번호 재설정',
                'subject' => '[{{site_name}}] 비밀번호 재설정 안내',
                'body' => <<<'HTML'
                <p>안녕하세요, {{user_name}}님.</p>
                <p>아래 링크를 클릭하여 비밀번호를 재설정해 주세요.</p>
                <p><a href="{{reset_url}}">비밀번호 재설정하기</a></p>
                <p>본 링크는 발송 시점으로부터 60분 동안 유효합니다.</p>
                HTML,
            ],
            [
                'type' => 'dormant_notice',
                'name' => '휴면 전환 예고',
                'subject' => '[{{site_name}}] 회원님의 계정이 곧 휴면 상태로 전환됩니다',
                'body' => <<<'HTML'
                <p>안녕하세요, {{user_name}}님.</p>
                <p>장기간(총 {{inactive_months}}개월 이상) 로그인 기록이 없어, 약 {{notice_days}}일 후 계정이 휴면 상태로 전환될 예정입니다.</p>
                <p>휴면 전환 후에도 로그인 시 간단한 절차로 즉시 해제할 수 있습니다.</p>
                <p>계속 이용하시려면 지금 로그인해 주세요: <a href="{{login_url}}">로그인하기</a></p>
                HTML,
            ],
            [
                'type' => 'withdrawal_notice',
                'name' => '휴면계정 강제탈퇴 예고',
                'subject' => '[{{site_name}}] 회원님의 계정이 곧 삭제(탈퇴) 처리됩니다',
                'body' => <<<'HTML'
                <p>안녕하세요, {{user_name}}님.</p>
                <p>장기간(총 {{inactive_months}}개월 이상) 로그인 기록이 없어, 약 {{notice_days}}일 후 계정이 자동으로 탈퇴 처리될 예정입니다.</p>
                <p>계정을 유지하시려면 지금 로그인해 주세요: <a href="{{login_url}}">로그인하기</a></p>
                <p>탈퇴 처리 후에는 계정 복구가 불가능합니다.</p>
                HTML,
            ],
            [
                'type' => 'policy_change_notice',
                'name' => '약관/방침 변경 안내',
                'subject' => '[{{site_name}}] {{policy_title}} 변경 안내',
                'body' => <<<'HTML'
                <p>안녕하세요, {{user_name}}님.</p>
                <p>{{policy_title}} 내용이 변경되어 안내드립니다.</p>
                <div>{{admin_message}}</div>
                {{diff_summary}}
                <p><a href="{{policy_url}}">변경된 전체 내용 보기</a></p>
                HTML,
            ],
            [
                'type' => 'login_country_changed',
                'name' => '새로운 국가에서 로그인 감지',
                'subject' => '[{{site_name}}] 새로운 국가에서 로그인이 감지되었습니다',
                'body' => <<<'HTML'
                <p>안녕하세요, {{user_name}}님.</p>
                <p><strong>{{country_name}}</strong>에서 새로운 로그인이 감지되었습니다.</p>
                <p>본인이 맞다면 별도 조치가 필요 없으며, {{trust_days}}일 후 이 국가는 신뢰할 수 있는 위치로 자동 등록됩니다.</p>
                <p><strong>본인이 아니라면 즉시 비밀번호를 변경해 주세요:</strong></p>
                <p><a href="{{reset_url}}">비밀번호 재설정하기</a></p>
                <p><a href="https://db-ip.com">IP Geolocation by DB-IP</a></p>
                HTML,
            ],
            [
                'type' => 'marketing_broadcast',
                'name' => '마케팅 메일',
                'subject' => '[{{site_name}}] ',
                'body' => <<<'HTML'
                <div>{{content}}</div>
                <hr>
                <p>더 이상 메일을 받고 싶지 않으시면 <a href="{{unsubscribe_url}}">수신거부</a>를 클릭해 주세요.</p>
                HTML,
            ],
        ];
    }

    /**
     * 어느 나라 회원이든 받을 수 있는 "범용" 메일 타입만 영어 버전을 별도로 둔다(사용자 지시 —
     * "한국어 제외하면 전부 영어로"). 일본어 등 그 외 모든 언어는 이 영어 버전으로 자동 폴백되므로
     * (EmailTemplate::findByType 참고) 언어가 늘어나도 이 목록을 늘릴 필요가 없다.
     * 휴면계정 안내처럼 한국 규제/정책 기반 메일은 한국어(ko) 한 버전만 발송 대상도 한국어 회원으로 제한됨
     * (DormantAccountService 참고, 6-1번에서 사용자가 확정한 설계) — 번역할 필요가 없어 여기 포함하지 않음.
     * 카카오 알림톡도 같은 이유로 한국 전용이라 별도 언어 버전이 필요 없음.
     * `policy_change_notice`(약관/방침 변경 안내)는 작성 당시(다국어 D섹션 10번 착수 전) "약관 자체가
     * 아직 언어별로 안 나뉘어 한국어 회원 전용"이라는 이유로 이 목록에서 제외돼 있었으나, 10번 완료 후
     * `policies`도 언어별 행 + 기본 언어 폴백을 갖추게 되어 그 전제가 사라짐 — 회귀 점검 중 발견해
     * `inquiry_received_user`/`inquiry_reply`와 동일한 "한국 제외 영어" 패턴으로 이 목록에 포함시킴
     * (PolicyChangeNoticeService::send()도 함께 수정 — 이제 실제로 변경된 정책을 보고 있는 언어의
     * 회원에게만 그 회원의 언어로 발송).
     * 1:1상담 메일 중 `inquiry_received_user`/`inquiry_reply`(문의자 본인이 받는 메일)는 사용자
     * 지시로 이 범용 목록에 포함(한국 제외 영어). 반면 `inquiry_received_admin`(운영자 알림)은 관리자
     * 패널 자체가 언어 라우팅과 무관한 한국어 전용 도구라 방문자 locale과 무관하게 항상 한국어로만
     * 발송하므로 여기 포함하지 않음(InquiryController::notify() 참고).
     *
     * @return array<int, array<string, string>>
     */
    public static function localizedDefinitions(): array
    {
        return [
            [
                'type' => 'welcome', 'locale' => 'en',
                'name' => 'Welcome Email',
                'subject' => '[{{site_name}}] Welcome!',
                'body' => <<<'HTML'
                <p>Hello, {{user_name}}.</p>
                <p>Thank you for joining {{site_name}}.</p>
                <p>Registered email address: {{user_email}}</p>
                <p>You can now enjoy a variety of news and services.</p>
                HTML,
            ],
            [
                'type' => 'email_verification', 'locale' => 'en',
                'name' => 'Email Verification',
                'subject' => '[{{site_name}}] Please verify your email address',
                'body' => <<<'HTML'
                <p>Hello, {{user_name}}.</p>
                <p>Please click the button below to verify your email address.</p>
                <p><a href="{{verification_url}}">Verify Email</a></p>
                <p>This link is valid for 24 hours from the time it was sent.</p>
                HTML,
            ],
            [
                'type' => 'password_reset', 'locale' => 'en',
                'name' => 'Password Reset',
                'subject' => '[{{site_name}}] Password reset instructions',
                'body' => <<<'HTML'
                <p>Hello, {{user_name}}.</p>
                <p>Please click the link below to reset your password.</p>
                <p><a href="{{reset_url}}">Reset Password</a></p>
                <p>This link is valid for 60 minutes from the time it was sent.</p>
                HTML,
            ],
            [
                'type' => 'login_country_changed', 'locale' => 'en',
                'name' => 'New Country Login Detected',
                'subject' => '[{{site_name}}] Login detected from a new country',
                'body' => <<<'HTML'
                <p>Hello, {{user_name}}.</p>
                <p>A new login was detected from <strong>{{country_name}}</strong>.</p>
                <p>If this was you, no action is needed — this country will be trusted automatically in {{trust_days}} days.</p>
                <p><strong>If this wasn't you, please change your password immediately:</strong></p>
                <p><a href="{{reset_url}}">Reset Password</a></p>
                <p><a href="https://db-ip.com">IP Geolocation by DB-IP</a></p>
                HTML,
            ],
            [
                'type' => 'inquiry_received_user', 'locale' => 'en',
                'name' => 'Inquiry Received (Customer)',
                'subject' => '[{{site_name}}] Your inquiry has been received',
                'body' => <<<'HTML'
                <p>{{inquiry_name}}, your inquiry has been received.</p>
                <p>Title: {{inquiry_title}}</p>
                <p>Category: {{inquiry_category}}</p>
                <p>Our team will review it and respond as soon as possible.</p>
                HTML,
            ],
            [
                'type' => 'inquiry_reply', 'locale' => 'en',
                'name' => 'Inquiry Reply',
                'subject' => '[{{site_name}}] A reply has been posted to your inquiry - {{inquiry_title}}',
                'body' => <<<'HTML'
                <p>{{inquiry_name}}, a reply has been posted to your inquiry.</p>
                <p>Title: {{inquiry_title}}</p>
                <p>Reply:</p>
                <div>{{reply_content}}</div>
                HTML,
            ],
            [
                'type' => 'policy_change_notice', 'locale' => 'en',
                'name' => 'Policy Change Notice',
                'subject' => '[{{site_name}}] {{policy_title}} has been updated',
                'body' => <<<'HTML'
                <p>Hello, {{user_name}}.</p>
                <p>We're writing to let you know that {{policy_title}} has changed.</p>
                <div>{{admin_message}}</div>
                {{diff_summary}}
                <p><a href="{{policy_url}}">View the full updated content</a></p>
                HTML,
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $template) {
            $template['locale'] ??= 'ko';
            EmailTemplate::updateOrCreate(['type' => $template['type'], 'locale' => $template['locale']], $template);
        }

        foreach (self::localizedDefinitions() as $template) {
            EmailTemplate::updateOrCreate(['type' => $template['type'], 'locale' => $template['locale']], $template);
        }
    }
}
