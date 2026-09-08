<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderWorkflowStepsRequest;
use App\Http\Requests\Admin\StoreWorkflowStepRequest;
use App\Http\Requests\Admin\UpdateWorkflowStepRequest;
use App\Http\Resources\TransactionTypeWorkflowStepResource;
use App\Http\Responses\ApiResponse;
use App\Models\TransactionType;
use App\Models\TransactionTypeWorkflowStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminWorkflowStepController extends Controller
{
    use ApiResponse;

    /**
     * List the workflow steps for a transaction type.
     */
    public function index(TransactionType $transactionType): JsonResponse
    {
        $steps = $transactionType->workflowSteps()->with('department')->get();

        return $this->success(TransactionTypeWorkflowStepResource::collection($steps));
    }

    /**
     * Create a workflow step.
     */
    public function store(StoreWorkflowStepRequest $request, TransactionType $transactionType): JsonResponse
    {
        $step = $transactionType->workflowSteps()->create($request->validated());

        return $this->success(
            new TransactionTypeWorkflowStepResource($step->load('department')),
            'Workflow step created successfully.',
            201
        );
    }

    /**
     * Update a workflow step.
     */
    public function update(UpdateWorkflowStepRequest $request, TransactionTypeWorkflowStep $workflowStep): JsonResponse
    {
        if ($workflowStep->transactionWorkflowStepSnapshots()->exists()) {
            return $this->error('This workflow step is referenced by active transactions and cannot be edited.', 409);
        }

        $workflowStep->update($request->validated());

        return $this->success(
            new TransactionTypeWorkflowStepResource($workflowStep->load('department')),
            'Workflow step updated successfully.'
        );
    }

    /**
     * Delete a workflow step.
     */
    public function destroy(TransactionTypeWorkflowStep $workflowStep): JsonResponse
    {
        if ($workflowStep->transactionWorkflowStepSnapshots()->exists()) {
            return $this->error('This workflow step is referenced by active transactions and cannot be deleted.', 409);
        }

        $workflowStep->delete();

        return $this->success(null, 'Workflow step deleted successfully.', 204);
    }

    /**
     * Reorder the workflow steps for a transaction type.
     */
    public function reorder(
    ReorderWorkflowStepsRequest $request,
    TransactionType $transactionType
): JsonResponse {
    try {
        DB::transaction(function () use (
            $request,
            $transactionType
        ) {
            $existing = $transactionType
                ->workflowSteps()
                ->pluck('id')
                ->all();

            foreach ($request->steps as $item) {
                if (! in_array(
                    $item['id'],
                    $existing,
                    true
                )) {
                    throw new RuntimeException(
                        'One of the steps does not belong to this transaction type.'
                    );
                }
            }

            /*
             * Use temporary unique step_order values first
             * to avoid UNIQUE constraint conflicts while swapping.
             */
            foreach ($request->steps as $index => $item) {
                TransactionTypeWorkflowStep::whereKey(
                    $item['id']
                )->update([
                    'step_order' => 1000 + $index,
                ]);
            }

            /*
             * Now apply the final order.
             */
            foreach ($request->steps as $item) {
                TransactionTypeWorkflowStep::whereKey(
                    $item['id']
                )->update([
                    'step_order' => $item['step_order'],
                ]);
            }
        });
    } catch (RuntimeException $e) {
        return $this->error(
            $e->getMessage(),
            422
        );
    }

    $steps = $transactionType
        ->workflowSteps()
        ->with('department')
        ->get();

    return $this->success(
        TransactionTypeWorkflowStepResource::collection(
            $steps
        ),
        'Workflow steps reordered successfully.'
    );
}
}
