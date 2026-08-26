<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Requests;

use Modules\Forms\Services\FormsAuthorization;

final class UpdateFormRequest extends StoreFormRequest
{
    public function authorize(): bool
    {
        return app(FormsAuthorization::class)->allows($this->user(), 'update');
    }
}
