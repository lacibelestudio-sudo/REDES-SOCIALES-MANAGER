<?php

declare(strict_types=1);

namespace App\Enums;

enum AdPlanStatus: string
{
    case Draft = 'draft';
    case ReadyForReview = 'ready_for_review';
}
