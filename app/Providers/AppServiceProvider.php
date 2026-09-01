<?php

namespace App\Providers;

use App\AI\AiConfiguration;
use App\AI\AiProvider;
use App\AI\DemoAiProvider;
use App\AI\MeteredAiProvider;
use App\AI\NullAiProvider;
use App\AI\OpenAiAudioTranscriber;
use App\Contracts\AudioTranscriber;
use App\Contracts\ContextIntelligenceExtractor;
use App\AI\OpenAiContextIntelligenceExtractor;
use App\Contracts\OdooCostExporter;
use App\Integrations\Odoo\Json2OdooCostExporter;
use App\Integrations\Odoo\NullOdooCostExporter;
use App\Contracts\MediaPreparationProvider;
use App\Media\PassThroughMediaPreparationProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MediaPreparationProvider::class, PassThroughMediaPreparationProvider::class);
        $this->app->bind(AudioTranscriber::class, OpenAiAudioTranscriber::class);
        $this->app->bind(ContextIntelligenceExtractor::class, OpenAiContextIntelligenceExtractor::class);
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
        //
    }
}
