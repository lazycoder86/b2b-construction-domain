# Multi-tenancy guide

Organizations are the tenants. A user can belong to many organizations, with one role in each
(`owner`, `admin`, `member`, `viewer` — see `app/Enums/OrganizationRole.php`).

## How a request gets its tenant

1. Tenant routes live in `routes/tenant.php` and are mounted at `/o/{organization}` with the
   `auth`, `verified` and `tenant` middleware (`bootstrap/app.php`).
2. `ResolveOrganization` looks up the slug and checks that the user is a member (404 otherwise,
   so slugs aren't leaked) and that the organization is active (403 otherwise). It then stores the
   organization and membership in the scoped `CurrentOrganization` service and sets
   `URL::defaults(['organization' => ...])` so `route('tenant.*')` needs no slug.
3. `ResolveOrganization` runs **before** route-model binding, so tenant models in the same URL
   (`/o/acme/projects/{project}`) resolve inside the tenant scope. A record from another
   organization gives a 404.

`/dashboard` (Fortify's `home`) redirects to the user's current organization, or to
`/organizations/create` if they have none.

## Adding a tenant-owned model

```php
// migration
$table->foreignId('organization_id')->constrained()->cascadeOnDelete();
$table->unique(['organization_id', 'slug']); // uniqueness is per tenant

// model
class Project extends Model
{
    use BelongsToOrganization, HasPublicId;
}
```

`BelongsToOrganization`:

- scopes every query to the current organization;
- **fails closed**: it throws `MissingTenantContextException` if no organization is set;
- fills in `organization_id` on create;
- refuses to move a record to another organization.

`tests/Feature/Tenancy/TenantModelsTest.php` fails if a model has an `organization_id` column but
doesn't use the trait.

Cross-tenant work (platform admin, console commands) must opt in explicitly:

```php
Project::withoutGlobalScope(OrganizationScope::class)->count();

app(CurrentOrganization::class)->runAs($organization, fn () => Project::create([...]));
```

## Queued jobs, mail and notifications

The current organization id is written into every queue payload, and the tenant is restored
while the job runs (`app/Support/Tenancy/QueueTenancy.php`). Jobs need no extra code.

## Authorization

Use Policies (see `OrganizationPolicy`). They check `OrganizationRole::can('permission')`. The
frontend receives `currentOrganization.permissions` **for showing/hiding UI only**. Always
authorize on the server.

### Roles

| Ability                                                         | Owner |               Admin               | Member | Viewer |
| --------------------------------------------------------------- | :---: | :-------------------------------: | :----: | :----: |
| View org, see member list                                       |   ✓   |                 ✓                 |   ✓    |   ✓    |
| Edit org name/slug                                              |   ✓   |                 ✓                 |        |        |
| Invite, change roles, remove members                            |   ✓   | ✓ (not owners, can't grant owner) |        |        |
| Security policies (require MFA), transfer ownership, delete org |   ✓   |                                   |        |        |

Additional rules (in `MembershipPolicy` and the `app/Actions/Members` actions):

- Nobody changes their own role.
- There is always at least one owner. Changes lock the organization row to rule out races.
- Anyone can leave (`DELETE /o/{org}/membership`), except the last owner.
- Ownership transfer requires the owner's password; the old owner becomes an admin.

Members are addressed by the user's `public_id` (`/o/{org}/members/{member:public_id}`), and invitations by
their own `public_id`. Laravel scopes these bindings to the organization's relations, so a
member of another organization gives a 404.

## Invitations

- `POST /o/{org}/invitations` creates an invitation and emails a link (`OrganizationInvitation`,
  queued after commit). Only a SHA-256 hash of the token is stored.
- An invitation expires after `config('tenancy.invitation_expiry_days')` (default 7 days). Resending
  issues a new link, which invalidates the old one. An expired invitation is replaced if the person
  is invited again.
- The link (`/invitations/{token}`) works for guests and sends them back to it after login,
  registration or email verification. The register form is prefilled with the invited address.
  The response sets `Referrer-Policy: no-referrer`.
- Accepting requires a **verified** account with the **same email address** as the invitation.
  Each invitation can be used once.
- Seat limit: `Organization::seatLimit()` is the plan's `seats` limit (see `docs/billing.md`; null =
  unlimited). It counts members plus pending invitations.

## Organization security policy

Owners can enable `require_two_factor`, but only after setting up MFA themselves. The
`tenant.mfa` middleware then sends members without TOTP or a passkey to `/settings/security`.

## Relations on Organization

`Organization::invitations()` removes the tenant scope (the relation already constrains
`organization_id`), so it works outside a tenant context. Do the same for future `hasMany`
relations to tenant models that are needed outside tenant routes.

## Frontend

- Shared props: `currentOrganization` (null outside tenant pages) and `organizations`
  (for the switcher).
- Always pass the slug to tenant route helpers:
  `tenant.dashboard({ organization: org.slug })`. Use `useCurrentOrganization()` in components and
  `organizationSlug(props)` in `Page.layout = (props) => ({ breadcrumbs })` callbacks. Don't
  build tenant URLs at module level: they are evaluated once and would point at the wrong
  organization after switching.

## IDs

Tables keep bigint `id` primary keys internally. Anything shown to the browser or an API uses
the ULID `public_id` (`HasPublicId`) or, for organizations, the slug. `User` hides `id` from
serialization.

## Testing

Tests run against PostgreSQL (`b2b_test`, set in `phpunit.xml`; host and credentials come from
`.env`), so Postgres-specific behaviour is tested too. `tests/TestCase.php` refuses to boot if the
database name doesn't end in `_test`, because `RefreshDatabase` wipes it.

Use `tests/Fixtures/Widget` (call `Widget::createTable()` in `beforeEach`) to test tenancy
behaviour without depending on a real domain model.

## Still to do (later phases)

- Postgres RLS as defence in depth (needs a non-owner DB role and `SET LOCAL` per transaction if
  PgBouncer runs in transaction mode).
- Cache keys, file paths, broadcast channels and search indexes must also be scoped by
  organization once those features are used.
