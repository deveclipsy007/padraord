<?php

namespace App\Providers;

use App\AI\AiConfiguration;
use App\AI\AiProvider;
use App\AI\DemoAiProvider;
use App\AI\DemoContextIntelligenceExtractor;
use App\AI\MeteredAiProvider;
use App\AI\NullAiProvider;
use App\AI\OpenAiAudioTranscriber;
use App\AI\OpenAiContextIntelligenceExtractor;
use App\Channels\ManualCommercialChannel;
use App\Contracts\AudioTranscriber;
use App\Contracts\CommercialChannel;
use App\Contracts\ContextIntelligenceExtractor;
use App\Contracts\MediaPreparationProvider;
use App\Contracts\OdooCostExporter;
use App\Enums\Ability;
use App\Integrations\Odoo\Json2OdooCostExporter;
use App\Integrations\Odoo\NullOdooCostExporter;
use App\Media\PassThroughMediaPreparationProvider;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CommercialChannel::class, ManualCommercialChannel::class);
        $this->app->bind(MediaPreparationProvider::class, PassThroughMediaPreparationProvider::class);
        $this->app->bind(AudioTranscriber::class, OpenAiAudioTranscriber::class);
        $this->app->bind(ContextIntelligenceExtractor::class, function () {
            return app(AiConfiguration::class)->publicState()['mode'] === 'demo'
                ? new DemoContextIntelligenceExtractor
                : app(OpenAiContextIntelligenceExtractor::class);
        });
        $this->app->bind(OdooCostExporter::class, fn () => config('odoo.mode') === 'json2' ? new Json2OdooCostExporter : new NullOdooCostExporter);
        $this->app->bind(AiProvider::class, function (): AiProvider {
            $configuration = app(AiConfiguration::class);
            $state = $configuration->publicState();
            $mode = $state['mode'];
            if ($mode === 'demo') {
                return new DemoAiProvider;
            }
            if ($state['status'] !== 'ready') {
                return new NullAiProvider;
            }

            return new MeteredAiProvider($configuration);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define(Ability::OperateWorkspace->value, fn (User $user): bool => $user->is_active);
        Gate::define(Ability::ManageTeam->value, fn (User $user): bool => $user->is_active && $user->isAdmin());
        Gate::define(Ability::ManageAi->value, fn (User $user): bool => $user->is_active && $user->isAdmin());
        Gate::define(Ability::ViewPilotFeedback->value, fn (User $user): bool => $user->is_active && $user->isAdmin());
        Gate::define(Ability::ManageDemo->value, fn (User $user): bool => $user->is_active && $user->isAdmin());
        Gate::define(Ability::ApproveCommercial->value, fn (User $user): bool => $user->is_active && $user->can_approve_commercial);

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower(Str::squish((string) $request->input('email')));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        // Redefinição de senha envia e-mail e aceita token: limitar por conta
        // alvo e por origem, para não virar sonda de e-mails nem força bruta.
        RateLimiter::for('password-reset', function (Request $request): Limit {
            $email = Str::lower(Str::squish((string) $request->input('email')));

            return Limit::perMinutes(15, 5)->by($email.'|'.$request->ip());
        });
    }
}
