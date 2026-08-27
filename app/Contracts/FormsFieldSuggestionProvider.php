<?php

declare(strict_types=1);

namespace Modules\Forms\Contracts;

use Modules\Forms\Models\FormField;

interface FormsFieldSuggestionProvider
{
    /** @return list<string> */
    public function suggestions(FormField $field, ?string $query = null, int $limit = 10): array;
}
