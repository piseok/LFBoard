<?php

namespace App\Http\Controllers;

use App\Models\Policy;
use Illuminate\Contracts\View\View;

class PolicyController extends Controller
{
    public function show(string $type): View
    {
        $policy = Policy::findByType($type, app()->getLocale());

        abort_if(! $policy, 404);

        return view('policy.show', [
            'policy' => $policy,
            'pageTitle' => $policy->title,
        ]);
    }

    public function changeNotice(string $type): View
    {
        $policy = Policy::findByType($type, app()->getLocale());

        abort_if(! $policy || ! $policy->hasPendingChange(), 404);

        return view('policy.change-notice', [
            'policy' => $policy,
            'pageTitle' => $policy->title.' '.__('변경 예고'),
        ]);
    }
}
