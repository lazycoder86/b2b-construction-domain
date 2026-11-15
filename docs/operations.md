# Operations guide: audit log, admin, flags, compliance

## Audit log

Append-only (`App\Models\AuditLog` refuses updates and deletes). Write entries with:

```php
Audit::record('member.role_changed', $organization, $subjectModel, ['from' => 'member', 'to' => 'admin']);
Audit::record('auth.login', organization: false);   // platform-level, no organization
```

Defaults: the organization is the current tenant and the actor is the signed-in user. IP and user
agent are captured. While impersonating, `impersonator_id` is filled in automatically. Actor name
and email are snapshotted, so entries stay readable after a user is deleted. Never put secrets in
`metadata`.

| Prefix                                   | Actions                                                                                                                                                               |
| ---------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `auth.`                                  | `login`, `login_failed` (attempted email only), `logout`, `password_reset`, `password_changed`, `two_factor_enabled/disabled`, `passkey_registered/deleted`           |
| `user.`                                  | `email_changed`, `data_exported`, `deleted`                                                                                                                           |
| `organization.`                          | `created`, `updated` (from/to), `security_updated`, `export_requested/downloaded`, `deletion_scheduled/cancelled`, `deleted`, `purged` (platform-level)               |
| `member.` / `invitation.` / `ownership.` | `member.invited/role_changed/removed/left`, `invitation.resent/cancelled/accepted/declined`, `ownership.transferred`                                                  |
| `billing.`                               | `checkout_started`, `plan_changed`, `portal_opened`; from Stripe webhooks, with no user actor: `subscription_created/updated/cancelled`, `payment_succeeded/failed`   |
| `admin.`                                 | `access_granted/revoked`, `impersonation_started/stopped` (with reason), `organization_suspended/activated`, `trial_extended`, `entitlements_updated`, `flag_changed` |

- **Who can read it:** owners and admins, at `/o/{org}/audit-log`. Platform staff see everything
  at `/admin/audit-log`.
- **Retention:** `AUDIT_RETENTION_DAYS` (365). Old entries are removed by the scheduled `model:prune`.
- **When an organization is purged:** its entries are deleted with it, leaving one platform-level
  `organization.purged` record.

## Internal admin (`/admin`)

- Grant access only from the CLI: `php artisan admin:grant you@example.com` (`--revoke` to remove).
  `is_platform_admin` is never mass-assignable.
- Requirements: MFA (TOTP or a passkey; `ADMIN_REQUIRE_MFA`, only disable locally) and a recent
  password confirmation. Users without access get a 404.
- Organizations: search (name, URL, public id, Stripe id, member email), suspend/reactivate,
  extend the trial, override entitlements, toggle feature flags. Each requires a reason where it
  matters, and all are audited.
- Users: search, view memberships and activity, **impersonate**.

### Impersonation

- A reason is required. It is time-limited (`ADMIN_IMPERSONATION_MINUTES`, 30), and a banner is
  always visible.
- Admins and yourself cannot be impersonated.
- Blocked while impersonating (`HandleImpersonation::BLOCKED_ROUTES`): security settings, password,
  2FA and passkeys, account and organization deletion, billing checkout and portal, ownership
  transfer, data exports, accepting invitations, and `/admin` itself.
- Starting and stopping are audited, and every entry written in between carries the admin as
  `impersonator_id`.

## Feature flags (Pennant)

These are for safe rollouts, not plan features (for those see `docs/billing.md`). Define flags in
`config/features.php`. They are scoped to the current organization:

```php
Feature::active('beta_dashboard');                    // PHP
useFlag('beta_dashboard')                             // React (currentOrganization.flags)
```

Admins turn flags on, off, or back to the default per organization.

## Observability

- **Sentry:** set `SENTRY_LARAVEL_DSN`. Exceptions are reported via `Integration::handles()`, and
  events are tagged with the organization's public id. User data is not sent by default
  (`SENTRY_SEND_DEFAULT_PII=false`).
- **Log context:** `Context::add('organization', …)` adds the organization to every log line and
  to queued jobs dispatched from the request.
- **Health:** `/up` also checks the database (`DiagnosingHealth`). Point the uptime monitor here.

## Security baseline

- `SetSecurityHeaders` sets `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`,
  `Permissions-Policy` and COOP. HSTS is added on HTTPS in production.
- HTTPS URLs are forced in production.
- There is no Content-Security-Policy yet: add one per product, once its assets, CDN and analytics
  are known.

## Data protection (GDPR basics)

| Request               | How                                                                                                                                                                                                                                                                                      |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| User access request   | Profile → "Download my data" (`/settings/profile/export`, JSON)                                                                                                                                                                                                                          |
| Organization export   | Org settings → Export (owners). A queued zip of JSON Lines covering every tenant table (auto-discovered via `TenantModels`), members, audit log and subscriptions. It's emailed as a signed link valid for 24 hours, and archives are deleted after `ORGANIZATION_EXPORT_RETENTION_DAYS` |
| Organization deletion | Owners need their password plus the slug. The subscription is cancelled immediately and members lose access. Owners can restore it for `ORGANIZATION_DELETION_GRACE_DAYS` (30); then `organizations:purge` deletes everything (database cascades, Stripe customer, export files, flags)  |
| Account deletion      | Existing flow. Blocked while the user is the sole owner with other members, or while a solo org still has a subscription                                                                                                                                                                 |

## Scheduler (`routes/console.php`)

Run `php artisan schedule:run` every minute (cron), plus a queue worker.

| When  | Command                                                                                  |
| ----- | ---------------------------------------------------------------------------------------- |
| 03:00 | `organizations:purge`: delete organizations past their grace period, and expired exports |
| 04:00 | `model:prune`: audit log retention                                                       |
| 09:00 | `billing:send-trial-reminders`                                                           |

## Not done yet: Postgres row-level security

Tenant isolation is enforced in the application layer (`BelongsToOrganization`, tests in
`tests/Feature/Tenancy`). RLS as a second layer still needs to be planned on its own:

- a **separate non-owner database role** for the app (the current role owns the tables, so RLS
  would only apply with `FORCE ROW LEVEL SECURITY`);
- setting `app.current_organization_id` per request and job (`SET LOCAL` if PgBouncer runs in
  transaction mode);
- an explicit bypass for the code paths that work across tenants on purpose: invitation links,
  webhooks, admin, the purge job.
