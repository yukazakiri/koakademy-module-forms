<?php

declare(strict_types=1);

namespace Modules\Forms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FormTemplate extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'form_templates';

    protected $fillable = [
        'tenant_key',
        'created_by',
        'name',
        'description',
        'model_key',
        'definition',
    ];

    /** @return HasMany<Form, $this> */
    public function forms(): HasMany
    {
        return $this->hasMany(Form::class, 'template_id');
    }

    public function scopeForTenant(Builder $query, string|int|null $tenantKey): Builder
    {
        if ($tenantKey === null) {
            return $query->whereNull('tenant_key');
        }

        return $query->where('tenant_key', (string) $tenantKey);
    }

    protected function casts(): array
    {
        return [
            'definition' => 'array',
        ];
    }
}
