<?php

declare(strict_types=1);

namespace Modules\Forms\Contracts;

interface FormsTenantResolver
{
    public function key(): string|int|null;
}
