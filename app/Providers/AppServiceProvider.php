<?php

namespace App\Providers;

use App\Domain\Geocoding\NominatimGeocoder;
use App\Domain\Installer\EnvWriter;
use App\Domain\Installer\InstallLock;
use App\Domain\Settings\SettingsRegistry;
use App\Support\Geocoder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsRegistry::class);
        $this->app->bind(Geocoder::class, NominatimGeocoder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Inertia props: single resources and plain collections arrive
        // unwrapped; paginators keep their data/links/meta envelope.
        JsonResource::withoutWrapping();

        $this->bootstrapInstallerEnvironment();
    }

    /**
     * Keep the web installer (M15) reachable on a fresh upload: before the
     * lock file exists the database is unusable, so session/cache/queue fall
     * back to file drivers, and a missing APP_KEY is generated on the fly
     * (cookie encryption needs one before the wizard can even render).
     */
    private function bootstrapInstallerEnvironment(): void
    {
        if ($this->app->runningUnitTests() || InstallLock::installed()) {
            return;
        }

        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'queue.default' => 'sync',
        ]);

        $key = config('app.key');
        if (($key === null || $key === '') && ! $this->app->runningInConsole()) {
            $generated = 'base64:'.base64_encode(random_bytes(32));

            try {
                EnvWriter::forApp()->write(['APP_KEY' => $generated]);
            } catch (RuntimeException) {
                // .env not writable — requirements screen will say so.
            }

            config(['app.key' => $generated]);
        }
    }
}
