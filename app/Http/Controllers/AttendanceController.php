<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Working hours.
     */
    private const WORK_START = '07:00';

    private const WORK_END = '17:00';

    /**
     * Get today's attendance record.
     */
    public function current(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        $attendance = $user
            ->todayAttendance();

        return response()->json([
            'success' => true,
            'data' => $attendance,
        ]);
    }

    /**
     * Check in.
     */
    public function checkIn(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        /*
         * Do not allow inactive accounts.
         */
        if (! $user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive.',
            ], 403);
        }

        /*
         * Do not allow check-in while on leave.
         */
        if ($user->isOnLeave()) {
            return response()->json([
                'success' => false,
                'message' => 'You are currently on approved leave.',
            ], 422);
        }

        /*
         * Check whether the current time is inside
         * the allowed working hours.
         *
         * Example:
         * 08:59 -> not allowed
         * 09:00 -> allowed
         * 12:00 -> allowed
         * 16:59 -> allowed
         * 17:00 -> not allowed
         */
        $now = now();

        $workStart = Carbon::createFromTimeString(
            self::WORK_START,
            $now->getTimezone()
        );

        $workEnd = Carbon::createFromTimeString(
            self::WORK_END,
            $now->getTimezone()
        );

        if (
            $now->lt($workStart) ||
            $now->gte($workEnd)
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Check-in is only allowed between ' .
                    Carbon::parse(self::WORK_START)
                        ->format('g:i A') .
                    ' and ' .
                    Carbon::parse(self::WORK_END)
                        ->format('g:i A') .
                    '.',
            ], 422);
        }

        $attendance = $user
            ->todayAttendance();

        /*
         * Already checked in and still working.
         */
        if (
            $attendance &&
            $attendance->check_in_at &&
            ! $attendance->check_out_at
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You are already checked in.',
                'data' => $attendance,
            ], 422);
        }

        /*
         * Today's attendance is already completed.
         */
        if (
            $attendance &&
            $attendance->check_in_at &&
            $attendance->check_out_at
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'You have already completed attendance for today.',
                'data' => $attendance,
            ], 422);
        }

        /*
         * Create today's attendance record.
         */
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $now->toDateString(),
            'check_in_at' => $now,
            'check_out_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checked in successfully.',
            'data' => $attendance,
        ]);
    }

    /**
     * Check out.
     */
    public function checkOut(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        $attendance = $user
            ->todayAttendance();

        /*
         * No check-in today.
         */
        if (! $attendance) {
            return response()->json([
                'success' => false,
                'message' =>
                    'You have not checked in today.',
            ], 422);
        }

        /*
         * Already checked out.
         */
        if ($attendance->check_out_at) {
            return response()->json([
                'success' => false,
                'message' => 'You are already checked out.',
                'data' => $attendance,
            ], 422);
        }

        /*
         * Store the current checkout time.
         */
        $attendance->update([
            'check_out_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checked out successfully.',
            'data' => $attendance->fresh(),
        ]);
    }
}