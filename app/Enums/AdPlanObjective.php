<?php

declare(strict_types=1);

namespace App\Enums;

enum AdPlanObjective: string
{
    case Awareness = 'awareness';
    case Traffic = 'traffic';
    case Engagement = 'engagement';
    case Leads = 'leads';
    case Sales = 'sales';
    case AppPromotion = 'app_promotion';
}
