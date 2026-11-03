<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\PhotoAlbum;
use App\Models\SavedCollection;
use App\Policies\MediaPolicy;
use App\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\Feed\FeedCacheService::class);
        $this->app->singleton(\App\Services\Messaging\PresenceService::class);
    }

    public function boot(): void
    {
        // Never wrap single resources in a "data" envelope — the frontend's
        // api layer expects the object at the top level.
        JsonResource::withoutWrapping();

        // Policies Laravel cannot auto-discover (model ↔ policy name mismatch).
        Gate::policy(PhotoAlbum::class, MediaPolicy::class);
        Gate::policy(SavedCollection::class, UserPolicy::class);

        // Implicit "admin" gate. There is no separate admin role in this build;
        // the hook exists so permissions have one place to grow.
        Gate::define('manageContent', fn (\App\Models\User $user): bool => false);

        Password::defaults(fn (): ?Password => Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->uncompromised());

        Model::shouldBeStrict(! $this->app->isProduction());

        // Catch N+1 queries in development: they are the single biggest
        // performance risk in a feed-driven product.
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->configureDatabase();
    }

    /**
     * Guard against slow queries and force HTTPS on production URLs.
     */
    private function configureDatabase(): void
    {
        DB::whenQueryingForLongerThan(
            1000,
            fn (): mixed => logger()->warning('Slow query detected', [
                'queries' => DB::getQueryLog(),
            ])
        );

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
