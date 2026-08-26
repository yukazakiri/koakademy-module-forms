<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use App\Services\TenantContext;
use Modules\Forms\Contracts\FormsTenantResolver;

final class KoAkademyFormsTenantResolver implements FormsTenantResolver
{
    public function key(): string|int|null
    {
        if (! class_exists(TenantContext::class)) {
            return null;
        }

        return app(TenantContext::class)->getCurrentSchoolId();
    }
}
