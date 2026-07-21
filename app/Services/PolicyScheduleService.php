<?php

namespace App\Services;

use App\Models\Policy;

class PolicyScheduleService
{
    public function applyDueChanges(): int
    {
        $due = Policy::query()
            ->whereNotNull('effective_at')
            ->whereNotNull('pending_version')
            ->where('effective_at', '<=', now())
            ->get();

        foreach ($due as $policy) {
            $policy->applyPendingChange();
        }

        return $due->count();
    }
}
