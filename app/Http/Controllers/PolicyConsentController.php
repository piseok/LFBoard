<?php

namespace App\Http\Controllers;

use App\Models\Policy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PolicyConsentController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $outdatedTypes = $request->user()->outdatedRequiredPolicyTypes();

        if (empty($outdatedTypes)) {
            return redirect(front_route('home'));
        }

        $policies = Policy::activeForLocale(app()->getLocale())
            ->whereIn('type', $outdatedTypes)
            ->values();

        return view('auth.policy-consent', [
            'pageTitle' => __('약관 재동의'),
            'policies' => $policies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $outdatedTypes = $user->outdatedRequiredPolicyTypes();

        $request->validate(
            collect($outdatedTypes)->mapWithKeys(fn (string $type) => ["policy_{$type}" => ['accepted']])->all()
        );

        foreach ($outdatedTypes as $type) {
            $policy = Policy::findByType($type, app()->getLocale());
            $user->recordPolicyConsent($type, app()->getLocale(), $policy?->version);
        }

        return redirect(front_route('home'))->with('status', __('약관 재동의가 완료되었습니다.'));
    }
}
