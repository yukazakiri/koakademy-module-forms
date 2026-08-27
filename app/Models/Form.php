<?php

declare(strict_types=1);

namespace Modules\Forms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Forms\Database\Factories\FormFactory;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Enums\FormStatus;

final class Form extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'forms';

    protected $fillable = [
        'tenant_key',
        'created_by',
        'template_id',
        'title',
        'slug',
        'description',
        'status',
        'access_mode',
        'identity_type',
        'settings',
        'version',
        'published_at',
        'closes_at',
    ];

    protected $attributes = [
        'status' => 'draft',
        'access_mode' => 'authenticated',
        'version' => 1,
        'settings' => '[]',
    ];

    /** @return HasMany<FormField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('position');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'template_id');
    }

    /** @return HasMany<FormResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    public function scopeForTenant(Builder $query, string|int|null $tenantKey): Builder
    {
        if ($tenantKey === null) {
            return $query->whereNull('tenant_key');
        }

        return $query->where('tenant_key', (string) $tenantKey);
    }

    public function isOpen(): bool
    {
        return $this->status === FormStatus::Published
            && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    protected function casts(): array
    {
        return [
            'status' => FormStatus::class,
            'access_mode' => FormAccessMode::class,
            'settings' => 'array',
            'published_at' => 'datetime',
            'closes_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function newFactory(): FormFactory
    {
        return FormFactory::new();
    }
}
