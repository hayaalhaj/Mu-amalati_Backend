# Mu'amalati Platform — API Reference

Base URL: `http://localhost:8000/api`

All endpoints (except `POST /v1/auth/login`) require a `Bearer` token in the `Authorization` header.

```
Authorization: Bearer <token>
```

## Response Envelope

Every response uses a uniform envelope.

```jsonc
// Success (single resource)
{ "success": true, "message": "Success.", "data": { /* ... */ } }

// Success (collection)
{ "success": true, "data": [ /* ... */ ], "meta": { "current_page": 1, "per_page": 15, "total": 42 } }

// Error
{ "success": false, "message": "The given data was invalid.", "errors": { "field": ["message"] } }
```

## HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200  | OK / processed |
| 201  | Created (new resource) |
| 204  | Deleted successfully (no body) |
| 401  | Unauthenticated / invalid credentials |
| 403  | Forbidden (policy denied) |
| 404  | Not found |
| 409  | Conflict (invalid workflow state / duplicate) |
| 422  | Validation failed |

---

## 1. Authentication

### `POST /v1/auth/login`

Authenticate and obtain a token.

```jsonc
// Request
{ "email": "admin@company.test", "password": "password" }

// Response 200
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "user": { "id": 1, "name": "System Admin", "email": "admin@company.test", "role": "admin", "status": "active", "department": null },
    "token": "1|xxxx..."
  }
}
```

### `POST /v1/auth/logout`

Revoke the current token.

### `POST /v1/auth/logout-all`

Revoke all tokens for the authenticated user.

### `GET /v1/auth/me`

Return the authenticated user's profile.

```jsonc
// Response 200
{
  "success": true,
  "data": {
    "id": 1, "name": "System Admin", "email": "admin@company.test", "phone": null,
    "job_title": "Manager", "role": "admin", "status": "active",
    "annual_leave_days": 30, "used_leave_days": 0,
    "department": null, "created_at": "...", "updated_at": "..."
  }
}
```

### `PATCH /v1/auth/me`

Update limited profile fields: `name`, `phone`, `job_title`. Role, email and status are **not** modifiable here.

### `PATCH /v1/auth/password`

Change the current password.

```jsonc
{
  "current_password": "old-password",
  "password": "new-password",
  "password_confirmation": "new-password"
}
```

---

## 2. Reference Data (authenticated)

### `GET /v1/departments` · `GET /v1/departments/{department}`
List / show departments.

### `GET /v1/transaction-types` · `GET /v1/transaction-types/{transactionType}`
List / show transaction types (with their configured workflow steps).

---

## 3. Employee Transactions

### `POST /v1/transactions`

Create a draft transaction. `creator`, `source_department` and `status` are taken from the authenticated user — do **not** send them.

```jsonc
{
  "transaction_type_id": 1,
  "title": "Purchase laptops",
  "description": "Laptops for the new team.",
  "priority": "medium"          // low | medium | high
}
```

```jsonc
// Response 201
{ "success": true, "message": "Draft created.", "data": { "id": 10, "transaction_number": "TRX-2026-000001", "title": "...", "status": "draft", /* ... */ } }
```

### `GET /v1/transactions`

List the **authenticated user's** transactions. Supports pagination and filters.

| Query param | Description |
|-------------|-------------|
| `status`    | draft, pending, returned, approved, rejected, completed |
| `priority`  | low, medium, high |
| `search`    | free-text on title / number |
| `page`      | page number |

### `GET /v1/transactions/{transaction}` · `PATCH /v1/transactions/{transaction}` · `DELETE /v1/transactions/{transaction}`
View / update / delete a draft or returned transaction owned by the user. Submitted transactions cannot be edited or deleted.

### `POST /v1/transactions/{transaction}/submit`
Submit a draft/re-returnable transaction. Validates workflow configuration and any required attachments.

### `POST /v1/transactions/{transaction}/resubmit`
Re-submit a returned transaction; resumes from the step where it was returned.

### `GET /v1/transactions/{transaction}/history`
Full audit trail of the transaction.

### `GET /v1/transactions/{transaction}/workflow`
Snapshot of the transaction's workflow steps.

