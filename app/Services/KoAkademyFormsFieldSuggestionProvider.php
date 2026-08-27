<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Modules\Forms\Contracts\FormsFieldSuggestionProvider;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Contracts\FormsTenantResolver;
use Modules\Forms\Models\FormField;

final class KoAkademyFormsFieldSuggestionProvider implements FormsFieldSuggestionProvider
{
    public function __construct(
        private readonly FormsModelRegistry $models,
        private readonly FormsTenantResolver $tenantResolver,
    ) {}

    public function suggestions(FormField $field, ?string $query = null, int $limit = 10): array
    {
        $presentation = is_array($field->presentation) ? $field->presentation : [];
        if (($presentation['suggestion_source'] ?? 'none') !== 'record_values') {
            return [];
        }

        $mapping = $field->mapping;
        if (! is_array($mapping) || ($mapping['model'] ?? null) !== 'student' || ! is_string($mapping['path'] ?? null)) {
            return [];
        }

        $allowedKeys = ['birthplace', 'region_of_origin', 'province_of_origin', 'city_of_origin', 'nationality', 'religion', 'civil_status'];
        if (! in_array($field->field_key, $allowedKeys, true)) {
            return [];
        }

        $limit = max(2, min($limit, 50));
        $cacheKey = 'forms:suggestions:'.($this->tenantResolver->key() ?? 'global').':'.sha1($field->field_key.':'.$mapping['path']);
        $values = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($mapping): array {
            $counts = [];
            $records = Student::query()
                ->with(['personalInfo'])
                ->when($this->tenantResolver->key() !== null, fn ($query) => $query->where('school_id', $this->tenantResolver->key()))
                ->cursor();

            foreach ($records as $record) {
                $value = $this->models->read($record, $mapping['path']);
                if (! is_scalar($value)) {
                    continue;
                }

                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }

                $key = mb_strtolower($value);
                $counts[$key] ??= ['label' => $value, 'count' => 0];
                $counts[$key]['count']++;
            }

            return collect($counts)
                ->filter(fn (array $item): bool => $item['count'] >= 2)
                ->sortByDesc('count')
                ->values()
                ->map(fn (array $item): string => $item['label'])
                ->all();
        });

        $query = mb_strtolower(trim((string) $query));

        return collect($values)
            ->filter(fn (string $value): bool => $query === '' || str_contains(mb_strtolower($value), $query))
            ->take($limit)
            ->values()
            ->all();
    }
}
