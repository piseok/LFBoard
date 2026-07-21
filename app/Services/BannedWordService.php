<?php

namespace App\Services;

use App\Models\BannedWord;
use Illuminate\Support\Facades\Cache;

class BannedWordService
{
    public function check(string $word, string $type): bool
    {
        $words = Cache::remember("banned_words.{$type}", 3600, function () use ($type) {
            return BannedWord::query()
                ->where(function ($query) use ($type) {
                    $query->where('type', $type)->orWhere('type', 'all');
                })
                ->pluck('word')
                ->all();
        });

        foreach ($words as $banned) {
            if ($banned !== '' && mb_stripos($word, $banned) !== false) {
                return true;
            }
        }

        return false;
    }
}
