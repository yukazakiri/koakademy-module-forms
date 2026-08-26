<?php

declare(strict_types=1);

namespace Modules\Forms\Enums;

enum FormStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
}
