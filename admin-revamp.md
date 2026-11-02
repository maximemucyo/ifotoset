# Admin panel revamp: implementation plan

Status: proposal, nothing implemented yet (audit done 2026-11-02).

Goals:

1. Business and policy settings can be changed from the admin panel without a code change or deploy.
2. The admin panel gives an accurate picture of the platform and covers what an admin team needs as paying studios and support volume grow.

---

## 1. What we found

### 1.1 Settings → General doesn't do anything

`Admin\SettingsController::updateGeneral` saves 8 keys to `system_settings`, but **nothing in the app reads any of them**:

| Key | Status |
|---|---|
| `platform_name` | Saved, never read |
| `support_email` | Saved, never read; `support@ifotoset.com` is hardcoded in `studio/billing/receipt.blade.php` (×2), `studio/billing/index.blade.php`, `errors/500.blade.php`, `legal/layout.blade.php` |
| `support_phone` | Saved, never read |
| `default_currency` | Saved, never read; currency comes from `plans.currency` and `services.pawapay.currency` |
| `momo_merchant_code`, `momo_account_name` | Saved, never read; payments go through PawaPay |
| `announcement_banner` | Saved, never shown anywhere |
| `maintenance_notice` | Accepted by the controller but has no field on the page and is never shown |

Only the **SMTP** and **Client exports** sections take effect.

### 1.2 `SystemSetting::getOption` caches defaults

`getOption()` wraps each key in `Cache::remember(..., 1 day)`, and the closure returns `$default` when the row is missing. That default is then cached. Two places that read the same key with different defaults get whichever was cached first, for up to a day. It also costs one cache lookup per key per request.

### 1.3 Settings that need a code or env change today

| Area | Value | Current location |
|---|---|---|
| Plans | Video allowance, max video file size, max video length | `PlanSeeder` only; `admin/plans.blade.php` says "can't be edited from this page" |
| Plans | Which features each plan includes | `FeatureSeeder` (plan↔feature pivot) |
| Plans | Hide/retire a plan, display order | Doesn't exist |
| Free over-quota | Restrict / archive / delete after 30 / 60 / 90 days | `config/billing.php` ← `.env`, read in `App\Enums\OverQuotaStage` |
| Retention | Trash retention (7 days) | `config/filesystems.php` ← `TRASH_RETENTION_DAYS`; read in `PurgeExpiredTrash` and `PhotoResource` |
| Retention | Deleted-account purge grace (30 days) | `users:purge-deleted --days=30` option default |
| Retention | ZIP download link lifetime (24 h) | Hardcoded in `GalleryDownload` (`addHours(24)`) |
| Timing | Team invitation expiry (7 days) | `AccountInvitation::EXPIRES_AFTER_DAYS` |
| Timing | Booking deposit hold (15 min) | `Booking::DEPOSIT_HOLD_MINUTES` |
| Timing | Plan expiry reminders (7 d and 1 d before) | Hardcoded in `SendSubscriptionExpiryReminders` (tied to the `expiry_reminder_7d_sent_at` / `_1d_sent_at` columns) |
| Sign-up | Disposable-email blocking | `resources/data/disposable-email-domains.txt`, `App\Rules\NotDisposableEmail` |
| Sign-up | Reserved usernames | `config/reserved_usernames.php` |
| Directory | Cities and aliases, specialties, max specialties | `config/directory.php`, read in `App\Support\Directory` |
| Ops | Admin alert recipients | `ADMIN_ALERT_EMAILS` → `config('mail.admin_alerts')`, read in `App\Support\AdminAlerts` |
| Ops | Edge cache on/off and TTL | `config/edge_cache.php` ← `.env` |

### 1.4 What stays in code or env

Not candidates for the admin panel:

- **Secrets and credentials:** PawaPay, Google OAuth, B2, Resend, Cloudflare, `APP_KEY`. These shouldn't be editable through a web form.
- **Media pipeline:** `config/images.php` variants and quality, `ProcessVideoJob::HLS_LADDER`, `MediaTypes`. Changing these leaves existing media inconsistent.
- **Infrastructure:** queue names, `media.ffmpeg_threads`, scheduler timing in `routes/console.php`, timezone, root domain.
- **Security constants:** `LoginController::MAX_ATTEMPTS` and similar.

