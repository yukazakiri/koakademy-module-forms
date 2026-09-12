<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Forms\Contracts\FormsTenantCountryResolver;

final class KoAkademyFormsTenantCountryResolver implements FormsTenantCountryResolver
{
    private ?bool $hasSchoolsTable = null;

    private ?bool $schoolsHaveSoftDeletes = null;

    private ?bool $schoolsHaveCountryCode = null;

    public function countryCode(string|int|null $tenantKey): ?string
    {
        if ($tenantKey === null || ! $this->hasSchoolsTable() || ! $this->schoolsHaveCountryCode()) {
            return null;
        }

        $query = DB::table('schools')->where('id', $tenantKey);
        if ($this->schoolsHaveSoftDeletes()) {
            $query->whereNull('deleted_at');
        }

        $countryCode = $query->value('country_code');
        if (! is_string($countryCode)) {
            return null;
        }

        $countryCode = mb_strtoupper(mb_trim($countryCode));

        return $countryCode === '' ? null : $countryCode;
    }

    private function hasSchoolsTable(): bool
    {
        return $this->hasSchoolsTable ??= Schema::hasTable('schools');
    }

    private function schoolsHaveSoftDeletes(): bool
    {
        return $this->schoolsHaveSoftDeletes ??= Schema::hasColumn('schools', 'deleted_at');
    }

    private function schoolsHaveCountryCode(): bool
    {
        return $this->schoolsHaveCountryCode ??= Schema::hasColumn('schools', 'country_code');
    }
}
