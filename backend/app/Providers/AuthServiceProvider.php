<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('manage-organization-data', function ($user) {
            return $user->role === 'admin';
        });

        Gate::define('register-files', function ($user) {
            return in_array($user->role, ['admin', 'registry_staff'], true);
        });

        Gate::define('create-transfers', function ($user) {
            return in_array($user->role, ['admin', 'registry_staff', 'supervisor'], true);
        });

        Gate::define('manage-issues', function ($user) {
            return in_array($user->role, ['admin', 'registry_staff', 'supervisor'], true);
        });
    }
}
