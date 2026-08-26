<?php

declare(strict_types=1);

namespace Modules\Forms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Forms\Database\Factories\FormResponseFactory;
use Modules\Forms\Enums\FormResponseStatus;

final class FormResponse extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'form_responses';

    protected $fillable = [
        'form_id',
        'tenant_key',
        'respondent_user_id',
        'respondent_email',
        'respondent_identifier',
        'respondent_email_hash',
        'respondent_identifier_hash',
        'status',
        'review_notes',
        'latest_revision',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'applied_by',
        'applied_at',
    ];

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** @return HasMany<FormResponseRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(FormResponseRevision::class)->orderByDesc('revision');
    }

    /** @return HasMany<FormResponseLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(FormResponseLink::class);
    }

    protected function casts(): array
    {
        return [
            'status' => FormResponseStatus::class,
            'respondent_email' => 'encrypted',
            'respondent_identifier' => 'encrypted',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
            'latest_revision' => 'integer',
        ];
    }

    protected static function newFactory(): FormResponseFactory
    {
        return FormResponseFactory::new();
    }
}
