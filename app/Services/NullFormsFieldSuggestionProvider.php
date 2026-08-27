<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Modules\Forms\Contracts\FormsFieldSuggestionProvider;
use Modules\Forms\Models\FormField;

final class NullFormsFieldSuggestionProvider implements FormsFieldSuggestionProvider
{
    public function suggestions(FormField $field, ?string $query = null, int $limit = 10): array
    {
        return [];
    }
}
