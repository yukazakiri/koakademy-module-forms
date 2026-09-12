<?php

declare(strict_types=1);

use Modules\Forms\Contracts\FormsFieldSuggestionProvider;
use Modules\Forms\Contracts\FormsInvitationTargetProvider;
use Modules\Forms\Contracts\FormsTenantCountryResolver;
use Modules\Forms\Providers\FormsServiceProvider;
use Modules\Forms\Services\KoAkademyFormsFieldSuggestionProvider;
use Modules\Forms\Services\KoAkademyFormsInvitationTargetProvider;
use Modules\Forms\Services\KoAkademyFormsTenantCountryResolver;

beforeEach(function (): void {
    if (! class_exists('App\Models\Student')) {
        eval('namespace App\Models; class Student {}');
    }

    if (! class_exists('App\Support\RegistrarStudentProfileWorkbook')) {
        eval('namespace App\Support; class RegistrarStudentProfileWorkbook { public function fields(): array { return []; } }');
    }

});

it('injects dependencies when constructing the KoAkademy providers', function (): void {
    (new FormsServiceProvider(app()))->register();

    expect(app(FormsFieldSuggestionProvider::class))
        ->toBeInstanceOf(KoAkademyFormsFieldSuggestionProvider::class)
        ->and(app(FormsInvitationTargetProvider::class))
        ->toBeInstanceOf(KoAkademyFormsInvitationTargetProvider::class)
        ->and(app(FormsTenantCountryResolver::class))
        ->toBeInstanceOf(KoAkademyFormsTenantCountryResolver::class);
});
