<?php

namespace App\Providers;

use App\Models\Assessment;
use App\Models\Project;
use App\Policies\AssessmentPolicy;
use App\Policies\ProjectPolicy;
use App\Services\Ai\AiChatClient;
use App\Services\Ai\OpenAiClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The AI narrative layer uses OpenAI (ChatGPT), bound behind a provider-agnostic
        // interface so it can be swapped without touching the reporting engine.
        $this->app->singleton(AiChatClient::class, fn () => OpenAiClient::fromConfig());
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
