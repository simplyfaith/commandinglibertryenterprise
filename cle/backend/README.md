# Commanding Liberty Enterprise — Backend (PHP)

Plain PHP 8.1+ / PDO / MySQL backend. No framework — deliberately, so it can be
dropped onto ordinary shared/cPanel hosting as well as a proper server. Swap in
Laravel/Slim later if the team wants routing sugar; the architecture (RBAC,
audit log, location scoping) carries over unchanged.

## Setup

1. Create a MySQL 8+ database and run `database/schema.sql` against it.
2. Set environment variables (in your web server vhost, `.env` loader, or
   PHP-FPM pool config — never commit secrets to git):
   ```
   APP_ENV=production
   APP_URL=https://api.yourdomain.com
   JWT_SECRET=<generate with: php -r "echo bin2hex(random_bytes(32));">
   JWT_TTL_MINUTES=60
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=commanding_liberty
   DB_USER=cle_app
   DB_PASS=<strong password>
   CORS_ALLOWED_ORIGINS=https://shop.yourdomain.com,https://staff.yourdomain.com,https://admin.yourdomain.com
   ```
3. Create your first Super Admin directly in the database (there is no public
   admin-registration endpoint, by design):
   ```sql
   INSERT INTO users (full_name, email, password_hash, role_id, is_active)
   VALUES ('Super Admin', 'admin@commandingliberty.com',
           -- generate with: php -r "echo password_hash('YourStrongPassword!', PASSWORD_BCRYPT);"
           '$2y$10$...', (SELECT id FROM roles WHERE name='SUPER_ADMIN'), 1);
   ```
4. Point your web server's document root at `backend/` (or `backend/api` behind
   a reverse-proxy path `/api`). Every file under `api/` is a self-contained
   endpoint — no build step required.
5. Create a dedicated MySQL user for the app with **no DELETE/UPDATE grant on
   `audit_logs`** (INSERT + SELECT only) to make the audit trail tamper-proof
   at the database layer, not just the application layer.

## Architecture

- `config/` — environment-driven configuration + PDO connection singleton.
- `includes/Auth.php` — dependency-free HS256 JWT issuing/verification, backed
  by a `sessions` table so logout / forced expiry immediately revokes a token.
- `includes/RBAC.php` — the single source of truth for "who can see/do what,
  where." Every endpoint calls into this rather than re-implementing checks.
- `includes/AuditLogger.php` — append-only writes to `audit_logs`.
- `includes/bootstrap.php` — required by every endpoint: CORS allow-list,
  security headers, rate limiting, and a catch-all exception handler that
  never leaks internals to the client.
- `api/<domain>/*.php` — one file per action (`index.php` = list/create via
  GET/POST, `update.php`, `create.php`, `*-status.php`, `decide.php` for
  approvals). This mirrors a typical REST layout without needing a router.

## Security posture implemented in this scaffold

- Passwords hashed with bcrypt (`password_hash`/`password_verify`).
- Account lockout after 5 failed logins (15 minutes).
- All SQL via PDO prepared statements (`ATTR_EMULATE_PREPARES => false`).
- CORS is an allow-list, never a wildcard/reflected origin.
- Every write endpoint re-validates permissions server-side — the frontend
  hiding a button is never the only protection (see `RBAC::require*`).
- Every sale/order/transfer/adjustment that touches money or stock runs
  inside a `PDO` transaction with `SELECT ... FOR UPDATE` row locks, so two
  concurrent sales can't oversell the same last unit.
- Discounts, price changes, large stock adjustments, large expenses, and
  paid-order/sale cancellations all route through `approval_requests` when
  they exceed the requester's authority — and a requester can never approve
  their own request (`discounts/decide.php` enforces this explicitly).
- Cancellations never delete rows; they flip `status = CANCELLED` and keep
  the original record (`sales.php`/`orders` cancel endpoints).
- `audit_logs` is written to on every sensitive action; pair it with the DB
  grant restriction above for true immutability.
- Payment confirmation (`orders/verify-payment.php`) is a stub that shows
  exactly where server-to-server provider verification must go — an order is
  never marked PAID from a client-supplied flag alone.

## What still needs to be built out (this is a foundation, not the full 60-section spec)

- MFA/2FA enrollment + verification step in `login.php` (schema already has
  `mfa_secret`/`mfa_enabled` columns).
- Excel/CSV import pipeline (`import_batches`/`import_rows` tables exist;
  the upload → map → preview → validate → confirm endpoints are not yet
  written — this is the single largest remaining piece of backend work).
- Stock purchase approval endpoints (table exists: `stock_purchases`).
- Generic `decide.php` handlers for STOCK_ADJUSTMENT / EXPENSE / PRICE_CHANGE
  / ORDER_CANCELLATION / SALE_CANCELLATION approval types — currently only
  `discounts/decide.php` is implemented; the others sit in `approval_requests`
  waiting for a decision endpoint that follows the same pattern.
- Full report/export endpoints (CSV/PDF) beyond the dashboard KPIs.
- Notifications dispatch (table exists; no email/webhook sender yet).
- Rate limiting is file-based (fine for one server); move to Redis for a
  multi-node deployment.