(SMTP is already in the admin panel and stays there.)

### 1.5 Admin panel gaps

What works well: user search and filters, the user detail drawer (usage, notes, per-user activity, IP reveal), impersonation, gallery takedown and restore, moderation queue, job and export monitor, plan pricing, SMTP and exports settings.

Problems:

1. **No over-quota visibility.** `storage:enforce-free-quota` restricts, archives and finally **deletes** Free galleries, but no screen shows who is at which stage or who will lose galleries soon.
2. **Wrong numbers.**
   - `AdminAnalyticsQuery` "Top studios" uses `User::withCount('galleries')->whereHas('galleries')->take(6)` with no ordering, so it shows any 6 studios. It also runs extra queries over `activity_logs` and `payments` for each studio, which will get slow as data grows.
   - `Admin\PlanController::index` estimates MRR as `monthly_price × users_count`. That ignores annual billing, comped or admin-assigned plans, and expired subscriptions.
   - "Active studios" means "has ever created a gallery", not recent activity.
3. **Missing views:**
   - Subscriptions: active, expiring soon, expired, event history.
   - Bookings and deposits: no admin view at all.
   - Global audit log: `AdminAuditLog` shows only inside each user's drawer.
   - Reviews and client invitations: no admin view.
4. **Payments page is too limited.** You can filter only by status. There's no search by phone, reference or studio, no date range, no totals per period, and no CSV export for reconciliation.
5. **One role does everything.** `admin` and `superadmin` can both reach every screen. A support hire would get full access to settings, payments and plan pricing.
6. **Business metrics missing:** Free→paid conversion, churn, renewals, storage used vs revenue, and studios active in the last 30 days.

---

## 2. Settings design

### 2.1 A single list of all settings

New class `App\Settings\SettingsRegistry` (or `config/settings.php` returning definitions). Each entry:

```php
'over_quota.delete_after_days' => [
    'group'       => 'storage',
    'type'        => 'int',            // bool | int | string | email | text | enum | list
    'label'       => 'Delete oldest galleries after',
    'suffix'      => 'days',
    'help'        => 'Days over the Free limit before the oldest galleries are permanently deleted.',
    'default'     => fn () => config('billing.over_quota.delete_after_days', 90),
    'rules'       => ['required', 'integer', 'min:30', 'max:365'],
    'destructive' => true,             // asks for confirmation when lowered
],
```

- The `default` is the current `config()` value, so `.env` still works as a fallback and **nothing changes on deploy** until an admin saves a value.
- `list` type is stored as JSON (or newline-separated) and parsed by the accessor.
- Cross-field rules are declared per group, for example `restrict < archive < delete`.

### 2.2 One way to read settings

New `App\Settings\Settings` (facade or static helper):

```php
Settings::get('support.email');
Settings::bool('signup.block_disposable_emails');
Settings::int('over_quota.delete_after_days');
Settings::list('signup.extra_blocked_domains');
```

- Loads **all** `system_settings` rows into one cache key (`system_settings.all`, cached until the next save), then applies registry defaults in memory. This fixes the cached-defaults problem in §1.2 and makes every read after the first free.
- Throws on keys missing from the registry, so a typo fails loudly in tests.
- Keep `SystemSetting::getOption/setOption` as thin wrappers until existing callers (`ClientExports`, `SmtpSettingsService`) are switched over, then remove them.

### 2.3 Settings page

Rebuild `admin/settings.blade.php` from the registry:

- Tabs per group: **General & contact**, **Sign-up**, **Storage & retention**, **Timing**, **Notifications**, **Client exports**, **Performance**, **Mail (SMTP)**, **My account**.
- Each field shows its current value and a **"Changed from default"** badge with a **Reset to default** button (which deletes the row).
- Destructive settings show a confirmation dialog that explains the effect, e.g. "Galleries over quota for 45+ days will be deleted on tomorrow's run".
- One endpoint, `PUT /admin/settings/{group}`, validates against the registry and writes only changed keys. Each save writes **one** `AdminAuditLog` entry (`settings.updated`) with a `{key: [old, new]}` diff.
- SMTP and My account keep their dedicated controllers (password encryption, OTP flow) and render as tabs.

