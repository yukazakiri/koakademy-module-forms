<?php

declare(strict_types=1);

namespace Modules\Forms\Providers;

use App\Models\Student;
use App\Services\TenantContext;
use App\Support\RegistrarStudentProfileWorkbook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Forms\Contracts\FormsFieldSuggestionProvider;
use Modules\Forms\Contracts\FormsInvitationTargetProvider;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Contracts\FormsTenantResolver;
use Modules\Forms\Services\KoAkademyFormsFieldSuggestionProvider;
use Modules\Forms\Services\KoAkademyFormsInvitationTargetProvider;
use Modules\Forms\Services\KoAkademyFormsModelRegistry;
use Modules\Forms\Services\KoAkademyFormsTenantResolver;
use Modules\Forms\Services\NullFormsFieldSuggestionProvider;
use Modules\Forms\Services\NullFormsInvitationTargetProvider;
use Modules\Forms\Services\NullFormsModelRegistry;
use Modules\Forms\Services\NullFormsTenantResolver;

final class FormsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations');
        $this->loadViewsFrom(dirname(__DIR__, 2).'/resources/views', 'forms');
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/forms.php', 'forms');
        RateLimiter::for('forms', fn ($request): Limit => Limit::perMinute(10)->by(
            $request->user()?->getAuthIdentifier() ?? $request->ip()
        ));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton(FormsModelRegistry::class, function (): FormsModelRegistry {
            return class_exists(Student::class) && class_exists(RegistrarStudentProfileWorkbook::class)
                ? new KoAkademyFormsModelRegistry
                : new NullFormsModelRegistry;
        });
        $this->app->singleton(FormsTenantResolver::class, function (): FormsTenantResolver {
            return class_exists(TenantContext::class)
                ? new KoAkademyFormsTenantResolver
                : new NullFormsTenantResolver;
        });
        $this->app->singleton(FormsInvitationTargetProvider::class, function (): FormsInvitationTargetProvider {
            return class_exists(Student::class) && class_exists(RegistrarStudentProfileWorkbook::class)
                ? new KoAkademyFormsInvitationTargetProvider
                : new NullFormsInvitationTargetProvider;
        });
        $this->app->singleton(FormsFieldSuggestionProvider::class, function (): FormsFieldSuggestionProvider {
            return class_exists(Student::class) && class_exists(RegistrarStudentProfileWorkbook::class)
                ? new KoAkademyFormsFieldSuggestionProvider
                : new NullFormsFieldSuggestionProvider;
        });
    }
}
