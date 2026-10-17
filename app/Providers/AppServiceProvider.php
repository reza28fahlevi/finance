<?php

namespace App\Providers;

use App\Models\AccountingPeriod;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(Permissions::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Paginator::defaultView('components.pagination');

        // RBAC: satu Gate per permission; matriks aktif diatur di Pengaturan → Role & Permission.
        foreach (Permissions::keys() as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with([
                'currentPeriod' => AccountingPeriod::current(),
                'pendingApprovalCount' => $user?->hasPermission('approval.act')
                    ? Transaction::where('status', 'PENDING_REVIEW')->where('created_by', '!=', $user->id)->count()
                    : 0,
                'companyName' => app(Settings::class)->get('company_name'),
            ]);
        });
    }
}
