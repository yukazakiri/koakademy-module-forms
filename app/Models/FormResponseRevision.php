<?php

declare(strict_types=1);

namespace Modules\Forms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Forms\Database\Factories\FormResponseRevisionFactory;

final class FormResponseRevision extends Model
{
    use HasFactory;
    use HasUlids;

    public $timestamps = false;

    protected $table = 'form_response_revisions';

    protected $fillable = [
        'form_response_id',
        'revision',
        'answer_payload',
        'field_snapshot',
        'created_by',
        'created_at',
    ];

    /** @return BelongsTo<FormResponse, $this> */
    public function response(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class, 'form_response_id');
    }

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function newFactory(): FormResponseRevisionFactory
    {
        return FormResponseRevisionFactory::new();
    }
}