### `POST /v1/transactions/{transaction}/attachments`
Upload attachments (multipart/form-data). Field name: `attachments[]`.

| Constraint | Value |
|------------|-------|
| Max files  | 5 per transaction |
| Max size   | 5 MB per file |
| Allowed    | pdf, jpg, jpeg, png, doc, docx |

### `DELETE /v1/transactions/{transaction}/attachments/{attachment}`
Delete an attachment (owner only).

---

## 4. Manager Workflow (prefix `/v1/manager`)

### `GET /v1/manager/pending-transactions`
List pending transactions for departments the manager supervises.

### `GET /v1/manager/transactions/{transaction}`
View a transaction the manager is authorized to review.

### `POST /v1/manager/transactions/{transaction}/approve`

```jsonc
{ "comment": "Approved." }   // optional
```

Approves the current workflow step and activates the next (or marks approved if final).

### `POST /v1/manager/transactions/{transaction}/return`

```jsonc
{ "comment": "Please attach the quotation." }   // required
```

Returns the transaction to the creator (status → `returned`).

### `POST /v1/manager/transactions/{transaction}/reject`

```jsonc
{ "comment": "Exceeds budget." }   // required
```

Rejects the transaction (status → `rejected`), ending the workflow.

---

## 5. Notifications

### `GET /v1/notifications`
List the authenticated user's notifications.

### `GET /v1/notifications/unread-count`
Return the unread count.

### `PATCH /v1/notifications/{notification}/read`
Mark a notification as read (owner only).

### `POST /v1/notifications/read-all`
Mark all of the user's notifications as read.

### `DELETE /v1/notifications/{notification}`
Delete a notification (owner only).

---

## 6. Dashboards

### `GET /v1/dashboard/employee`
Counts by status, recent transactions, leave balance, unread count.

### `GET /v1/dashboard/manager`
Pending approvals for supervised departments, today's approved/returned/rejected counts, top pending transactions.

### `GET /v1/dashboard/admin`
Global counts, transactions by status, users/transactions by department, latest users & transactions.

---

## 7. Admin (prefix `/v1/admin`, admin role required)

### Users
| Method | Endpoint | Notes |
|--------|----------|-------|
| GET    | `/v1/admin/users` | list (filters) |
| POST   | `/v1/admin/users` | create |
| GET    | `/v1/admin/users/{user}` | show |
| PATCH  | `/v1/admin/users/{user}` | update |
| DELETE | `/v1/admin/users/{user}` | delete |
| POST   | `/v1/admin/users/{user}/activate` | activate |
| POST   | `/v1/admin/users/{user}/deactivate` | deactivate |

Create/update user payload:

```jsonc
{
  "name": "Sarah", "email": "sarah@company.com", "password": "secret",
  "role": "employee",                       // employee | manager | admin
  "department_id": 1,                       // optional
  "job_title": "Analyst", "phone": null,
  "annual_leave_days": 30, "used_leave_days": 0
}
```

### Departments
| Method | Endpoint |
|--------|----------|
| GET/POST | `/v1/admin/departments` |
| GET/PATCH/DELETE | `/v1/admin/departments/{department}` |
| POST | `/v1/admin/departments/{department}/activate` |
| POST | `/v1/admin/departments/{department}/deactivate` |

### Transaction Types
| Method | Endpoint |
|--------|----------|
| GET/POST | `/v1/admin/transaction-types` |
| GET/PATCH/DELETE | `/v1/admin/transaction-types/{transactionType}` |
| POST | `/v1/admin/transaction-types/{transactionType}/activate` |
| POST | `/v1/admin/transaction-types/{transactionType}/deactivate` |

### Workflow Steps
| Method | Endpoint |
|--------|----------|
| GET/POST | `/v1/admin/transaction-types/{transactionType}/workflow-steps` |
| PUT | `/v1/admin/transaction-types/{transactionType}/workflow-steps/reorder` |
| PATCH | `/v1/admin/workflow-steps/{workflowStep}` |
| DELETE | `/v1/admin/workflow-steps/{workflowStep}` |

Workflow step payload:

