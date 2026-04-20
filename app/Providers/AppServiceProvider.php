<?php

namespace App\Providers;
use App\Resolvers\ContractSignableResolver;
use App\Resolvers\InvestorRequestSignableResolver;
use App\Services\SignableRegistry;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registry is a singleton — one instance for the entire request lifecycle
        $this->app->singleton(SignableRegistry::class, function () {
            $registry = new SignableRegistry();
            $registry->register(new ContractSignableResolver());
            $registry->register(new InvestorRequestSignableResolver());
            // Future: $registry->register(new SomeOtherSignableResolver());
            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceRootUrl(config('app.url'));
        URL::forceScheme('https');
    }
}
