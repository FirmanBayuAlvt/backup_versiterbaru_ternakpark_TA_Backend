<?php

namespace App\Observers;

use App\Models\FeedingRecord;
use App\Services\HppCalculatorService;

class FeedingRecordObserver
{
    public function saved(FeedingRecord $feedingRecord)
    {
        if ($feedingRecord->livestock_id) {
            app(HppCalculatorService::class)->calculateForLivestock($feedingRecord->livestock_id);
        }
    }

    public function deleted(FeedingRecord $feedingRecord)
    {
        if ($feedingRecord->livestock_id) {
            app(HppCalculatorService::class)->calculateForLivestock($feedingRecord->livestock_id);
        }
    }
}