<?php

namespace App\Http\Requests;

use App\Services\IdentityVerificationService;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// 회원가입 시 관리자가 설정한 필드별 필수/선택/숨김(signup_field_*)을 마이페이지 수정 폼에도
// 그대로 적용한다 — RegisterRequest와 동일한 규칙 생성 로직(숨김 필드는 애초에 입력을 받지 않음).
// 본인인증이 켜져 있으면 name/phone/gender/birthdate는 IdentityVerificationController::callback()이
// 공급사 응답으로만 갱신하는 필드라, 여기서 임의 값을 받으면 인증된 정보와 어긋날 수 있어
// 폼 자체에서 아예 제외한다(본인인증을 다시 진행해야만 바뀜).
class MyPageUpdateRequest extends FormRequest
{
    private const IDENTITY_LOCKED_FIELDS = ['phone', 'gender', 'birthdate'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $settings = app(SiteSettingService::class);
        $identityLocked = app(IdentityVerificationService::class)->isEnabled();

        $rules = [];

        if (! $identityLocked) {
            $rules['name'] = ['required', 'string', 'max:50'];
        }

        foreach (self::editableFields() as $field) {
            if ($identityLocked && in_array($field, self::IDENTITY_LOCKED_FIELDS, true)) {
                continue;
            }

            $mode = $settings->get("signup_field_{$field}", 'hidden');

            if ($mode === 'required') {
                $rules[$field] = ['required', 'max:255'];
            } elseif ($mode === 'optional') {
                $rules[$field] = ['nullable', 'max:255'];
            }

            if ($field === 'gender' && isset($rules['gender'])) {
                $rules['gender'][] = Rule::in(['male', 'female']);
            }
        }

        return $rules;
    }

    /**
     * @return array<int, string>
     */
    public static function editableFields(): array
    {
        return ['nickname', 'phone', 'gender', 'birthdate', 'homepage', 'address'];
    }

    /**
     * @return array<int, string>
     */
    public static function identityLockedFields(): array
    {
        return self::IDENTITY_LOCKED_FIELDS;
    }
}
