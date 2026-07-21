<?php

namespace App\Http\Requests;

use App\Models\Policy;
use App\Services\BannedWordService;
use App\Services\CaptchaService;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $settings = app(SiteSettingService::class);
        $loginType = $settings->get('login_type', 'email');

        $rules = [
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];

        if ($loginType === 'username') {
            $rules['username'] = ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'];
        }

        foreach (['nickname', 'phone', 'gender', 'birthdate', 'homepage', 'address'] as $field) {
            $mode = $settings->get("signup_field_{$field}", 'hidden');

            if ($mode === 'required') {
                $rules[$field] = ['required', 'max:255'];
            } elseif ($mode === 'optional') {
                $rules[$field] = ['nullable', 'max:255'];
            }

            if ($field === 'gender' && isset($rules['gender'])) {
                $rules['gender'][] = Rule::in(['male', 'female']);
            }

            if ($field === 'phone' && isset($rules['phone'])) {
                $rules['phone_country'] = ['nullable', Rule::in(array_keys(require config_path('phone_countries.php')))];
            }
        }

        foreach (Policy::activeForLocale(app()->getLocale()) as $policy) {
            $rules["policy_{$policy->type}"] = $policy->is_required ? ['accepted'] : ['nullable', 'boolean'];
        }

        if ($settings->get('captcha_apply_signup') === '1' && ! empty($settings->get('captcha_provider'))) {
            $rules['captcha_token'] = ['required', 'string'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $settings = app(SiteSettingService::class);
            $bannedWords = app(BannedWordService::class);

            if ($this->filled('username') && $bannedWords->check((string) $this->input('username'), 'username')) {
                $validator->errors()->add('username', __('사용할 수 없는 아이디입니다.'));
            }

            if ($this->filled('nickname') && $bannedWords->check((string) $this->input('nickname'), 'nickname')) {
                $validator->errors()->add('nickname', __('사용할 수 없는 닉네임입니다.'));
            }

            if ($settings->get('captcha_apply_signup') === '1' && ! empty($settings->get('captcha_provider'))) {
                $captcha = app(CaptchaService::class);
                if (! $captcha->verify((string) $this->input('captcha_token'))) {
                    $validator->errors()->add('captcha_token', __('보안 인증에 실패했습니다.'));
                }
            }
        });
    }
}
