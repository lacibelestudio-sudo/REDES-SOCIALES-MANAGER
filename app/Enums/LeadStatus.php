<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Qualified = 'qualified';
    case Opportunity = 'opportunity';
    case Won = 'won';
    case Lost = 'lost';
}
