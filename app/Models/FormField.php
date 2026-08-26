<?php

declare(strict_types=1);

namespace Modules\Forms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Forms\Database\Factories\FormFieldFactory;

final class FormField extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'form_fields';

    protected $fillable = [
        'form_id',
        'field_key',
        'label',
        'type',
        'description',
        'required',
        'position',
        'options',
        'validation',
        'visibility',
        'mapping',
        'is_sensitive',
    ];

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'options' => 'array',
            'validation' => 'array',
            'visibility' => 'array',
            'mapping' => 'array',
            'is_sensitive' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function newFactory(): FormFieldFactory
    {
        return FormFieldFactory::new();
    }
}
