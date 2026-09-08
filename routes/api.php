<?php

use App\Http\Controllers\Api\V1\Admin\AdminDepartmentController;
use App\Http\Controllers\Api\V1\Admin\AdminTransactionController;
use App\Http\Controllers\Api\V1\Admin\AdminTransactionTypeController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AdminWorkflowStepController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\ManagerTransactionController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\TransactionTypeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Admin\AdminTransactionTypeFieldController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Api\V1\Admin\AdminAttendanceController;
Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::patch('/auth/me', [AuthController::class, 'updateProfile']);
        Route::patch('/auth/password', [AuthController::class, 'changePassword']);
        Route::get(
    '/attendance/current',
    [AttendanceController::class, 'current']
);

Route::post(
    '/attendance/check-in',
    [AttendanceController::class, 'checkIn']
);

Route::post(
    '/attendance/check-out',
    [AttendanceController::class, 'checkOut']
);
    });

    /*
    |--------------------------------------------------------------------------
    | Reference Data (authenticated)
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/departments', [DepartmentController::class, 'index']);
        Route::get('/departments/{department}', [DepartmentController::class, 'show']);

        Route::get('/transaction-types', [TransactionTypeController::class, 'index']);
        Route::get('/transaction-types/{transactionType}', [TransactionTypeController::class, 'show']);
        Route::get('/transaction-types/{transactionType}/fields', [TransactionTypeController::class, 'fields']
);
    });

    /*
    |--------------------------------------------------------------------------
    | Employee Transactions
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::post('/transactions', [TransactionController::class, 'store']);
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
        Route::patch('/transactions/{transaction}', [TransactionController::class, 'update']);
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);
        Route::post('/transactions/{transaction}/submit', [TransactionController::class, 'submit']);
        Route::post('/transactions/{transaction}/resubmit', [TransactionController::class, 'resubmit']);
        Route::get('/transactions/{transaction}/history', [TransactionController::class, 'history']);
        Route::get('/transactions/{transaction}/workflow', [TransactionController::class, 'workflow']);
        Route::post('/transactions/{transaction}/attachments', [TransactionController::class, 'attachments']);
        Route::delete('/transactions/{transaction}/attachments/{attachment}', [TransactionController::class, 'destroyAttachment']);
    });

    /*
    |--------------------------------------------------------------------------
    | Manager Workflow
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'active'])->prefix('manager')->group(function () {
        Route::get('/pending-transactions', [ManagerTransactionController::class, 'pendingTransactions']);
        Route::get('/transactions/{transaction}', [ManagerTransactionController::class, 'show']);
        Route::post('/transactions/{transaction}/approve', [ManagerTransactionController::class, 'approve']);
        Route::post('/transactions/{transaction}/return', [ManagerTransactionController::class, 'return']);
        Route::post('/transactions/{transaction}/reject', [ManagerTransactionController::class, 'reject']);
    });

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Dashboards
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/dashboard/employee', [DashboardController::class, 'employee']);
        Route::get('/dashboard/manager', [DashboardController::class, 'manager']);
        Route::get('/dashboard/admin', [DashboardController::class, 'admin']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'active', 'admin'])->prefix('admin')->group(function () {
        // Users
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::patch('/users/{user}', [AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
        Route::post('/users/{user}/activate', [AdminUserController::class, 'activate']);
        Route::post('/users/{user}/deactivate', [AdminUserController::class, 'deactivate']);

        // Departments
        Route::get('/departments', [AdminDepartmentController::class, 'index']);
        Route::post('/departments', [AdminDepartmentController::class, 'store']);
        Route::get('/departments/{department}', [AdminDepartmentController::class, 'show']);
        Route::patch('/departments/{department}', [AdminDepartmentController::class, 'update']);
        Route::delete('/departments/{department}', [AdminDepartmentController::class, 'destroy']);
        Route::post('/departments/{department}/activate', [AdminDepartmentController::class, 'activate']);
        Route::post('/departments/{department}/deactivate', [AdminDepartmentController::class, 'deactivate']);
//attendance
        Route::get(
    '/attendance/report',
    [AdminAttendanceController::class, 'report']
);
        // Transaction types
        Route::get('/transaction-types', [AdminTransactionTypeController::class, 'index']);
        Route::post('/transaction-types', [AdminTransactionTypeController::class, 'store']);
        Route::get('/transaction-types/{transactionType}', [AdminTransactionTypeController::class, 'show']);
        Route::patch('/transaction-types/{transactionType}', [AdminTransactionTypeController::class, 'update']);
        Route::delete('/transaction-types/{transactionType}', [AdminTransactionTypeController::class, 'destroy']);
        Route::post('/transaction-types/{transactionType}/activate', [AdminTransactionTypeController::class, 'activate']);
        Route::post('/transaction-types/{transactionType}/deactivate', [AdminTransactionTypeController::class, 'deactivate']);

        // Workflow steps
        Route::get('/transaction-types/{transactionType}/workflow-steps', [AdminWorkflowStepController::class, 'index']);
        Route::post('/transaction-types/{transactionType}/workflow-steps', [AdminWorkflowStepController::class, 'store']);
        Route::put('/transaction-types/{transactionType}/workflow-steps/reorder', [AdminWorkflowStepController::class, 'reorder']);
        Route::patch('/workflow-steps/{workflowStep}', [AdminWorkflowStepController::class, 'update']);
        Route::delete('/workflow-steps/{workflowStep}', [AdminWorkflowStepController::class, 'destroy']);

        // Transactions
        Route::get('/transactions', [AdminTransactionController::class, 'index']);
        Route::get('/transactions/{transaction}', [AdminTransactionController::class, 'show']);
        Route::post('/transactions/{transaction}/complete', [AdminTransactionController::class, 'complete']);

        Route::get(
        '/transaction-types/{transactionType}/fields',
        [AdminTransactionTypeFieldController::class, 'index']
    );

    Route::post(
        '/transaction-types/{transactionType}/fields',
        [AdminTransactionTypeFieldController::class, 'store']
    );

    Route::patch(
        '/transaction-type-fields/{transactionTypeField}',
        [AdminTransactionTypeFieldController::class, 'update']
    );

    Route::delete(
        '/transaction-type-fields/{transactionTypeField}',
        [AdminTransactionTypeFieldController::class, 'destroy']
    );

    Route::put(
        '/transaction-types/{transactionType}/fields/reorder',
        [AdminTransactionTypeFieldController::class, 'reorder']
    );  
    });
});
