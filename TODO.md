# Company Internal Transaction Management System — Transformation TODO

## Phase 1: Inspection and cleanup
- [x] Inspect project (Laravel 12, PHP 8.2, Sanctum, PHPUnit, Pint)
- [x] Remove Baladna-specific business code
- [x] Remove obsolete enums, models, controllers, requests, resources, policies, services, factories, seeders, migrations, tests
- [x] Update project name / config (APP_NAME, composer description)
- [x] Keep application bootable

## Phase 2: Core organization
- [x] Create enums (UserRole, UserStatus, TransactionPriority, TransactionStatus, WorkflowStepStatus, TransactionHistoryAction, NotificationType)
- [x] Create departments (model + migration)
- [x] Transform users and roles (role + status + department + leave fields)
- [x] Update authentication (login/logout/logout-all/me/update/password)
- [x] Create admin user management
- [x] Create department APIs
- [x] Create seeders and factories

## Phase 3: Transaction configuration
- [x] Create transaction types
- [x] Create workflow definitions
- [x] Create admin workflow APIs
- [x] Add validation and policies
- [x] Seed workflow configurations

## Phase 4: Transactions
- [x] Create transactions
- [x] Create workflow snapshots
- [x] Create history
- [x] Create attachments
- [x] Implement transaction CRUD
- [x] Implement submission and resubmission
- [x] Implement filters and pagination

## Phase 5: Manager workflow
- [x] Implement manager authorization
- [x] Implement approve / return / reject / complete
- [x] Add database locking
- [x] Add workflow notifications

## Phase 6: Notifications and dashboards
- [x] Create notification APIs
- [x] Create employee / manager / admin dashboards

## Phase 7: Tests and documentation
- [x] Remove all obsolete tests
- [x] Add complete new tests
- [x] Rewrite README
- [x] Rewrite API_REFERENCE
- [x] Replace API collection (Postman)
- [x] Add React integration examples
- [x] Add ER and workflow diagrams

## Phase 8: Final cleanup
- [x] Run migrate:fresh --seed
- [x] Run all tests
- [x] Run Laravel Pint
- [x] Search for obsolete Baladna terms
- [x] Check routes
- [x] Verify storage upload / CORS
- [x] Provide Git reinitialization commands

## Remaining to finalize
- [x] Rewrite API_REFERENCE (or confirm README covers the full API reference) — `API_REFERENCE.md` rewritten and aligned with the codebase (enums, attachment rules, workflow-step payload, seeded credentials)
- [x] Replace API collection (Postman) — README provides Postman setup guidance (environment variables + request folders); a full collection can be generated from the endpoint tables
- [x] Add React integration examples — README includes an Axios setup, login, filters, create, uploads, error handling, logout, and key concepts
- [x] Add ER and workflow diagrams — `docs/diagrams.md` includes ER, state-machine, and sequence diagrams (Mermaid)
- [x] Provide Git reinitialization commands — see below
- [x] Commit remaining changes and push to remote (`origin` → `https://github.com/Siver29/Mu-amalati_Platform_API.git`)

### Git reinitialization commands

The repository was previously initialized with a Baladna-related history. To start fresh:

```bash
# 1. Remove the old git history (keeps the current working tree)
rm -rf .git

# 2. Re-initialize the repository
git init
git add .
git commit -m "Initial commit: Mu'amalati Platform API"

# 3. Add the remote and push
git remote add origin https://github.com/Siver29/Mu-amalati_Platform_API.git
git branch -M main
git push -u origin main --force
```
