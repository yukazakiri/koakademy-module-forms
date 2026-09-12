<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Modules\Forms\Contracts\FormsTenantCountryResolver;

final class NullFormsTenantCountryResolver implements FormsTenantCountryResolver
{
    public function countryCode(string|int|null $tenantKey): ?string
    {
        return null;
    }
}
