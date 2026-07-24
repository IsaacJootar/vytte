<?php

namespace App\Providers;

use App\Models\Assessment;
use App\Models\Project;
use App\Policies\AssessmentPolicy;
use App\Policies\ProjectPolicy;
use App\Services\Ai\AnthropicClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AnthropicClient::class, fn () => AnthropicClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Assessment::class, AssessmentPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
    }
}
