# ifotoset — Multi-user (Team) Accounts: Analysis & Integration Plan

_Date: 2026-10-26 · Scope: Business plan "Multi-user team accounts" promise_

**Verdict:** Yes, we can build this, and the codebase is in good shape for it. Every studio record is owned through a single `user_id`, so we don't have to restructure the data. We keep the paying **owner** as the tenant and let **members** sign in with their own logins and work inside the owner's studio. The core feature (Phase 1) is about **7–9 developer days**, plus 3–4 days for attribution and notifications (Phase 2). Until it ships, the pricing page should stop promising it as a finished feature (see [Phase 0](#phase-0--stop-the-overpromise-today)).

---

## Contents

1. [What we promise vs. what exists](#1-what-we-promise-vs-what-exists)
2. [How the codebase is built today](#2-how-the-codebase-is-built-today)
3. [Options considered](#3-options-considered)
4. [Recommended design](#4-recommended-design)
5. [Phase 0 — Stop the overpromise (today)](#phase-0--stop-the-overpromise-today)
6. [Phase 1 — Core team accounts](#phase-1--core-team-accounts)
7. [Phase 2 — Attribution, activity and notifications](#phase-2--attribution-activity-and-notifications)
8. [Phase 3 — Later / optional](#phase-3--later--optional)
9. [Edge cases and rules](#9-edge-cases-and-rules)
10. [Test plan](#10-test-plan)
11. [Deployment notes](#11-deployment-notes)
12. [Open decisions](#12-open-decisions)

---

## 1. What we promise vs. what exists

| Where | What it says / holds |
|---|---|
| `resources/views/components/landing/pricing.blade.php:28-29` | Business: _"For agencies, studios, and teams…"_, feature bullet **"Multi-user team accounts"** |
| `database/seeders/PlanSeeder.php` | `team_limit`: Free 0, Basic 0, **Pro 1**, **Business 5** |
| `app/Models/Plan.php` | `team_limit` is fillable and cast, but **nothing reads it** |
| `resources/views/admin/plans.blade.php:133-167` | Shows "Team seats" read-only; admins can't edit it |
| Everything else | No team, member, invite or role-in-account concept anywhere |

So the seat numbers are already in the data model, but no feature uses them. Note that **Pro has `team_limit = 1`**, yet the Pro pricing card says nothing about seats. We need to decide what Pro gets ([Open decisions](#12-open-decisions)).

## 2. How the codebase is built today

These are the facts that shape the design:

- **One user is one tenant.** `galleries`, `clients`, `packages`, `bookings`, `availability_settings`, `availability_exceptions`, `blocked_slots`, `upload_sessions`, `subscriptions`, `payments` all carry `user_id → users.id` with cascade delete.
- **The tenant is also the public identity.** `users.username` is the subdomain and portfolio, `plan_id` is the entitlement, and `storage_used_bytes` / `video_seconds_used` are the quotas. They all sit on the same row.
- **Studio scoping is done by hand with `$request->user()`.** About 55 call sites across 12 controllers in `app/Http/Controllers/Web/Studio/`, plus `UploadService`, `TrashService`, `StudioDashboardQuery`, `StudioClientsQuery`, `AvailabilityService` and a few Blade views (`layouts/app.blade.php:16`, `studio/galleries/show.blade.php`, `studio/bookings/index.blade.php`, `package-fields.blade.php:74`, `over-quota-banner.blade.php`). The pattern is `Gallery::where('user_id', $user->id)`.
- **Policies** (`app/Policies/*`) all check `$user->id === $model->user_id || $user->role === 'admin'`.
- **`users.role`** is a platform role (`photographer` / `admin` / `superadmin`). It is not a role inside an account, and we should keep it that way.
- **Admin impersonation already exists.** A session key `impersonation` is read by `HandleImpersonationState`. It proves that "the logged-in person acts on someone else's studio" fits this app. Team access is a permanent, permissioned version of the same idea, but the member stays logged in as **themselves**.
- **Caches are keyed by account id.** Examples: `StorageStatisticsService::clearCache($userId)`, `availability-version:photographer_id:{id}`, `studio:pending-bookings:{id}`. These keep working unchanged if "account" means the owner.
- **`tests/Feature/StudioIsolationTest.php`** already checks cross-tenant isolation. We extend it for teams.

## 3. Options considered

**A. Owner-as-tenant plus a membership table (recommended).** Add `account_members` (owner user ↔ member user, role). On each studio request we work out the **current account** (the owner `User`). Studio code scopes by that account instead of the logged-in user.
- ✅ No changes to the ownership of existing tables, no data backfill, migrations are purely additive (this matters because `deploy.sh` runs migrations before the code switch).
- ✅ Quotas, plan, subdomain and portfolio stay on the owner, which is how Business is sold: one studio, many hands.
- ⚠️ Every studio call site has to be classified as "account" or "actor". This is mechanical, but it needs care and tests.

**B. Full organisations refactor.** A new `teams` table becomes the tenant. Move `plan_id`, quotas, username and every `user_id` foreign key onto `team_id`.
- ✅ The textbook SaaS model.
- ❌ It touches every table, the billing actions, the public routes, the CDN/storage paths and every test. It needs a big backfill and a non-additive migration. That is weeks of work and risky against our zero-downtime deploy, and it gives no visible benefit over A for a feature capped at 5 seats.

**C. Shared login (tell studios to share a password).** ❌ That doesn't meet the promise, breaks audit and password security, and Google sign-in makes it impossible anyway.

→ **Go with A.** If we ever need several studios per business, A can still grow into B later.

## 4. Recommended design

### 4.1 Concepts

- **Account (studio):** the owner's `users` row. It holds the plan, quotas, subdomain, portfolio, galleries, clients, bookings and billing.
- **Owner:** the user whose id is the account. There is exactly one owner per account, and the owner is never stored as a member row.
- **Member:** another `users` row, with its own email and password or Google login, linked to the account by an `account_members` row.
- **Actor:** whoever is logged in (`$request->user()`). It is used for password, personal profile, `invited_by`, audit and "created by".

A person can be a member of a studio **and** have their own free studio. A studio switcher in the shell lets them move between the two.

### 4.2 Roles and permissions

There are three in-account roles. They are kept as an enum with a fixed permission map, not a configurable ACL:

| Permission | Owner | Manager | Member |
|---|:-:|:-:|:-:|
| Galleries: create, edit, upload, hide, invite clients | ✅ | ✅ | ✅ |
| Galleries: delete, empty trash | ✅ | ✅ | ❌ |
| Bookings: view, create, change status | ✅ | ✅ | ✅ |
| Clients: view, create, edit | ✅ | ✅ | ✅ |
| Clients: delete | ✅ | ✅ | ❌ |
| Packages and availability | ✅ | ✅ | ❌ (view only) |
| Portfolio builder and public profile | ✅ | ✅ | ❌ |
| Analytics | ✅ | ✅ | ❌ |
| Team: view member list | ✅ | ✅ | ❌ |
| Team: invite, remove, change role | ✅ | ❌ | ❌ |
| Billing and plan | ✅ | ❌ | ❌ |
| Data export and account deletion | ✅ | ❌ | ❌ |
| Own password, own name and avatar, own notification preferences | ✅ | ✅ | ✅ |

The map goes in `app/Enums/AccountRole.php` and exposes `can(string $ability): bool`. It is registered as Gates (`Gate::define('studio.billing', …)`) so that Blade can use `@can` to hide menu items and buttons.

### 4.3 Data model (all additive)

```php
// account_members
id
account_id        FK users.id  cascade   // the owner
user_id           FK users.id  cascade   // the member
role              string(20)              // manager | member
invited_by        FK users.id  null on delete
suspended_at      timestamp null          // set when seats are over the plan limit
created_at / updated_at
unique(account_id, user_id)
index(user_id)

// account_invitations
id, uuid (binary, like the other tables)
account_id        FK users.id  cascade
email             string
role              string(20)
token_hash        string(64) unique       // sha256 of the emailed token
invited_by        FK users.id  null on delete
expires_at        timestamp               // 7 days
accepted_at       timestamp null
revoked_at        timestamp null
timestamps
index(account_id, email)
```

Phase 2 adds attribution (all nullable, so there's no backfill):
`galleries.created_by`, `photos.uploaded_by`, `upload_sessions.actor_id`, `bookings.created_by`, `bookings.assigned_to`, `clients.created_by`. Each one is FK `users.id`, null on delete.

### 4.4 Resolving the current account

1. **`App\Support\StudioAccount`** is a request-scoped service. It holds `account(): User`, `actor(): User`, `role(): AccountRole` and `isOwner(): bool`.
2. **Middleware `ResolveStudioAccount`** is added to the `studio.` route group after `verified`:
   - It reads `session('studio_account_id')`. If that is missing, it uses the actor's own id.
   - If the id isn't the actor's own, it checks that there is an `account_members` row with `suspended_at IS NULL`, that the owner `is_active` and is not trashed, and that the owner's plan still allows teams. If any check fails, it falls back to the actor's own account and flashes a notice.
   - It binds the result into the container. It also adds a `Request::macro('account', …)` so controllers can write `$request->account()`, and calls `View::share('studioAccount', …)`.
3. **Member-only users.** Someone who signed up through an invite has no studio of their own: their `username` is null, which is already nullable in the schema. They are always sent to the team account. The "my own studio" entry in the switcher becomes "Create my own studio" (see §4.6).
4. **Switcher:** `POST /studio/account/switch {account}` checks membership and sets the session key. The shell shows the account name with a "Working in **Studio X**" badge when the account isn't the actor's own, styled like `components/admin/impersonation-banner.blade.php`.

### 4.5 Refactor rule for existing code

Go through every `$request->user()` / `auth()->user()` / `auth()->id()` in Studio controllers, studio queries, services and studio Blade views, and sort each one into a bucket:

- **Account** (most of them): data scoping, quotas, plan and feature checks (`hasFeature`, `hasVideoSupport`, `isOverFreeQuota`), storage stats, slugs, cache keys, availability → `$request->account()`.
- **Actor:** `SettingsController::updatePassword`, the personal part of `updateProfile` (name, avatar), `updateNotifications`, `GalleryController` `invited_by` (lines 551/559), the Sanctum avatar endpoints, `logout`, verify-email → keep `$request->user()`.
- **Owner-only:** billing, export and delete account. Check `studio.billing` / `studio.account.manage` first, then use the account.

Policies change to:
```php
public function update(User $user, Gallery $gallery): bool
{
    return app(StudioAccount::class)->owns($gallery->user_id, 'galleries.edit') || $user->role === 'admin';
}
```
`owns()` is true when the record belongs to the current account **and** the actor's role has that permission. Because the policy compares against the *current* account, a member can't reach a record in another account they belong to without switching first. That keeps isolation simple to reason about.

### 4.6 A member who later wants a personal studio

Members never sign up a second time. Their login already exists; it just has no public studio.

1. In the switcher (or on a banner on the member's dashboard), they choose **"Create my own studio"**. This opens a short form that only asks for a username, with the same rules as `RegisterController` / `GoogleAuthController::complete`: unique, not reserved.
2. Saving sets `username` on their existing `users` row. That row is already on the Free plan (invite acceptance assigns it), so they now have their own subdomain, portfolio, galleries and quotas, and they can upgrade it independently.
3. The two studios never mix. Team galleries, clients and bookings stay with the owner, and the member's personal data stays on their own row. The switcher lists both: "Studio X (team)" and "My studio".
4. Leaving the team, or being removed, deletes only the `account_members` row. Their login and personal studio stay.
5. Signup paths must handle the existing login:
   - `/register` with a member's email currently fails with a generic `unique:users` error. Detect a username-less user with that email and show "You already have an account. Log in, then create your studio", with a link to login.
   - Google sign-in already matches an existing user by email (`GoogleAuthController.php:83-84`) and logs them in. No change is needed beyond the switcher entry.
6. If they want their personal studio on a **different** email, that is just a normal new signup. The two logins are unrelated.

## Phase 0 — Stop the overpromise (today)

- [ ] Change the Business bullet to **"Multi-user team accounts (coming soon)"**, as we already do for Pro's custom domain (`pricing.blade.php:29`). Do the same on any feature or FAQ page and in the plan comparison on `/pricing`.
- [ ] Check production for active Business subscribers who might expect seats (`subscriptions` joined to `plans.slug = 'business'`, `ends_at > now()`). If there are any, contact them personally and offer to onboard them first when it ships.
- [ ] Decide Pro's `team_limit` (see [Open decisions](#12-open-decisions)).

## Phase 1 — Core team accounts

_About 7–9 dev days. Ship it behind a `teams` feature code so it can be switched on per plan from the admin matrix._

**1.1 Schema and models (0.5 d)**
- [x] Migrations for `account_members` and `account_invitations`. They are additive and safe under `deploy.sh`.
- [x] Models `AccountMember` and `AccountInvitation` (binary UUID via `HasBinaryUuid`, like the others).
- [x] `User` relations: `teamMembers()` (as owner), `memberships()` (as member), `accounts()` (owners they can access), `seatLimit()` (`plan->team_limit` when the plan has the `teams` feature, otherwise 0), `seatsUsed()` (active members + pending unexpired invites).
- [x] `FeatureSeeder`: add the `teams` feature, linked to `business` (and `pro` if decided). Keep the "only link on first creation" rule.

**1.2 Account resolution (1 d)**
- [x] `StudioAccount` service, `ResolveStudioAccount` middleware, `Request::account()` macro and the view share.
- [x] Switch route and session key. Clear the key on logout.
- [x] Impersonation: when an admin impersonates a member, resolution applies as normal. When they leave impersonation, clear `studio_account_id`.

**1.3 Refactor studio call sites (2–2.5 d)**
- [x] Controllers: Gallery, Upload, Booking, Client, Package, Availability, Portfolio, Analytics, Dashboard, Trash, Settings, Billing, ProcessingStatus.
- [x] Services and queries: `UploadService` (quota and reservations on the account), `TrashService`, `StudioDashboardQuery`, `StudioClientsQuery`, `AvailabilityService`, the `/studio/storage/stats` closure in `routes/web.php:206`.
- [x] Blade: `layouts/app.blade.php:16` (pending-bookings badge cache key → account id), `studio/galleries/show.blade.php`, `galleries/partials/header.blade.php`, `bookings/index.blade.php`, `packages/index.blade.php`, `package-fields.blade.php:74`, `over-quota-banner.blade.php`, `layouts/partials/shell.blade.php`.
- [x] Policies → `StudioAccount::owns()` with permission names.
- [x] Add `@can` guards to the nav and to destructive buttons.

**1.4 Invitations (1.5 d)**
- [x] `Studio\TeamController`: `index`, `invite`, `resend`, `revoke`, `updateRole`, `remove`, plus `leave` for a member.
- [x] Every action except `index` and `leave` is **owner-only** (gate `studio.team.manage`). Managers get a read-only list.
- [x] Before inviting: `seatsUsed() < seatLimit()`. Normalise the email to lowercase. Refuse the owner's own email, existing active members and duplicate pending invites. Throttle with `throttle:10,1`.
- [x] `TeamInvitationMail` (queued, same layout as `GalleryInvitation`). The link goes to `GET /team/invite/{token}`.
- [x] Acceptance flow (outside the `studio.` group, inside `web`):
  - Logged in with the invited email → create the membership, set the session account, redirect to the studio.
  - Logged in with a different email → show "This invite is for x@y. Sign out to accept."
  - An account with that email exists but isn't logged in → go to login with `intended` set back to the invite.
  - No account → a short form (name and password, **no username**), or "Continue with Google" (`GoogleAuthController` must keep the invite token through `intended`). Accepting proves the email, so set `email_verified_at`. New users get the Free plan, as in `RegisterController`.
- [x] Store a hash of the token, expire it after 7 days, and make it single-use.

**1.5 Team settings UI (1 d)**
- [x] A "Team" tab in `studio/settings` (or `studio/team`): a members table (name, email, role, joined, last active), pending invites, a seat meter ("3 of 5 seats used"), and an upgrade prompt when the plan has no seats.
- [x] An account switcher in the shell user menu. It is only shown when `accounts()` has more than one entry.
- [x] Follow `resources/views/DESIGN-SYSTEM.md`.

**1.6 Plan changes and seat enforcement (0.5–1 d)**
- [x] A single `App\Actions\Teams\SyncSeatsWithPlan` that runs after `ApplySubscriptionEntitlement`, `AssignPlan`, `RevokePlan` and `ExpireSubscriptions`. If active members exceed the new limit, it suspends the newest members (`suspended_at = now()`) and emails the owner. On upgrade it un-suspends them, oldest first, up to the limit. Nothing is ever deleted.
- [x] Pending invites over the limit are revoked.
- [x] Make `team_limit` editable on `admin/plans` (remove "read-only" in `plans.blade.php:133-167` and update `AdminPlanController@update` validation).

**1.7 Account lifecycle (0.5 d)**
- [x] Owner deletes their account (`SettingsController::destroyAccount`, `PurgeDeletedUserJob`): memberships and invites cascade. Members' own user rows are left alone.
- [x] A member deletes their own account: their memberships cascade. The `invited_by` / `created_by` fields go null.
- [x] The owner is suspended by an admin (`is_active = false`): members can't open that studio, and resolution falls back.
- [x] Data export (`exportData`) stays owner-only and exports the account's data, plus a team list.

**Implementation notes (2026-10-26).** Phase 1 is built; `tests/Feature/TeamAccountsTest.php` covers it (28 tests). Where it differs from the plan above:
- Team management is its own page, `/studio/team` (nav item "Team"), not a tab in Settings. It has no "last active" column because nothing records activity yet.
- Members can delete single photos inside a gallery, since that only moves them to the trash. Deleting a gallery, purging, and emptying the trash need a manager.
- In a team studio, Settings only lets a member edit their own name, photo and password, and gives them a "Leave team" button. The studio's bio, contact details and specialties stay owner-only there. Managers edit the public profile through the Public profile tab and the portfolio builder. The Notifications tab is hidden for members until Phase 2 routes mail to them.
- The "send to Create my own studio" redirect only applies to people who joined through an accepted invitation. Older accounts that happen to have no username are left alone.
- Still open: in the gallery upload screen, the over-quota and video-upgrade dialogs still point members at Billing, which returns 403 for them.

## Phase 2 — Attribution, activity and notifications

_About 3–4 dev days._

- [ ] Attribution columns (§4.3), filled from `$request->user()` at creation. Show "Uploaded by", "Created by" and "Assigned to" in gallery and booking views.
- [ ] `account_activity_logs` (account_id, actor_id, action, subject_type/id, meta JSON, created_at) for team-relevant events: gallery deleted, client deleted, booking status changed, member invited, removed or role changed, plan changed. Show a simple feed in Team settings. Keep it separate from `admin_audit_logs`, which is platform-staff only.
- [ ] Notification routing. Today every photographer mail goes to the owner. Add a per-member preference (`notification_preferences` already exists on `users`):
  - New booking / deposit paid (`NewBookingPhotographerMail`, `BookingDepositPaidPhotographerMail`): owner, plus members who opt in, plus the `assigned_to` member.
  - Gallery activity digest (`SendGalleryActivityDigest`): owner, plus each member's own galleries if they opt in.
  - Billing, quota and expiry mails (`PlanUpgradedMail`, `StorageQuotaAlertMail`, `OverQuotaNoticeMail`, `Subscription*Mail`, `PaymentFailedMail`): **owner only**.
- [ ] Booking assignment: managers can assign a booking to a member, and members get a "My bookings" filter.

## Phase 3 — Later / optional

- Per-member availability and calendars (second shooters with their own slots). Today availability is one calendar per account (`availability_settings.unique(user_id, day_of_week)`), so this means adding a nullable `member_id` to the scheduling tables plus resolver changes. It's a big change, so only do it if customers ask.
- Restrict a member to the galleries assigned to them (a "contractor" role).
- Transfer studio ownership.
- Extra paid seats beyond the plan (`team_limit` + purchased add-on seats via PawaPay).
- Member sessions overview and "sign out everywhere" for owners.

## 9. Edge cases and rules

- **Quotas are pooled.** All uploads count against the owner's storage and video limits. Reservations in `UploadService` use the account id. The over-quota timeline and banner apply to everyone working in that studio, but only the owner sees the "Upgrade" call to action.
- **Gallery slugs** stay unique per account (`uq_user_slug`), whoever created the gallery.
- **The public site is unchanged.** Subdomain, portfolio, booking page and gallery URLs all belong to the owner. Members never get a public page unless they create their own studio.
- **Platform roles stay separate.** An `admin` cannot be invited as a member. Block it at invite time to avoid confusing `role === 'admin'` checks in policies.
- **Suspended members** can still log in (they may have their own studio). They just can't select that account.
- **Downgrade mid-session:** the middleware re-checks the membership and plan on every request, so access ends right away.
- **Rate limits and throttles** that are keyed by user stay per actor. That's fine.
- **Google Photos tokens and gallery downloads** are client-side features and aren't affected.

## 10. Test plan

New `tests/Feature/TeamAccountsTest.php`, and extend `StudioIsolationTest.php`:

- [ ] Owner invites a member, the member accepts (new user, existing logged-in user, Google), and the member sees the owner's galleries, clients and bookings.
- [ ] A member of Studio A can't see Studio B's data, including when they're a member of both but have A selected (repeat each `StudioIsolationTest` case as "member of A vs owner of B").
- [ ] Role matrix: every ✅/❌ cell in §4.2 has a test. In particular, members can't open billing, delete a gallery, export data or delete the account.
- [ ] A manager can't invite, remove or change roles (403), but can see the team list.
- [ ] A member-only user creates their own studio: the username is set, the switcher shows both studios, and the data stays separate in both directions.
- [ ] `/register` with a member's email shows the "log in and create your studio" message, and Google sign-in logs into the existing member login.
- [ ] Seat limit: an invite past the limit is refused, and pending invites count as seats.
- [ ] Downgrade to Basic suspends members, they lose access straight away, and re-upgrading restores them.
- [ ] A member's upload counts against the owner's quota and is refused when the owner is over quota.
- [ ] Owner deletion cascades memberships, and member deletion keeps the owner's data intact.
- [ ] Invite tokens: expired, revoked, reused and wrong-email cases are all rejected.
- [ ] Impersonating a member and then leaving clears the account session.
- [ ] Run `RouteSmokeTest` and `WebFullStackTest` as members as well as owners.

Run the tests through docker compose as usual (`ifotoset_testing` DB).

## 11. Deployment notes

- Every migration is additive (new tables, nullable columns), so it's safe with `deploy.sh` running migrations before the code switch.
- `FeatureSeeder` runs on each deploy. The new `teams` feature is linked only on first creation, so admins can unlink it afterwards.
- Ship Phase 1 with the `teams` feature linked **only** to `business`. This stays the long-term setup: Pro's `team_limit = 1` is inert without the feature. After a week of real use, remove "(coming soon)" from the pricing copy.
- Locally, don't send real invite emails or touch B2 from docker (the local `b2` disk is the production bucket). Use `Mail::fake()` and `Storage::fake('b2')` in tests.

## 12. Decisions (confirmed 2026-10-26)

1. **Pro seats: none.** Teams are a Business-only selling point. The `teams` feature is linked to Business only, and Pro's `team_limit = 1` stays inert.
2. **The owner does not take a seat.** Business is "5 team members + you".
3. **Only the owner invites, removes and changes roles.** Managers can see the team list but can't change it (§4.2, §1.4).
4. **Members join without a username or public page.** They all serve the same studio, and they can create a personal studio later on the same login (§4.6). This still needs a null-username audit: `isPubliclyIndexable()` already handles empty usernames, but the studio shell and `PublicUrlService` must not assume a username exists.