```jsonc
{
  "name": "Department Manager Approval",
  "department_id": 1,          // required
  "step_order": 1,             // required (unique per transaction type)
  "is_final": false            // optional; marks the final approval step
}
```

### Transactions
| Method | Endpoint |
|--------|----------|
| GET | `/v1/admin/transactions` |
| GET | `/v1/admin/transactions/{transaction}` |
| POST | `/v1/admin/transactions/{transaction}/complete` |

---

## Resource Shapes

### Transaction (core fields)
```jsonc
{
  "id": 1, "transaction_number": "TRX-2026-000001", "title": "...", "description": "...",
  "priority": "medium", "status": "pending",
  "creator": { "id": 2, "name": "...", "job_title": "...", "department": { "id": 1, "name": "IT" } },
  "transaction_type": { "id": 1, "name_en": "...", "name_ar": "..." },
  "source_department": { "id": 1, "name": "IT" },
  "destination_department": { "id": 2, "name": "Finance" },
  "current_department": { "id": 2, "name": "Finance" },
  "current_workflow_step": { "id": 5, "name": "...", "step_order": 1, "status": "pending" },
  "workflow_steps": [ /* TransactionWorkflowStep */ ],
  "attachments": [ /* TransactionAttachment */ ],
  "histories": [ /* TransactionHistory */ ],
  "submitted_at": "...", "approved_at": "...", "rejected_at": "...",
  "returned_at": "...", "completed_at": "...", "created_at": "...", "updated_at": "..."
}
```

### TransactionWorkflowStep
```jsonc
{ "id": 5, "transaction_id": 1, "step_order": 1, "name": "...", "status": "pending",
  "department": { "id": 2, "name": "Finance" }, "reviewer": { "id": 7, "name": "..." },
  "comment": null, "reviewed_at": null }
```

### TransactionHistory
```jsonc
{ "id": 1, "action": "submitted", "old_status": "draft", "new_status": "pending",
  "workflow_step_name": "...", "comment": null, "performer": { "id": 2, "name": "..." },
  "created_at": "..." }
```

### TransactionAttachment
```jsonc
{ "id": 1, "transaction_id": 1, "original_name": "quotation.pdf", "mime_type": "application/pdf",
  "file_size": 2048, "url": "http://localhost:8000/storage/transactions/quotation.pdf",
  "uploader": { "id": 2, "name": "..." }, "created_at": "..." }
```

### Notification
```jsonc
{ "id": 1, "transaction_id": 1, "title": "...", "message": "...", "type": "review_required",
  "is_read": false, "read_at": null, "created_at": "..." }
```

---

## Enums

| Enum | Values |
|------|--------|
| `UserRole` | `employee`, `manager`, `admin` |
| `UserStatus` | `active`, `inactive` |
| `TransactionPriority` | `low`, `medium`, `high` |
| `TransactionStatus` | `draft`, `pending`, `returned`, `approved`, `rejected`, `completed` |
| `WorkflowStepStatus` | `waiting`, `pending`, `approved`, `returned`, `rejected`, `skipped` |
| `TransactionHistoryAction` | `created`, `updated`, `submitted`, `resubmitted`, `approved_step`, `returned`, `rejected`, `fully_approved`, `completed`, `attachment_added`, `attachment_removed` |
| `NotificationType` | `transaction_submitted`, `transaction_approved`, `transaction_returned`, `transaction_rejected`, `transaction_completed`, `review_required` |

---

## Example: Full Transaction Lifecycle

1. **Login** → `POST /v1/auth/login` → keep token.
2. **Create** → `POST /v1/transactions` → status `draft`.
3. **Upload attachments** → `POST /v1/transactions/{id}/attachments`.
4. **Submit** → `POST /v1/transactions/{id}/submit` → status `pending`.
5. **Manager approves** → `POST /v1/manager/transactions/{id}/approve` for each step.
6. **Final approval** → status `approved`.
7. **Admin completes** → `POST /v1/admin/transactions/{id}/complete` → status `completed`.

If a manager **returns** the transaction, it becomes `returned`; the creator edits and calls `POST /v1/transactions/{id}/resubmit` to resume from the returned step.
