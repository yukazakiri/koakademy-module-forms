<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Contracts\FormsTenantResolver;
use Modules\Forms\Enums\FormStatus;
use Modules\Forms\Models\Form;

final class FormLifecycleService
{
    public function __construct(
        private readonly FormsTenantResolver $tenantResolver,
        private readonly FormsModelRegistry $models,
    ) {}

    /** @param array<string, mixed> $payload */
    public function create(array $payload, mixed $actor): Form
    {
        return DB::transaction(function () use ($payload, $actor): Form {
            $form = Form::query()->create([
                'tenant_key' => $this->tenantResolver->key() === null ? null : (string) $this->tenantResolver->key(),
                'created_by' => data_get($actor, 'id'),
                'template_id' => $payload['template_id'] ?? null,
                'title' => $payload['title'],
                'slug' => $payload['slug'] ?: Str::slug($payload['title']).'-'.Str::lower(Str::random(6)),
                'description' => $payload['description'] ?? null,
                'status' => $payload['status'] ?? FormStatus::Draft->value,
                'access_mode' => $payload['access_mode'],
                'identity_type' => $payload['identity_type'] ?? null,
                'settings' => $payload['settings'] ?? [],
                'version' => 1,
                'published_at' => ($payload['status'] ?? null) === FormStatus::Published->value ? now() : null,
                'closes_at' => $payload['closes_at'] ?? null,
            ]);

            $this->replaceFields($form, $payload['fields'] ?? []);

            return $form->load('fields');
        });
    }

    /** @param array<string, mixed> $payload */
    public function update(Form $form, array $payload): Form
    {
        return DB::transaction(function () use ($form, $payload): Form {
            $form->update([
                'title' => $payload['title'],
                'slug' => $payload['slug'] ?: $form->slug,
                'description' => $payload['description'] ?? null,
                'access_mode' => $payload['access_mode'],
                'identity_type' => $payload['identity_type'] ?? null,
                'settings' => $payload['settings'] ?? [],
                'closes_at' => $payload['closes_at'] ?? null,
                'version' => $form->version + 1,
            ]);

            $this->replaceFields($form, $payload['fields'] ?? []);

            return $form->fresh('fields');
        });
    }

    public function publish(Form $form): Form
    {
        $form->update([
            'status' => FormStatus::Published,
            'published_at' => $form->published_at ?? now(),
        ]);

        return $form->fresh();
    }

    public function close(Form $form): Form
    {
        $form->update(['status' => FormStatus::Closed]);

        return $form->fresh();
    }

    /** @param array<int, array<string, mixed>> $fields */
    private function replaceFields(Form $form, array $fields): void
    {
        $form->fields()->delete();
        $approvedPathsByModel = [];

        foreach (array_values($fields) as $position => $field) {
            $form->fields()->create([
                'field_key' => $field['field_key'],
                'label' => $field['label'],
                'type' => $field['type'],
                'description' => $field['description'] ?? null,
                'section' => $field['section'] ?? null,
                'required' => (bool) ($field['required'] ?? false),
                'position' => $position,
                'options' => $field['options'] ?? [],
                'validation' => $field['validation'] ?? [],
                'presentation' => $field['presentation'] ?? [],
                'behavior' => $field['behavior'] ?? [],
                'visibility' => $field['visibility'] ?? null,
                'mapping' => $this->approvedMapping($field['mapping'] ?? null, $approvedPathsByModel),
                'is_sensitive' => (bool) ($field['is_sensitive'] ?? false),
            ]);
        }
    }

    /**
     * @param  array<string, list<string>>  $approvedPathsByModel
     * @return array{model: string, path: string}|null
     */
    private function approvedMapping(mixed $mapping, array &$approvedPathsByModel): ?array
    {
        if (! is_array($mapping)) {
            return null;
        }

        $model = $mapping['model'] ?? null;
        $path = $mapping['path'] ?? null;
        if (! is_string($model) || ! is_string($path) || $model === '' || $path === '') {
            return null;
        }

        $approvedPaths = $approvedPathsByModel[$model] ??= collect($this->models->fields($model))
            ->flatMap(fn (array $field): array => $field['write_paths'] ?? [])
            ->filter(fn (mixed $approvedPath): bool => is_string($approvedPath))
            ->values()
            ->all();

        return in_array($path, $approvedPaths, true) ? ['model' => $model, 'path' => $path] : null;
    }
}
