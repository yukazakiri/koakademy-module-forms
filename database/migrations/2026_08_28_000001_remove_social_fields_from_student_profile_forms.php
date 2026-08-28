<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const SOCIAL_FIELDS = [
        'facebook_contact',
        'twitter',
        'instagram',
        'linkedin',
    ];

    public function up(): void
    {
        DB::table('forms')
            ->where('settings->template_key', 'student_profile_completion')
            ->pluck('id')
            ->each(function (mixed $formId): void {
                DB::table('form_fields')
                    ->where('form_id', $formId)
                    ->whereIn('field_key', self::SOCIAL_FIELDS)
                    ->delete();
            });
    }

    public function down(): void
    {
        // Social fields were intentionally removed from the built-in profile form.
    }
};
