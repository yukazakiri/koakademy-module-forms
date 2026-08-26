<?php

declare(strict_types=1);

namespace Modules\Forms\Enums;

enum FormAccessMode: string
{
    case Authenticated = 'authenticated';
    case GuestIdentifier = 'guest_identifier';
    case Anonymous = 'anonymous';
}
