<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

class MarketingController extends Controller
{
    public function unsubscribe(string $token): View
    {
        $user = User::query()->where('unsubscribe_token', $token)->first();

        if ($user && $user->marketing_agreed) {
            $user->forceFill([
                'marketing_agreed' => false,
                'marketing_agreed_at' => null,
            ])->save();
        }

        return view('marketing.unsubscribe', [
            'success' => (bool) $user,
            'pageTitle' => __('수신 거부'),
        ]);
    }
}
