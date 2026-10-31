<?php

declare(strict_types=1);

namespace App\Enums;

enum SalesCampaignStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Archived = 'archived';
}
