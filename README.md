# Mu'amalati Platform API

**Mu'amalati** (معاملاتي) is a **company internal transaction management platform**. Employees submit internal requests (leave, purchases, IT support, advance payments, accounts, complaints), and managers review them through a configurable multi-step approval workflow. This is an **educational MVP** built with **Laravel 12** to practice consuming Laravel REST APIs from a separate React frontend.

## Features

- **Authentication** with Laravel Sanctum (login, logout, logout-all, profile, password change)
- **Three roles**: `employee`, `manager`, `admin`
- **Departments** with a designated department manager
- **Transaction types** and **configurable workflow steps** (admin-managed)
- **Transaction lifecycle**: draft → pending → approved / returned / rejected → completed
- **Workflow snapshots** per transaction, **history** log, and **attachments** (max 5, JPG/PNG/PDF/DOC/DOCX, 5 MB)
- **Manager approvals**: approve / return / reject at each step with authorization & database locking
- **Notifications** for workflow events
- **Dashboards** for employee, manager, and admin
- **Admin CRUD** for users, departments, transaction types, and workflow steps
- **Consistent JSON responses**, **API Resources**, and **policies**
- **Feature tests** covering the full workflow
- **Postman collection** for API testing

## Requirements

- PHP 8.2+
- Composer
- MySQL (or SQLite for a quick local start)

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

### Configure MySQL in `.env`

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=muamalati
DB_USERNAME=root
DB_PASSWORD=
```

> **Note:** The local project currently uses SQLite (`DB_CONNECTION=sqlite` with `database/database.sqlite`) so it runs out of the box. Switch to MySQL using the values above for the production-style setup. Automated tests always use an in-memory SQLite database regardless of `.env`.

After configuring MySQL, run:

```bash
php artisan migrate --seed
```

## Test Credentials

The `--seed` command creates these accounts (local development only). All use password `password`.

| Role | Email |
|------|-------|
| Admin | `admin@company.test` |
| Manager (HR) | `hr.manager@company.test` |
| Manager (Finance) | `finance.manager@company.test` |
| Manager (IT) | `it.manager@company.test` |
| Manager (Procurement) | `procurement.manager@company.test` |
| Manager (Operations) | `operations.manager@company.test` |
| Employee | `employee@company.test` |
| Employee (HR) | `hr.employee@company.test` |
| Employee (Finance) | `finance.employee@company.test` |
| Employee (IT) | `it.employee@company.test` |

## Running Tests

```bash
php artisan test
```

## Code Style

```bash
vendor/bin/pint
```

## Authentication

All protected endpoints require a Bearer token:

```
Authorization: Bearer {sanctum-token}
```

Obtain a token from `POST /api/v1/auth/login`. Protected endpoints use the `auth:sanctum` guard. Inactive users are blocked from login and protected endpoints.

---

## API Endpoints

Base URL: `http://localhost:8000/api/v1`

### Authentication

| Method | URI | Auth | Role | Description |
|--------|-----|------|------|-------------|
| POST | `/auth/login` | Public | – | Login, returns user + token |
| POST | `/auth/logout` | `auth:sanctum` | Any | Revoke current token |
| POST | `/auth/logout-all` | `auth:sanctum` | Any | Revoke all tokens |
| GET | `/auth/me` | `auth:sanctum` | Any | Get authenticated user |
| PATCH | `/auth/me` | `auth:sanctum` | Any | Update own profile (limited fields) |
| PATCH | `/auth/password` | `auth:sanctum` | Any | Change own password |

### Reference Data (Authenticated)

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/departments` | List departments |
| GET | `/departments/{department}` | Show a department |
| GET | `/transaction-types` | List transaction types (with workflow) |
| GET | `/transaction-types/{transactionType}` | Show a transaction type |

### Employee Transactions

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/transactions` | List/filter/paginate own transactions |
| POST | `/transactions` | Create a draft transaction |
| GET | `/transactions/{transaction}` | Show own transaction |
| PATCH | `/transactions/{transaction}` | Update own draft/returned transaction |
| DELETE | `/transactions/{transaction}` | Delete own draft transaction |
| POST | `/transactions/{transaction}/submit` | Submit a draft for approval |
| POST | `/transactions/{transaction}/resubmit` | Resubmit a returned transaction |
| GET | `/transactions/{transaction}/history` | History of the transaction |
| GET | `/transactions/{transaction}/workflow` | Workflow steps of the transaction |
| POST | `/transactions/{transaction}/attachments` | Upload attachments (multipart) |
| DELETE | `/transactions/{transaction}/attachments/{attachment}` | Delete an attachment |

