<?php

namespace App\Services;

use App\Models\Popup;
use Illuminate\Database\Eloquent\Collection;

class PopupService
{
    public function getActive(): Collection
    {
        $now = now();

        return Popup::query()
            ->where('locale', app()->getLocale())
            ->where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('started_at')->orWhere('started_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ended_at')->orWhere('ended_at', '>=', $now);
            })
            ->orderBy('sort_order')
            ->get();
    }
}
