<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Employee dashboard.
     */
    public function employee(Request $request): JsonResponse
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Transactions
        |--------------------------------------------------------------------------
        */

        $transactionsQuery = $user->createdTransactions();

        /*
         * Instead of running one count query for every status,
         * get all status counts in one query.
         */
        $statusCounts = (clone $transactionsQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'total' => (clone $transactionsQuery)->count(),

            'draft' => (int) ($statusCounts['draft'] ?? 0),

            'pending' => (int) ($statusCounts['pending'] ?? 0),

            'returned' => (int) ($statusCounts['returned'] ?? 0),

            'rejected' => (int) ($statusCounts['rejected'] ?? 0),

            'approved' => (int) ($statusCounts['approved'] ?? 0),

            'completed' => (int) ($statusCounts['completed'] ?? 0),
        ];

        /*
        |--------------------------------------------------------------------------
        | Recent transactions
        |--------------------------------------------------------------------------
        */

        $recent = (clone $transactionsQuery)
            ->with('transactionType')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Unread notifications
        |--------------------------------------------------------------------------
        */

        $unread = $user
            ->notifications()
            ->where('is_read', false)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | User
        |--------------------------------------------------------------------------
        */

        $user->loadMissing('department:id,name');

        return $this->success([
            'user' => [
                'id' => $user->id,

                'name' => $user->name,

                'email' => $user->email,

                'job_title' => $user->job_title,

                'department' => $user->department
                    ? [
                        'id' => $user->department->id,
                        'name' => $user->department->name,
                    ]
                    : null,
            ],

            'leave_balance' => [
                'annual_leave_days' => $user->annual_leave_days,

                'used_leave_days' => $user->used_leave_days,

                'remaining_leave_days' => max(
                    0,
                    $user->annual_leave_days -
                        $user->used_leave_days
                ),
            ],

            'transaction_counts' => $counts,

            'recent_transactions' => $recent->map(
                fn ($transaction) => [
                    'id' => $transaction->id,

                    'transaction_number' =>
                        $transaction->transaction_number,

                    'title' => $transaction->title,

                    'status' =>
                        $transaction->status->value,

                    'priority' =>
                        $transaction->priority->value,

                    'transaction_type' =>
                        $transaction->transactionType?->name_en,

                    'created_at' =>
                        $transaction->created_at?->toISOString(),
                ]
            ),

            'unread_notification_count' => $unread,
        ]);
    }

    /**
     * Manager dashboard.
     */
    public function manager(Request $request): JsonResponse
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Managed departments
        |--------------------------------------------------------------------------
        */

        $departmentIds = $user
            ->managedDepartments()
            ->pluck('id')
            ->all();

        $departments = Department::query()
            ->whereIn('id', $departmentIds)
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Pending transactions
        |--------------------------------------------------------------------------
        */

        $pendingQuery = Transaction::query()
            ->where(
                'status',
                TransactionStatus::Pending
            )
            ->whereIn(
                'current_department_id',
                $departmentIds
            );

        $pendingCount = (clone $pendingQuery)->count();

        /*
        |--------------------------------------------------------------------------
        | Today
        |--------------------------------------------------------------------------
        */

        $today = now()->toDateString();

        $approvedToday = Transaction::query()
            ->where(
                'status',
                TransactionStatus::Approved
            )
            ->whereIn(
                'source_department_id',
                $departmentIds
            )
            ->whereDate(
                'approved_at',
                $today
            )
            ->count();

        $returnedToday = Transaction::query()
            ->where(
                'status',
                TransactionStatus::Returned
            )
            ->whereIn(
                'source_department_id',
                $departmentIds
            )
            ->whereDate(
                'returned_at',
                $today
            )
            ->count();

        $rejectedToday = Transaction::query()
            ->where(
                'status',
                TransactionStatus::Rejected
            )
            ->whereIn(
                'source_department_id',
                $departmentIds
            )
            ->whereDate(
                'rejected_at',
                $today
            )
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Pending transactions - first five
        |--------------------------------------------------------------------------
        */

        $pendingFive = (clone $pendingQuery)
            ->with([
                'creator',
                'transactionType',
            ])
            ->orderByRaw(
                "CASE priority
                    WHEN 'high' THEN 0
                    WHEN 'medium' THEN 1
                    ELSE 2
                END"
            )
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Department users
        |--------------------------------------------------------------------------
        |
        | Get all users for all managed departments in one query.
        |
        */

        $departmentUsers = User::query()
            ->whereIn(
                'department_id',
                $departmentIds
            )
            ->get([
                'id',
                'name',
                'email',
                'role',
                'department_id',
                'status',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Today's attendance
        |--------------------------------------------------------------------------
        |
        | One query instead of calling todayAttendance()
        | for every employee.
        |
        */

        $todayAttendances = Attendance::query()
            ->whereIn(
                'user_id',
                $departmentUsers->pluck('id')
            )
            ->whereDate(
                'date',
                $today
            )
            ->orderByDesc('id')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        /*
        |--------------------------------------------------------------------------
        | Leave transaction type
        |--------------------------------------------------------------------------
        */

        $leaveTypeId = TransactionType::query()
            ->where(
                'name_en',
                'Leave Request'
            )
            ->value('id');

        /*
        |--------------------------------------------------------------------------
        | Approved leaves covering today
        |--------------------------------------------------------------------------
        |
        | One query for all employees instead of one query
        | inside workStatus() for every employee.
        |
        */

        $leaveTransactions = collect();

        if ($leaveTypeId) {
            $leaveTransactions = Transaction::query()
                ->where(
                    'transaction_type_id',
                    $leaveTypeId
                )
                ->where(
                    'status',
                    TransactionStatus::Approved
                )
                ->whereDate(
                    'start_date',
                    '<=',
                    $today
                )
                ->whereDate(
                    'end_date',
                    '>=',
                    $today
                )
                ->whereIn(
                    'created_by',
                    $departmentUsers->pluck('id')
                )
                ->orderBy('end_date')
                ->get([
                    'id',
                    'created_by',
                    'title',
                    'start_date',
                    'end_date',
                ])
                ->groupBy('created_by');
        }

        /*
        |--------------------------------------------------------------------------
        | Department transaction counts
        |--------------------------------------------------------------------------
        |
        | One grouped query instead of one query per department.
        |
        */

        $transactionCounts = Transaction::query()
            ->selectRaw(
                'source_department_id, COUNT(*) as total'
            )
            ->whereIn(
                'source_department_id',
                $departmentIds
            )
            ->groupBy(
                'source_department_id'
            )
            ->pluck(
                'total',
                'source_department_id'
            );

        /*
        |--------------------------------------------------------------------------
        | Department summaries
        |--------------------------------------------------------------------------
        */

        $departmentSummaries = $departments
            ->map(
                function (Department $department) use (
                    $departmentUsers,
                    $todayAttendances,
                    $leaveTransactions,
                    $transactionCounts
                ) {
                    $employees = $departmentUsers
                        ->where(
                            'department_id',
                            $department->id
                        )
                        ->filter(
                            fn (User $departmentUser) =>
                                $departmentUser->role?->value === 'employee'
                        );

                    $working = 0;
                    $onLeave = 0;

                    foreach ($employees as $employee) {
                        /*
                         * Leave has priority,
                         * same behavior as User::workStatus().
                         */
                        $employeeLeaves =
                            $leaveTransactions->get(
                                $employee->id,
                                collect()
                            );

                        if ($employeeLeaves->isNotEmpty()) {
                            $onLeave++;
                            continue;
                        }

                        /*
                         * Inactive account.
                         */
                        if ($employee->status?->value !== 'active') {
                            continue;
                        }

                        /*
                         * Working = checked in
                         * and not checked out.
                         */
                        $attendance =
                            $todayAttendances->get(
                                $employee->id
                            );

                        if (
                            $attendance &&
                            $attendance->check_in_at &&
                            ! $attendance->check_out_at
                        ) {
                            $working++;
                        }
                    }

                    $employeeCount = $employees->count();

                    $notWorking =
                        $employeeCount -
                        $working -
                        $onLeave;

                    return [
                        'department' => [
                            'id' => $department->id,
                            'name' => $department->name,
                        ],

                        'employee_count' =>
                            $employeeCount,

                        'working' =>
                            $working,

                        'on_leave' =>
                            $onLeave,

                        'not_working' =>
                            max(
                                0,
                                $notWorking
                            ),

                        'transaction_count' =>
                            (int) (
                                $transactionCounts[
                                    $department->id
                                ] ?? 0
                            ),
                    ];
                }
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Department activity
        |--------------------------------------------------------------------------
        */

        $departmentActivity = $departmentSummaries
            ->map(
                fn ($summary) => [
                    'department' =>
                        $summary['department']['name'],

                    'transactions' =>
                        $summary['transaction_count'],
                ]
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return $this->success([
            'manager' => [
                'id' => $user->id,

                'name' => $user->name,

                'email' => $user->email,

                'job_title' => $user->job_title,
            ],

            'managed_departments' =>
                $departments,

            'pending_approval_count' =>
                $pendingCount,

            'approved_today_count' =>
                $approvedToday,

            'returned_today_count' =>
                $returnedToday,

            'rejected_today_count' =>
                $rejectedToday,

            'pending_transactions' =>
                $pendingFive->map(
                    fn ($transaction) => [
                        'id' => $transaction->id,

                        'transaction_number' =>
                            $transaction->transaction_number,

                        'title' =>
                            $transaction->title,

                        'priority' =>
                            $transaction->priority->value,

                        'creator' =>
                            $transaction->creator?->name,

                        'transaction_type' =>
                            $transaction->transactionType?->name_en,

                        'created_at' =>
                            $transaction->created_at?->toISOString(),
                    ]
                ),

            'department_summaries' =>
                $departmentSummaries,

            'department_activity' =>
                $departmentActivity,
        ]);
    }

    /**
     * Admin dashboard.
     */
    public function admin(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        /*
        |--------------------------------------------------------------------------
        | Basic account statistics
        |--------------------------------------------------------------------------
        */

        $totalEmployees = User::query()
            ->where(
                'role',
                'employee'
            )
            ->count();

        $totalManagers = User::query()
            ->where(
                'role',
                'manager'
            )
            ->count();

        $activeUsers = User::query()
            ->where(
                'status',
                'active'
            )
            ->count();

        $inactiveUsers = User::query()
            ->where(
                'status',
                'inactive'
            )
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Employees
        |--------------------------------------------------------------------------
        */

        $employees = User::query()
            ->where(
                'role',
                'employee'
            )
            ->with('department:id,name')
            ->get([
                'id',
                'name',
                'email',
                'job_title',
                'department_id',
                'status',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Today's attendance
        |--------------------------------------------------------------------------
        */

        $todayAttendances = Attendance::query()
            ->whereIn(
                'user_id',
                $employees->pluck('id')
            )
            ->whereDate(
                'date',
                $today
            )
            ->orderByDesc('id')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        /*
        |--------------------------------------------------------------------------
        | Leave transaction type
        |--------------------------------------------------------------------------
        */

        $leaveTypeId = TransactionType::query()
            ->where(
                'name_en',
                'Leave Request'
            )
            ->value('id');

        /*
        |--------------------------------------------------------------------------
        | Approved leaves covering today
        |--------------------------------------------------------------------------
        */

        $leaveTransactions = collect();

        if ($leaveTypeId) {
            $leaveTransactions = Transaction::query()
                ->where(
                    'transaction_type_id',
                    $leaveTypeId
                )
                ->where(
                    'status',
                    TransactionStatus::Approved
                )
                ->whereDate(
                    'start_date',
                    '<=',
                    $today
                )
                ->whereDate(
                    'end_date',
                    '>=',
                    $today
                )
                ->whereIn(
                    'created_by',
                    $employees->pluck('id')
                )
                ->orderBy('end_date')
                ->get([
                    'id',
                    'created_by',
                    'title',
                    'start_date',
                    'end_date',
                ])
                ->groupBy('created_by');
        }

        /*
        |--------------------------------------------------------------------------
        | Work status statistics
        |--------------------------------------------------------------------------
        */

        $workingEmployees = 0;
        $employeesOnLeave = 0;
        $inactiveEmployees = 0;

        foreach ($employees as $employee) {
            $employeeLeaves =
                $leaveTransactions->get(
                    $employee->id,
                    collect()
                );

            if ($employeeLeaves->isNotEmpty()) {
                $employeesOnLeave++;
                continue;
            }

            if ($employee->status?->value !== 'active') {
                $inactiveEmployees++;
                continue;
            }

            $attendance =
                $todayAttendances->get(
                    $employee->id
                );

            if (
                $attendance &&
                $attendance->check_in_at &&
                ! $attendance->check_out_at
            ) {
                $workingEmployees++;
            } else {
                $inactiveEmployees++;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Employees currently on leave
        |--------------------------------------------------------------------------
        */

        $employeesCurrentlyOnLeave = $employees
            ->filter(
                fn (User $employee) =>
                    $employee->status?->value === 'active' &&
                    $leaveTransactions
                        ->get(
                            $employee->id,
                            collect()
                        )
                        ->isNotEmpty()
            )
            ->map(
                function (User $employee) use (
                    $leaveTransactions
                ) {
                    $leaveTransaction =
                        $leaveTransactions
                            ->get(
                                $employee->id,
                                collect()
                            )
                            ->first();

                    return [
                        'id' => $employee->id,

                        'name' => $employee->name,

                        'email' => $employee->email,

                        'job_title' =>
                            $employee->job_title,

                        'department' =>
                            $employee->department?->name,

                        'status' => 'on_leave',

                        'leave_start' =>
                            $leaveTransaction?->start_date
                                ?->toDateString(),

                        'leave_end' =>
                            $leaveTransaction?->end_date
                                ?->toDateString(),
                    ];
                }
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | System statistics
        |--------------------------------------------------------------------------
        */

        $totalDepartments =
            Department::count();

        $totalTypes =
            TransactionType::count();

        $totalTransactions =
            Transaction::count();

        /*
        |--------------------------------------------------------------------------
        | Transactions by status
        |--------------------------------------------------------------------------
        */

        $byStatus =
            Transaction::query()
                ->selectRaw(
                    'status, COUNT(*) as total'
                )
                ->groupBy('status')
                ->pluck(
                    'total',
                    'status'
                );

        /*
        |--------------------------------------------------------------------------
        | Users by department
        |--------------------------------------------------------------------------
        */

        $usersByDept =
            User::query()
                ->selectRaw(
                    'department_id, COUNT(*) as total'
                )
                ->whereNotNull(
                    'department_id'
                )
                ->groupBy(
                    'department_id'
                )
                ->with(
                    'department:id,name'
                )
                ->get()
                ->map(
                    fn ($row) => [
                        'department' =>
                            $row->department?->name,

                        'total' =>
                            $row->total,
                    ]
                );

        /*
        |--------------------------------------------------------------------------
        | Transactions by department
        |--------------------------------------------------------------------------
        */

        $transactionsByDept =
            Transaction::query()
                ->selectRaw(
                    'source_department_id, COUNT(*) as total'
                )
                ->groupBy(
                    'source_department_id'
                )
                ->with(
                    'sourceDepartment:id,name'
                )
                ->get()
                ->map(
                    fn ($row) => [
                        'department' =>
                            $row->sourceDepartment?->name,

                        'total' =>
                            $row->total,
                    ]
                );

        /*
        |--------------------------------------------------------------------------
        | Latest users
        |--------------------------------------------------------------------------
        */

        $latestUsers =
            User::query()
                ->with(
                    'department:id,name'
                )
                ->orderByDesc(
                    'created_at'
                )
                ->limit(5)
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Latest transactions
        |--------------------------------------------------------------------------
        */

        $latestTransactions =
            Transaction::query()
                ->with([
                    'transactionType',
                    'creator',
                ])
                ->orderByDesc(
                    'created_at'
                )
                ->limit(5)
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return $this->success([
            'total_employees' =>
                $totalEmployees,

            'total_managers' =>
                $totalManagers,

            'active_users' =>
                $activeUsers,

            'inactive_users' =>
                $inactiveUsers,

            'working_employees' =>
                $workingEmployees,

            'employees_on_leave' =>
                $employeesOnLeave,

            'inactive_employees' =>
                $inactiveEmployees,

            'employees_currently_on_leave' =>
                $employeesCurrentlyOnLeave,

            'total_departments' =>
                $totalDepartments,

            'total_transaction_types' =>
                $totalTypes,

            'total_transactions' =>
                $totalTransactions,

            'transactions_by_status' =>
                $byStatus,

            'users_by_department' =>
                $usersByDept,

            'transactions_by_department' =>
                $transactionsByDept,

            'latest_users' =>
                $latestUsers->map(
                    fn ($u) => [
                        'id' => $u->id,

                        'name' => $u->name,

                        'email' => $u->email,

                        'role' =>
                            $u->role->value,

                        'department' =>
                            $u->department?->name,

                        'account_status' =>
                            $u->status->value,

                        'work_status' =>
                            $this->calculateUserWorkStatus(
                                $u,
                                $todayAttendances,
                                $leaveTransactions
                            ),
                    ]
                ),

            'latest_transactions' =>
                $latestTransactions->map(
                    fn ($t) => [
                        'id' => $t->id,

                        'transaction_number' =>
                            $t->transaction_number,

                        'title' =>
                            $t->title,

                        'status' =>
                            $t->status->value,

                        'transaction_type' =>
                            $t->transactionType?->name_en,

                        'creator' =>
                            $t->creator?->name,
                    ]
                ),
        ]);
    }

    /**
     * Calculate work status without triggering extra database queries.
     */
    private function calculateUserWorkStatus(
        User $user,
        $todayAttendances,
        $leaveTransactions
    ): string {
        $employeeLeaves =
            $leaveTransactions->get(
                $user->id,
                collect()
            );

        if ($employeeLeaves->isNotEmpty()) {
            return 'on_leave';
        }

        if (! $user->isActive()) {
            return 'inactive';
        }

        $attendance =
            $todayAttendances->get(
                $user->id
            );

        if (
            $attendance &&
            $attendance->check_in_at &&
            ! $attendance->check_out_at
        ) {
            return 'working';
        }

        /*
         * Latest users may include managers/admins,
         * for whom attendance data was not loaded above.
         *
         * Preserve the previous fallback behavior.
         */
        return 'inactive';
    }
}