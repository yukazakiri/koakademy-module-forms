<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Forms\Services\KoAkademyFormsTenantCountryResolver;

it('resolves an active school country code and fails closed for missing schools', function (): void {
    ensureHostStudentProfileClasses();
    $schoolsTableCreated = ! Schema::hasTable('schools');
    if ($schoolsTableCreated) {
        Schema::create('schools', function (Blueprint $table): void {
            $table->id();
            $table->string('country_code')->nullable();
            $table->softDeletes();
        });
    }

    try {
        $schoolId = DB::table('schools')->insertGetId(['country_code' => ' ph ']);
        $resolver = new KoAkademyFormsTenantCountryResolver;

        expect($resolver->countryCode($schoolId))->toBe('PH')
            ->and($resolver->countryCode(999_999))->toBeNull();
    } finally {
        if ($schoolsTableCreated) {
            Schema::dropIfExists('schools');
        }
    }
});

it('does not resolve a soft-deleted school country code', function (): void {
    ensureHostStudentProfileClasses();
    $schoolsTableCreated = ! Schema::hasTable('schools');
    if ($schoolsTableCreated) {
        Schema::create('schools', function (Blueprint $table): void {
            $table->id();
            $table->string('country_code')->nullable();
            $table->softDeletes();
        });
    }

    try {
        $schoolId = DB::table('schools')->insertGetId([
            'country_code' => 'PH',
            'deleted_at' => now(),
        ]);

        expect((new KoAkademyFormsTenantCountryResolver)->countryCode($schoolId))->toBeNull();
    } finally {
        if ($schoolsTableCreated) {
            Schema::dropIfExists('schools');
        }
    }
});

function ensureHostStudentProfileClasses(): void
{
    if (! class_exists('App\Models\Student')) {
        eval('namespace App\Models; class Student {}');
    }

    if (! class_exists('App\Support\RegistrarStudentProfileWorkbook')) {
        eval('namespace App\Support; class RegistrarStudentProfileWorkbook { public function fields(): array { return []; } }');
    }
}