### 2.4 Settings to add (by group)

**General & contact**
- `support.email`, `support.phone`. Replace the hardcoded addresses in the 5 views listed in §1.1. Share through a view composer or `View::share` so mail templates get them too.
- `site.announcement_banner` (text, optional level: info/warning). Show a dismissible banner in the studio shell, and optionally on marketing pages. Dismissal stored per banner text in `localStorage`.
- `site.maintenance_notice` (text). Show a non-blocking notice at the top of studio pages. This is **not** the same as `artisan down`.
- Delete `platform_name`, `default_currency`, `momo_merchant_code`, `momo_account_name`. If a key later turns out to be needed, it comes back through the registry with a real reader.

**Sign-up**
- `signup.block_disposable_emails` (bool, default on).
- `signup.extra_blocked_domains` (list). Checked on top of the bundled file.
- `signup.allowed_domains` (list). Exceptions for false positives in the bundled list.
- `NotDisposableEmail::isDisposable` checks: allowed → pass; extra blocked or bundled list → fail.
- Reserved usernames: stay in `config/reserved_usernames.php` as the base list, plus admin-added `signup.extra_reserved_usernames` (list), read by `StudioUsername`. Admins can add names but not remove the base list, so system routes stay protected.

**Storage & retention**
- `over_quota.restrict_after_days`, `over_quota.archive_after_days`, `over_quota.delete_after_days` (destructive, ordered, minimum ~14/30/30). `OverQuotaStage` reads these from `Settings`.
- `trash.retention_days` (destructive, minimum 1). Used by `PurgeExpiredTrash` **and** `PhotoResource`, so the "days remaining" shown matches what is actually purged.
- `accounts.purge_after_days` (destructive, minimum 7). The `--days` option still overrides it.
- `downloads.zip_link_hours` (1–168). Used by `GalleryDownload` expiry and `gallery:cleanup-expired-zips`.

**Timing**
- `teams.invitation_expiry_days` replaces `AccountInvitation::EXPIRES_AFTER_DAYS`.
- `bookings.deposit_hold_minutes` replaces `Booking::DEPOSIT_HOLD_MINUTES` (used in `CreateBookingAction`, `PublicBookingController`, `bookings:expire-holds`).
- Plan expiry reminder days: **leave as is for now**. The two `*_sent_at` columns are tied to "7 days" and "1 day". Making them configurable means changing the schema (a `reminders_sent` JSON column). Revisit only if someone asks for it.

**Notifications**
- `alerts.admin_emails` (list of emails). `AdminAlerts` reads it, falling back to `ADMIN_ALERT_EMAILS`.
- Add a "Send test alert" button that sends a test email to that list.

**Performance**
- `edge_cache.enabled` (bool) as an emergency switch.
- `edge_cache.ttl` (seconds, 60–3600).
- Save hook: when either changes, queue the existing main-site purge (`GalleryCachePurgeService::queueMainSite()`).
- The `ApplyEdgeCacheHeaders` middleware reads from the single cached array, so this adds no database query per request.

**Client exports**: already done; move it onto the registry.

### 2.5 Lists get their own tables, not settings

These are lists with relationships, so each gets a table and its own create/edit/delete screen instead of a key-value setting.

