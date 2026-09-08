<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * Set up an employee and a draft transaction they own.
     */
    protected function makeDraftTransaction(): array
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $type = TransactionType::factory()->create(['destination_department_id' => $department->id]);

        $transaction = Transaction::factory()->draft()->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        return compact('department', 'employee', 'type', 'transaction');
    }

    public function test_employee_can_upload_a_valid_attachment(): void
    {
        $ctx = $this->makeDraftTransaction();

        $file = UploadedFile::fake()->create('quotation.pdf', 100, 'application/pdf');

        $response = $this->actingAs($ctx['employee'], 'sanctum')
            ->post("/api/v1/transactions/{$ctx['transaction']->id}/attachments", [
                'attachments' => [$file],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('transaction_attachments', 1);
        Storage::disk('public')->assertExists('transactions/quotation.pdf');
    }

    public function test_invalid_file_types_are_rejected(): void
    {
        $ctx = $this->makeDraftTransaction();

        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($ctx['employee'], 'sanctum')
            ->post("/api/v1/transactions/{$ctx['transaction']->id}/attachments", [
                'attachments' => [$file],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('attachments.0');
    }

    public function test_files_larger_than_5_mb_are_rejected(): void
    {
        $ctx = $this->makeDraftTransaction();

        $file = UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf');

        $response = $this->actingAs($ctx['employee'], 'sanctum')
            ->post("/api/v1/transactions/{$ctx['transaction']->id}/attachments", [
                'attachments' => [$file],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('attachments.0');
    }

    public function test_more_than_five_attachments_are_rejected(): void
    {
        $ctx = $this->makeDraftTransaction();

        $files = array_map(fn (int $i) => UploadedFile::fake()->create(
            "file{$i}.pdf",
            100,
            'application/pdf'
        ), range(1, 6));

        $response = $this->actingAs($ctx['employee'], 'sanctum')
            ->post("/api/v1/transactions/{$ctx['transaction']->id}/attachments", [
                'attachments' => $files,
            ]);

        $response->assertUnprocessable();
    }

    public function test_employee_cannot_add_attachments_to_a_pending_transaction(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $type = TransactionType::factory()->create(['destination_department_id' => $department->id]);

        $transaction = Transaction::factory()->pending()->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->actingAs($employee, 'sanctum')
            ->post("/api/v1/transactions/{$transaction->id}/attachments", [
                'attachments' => [$file],
            ]);

        $response->assertForbidden();
    }

    public function test_employee_cannot_delete_another_users_attachment(): void
    {
        $ctx = $this->makeDraftTransaction();
        $other = User::factory()->employee($ctx['department'])->create();

        $attachment = TransactionAttachment::factory()->create([
            'transaction_id' => $ctx['transaction']->id,
            'uploaded_by' => $ctx['employee']->id,
        ]);

        $response = $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/v1/transactions/{$ctx['transaction']->id}/attachments/{$attachment->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('transaction_attachments', ['id' => $attachment->id]);
    }
}
