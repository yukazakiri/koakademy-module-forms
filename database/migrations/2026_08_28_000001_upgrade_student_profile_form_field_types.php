<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $formIds = DB::table('forms')
            ->where('settings->template_key', 'student_profile_completion')
            ->pluck('id');

        if ($formIds->isEmpty()) {
            return;
        }

        DB::table('form_fields')
            ->whereIn('form_id', $formIds)
            ->get()
            ->each(function (object $field): void {
                $key = (string) $field->field_key;
                $type = (string) $field->type;
                $validation = json_decode((string) ($field->validation ?? '[]'), true) ?: [];

                if ($key === 'birth_date') {
                    $type = 'date';
                    $validation['before_or_equal'] = 'today';
                } elseif (str_contains($key, 'address')) {
                    $type = 'textarea';
                } elseif (in_array($key, ['phone', 'father_contact', 'mother_contact', 'guardian_contact', 'emergency_contact_phone'], true)) {
                    $type = 'phone';
                }

                DB::table('form_fields')
                    ->where('id', $field->id)
                    ->update([
                        'type' => $type,
                        'validation' => json_encode($validation, JSON_THROW_ON_ERROR),
                    ]);
            });
    }

    public function down(): void
    {
        // This migration upgrades stored form definitions and is intentionally forward-only.
    }
};
