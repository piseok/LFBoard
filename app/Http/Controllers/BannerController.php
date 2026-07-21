<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\RedirectResponse;

class BannerController extends Controller
{
    public function click(int $id): RedirectResponse
    {
        $banner = Banner::query()->where('is_active', true)->findOrFail($id);
        $banner->increment('click_count');

        $url = $banner->link_url ?: url('/');

        return preg_match('#^https?://#i', $url) ? redirect()->away($url) : redirect($url);
    }
}
