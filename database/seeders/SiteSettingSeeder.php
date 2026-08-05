<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // 일반
            'general' => [
                'site_name' => 'LFboard',
                'site_description' => '',
                'site_keywords' => '',
                'site_logo' => '',
                'site_favicon' => '',
                'admin_email' => 'admin@admin.com',
                'meta_title_separator' => ' | ',
                'footer_copyright' => '',
                'footer_address' => '',
                'maintenance_report_url' => '',
                'maintenance_report_token' => '',
                'google_analytics' => '',
                'head_scripts' => '',
                'body_scripts' => '',
                'default_locale' => 'ko',
                'show_footer_inquiry' => '1',
                'show_quick_menu' => '1',
                'inquiry_categories' => '["일반문의","견적문의","기술지원","기타"]',
                'sitemap_enabled' => '1',
                'robots_txt' => "User-agent: *\nAllow: /",
                // 관리자 접근 경로 (S-7): 기본값 admin, 설치 마법사에서 변경 가능
                'admin_path' => 'admin',
            ],

            // 메일
            'mail' => [
                // 비워두면 .env(MAIL_HOST 등) 설정을 사용하고, 입력하면 이 값이 우선됨 (EmailService 참고)
                'mail_host' => '',
                'mail_port' => '',
                'mail_username' => '',
                'mail_password' => '',
                'mail_encryption' => 'tls',
                'mail_from_address' => '',
                'mail_from_name' => '',
                'email_welcome_enabled' => '1',
                'email_inquiry_received_admin_enabled' => '1',
                'email_inquiry_received_user_enabled' => '1',
                'email_inquiry_reply_enabled' => '1',
                'email_password_reset_enabled' => '1',
                'email_marketing_broadcast_enabled' => '1',
            ],

            // 회원
            'member' => [
                'login_type' => 'email',
                'email_verification_enabled' => '0',
                'signup_approval_required' => '0',
                'signup_field_nickname' => 'hidden',
                'signup_field_phone' => 'optional',
                'signup_field_gender' => 'hidden',
                'signup_field_birthdate' => 'hidden',
                'signup_field_homepage' => 'hidden',
                'signup_field_address' => 'hidden',
            ],

            // 스팸방지
            'captcha' => [
                'captcha_provider' => '',
                'captcha_site_key' => '',
                'captcha_secret_key' => '',
                'captcha_apply_signup' => '1',
                'captcha_apply_login' => '0',
                'captcha_apply_inquiry' => '1',
            ],

            // 소셜 로그인 (차후 연동 준비)
            'social' => [
                'social_google_enabled' => '0',
                'social_google_client_id' => '',
                'social_google_client_secret' => '',
                'social_kakao_enabled' => '0',
                'social_kakao_client_id' => '',
                'social_kakao_client_secret' => '',
                'social_naver_enabled' => '0',
                'social_naver_client_id' => '',
                'social_naver_client_secret' => '',
            ],

            // 테마(2026-08) — 브랜드 컬러 3키만 관리자가 조정 가능. 기본값은 public/css/frontend.css의
            // --color-brand-primary/-dark/-accent 기본값과 동일해 non-breaking(설치 직후에도 시각적
            // 변화 없음). layouts/app.blade.php의 인라인 <style>이 이 값으로 CSS 변수를 오버라이드한다.
            'theme' => [
                'theme_color_brand_primary' => '#f58220',
                'theme_color_brand_primary_dark' => '#c96a15',
                'theme_color_brand_accent' => '#2563eb',
            ],

            // 차후 연동 여지 (SMS/카카오알림톡/본인인증)
            'integration' => [
                'sms_provider' => '',
                'sms_api_key' => '',
                'sms_api_secret' => '',
                'sms_from' => '',
                'kakao_provider' => '',
                'kakao_api_key' => '',
                'kakao_sender_key' => '',
                'identity_verification_enabled' => '0',
                'identity_verification_provider' => '',
                'identity_verification_api_key' => '',
            ],
        ];

        foreach ($settings as $group => $keys) {
            foreach ($keys as $key => $value) {
                SiteSetting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
            }
        }
    }
}