### Manager Workflow

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/manager/pending-transactions` | List transactions awaiting the manager |
| GET | `/manager/transactions/{transaction}` | Show a transaction assigned to the manager |
| POST | `/manager/transactions/{transaction}/approve` | Approve the current step |
| POST | `/manager/transactions/{transaction}/return` | Return to the submitter (requires comment) |
| POST | `/manager/transactions/{transaction}/reject` | Reject (ends workflow, requires comment) |

### Notifications

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/notifications` | List own notifications |
| GET | `/notifications/unread-count` | Unread notification count |
| PATCH | `/notifications/{notification}/read` | Mark one notification as read |
| POST | `/notifications/read-all` | Mark all own notifications as read |
| DELETE | `/notifications/{notification}` | Delete own notification |

### Dashboards

| Method | URI | Role | Description |
|--------|-----|------|-------------|
| GET | `/dashboard/employee` | employee | Employee transaction stats & recent items |
| GET | `/dashboard/manager` | manager | Manager pending approvals & daily stats |
| GET | `/dashboard/admin` | admin | Admin-wide statistics |

### Admin

All admin endpoints require the `admin` role.

| Method | URI | Description |
|--------|-----|-------------|
| GET/POST | `/admin/users` | List / create users |
| GET/PATCH/DELETE | `/admin/users/{user}` | Show / update / delete user |
| POST | `/admin/users/{user}/activate` | Activate a user |
| POST | `/admin/users/{user}/deactivate` | Deactivate a user |
| GET/POST | `/admin/departments` | List / create departments |
| GET/PATCH/DELETE | `/admin/departments/{department}` | Show / update / delete department |
| POST | `/admin/departments/{department}/activate` | Activate a department |
| POST | `/admin/departments/{department}/deactivate` | Deactivate a department |
| GET/POST | `/admin/transaction-types` | List / create transaction types |
| GET/PATCH/DELETE | `/admin/transaction-types/{transactionType}` | Show / update / delete type |
| POST | `/admin/transaction-types/{transactionType}/activate` | Activate a type |
| POST | `/admin/transaction-types/{transactionType}/deactivate` | Deactivate a type |
| GET/POST | `/admin/transaction-types/{transactionType}/workflow-steps` | List / create workflow steps |
| PUT | `/admin/transaction-types/{transactionType}/workflow-steps/reorder` | Reorder workflow steps |
| PATCH | `/admin/workflow-steps/{workflowStep}` | Update a workflow step |
| DELETE | `/admin/workflow-steps/{workflowStep}` | Delete a workflow step |
| GET | `/admin/transactions` | List all transactions |
| GET | `/admin/transactions/{transaction}` | Show any transaction |
| POST | `/admin/transactions/{transaction}/complete` | Complete an approved transaction |

---

## Example API Requests

### Login

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "employee@company.test",
  "password": "password"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "user": {
      "id": 7,
      "name": "Ahmad Mohammad",
      "email": "employee@company.test",
      "role": "employee"
    },
    "token": "1|sanctum-token"
  }
}
```

### Create a draft transaction

```http
POST /api/v1/transactions
Authorization: Bearer {token}
Content-Type: application/json

{
  "transaction_type_id": 1,
  "title": "Annual leave for June",
  "description": "Requesting 5 days of annual leave.",
  "priority": "medium"
}
```

The backend automatically sets `status=draft`, assigns the creator from the authenticated user, the source department from the user's department, and the destination department from the transaction type. A `transaction_number` (format `TRX-YYYY-######`) is generated.

### Submit a transaction

```http
POST /api/v1/transactions/{transaction}/submit
Authorization: Bearer {token}
```

Submission creates workflow snapshots from the type's configured steps, sets the first step as active, notifies the first approver, and changes the status to `pending`. Submission is blocked (409) if the type is inactive, has no workflow configured, or requires attachments and none are attached.

### Manager approve

```http
POST /api/v1/manager/transactions/{transaction}/approve
Authorization: Bearer {manager_token}
Content-Type: application/json

{
  "comment": "Approved."
}
```

Authorized managers approve the active step. The last approval changes the status to `approved` (ready to be completed by an admin).

### Manager return / reject

```http
POST /api/v1/manager/transactions/{transaction}/return
POST /api/v1/manager/transactions/{transaction}/reject
```

Both require a `comment`. Returning changes the status to `returned` (the employee can edit and resubmit). Rejecting ends the workflow with status `rejected`.

