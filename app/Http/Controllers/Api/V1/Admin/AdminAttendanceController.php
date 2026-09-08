<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAttendanceController extends Controller
{
    use ApiResponse;

    /**
     * Monthly attendance report for employees and managers.
     *
     * Working days:
     * Sunday -> Thursday
     *
     * Weekend:
     * Friday + Saturday
     *
     * Working hours:
     * 09:00 -> 17:00
     *
     * Attendance statuses:
     * - present
     * - late
     * - working
     * - missing_checkout
     * - absent
     * - leave
     * - weekend
     * - upcoming
     */
    public function report(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'month' => [
                'nullable',
                'integer',
                'min:1',
                'max:12',
            ],

            'year' => [
                'nullable',
                'integer',
                'min:2020',
                'max:2100',
            ],
        ]);

        $month = (int) (
            $validated['month']
            ?? now()->month
        );

        $year = (int) (
            $validated['year']
            ?? now()->year
        );

        /*
         * Company work schedule.
         */
        $workStartTime = '07:00';
        $workEndTime = '17:00';

        /*
         * Selected month.
         */
        $startOfMonth = Carbon::create(
            $year,
            $month,
            1
        )->startOfDay();

        $endOfMonth = $startOfMonth
            ->copy()
            ->endOfMonth()
            ->endOfDay();

        /*
         * Current date/time.
         */
        $now = now();

        $today = $now
            ->copy()
            ->startOfDay();

        /*
         * Employees + Managers only.
         *
         * Admins are excluded.
         */
        $users = User::query()
            ->with('department')
            ->whereIn(
                'role',
                [
                    'employee',
                    'manager',
                ]
            )
            ->orderBy('name')
            ->get();

        $employees = [];

        foreach ($users as $user) {
            /*
             * ---------------------------------------------------------
             * Attendance records for selected month
             * ---------------------------------------------------------
             */
            $attendanceByDate =
                $user->attendances()
                    ->whereBetween(
                        'date',
                        [
                            $startOfMonth
                                ->toDateString(),

                            $endOfMonth
                                ->toDateString(),
                        ]
                    )
                    ->orderBy('date')
                    ->orderBy('id')
                    ->get()
                    ->groupBy(
                        fn ($attendance) =>
                            Carbon::parse(
                                $attendance->date
                            )->toDateString()
                    );

            /*
             * ---------------------------------------------------------
             * Approved leave transactions
             * ---------------------------------------------------------
             *
             * We use the same Leave Request logic
             * already used by User::isOnLeave().
             */
            $approvedLeaves =
                $user->createdTransactions()
                    ->whereHas(
                        'transactionType',
                        function ($query) {
                            $query->where(
                                'name_en',
                                'Leave Request'
                            );
                        }
                    )
                    ->where(
                        'status',
                        'approved'
                    )
                    ->whereDate(
                        'start_date',
                        '<=',
                        $endOfMonth->toDateString()
                    )
                    ->whereDate(
                        'end_date',
                        '>=',
                        $startOfMonth->toDateString()
                    )
                    ->get();

            /*
             * Turn approved leave ranges into
             * date => leave transaction.
             */
            $leaveByDate = [];

            foreach ($approvedLeaves as $leave) {
                if (
                    ! $leave->start_date ||
                    ! $leave->end_date
                ) {
                    continue;
                }

                $leaveStart =
                    Carbon::parse(
                        $leave->start_date
                    )->startOfDay();

                $leaveEnd =
                    Carbon::parse(
                        $leave->end_date
                    )->startOfDay();

                /*
                 * Limit the leave range to the
                 * selected report month.
                 */
                $rangeStart =
                    $leaveStart->greaterThan(
                        $startOfMonth
                    )
                        ? $leaveStart->copy()
                        : $startOfMonth->copy();

                $rangeEnd =
                    $leaveEnd->lessThan(
                        $endOfMonth
                    )
                        ? $leaveEnd->copy()
                        : $endOfMonth->copy();

                $leaveDay =
                    $rangeStart->copy();

                while (
                    $leaveDay->lte(
                        $rangeEnd
                    )
                ) {
                    $leaveByDate[
                        $leaveDay->toDateString()
                    ] = $leave;

                    $leaveDay->addDay();
                }
            }

            /*
             * ---------------------------------------------------------
             * Build every calendar day
             * ---------------------------------------------------------
             */
            $days = [];

            $currentDay =
                $startOfMonth->copy();

            while (
                $currentDay->lte(
                    $endOfMonth
                )
            ) {
                $date =
                    $currentDay->copy();

                $dateString =
                    $date->toDateString();

                /*
                 * Friday / Saturday.
                 */
                $isWeekend =
                    in_array(
                        $date->dayOfWeek,
                        [
                            Carbon::FRIDAY,
                            Carbon::SATURDAY,
                        ],
                        true
                    );

                /*
                 * Future date.
                 */
                $isFutureDay =
                    $date->greaterThan(
                        $today
                    );

                /*
                 * Attendance.
                 */
                $attendance =
                    $attendanceByDate
                        ->get(
                            $dateString
                        )
                        ?->last();

                $checkInAt =
                    $attendance?->check_in_at;

                $checkOutAt =
                    $attendance?->check_out_at;

                $workedMinutes = null;

                $lateMinutes = 0;

                /*
                 * Approved leave for this date.
                 */
                $leave =
                    $leaveByDate[
                        $dateString
                    ] ?? null;

                /*
                 * -----------------------------------------------------
                 * STATUS
                 * -----------------------------------------------------
                 */

                /*
                 * Weekend has priority.
                 */
                if ($isWeekend) {
                    $status =
                        'weekend';
                }

                /*
                 * Approved leave.
                 *
                 * We check leave before upcoming,
                 * because an approved future leave
                 * should still be shown as Leave.
                 */
                elseif ($leave) {
                    $status =
                        'leave';
                }

                /*
                 * Future working day.
                 */
                elseif ($isFutureDay) {
                    $status =
                        'upcoming';
                }

                /*
                 * No check-in.
                 */
                elseif (! $checkInAt) {
                    $status =
                        'absent';
                }

                /*
                 * There is a check-in.
                 */
                else {
                    $checkIn =
                        Carbon::parse(
                            $checkInAt
                        );

                    /*
                     * Scheduled start.
                     */
                    $scheduledStart =
                        $date
                            ->copy()
                            ->setTimeFromTimeString(
                                $workStartTime
                            );

                    /*
                     * Late minutes.
                     */
                    if (
                        $checkIn->greaterThan(
                            $scheduledStart
                        )
                    ) {
                        $lateMinutes =
                            $scheduledStart
                                ->diffInMinutes(
                                    $checkIn
                                );
                    }

                    /*
                     * There is a check-out.
                     */
                    if ($checkOutAt) {
                        $checkOut =
                            Carbon::parse(
                                $checkOutAt
                            );

                        $workedMinutes =
                            max(
                                0,
                                $checkIn
                                    ->diffInMinutes(
                                        $checkOut
                                    )
                            );

                        $status =
                            $lateMinutes > 0
                                ? 'late'
                                : 'present';
                    }

                    /*
                     * Check-in but no check-out.
                     */
                    else {
                        $scheduledEnd =
                            $date
                                ->copy()
                                ->setTimeFromTimeString(
                                    $workEndTime
                                );

                        /*
                         * Today and still inside work hours.
                         */
                        if (
                            $date->isSameDay(
                                $today
                            ) &&
                            $now->lt(
                                $scheduledEnd
                            )
                        ) {
                            $status =
                                $lateMinutes > 0
                                    ? 'late'
                                    : 'working';
                        }

                        /*
                         * Past day or after work hours.
                         */
                        else {
                            $status =
                                'missing_checkout';
                        }
                    }
                }

                $days[] = [
                    'date' =>
                        $dateString,

                    'check_in_at' =>
                        $checkInAt,

                    'check_out_at' =>
                        $checkOutAt,

                    'worked_minutes' =>
                        $workedMinutes,

                    'late_minutes' =>
                        $lateMinutes,

                    'status' =>
                        $status,

                    /*
                     * Extra information for Leave.
                     */
                    'leave_transaction_id' =>
                        $leave?->id,

                    'leave_title' =>
                        $leave?->title,
                ];

                $currentDay->addDay();
            }

            /*
             * ---------------------------------------------------------
             * Monthly summary
             * ---------------------------------------------------------
             */
            $dayCollection =
                collect($days);

            $presentDays =
                $dayCollection
                    ->whereIn(
                        'status',
                        [
                            'present',
                            'late',
                            'working',
                            'missing_checkout',
                        ]
                    )
                    ->count();

            $completedDays =
                $dayCollection
                    ->whereIn(
                        'status',
                        [
                            'present',
                            'late',
                        ]
                    )
                    ->count();

            $lateDays =
                $dayCollection
                    ->where(
                        'status',
                        'late'
                    )
                    ->count();

            $workingDays =
                $dayCollection
                    ->where(
                        'status',
                        'working'
                    )
                    ->count();

            $missingCheckoutDays =
                $dayCollection
                    ->where(
                        'status',
                        'missing_checkout'
                    )
                    ->count();

            $absentDays =
                $dayCollection
                    ->where(
                        'status',
                        'absent'
                    )
                    ->count();

            $leaveDays =
                $dayCollection
                    ->where(
                        'status',
                        'leave'
                    )
                    ->count();

            $weekendDays =
                $dayCollection
                    ->where(
                        'status',
                        'weekend'
                    )
                    ->count();

            $upcomingDays =
                $dayCollection
                    ->where(
                        'status',
                        'upcoming'
                    )
                    ->count();

            $totalWorkedMinutes =
                $dayCollection
                    ->sum(
                        fn ($day) =>
                            $day['worked_minutes']
                            ?? 0
                    );

            $totalLateMinutes =
                $dayCollection
                    ->sum(
                        fn ($day) =>
                            $day['late_minutes']
                            ?? 0
                    );

            /*
             * Final employee result.
             */
            $employees[] = [
                'id' =>
                    $user->id,

                'name' =>
                    $user->name,

                'email' =>
                    $user->email,

                'job_title' =>
                    $user->job_title,

                'role' =>
                    $user->role->value
                        ?? (string) $user->role,

                'department' =>
                    $user->department?->name,

                'summary' => [
                    'total_days' =>
                        count($days),

                    'present_days' =>
                        $presentDays,

                    'completed_days' =>
                        $completedDays,

                    'late_days' =>
                        $lateDays,

                    'working_days' =>
                        $workingDays,

                    'missing_checkout_days' =>
                        $missingCheckoutDays,

                    'absent_days' =>
                        $absentDays,

                    'leave_days' =>
                        $leaveDays,

                    'weekend_days' =>
                        $weekendDays,

                    'upcoming_days' =>
                        $upcomingDays,

                    'total_worked_minutes' =>
                        $totalWorkedMinutes,

                    'total_late_minutes' =>
                        $totalLateMinutes,
                ],

                'days' =>
                    $days,
            ];
        }

        return $this->success([
            'month' =>
                $month,

            'year' =>
                $year,

            'month_name' =>
                $startOfMonth->format('F'),

            'start_date' =>
                $startOfMonth->toDateString(),

            'end_date' =>
                $endOfMonth->toDateString(),

            'work_schedule' => [
                'start' =>
                    $workStartTime,

                'end' =>
                    $workEndTime,

                'working_days' => [
                    'Sunday',
                    'Monday',
                    'Tuesday',
                    'Wednesday',
                    'Thursday',
                ],

                'weekend_days' => [
                    'Friday',
                    'Saturday',
                ],
            ],

            'users_count' =>
                count($employees),

            'employees' =>
                $employees,
        ]);
    }
}