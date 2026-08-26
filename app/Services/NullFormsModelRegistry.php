<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Modules\Forms\Contracts\FormsModelRegistry;

final class NullFormsModelRegistry implements FormsModelRegistry
{
    public function models(): array
    {
        return [];
    }

    public function fields(string $modelKey): array
    {
        return [];
    }

    public function resolveByIdentifier(string $modelKey, string $identifierType, string $identifier): ?object
    {
        return null;
    }

    public function resolveForUser(string $modelKey, mixed $user): ?object
    {
        return null;
    }

    public function resolveById(string $modelKey, string|int $id): ?object
    {
        return null;
    }

    public function read(object $record, string $path): mixed
    {
        return null;
    }

    public function write(object $record, string $path, mixed $value): void {}

    public function persist(object $record): void {}
}
