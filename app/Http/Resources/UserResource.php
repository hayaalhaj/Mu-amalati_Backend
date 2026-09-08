<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' =>
                $this->id,

            'name' =>
                $this->name,

            'email' =>
                $this->email,

            'phone' =>
                $this->phone,

            'job_title' =>
                $this->job_title,

            'role' =>
                $this->role->value,

            'status' =>
                $this->status->value,

            'work_status' =>
                $this->workStatus(),

            'annual_leave_days' =>
                $this->annual_leave_days,

            'used_leave_days' =>
                $this->used_leave_days,

            'department' =>
                $this->whenLoaded(
                    'department',
                    fn () =>
                        $this->department
                            ? [
                                'id' =>
                                    $this->department->id,

                                'name' =>
                                    $this->department->name,
                            ]
                            : null
                ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}

