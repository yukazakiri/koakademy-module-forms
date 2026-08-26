<?php

declare(strict_types=1);

namespace Modules\Forms\Contracts;

interface FormsModelRegistry
{
    /** @return list<array{key: string, label: string}> */
    public function models(): array;

    /** @return list<array<string, mixed>> */
    public function fields(string $modelKey): array;

    public function resolveByIdentifier(string $modelKey, string $identifierType, string $identifier): ?object;

    public function resolveForUser(string $modelKey, mixed $user): ?object;

    public function resolveById(string $modelKey, string|int $id): ?object;

    public function read(object $record, string $path): mixed;

    public function write(object $record, string $path, mixed $value): void;

    public function persist(object $record): void;
}
