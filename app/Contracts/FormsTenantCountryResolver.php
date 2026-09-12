<?php

declare(strict_types=1);

namespace Modules\Forms\Contracts;

interface FormsTenantCountryResolver
{
    public function countryCode(string|int|null $tenantKey): ?string;
}
