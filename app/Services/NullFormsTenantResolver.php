<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Modules\Forms\Contracts\FormsTenantResolver;

final class NullFormsTenantResolver implements FormsTenantResolver
{
    public function key(): string|int|null
    {
        return null;
    }
}
