<?php

declare(strict_types=1);

namespace Modules\Forms\Contracts;

interface FormsLockableModelRegistry
{
    public function lock(object $record): object;
}
