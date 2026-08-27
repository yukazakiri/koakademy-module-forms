<?php

declare(strict_types=1);

namespace Modules\Forms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Forms\Database\Factories\FormInvitationFactory;

final class FormInvitation extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'form_invitations';

    protected $fillable = [
        'form_id',
        'model_key',
        'model_type',
        'model_id',
        'token_hash',
        'recipient_email',
        'status',
        'expires_at',
        'sent_at',
        'opened_at',
        'completed_at',
        'response_id',
        'error_message',
    ];

    protected $hidden = [
        'token_hash',
        'recipient_email',
    ];

    public static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function newToken(): string
    {
        return Str::random(64);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class, 'response_id');
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['pending', 'sent'], true)
            && $this->expires_at?->isFuture() === true;
    }

    protected function casts(): array
    {
        return [
            'recipient_email' => 'encrypted',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): FormInvitationFactory
    {
        return FormInvitationFactory::new();
    }
}
