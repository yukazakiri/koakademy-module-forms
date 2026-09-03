<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $incomeFields = [
        'family_income_bracket',
        'father_income_bracket',
        'mother_income_bracket',
    ];

    public function up(): void
    {
        $options = $this->incomeBracketOptions();
        if ($options === []) {
            return;
        }

        $formIds = DB::table('forms')
            ->select(['id', 'settings'])
            ->get()
            ->filter(fn (object $form): bool => data_get($this->decodeJson($form->settings), 'template_key') === 'student_profile_completion')
            ->pluck('id')
            ->values()
            ->all();

        if ($formIds === []) {
            return;
        }

        DB::table('form_fields')
            ->whereIn('form_id', $formIds)
            ->whereIn('field_key', $this->incomeFields)
            ->update([
                'type' => 'select',
                'options' => json_encode($options, JSON_THROW_ON_ERROR),
                'presentation' => json_encode([
                    'control' => 'select',
                    'input_mode' => 'text',
                    'suggestion_source' => 'none',
                    'suggestion_limit' => 10,
                    'unit' => null,
                ], JSON_THROW_ON_ERROR),
            ]);
    }

    public function down(): void
    {
        throw new RuntimeException('This migration is forward-only.');
    }

    private function incomeBracketOptions(): array
    {
        $mode = (string) config('income_brackets.default_mode', '');
        $brackets = config('income_brackets.modes.'.$mode.'.brackets', []);
        if (! is_array($brackets)) {
            return [];
        }

        $symbol = $this->currencySymbol();

        return collect($brackets)
            ->mapWithKeys(fn (mixed $bracket, string $key): array => [
                $key => str_replace('{symbol}', $symbol, (string) data_get($bracket, 'label', '')),
            ])
            ->filter(fn (string $label): bool => $label !== '')
            ->all();
    }

    private function currencySymbol(): string
    {
        if (class_exists('App\\Settings\\SiteSettings')) {
            $currency = app('App\\Settings\\SiteSettings')->getCurrency();
            if ($currency instanceof BackedEnum) {
                $currency = $currency->value;
            } elseif ($currency instanceof UnitEnum) {
                $currency = $currency->name;
            }

            return match ((string) $currency) {
                'PHP' => '₱',
                'USD' => '$',
                default => (string) $currency,
            };
        }

        return '₱';
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
