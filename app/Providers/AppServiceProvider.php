<?php

namespace App\Providers;

use App\Models\Department;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use App\Models\TransactionType;
use App\Models\User;
use App\Policies\DepartmentPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\TransactionAttachmentPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\TransactionTypePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(TransactionType::class, TransactionTypePolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(TransactionAttachment::class, TransactionAttachmentPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
    }
}