### Upload attachments (multipart)

```
POST /api/v1/transactions/{transaction}/attachments
Authorization: Bearer {token}
Content-Type: multipart/form-data

attachments[]=@quotation.pdf
```

---

## Response Format

### Success (single resource)

```json
{
  "success": true,
  "message": "Transaction created successfully.",
  "data": {}
}
```

### Success (collection)

```json
{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 50,
    "last_page": 5
  }
}
```

### Validation error (422)

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."]
  }
}
```

### Authentication error (401)

```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

### Authorization error (403)

```json
{
  "success": false,
  "message": "You are not authorized to perform this action."
}
```

### Not found (404)

```json
{
  "success": false,
  "message": "Resource not found."
}
```

### HTTP status codes

| Code | Meaning |
|------|---------|
| 200 | Successful retrieval / update |
| 201 | Created resource |
| 204 | Successful deletion (no body) |
| 401 | Unauthenticated |
| 403 | Unauthorized |
| 404 | Not found |
| 409 | Invalid state conflict |
| 422 | Validation error |

---

## React Integration Guide

Create an Axios instance with the base URL and token interceptor:

```js
import axios from "axios";

const api = axios.create({
  baseURL: "http://localhost:8000/api/v1",
  headers: {
    Accept: "application/json",
  },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

export default api;
```

### 1. Login

```js
const response = await api.post("/auth/login", {
  email,
  password,
});

localStorage.setItem("token", response.data.data.token);
```

### 2. Fetch transactions with filters

```js
const response = await api.get("/transactions", {
  params: {
    page: 1,
    status: "pending",
    transaction_type_id: 1,
  },
});
```

### 3. Create a transaction

```js
const response = await api.post("/transactions", {
  transaction_type_id,
  title,
  description,
  priority: "medium",
});
```

### 4. Upload attachments using FormData

```js
const formData = new FormData();

files.forEach((file) => {
  formData.append("attachments[]", file);
});

const response = await api.post(`/transactions/${id}/attachments`, formData, {
  headers: {
    "Content-Type": "multipart/form-data",
  },
});
```

### 5. Handle validation errors

```js
try {
  await api.post("/transactions", data);
} catch (error) {
  if (error.response?.status === 422) {
    setErrors(error.response.data.errors);
  }
}
```

### 6. Logout

```js
await api.post("/auth/logout");
localStorage.removeItem("token");
```

### Key concepts for React

- **Bearer token auth**: send `Authorization: Bearer {token}` on every request.
- **CORS**: `config/cors.php` already allows `http://localhost:3000` and `http://localhost:5173`.
- **Pagination**: read `meta.current_page`, `meta.last_page`, `meta.total` from collection responses.
- **Validation errors**: `error.response.data.errors` is an object keyed by field name.
- **Attachment URLs**: the `Storage::disk('public')` files are served from `/storage/...` after running `php artisan storage:link`. Use `http://localhost:8000/storage/{file_path}`.
- **Protected React routes**: check the authenticated user's `role` (from `/auth/me`) to guard routes.
- **Role-based UI**: render different views for `employee`, `manager`, and `admin` based on `user.role`.

---

## Project Structure

```
app/
├── Enums/                 # UserRole, UserStatus, TransactionStatus, Priority, WorkflowStepStatus, ...
├── Http/
│   ├── Controllers/Api/V1/  # Auth, reference, transactions, manager, admin, dashboards
│   ├── Middleware/          # EnsureUserIsAdmin, EnsureUserIsActive
│   ├── Requests/            # Form Request validation classes
│   ├── Resources/           # API Resources
│   └── Responses/           # ApiResponse trait
├── Models/                 # Eloquent models + relationships
├── Policies/               # User, Department, TransactionType, Transaction, Attachment, Notification
├── Providers/
└── Services/               # TransactionSubmission/Workflow/Number/Notification services
database/
├── factories/
├── migrations/
└── seeders/
tests/
├── Feature/               # Feature tests
└── Unit/                  # Enum unit tests
```

## Postman Testing

The API can be tested with a Postman collection. Configure an environment with:

- `base_url` = `http://localhost:8000/api/v1`
- `employee_token`
- `manager_token`
- `admin_token`

Use the **Login** request to obtain a token and store it in the matching environment variable, then use `Authorization: Bearer {{role_token}}` on protected requests. Organize requests into folders matching the API sections above: **Authentication**, **Reference Data**, **Transactions**, **Manager Workflow**, **Notifications**, **Dashboards**, and **Admin**.
