<?php

declare(strict_types=1);

namespace Modules\Forms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Forms\Database\Factories\FormResponseLinkFactory;

final class FormResponseLink extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'form_response_links';

    protected $fillable = [
        'form_response_id',
        'model_key',
        'model_type',
        'model_id',
        'match_method',
        'status',
        'applied_by',
        'applied_at',
        'error_message',
    ];

    /** @return BelongsTo<FormResponse, $this> */
    public function response(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class, 'form_response_id');
    }

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
        ];
    }

    protected static function newFactory(): FormResponseLinkFactory
    {
        return FormResponseLinkFactory::new();
    }
}
