<?php

declare(strict_types=1);

namespace Modules\Forms\Enums;

enum FormResponseStatus: string
{
    case Submitted = 'submitted';
    case Reviewed = 'reviewed';
    case Applied = 'applied';
    case Rejected = 'rejected';
}
