<?php

declare(strict_types=1);

namespace Modules\Forms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Forms\Database\Factories\FormAuditEventFactory;

final class FormAuditEvent extends Model
{
    use HasFactory;
    use HasUlids;

    public $timestamps = false;

    protected $table = 'form_audit_events';

    protected $fillable = [
        'form_id',
        'form_response_id',
        'actor_id',
        'action',
        'metadata',
        'created_at',
    ];

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function newFactory(): FormAuditEventFactory
    {
        return FormAuditEventFactory::new();
    }
}