**Plans** (extend the existing `/admin/plans`)
- Add editable fields: `video_limit_seconds` (shown in minutes/hours), `max_video_size_bytes` (MB/GB), `max_single_video_duration_seconds` (minutes). Keep `video_limit` in sync while it still exists. Remove the "can't be edited" note.
- Feature checkboxes per plan (custom domain, booking deposits, premium templates, teams, priority support), saved through the existing pivot.
- New columns (additive migration): `is_public` (shown on pricing / available at checkout), `sort_order`. Existing subscribers stay on a hidden plan.
- Fix storage conversion to use `App\Support\Bytes` so it matches how the rest of the app formats sizes.
- Existing hooks already clear the landing page cache and purge the cached pricing pages. Confirm they also fire when features change (pivot changes don't trigger `saved`), and fire them manually if not.
- `PlanSeeder` and `FeatureSeeder` use `firstOrCreate`, so admin edits survive deploys. Keep it that way.

**Directory** (`/admin/directory`)
- Tables `directory_cities` (`slug` unique & immutable, `name`, `country`, `aliases` JSON, `is_active`, `sort_order`) and `directory_specialties` (`slug` immutable, `label`, `is_active`, `sort_order`).
- Seeder fills them from `config/directory.php` with `firstOrCreate` (runs on every deploy). After that the config file is only seed data.
- `App\Support\Directory` reads the tables (cached, cleared on save). `directory:match-cities` already re-matches nightly, so new aliases apply within a day; add a "Re-match now" button that dispatches it.
- Slugs can't be edited after creation because they become page addresses (`/photographers/{slug}`). Deactivate a city instead of deleting it.
- `max_specialties` becomes a registry setting (`directory.max_specialties`).

### 2.6 Guardrails

- Minimum and maximum values on every number. Destructive fields require confirmation and log `destructive: true` in the audit entry.
- Cross-field checks (`restrict < archive < delete`).
- Every change is audit-logged with old and new values.
- Settings changes need the superadmin role (see §3.5).

---

## 3. Admin view improvements

### 3.1 Over-quota screen (`/admin/storage`): highest priority

- Table of Free studios over their limit: usage vs limit, `over_quota_since`, current `OverQuotaStage`, and the date of the next stage. Sorted by soonest deletion.
- Filters: stage, deleting within 7 days.
- Per-studio actions:
  - Open user drawer.
  - Grant grace: push `over_quota_since` forward by N days (audit-logged).
  - Assign plan (existing action).
- A "Largest storage users" tab (all plans) with platform total vs B2 usage.
- A dashboard card: "N studios will lose galleries in the next 7 days".

### 3.2 Fix analytics numbers

- **Top studios:** one aggregate query ordered by the chosen metric (views, revenue, storage), with a selector and time range. No per-studio queries.
- **MRR:** compute from active `subscriptions`. Annual plans count as `annual_price / 12`. Exclude admin-assigned or comped plans, or show them separately.
- **Active studios:** activity (login, upload or gallery view) in the last 30 days.
- New metrics:
  - Free→paid conversion by signup month.
  - Churn: subscriptions that ended and weren't renewed within N days.
  - Renewals.
  - Storage used vs revenue per plan.
- Count `activity_logs` with time-limited queries or a nightly rollup table (`daily_platform_stats`). Full-table counts will get slow.

### 3.3 New screens

- **Subscriptions** (`/admin/subscriptions`): active / expiring in 7 days / expired / cancelled, filtered by plan; per-subscription `SubscriptionEvent` timeline; linked to the user drawer.
- **Bookings** (`/admin/bookings`): all bookings with status, studio, deposit payment and amount, expired holds. Used for client disputes.
- **Audit log** (`/admin/audit-log`): every `AdminAuditLog` entry, filtered by admin, action, target and date. Respects the existing IP reveal flow.
- **Reviews** (optional, later): list and hide abusive reviews.

### 3.4 Payments page

- Search by phone, PawaPay reference / deposit ID, studio name or email.
- Date range filter, purpose filter (subscription / booking deposit), and totals for the filtered range.
- CSV export of the filtered set (streamed).
- Link to the related subscription or booking.

### 3.5 Admin roles

Current state: `users.role` is `admin` or `superadmin`, and the `admin` middleware gives access to everything.

Proposal: keep `users.role` and add `users.admin_permissions` (JSON) or a small `admin_roles` set:

| Role | Access |
|---|---|
| `support` | Users (read, notes, resend verification, password reset email, impersonate), galleries (read, preview), moderation, job monitor (read, retry) |
| `finance` | Support access + payments (sync, export), subscriptions, assign/revoke plans |
| `superadmin` | Everything, including settings, plans, roles, deleting users, restarting workers |

- Laravel Gates (`Gate::define('admin.settings', ...)`) checked in routes with `can:` middleware. Nav items hidden when not allowed.
- Existing `admin` users map to `superadmin` behaviour on rollout, so nobody loses access.

### 3.6 Smaller improvements

- User list: filter by plan, over-quota stage and signup date range; sort by storage used.
- Dashboard: link each "System health" item to the screen where it can be fixed.
- Search uses `LIKE '%x%'`, which is fine up to tens of thousands of rows. Revisit (prefix search or full-text index) only if it gets slow.

---

## 4. Phases

Each phase can ship on its own. Every migration only adds things, because `deploy.sh` runs migrations before switching code. Seeders run on every deploy and must stay `firstOrCreate`.

### Phase 1: Fix General settings (small)
- [ ] Add `support.email`, `support.phone`, `site.announcement_banner`, `site.maintenance_notice` (as plain `SystemSetting` keys, or start the registry here).
- [ ] Replace hardcoded `support@ifotoset.com` in the 5 views; share through a view composer.
- [ ] Show the banner and maintenance notice in `layouts/partials/shell.blade.php`.
- [ ] Remove `platform_name`, `default_currency`, `momo_*` from the form and controller.
- [ ] Tests: saved support email appears on the billing page and the 500 page; banner shows only when set.

### Phase 2: Settings list, reader and cache
- [ ] `SettingsRegistry`, `Settings` accessor, single cache key, cleared on save.
- [ ] Generic `PUT /admin/settings/{group}`, page built from the registry, reset to default, audit diff.
- [ ] Move over: over-quota days, trash retention, account purge grace, ZIP link hours, team invite expiry, deposit hold, admin alert emails, edge cache, disposable-email controls, extra reserved usernames, `directory.max_specialties`.
- [ ] Move `ClientExports` and `SmtpSettingsService` onto `Settings`; remove `getOption`/`setOption`.
- [ ] Tests:
  - Each setting falls back to config when unset.
  - Validation rejects out-of-range values and bad ordering.
  - A saved value changes behaviour (e.g. `OverQuotaStage::since`, `NotDisposableEmail`, trash purge threshold).
  - The audit log records the diff.

### Phase 3: Plans
- [ ] Video fields, feature checkboxes, `is_public`, `sort_order` (additive migration).
- [ ] Hidden plans excluded from pricing page and checkout; existing subscribers unaffected.
- [ ] Cache purge on feature changes.
- [ ] Extend `AdminPlanManagementTest`.

### Phase 4: Over-quota screen and analytics fixes
- [ ] `/admin/storage` with stages, upcoming deletions, grace action, dashboard card.
- [ ] Fix Top studios, MRR, active studios; add conversion and churn; nightly rollup if needed.

### Phase 5: Subscriptions, bookings, audit log, payments
- [ ] Three new screens + payments search, date range, totals, CSV export.

### Phase 6: Directory tables and admin roles
- [ ] `directory_cities` / `directory_specialties` tables, seeder from config, admin screens, re-match button.
- [ ] Admin roles with gates; existing admins mapped to superadmin.

---

## 5. Risks and notes

- **Changing a value doesn't change its unit.** Settings store only the number; the label shows the unit and the code converts. No unit is stored next to the value.
- **Destructive settings apply on the next scheduled run.** For example, lowering `over_quota.delete_after_days` takes effect at the next 10:00 `storage:enforce-free-quota` run. The confirmation dialog must say how many studios or galleries are affected.
- **Local Docker uses the production B2 bucket.** Any new "re-run now" buttons (re-match, purge) must not be tested against real data from local. Use tests with faked storage.
- **The Google Photos export setting** must stay "Test studios only" until Google approves the app. Moving it onto the registry must not reset it.
- **Edge cache:** pages cached before a settings change (support email in the footer, banner) stay stale until the TTL expires. Purge the main site when display settings are saved.
