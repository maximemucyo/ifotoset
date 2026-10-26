# ifotoset — Platform Audit & Implementation Plan

_Status 2026-10-15: phases 1–6 deployed to production (commit `a7fbae0`). See [Deployment log](#deployment-log-2026-10-15) for server changes and what is still open._

_Audit date: 2026-10-14 · Scope: `backend/` (Laravel 11), `cdn_cloudflare_worker.js`, nginx configs, `ecosystem.config.js`, legacy `frontend/`_

**Verdict:** The redesign is wired up correctly, but the platform is not production-safe yet. Every route name, view, component, icon, Alpine handler and fetch endpoint resolves, and all 208 feature tests pass. The problems are underneath: committed storage credentials, a forgeable payment webhook, scheduled jobs that never run, and a few quietly broken user flows.

Severity legend: **P0** = fix today · **P1** = this week · **P2** = next sprint · **P3** = backlog / polish.
Items marked ✅ were re-verified directly in the code during the audit.

---

## Contents

1. [Phase 0 — Emergency security fixes (today)](#phase-0--emergency-security-fixes-today)
2. [Phase 1 — Broken functionality (this week)](#phase-1--broken-functionality-this-week)
3. [Phase 2 — Security hardening](#phase-2--security-hardening)
4. [Phase 3 — Production readiness & operations](#phase-3--production-readiness--operations)
5. [Phase 4 — Notifications & email](#phase-4--notifications--email)
6. [Phase 5 — SEO, performance & accessibility](#phase-5--seo-performance--accessibility)
7. [Phase 6 — Design leftovers & cleanup](#phase-6--design-leftovers--cleanup)
8. [What is already solid](#what-is-already-solid)
9. [Open questions to confirm on the server](#open-questions-to-confirm-on-the-server)
10. [Test plan for the fixes](#test-plan-for-the-fixes)

---

## Phase 0 — Emergency security fixes (today)

### 0.1 Live storage credentials committed to git ✅ — P0
- **Where:** `cdn_cloudflare_worker.js:3-6` (tracked since commit `336ec1f`). Contains the Backblaze B2 `KEY_ID`, `APP_KEY` and `CDN_SECRET`. The CDN secret matches `CLOUDFLARE_CDN_SECRET` in `backend/.env`.
- **Impact:** Anyone with repo access can read, write or delete the whole bucket and forge signed media tokens for protected galleries.
- **Plan:**
  - [ ] (Deferred: private repo, single owner) Create a new B2 application key, scoped to the `ifotoset` bucket only, and revoke the old key.
  - [ ] (Deferred) Generate a new CDN secret. Update it in the Worker and in the production `.env` at the same time.
  - [x] Move all three values to Worker secrets (`wrangler secret put KEY_ID` etc.) and read them from `env`.
  - [x] Replace the constants in the committed file with `env.*` lookups.
  - [ ] Optional: purge the file's history (`git filter-repo`). Rotation is what actually closes the hole.
  - [x] Remove the hard-coded fallbacks in `config/app.php:13` (`APP_KEY`) and `config/services.php:10` (`IFOTOSET_RELAY_SECRET`) so they fail closed.

### 0.2 PawaPay webhook can be forged ✅ — P0
- **Where:** `app/Http/Controllers/Api/V1/Callback/PawaPayCallbackController.php:30-63`, `config/services.php:9`.
- **Problem:**
  - The signature is only checked when `PAWAPAY_WEBHOOK_SECRET` is set, and it defaults to `''`.
  - The relay check only runs when an `X-Relay-Secret` header is present, so an attacker just leaves it off.
  - `FinalizePawaPayPayment` then trusts `status`, `amount` and `currency` from the request body.
  - The deposit ID is returned to the user (`BillingController.php:168`, `PaymentController.php:71`).
- **Impact:** A user starts a Pro checkout, then POSTs `{"depositId": …, "status": "COMPLETED", …}` to `/api/v1/callbacks/pawapay`. They get Pro, or a confirmed booking, without paying.
- **Plan:**
  - [x] Treat the callback only as a notification. Before finalising, re-fetch the deposit status from PawaPay with `PaymentGateway::verifyDepositStatus()`, and use that response's status, amount and currency.
  - [x] Fail closed (opt-in via `PAWAPAY_REQUIRE_CALLBACK_AUTH=true` once the relay is confirmed as the only route in): if neither a valid relay secret nor a valid signature is present, return 401.
  - [ ] (Open: confirm with PawaPay docs; harmless now that the body is never trusted) Check the current HMAC scheme against PawaPay's real callback signing (RFC 9421 signatures with public keys). Today's HMAC check would reject genuine callbacks if it were switched on.
  - [x] Throttle the endpoint (e.g. `throttle:120,1`).
  - [x] Tests: a forged callback with no secret is rejected; a callback whose re-fetched status is not `COMPLETED` does not finalise.

### 0.3 Script injection through photographer profile fields ✅ — P0
- **Where:** `resources/views/layouts/partials/seo.blade.php:40`, which uses `json_encode(..., JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)` inside `<script type="application/ld+json">`. `app/Support/Seo/SeoMetadata.php:128-139` feeds it the raw `bio`, `name` and `location`. `bio` is validated only as `string` (`UpdateProfileRequest.php:46`).
- **Impact:** A bio containing `</script><script>…</script>` runs in the browser of every visitor to that photographer's public page.
- **Plan:**
  - [x] Encode with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES`.
  - [x] Apply the same fix to `studio/galleries/show.blade.php:111` (`$initialPhotosData->toJson()` contains filenames).
  - [x] `grep -rn '{!!' resources/views` and review every unescaped output.
  - [x] Test: a bio containing `</script>` renders escaped.

### 0.4 CDN worker serves protected media without a token — P0
- **Where:** `cdn_cloudflare_worker.js`, `isAllowedRequest`.
- **Problems:**
  - Any URL with `?download`, `?filename`, `/downloads/` or ending `.zip` is allowed before the `/protected-galleries/` signature check.
  - `/galleries/` falls back to a substring Referer check (`r.includes('ifotoset.com')`), which can be spoofed with `evil.com/?ifotoset.com`.
  - `decodeURIComponent(pathname)` allows `%2F..%2F` traversal.
  - Protected-gallery ZIPs are written under `galleries/<uuid>/downloads/` (`app/Jobs/GenerateGalleryZipJob.php:185`), so they are served with no token.
- **Plan:**
  - [x] Verify the token first for every `protected-*` path, whatever the query string.
  - [x] Reject paths that contain `..`, `//` or an encoded slash after decoding.
  - [x] Write ZIPs of protected or PIN galleries under `protected-galleries/<uuid>/downloads/` and serve them with signed URLs.
  - [x] Drop the Referer allowance, or compare the parsed hostname exactly.
  - Ship this together with the key rotation in 0.1, since the Worker is redeployed anyway.

---

## Phase 1 — Broken functionality (this week)

### 1.1 Subscriptions never expire; payments never reconcile ✅ — P1
- **Where:** `routes/console.php:9-10` schedules only `ifotoset:purge-expired-trash` and `gallery:cleanup-expired-zips`. `ecosystem.config.js` has no scheduler process.
- **Not scheduled:** `subscriptions:expire`, `payments:reconcile`, `uploads:reconcile-reservations`, `media:reconcile-statistics`.
- **Plan:**
  - [x] Add to `routes/console.php`:
    ```php
    Schedule::command('subscriptions:expire')->hourly()->withoutOverlapping()->onOneServer();
    Schedule::command('payments:reconcile')->everyTenMinutes()->withoutOverlapping()->onOneServer();
    Schedule::command('uploads:reconcile-reservations')->daily()->withoutOverlapping()->onOneServer();
    Schedule::command('media:reconcile-statistics')->daily()->withoutOverlapping()->onOneServer();
    ```
  - [x] Add `withoutOverlapping()` to the two existing entries.
  - [x] (Server) Root's crontab already runs `schedule:run` every minute; no pm2 scheduler (that would double-run jobs).
  - [x] (Decision) Free accounts over quota: uploads blocked from day 0, new galleries + client downloads paused at day 30, galleries offline at day 60, oldest galleries deleted at day 90 (`storage:enforce-free-quota`, config/billing.php).

### 1.2 Every booking email is sent twice ✅ — P1
- **Where:** Laravel 11 discovers listeners automatically, and `app/Providers/AppServiceProvider.php:49-74` also registers them by hand. `php artisan event:list` shows each listener twice.
- **Impact:**
  - `BookingCreated` sends the photographer and the client 2 emails each.
  - `BookingStatusChanged` sends the client 2 emails.
  - Deposit and plan receipts are only saved from duplicates by the unique `payment_receipts(payment_id, type)` index.
- **Plan:**
  - [x] Delete the manual `Event::listen` calls (keep auto-discovery).
  - [x] Test: assert each listener appears once (`Event::assertListening`, or count `event:list`), and `Mail::assertQueued(..., 1)` for a booking.

### 1.3 Two photographers cannot have the same gallery slug ✅ — P1
- **Where:** Slugs are unique per user (`uq_user_slug`, migration `2026_08_03_000000…:110`), but lookups ignore the user:
  - `app/Queries/GalleryQuery.php:14`
  - `PublicGalleryController.php:36`
  - `routes/web.php:135` (`/g/{slug}`)
  - `Api/V1/GalleryController.php:206, 261, 382…`
- **Impact:** If two photographers both create `wedding`, one gets a 404. Unlock and API calls can hit the other photographer's gallery.
- **Plan:**
  - [x] Add `GalleryQuery::findForUser(string $username, string $slug)` that scopes with `whereHas('user', fn ($q) => $q->where('username', strtolower($username)))`.
  - [x] Use it in `show`, `photos`, `export`, `unlock` and `downloadPhoto`, and in the public API routes. Those routes need the username: add it to the API paths or resolve it from the subdomain.
  - [x] `/g/{slug}`: either remove it or make it `/g/{username}/{slug}`.
  - [x] Test: two users with the same slug each resolve to their own gallery.

### 1.4 Public booking form posts to the wrong host ✅ — P1
- **Where:** `resources/views/public/photographer.blade.php:221` uses `route('public.photographer.book')` (root domain). The page is served on `{username}.ifotoset.com`, and the session cookie is host-only, so the POST fails CSRF (419). The correct route `subdomain.photographer.book` (`web.php:69`) is unused.
- **Plan:**
  - [x] Change the action to `route('subdomain.photographer.book', ['username' => $photographer->username])`.
  - [x] Throttle and spam-protect it (see 2.3).
  - [x] Test: a POST to the subdomain booking route creates a booking and redirects back with a success flash.

### 1.5 Password reset links depend on the dead Next.js frontend ✅ — P1
- **Where:** `app/Providers/AppServiceProvider.php:82-93` builds the URL from `config('app.frontend_url')` and throws if it's missing. The reset email failed on every retry in local `failed_jobs` (`RuntimeException: FRONTEND_URL is not configured`). Tests mask this by setting the config value by hand.
- **Plan:**
  - [x] Build the URL with `route('password.reset', ['token' => $token, 'email' => $user->email])`.
  - [x] Replace the other `config('app.frontend_url', config('app.url'))` uses in Mailables with `route()` or `url()`. The fallback never applies, because the key always exists.
  - [x] Remove `frontend_url` from `config/app.php` once nothing uses it.
  - [x] Update `PasswordResetTest` and `EmailSystemTest` to assert the Laravel route instead of setting `frontend_url`.

### 1.6 Suspended users can still log in ✅ — P1
- **Where:** `app/Http/Controllers/Web/Auth/LoginController.php:32` and the API `LoginController` never check `is_active`. No middleware does either. `ToggleUserStatus` only flips the flag.
- **Plan:**
  - [x] `Auth::attempt([...$credentials, 'is_active' => true], …)`, with a clear "account suspended" message.
  - [x] Add an `EnsureUserIsActive` middleware on the `auth` group that logs out a user suspended mid-session.
  - [x] Hide the public site and galleries of suspended users (scope public queries by `user.is_active`).
  - [x] Tests for all three.

### 1.7 Notification preferences don't match ✅ — P1
- **Where:**
  - The studio form saves `booking_created`, `photo_downloaded` and `photo_favorited`, and overwrites the whole array (`Web/Studio/SettingsController.php:113-119`).
  - The listeners read `new_bookings` and `payment_received` (`SendBookingNotifications.php:34`, `SendBookingDepositReceipts.php`).
- **Impact:** Turning off "New booking requests" does nothing. Saving the form erases `payment_received`. The download and favourite toggles have no sender.
- **Plan:**
  - [x] Create one `NotificationPreference` enum or const list as the single source of keys.
  - [x] Merge on save (`array_merge($existing, $validated)`) instead of overwriting.
  - [x] Either implement the download and favourite notifications (a daily digest is better than one email per event) or remove those toggles.
  - [x] Migrate existing rows: `booking_created` → `new_bookings`.

### 1.8 Unverified users get the full web studio — P1
- **Where:** `routes/web.php:148` uses `['auth']` only. The API uses `verified.api`.
- **Plan:**
  - [x] Add `verified` to the studio group, keeping the verification notice and resend routes reachable.

---

## Phase 2 — Security hardening

### 2.1 Upload type is not validated — P1
- `UploadService.php:38,161-170` keeps the user's extension, and the presigned PutObject (`StorageService.php:26-30`) doesn't sign `ContentType`. So an `.html` file can be uploaded and rendered on `cdn.ifotoset.com`.
  - [x] Allow only image and video extensions and MIME types, server-side, in `requestUpload`.
  - [x] Sign `ContentType` into the presigned URL.
  - [x] Have the Worker add `X-Content-Type-Options: nosniff`, plus `Content-Disposition: attachment` for anything that isn't an image or video.

### 2.2 Login and registration have no brute-force limits — P1
- [x] `throttle` plus a per-email `RateLimiter` (Breeze-style `LoginRequest`) on web `POST /login`, `/register`, `/forgot-password`, `/reset-password`.
- [x] The same on the API `auth/login`, `auth/register` and `forgot-password`.
- [x] Cap `per_page` on API index endpoints (e.g. `min($perPage, 100)`).

### 2.3 Booking and review forms can be used to spam email — P1
- `POST /book`, `/p/{username}/book` and the reviews POST have no throttle, and each booking emails any address entered.
  - [x] `throttle:5,1` per IP, plus a honeypot field or Cloudflare Turnstile.

### 2.4 Gallery PIN brute force and token lifetime — P2
- [x] Minimum PIN length of 6 (`Studio/GalleryController.php:94,339`).
- [x] Throttle per gallery as well as per IP.
- [x] Include the password hash and an expiry in the unlock token (`VerifiesGalleryAccess.php:66`), so a PIN change invalidates old tokens.
- [x] Stop accepting the token in `?token=`, since it ends up in logs.
- [x] Restrict `trustProxies(at: '*')` (`bootstrap/app.php`) to Cloudflare's IP ranges. Otherwise `X-Forwarded-For` can be spoofed and every IP throttle bypassed.

### 2.5 Security headers, session and CORS — P2
- [x] Add HSTS and a CSP (start with `Content-Security-Policy-Report-Only`).
- [x] Remove the deprecated `X-XSS-Protection`.
- [x] nginx: `add_header` inside a `location` drops the server-level headers. Move them to a snippet and `include` it in every location (`nginx.prod.conf`, `ifotoset-laravel.conf`).
- [x] `config/cors.php`: allow `https://` origins only. Consider dropping `cdn.` from origins that can send credentials.
- [x] Add `config/session.php` (or env): `SESSION_SECURE_COOKIE=true`, keep `SESSION_DOMAIN` host-only, `same_site=lax`.

### 2.6 Remove the unused API surface — P2
- About 70 of the 87 `api/v1` routes are only used by the dead `frontend/`: auth, admin, bookings, clients, packages, galleries CRUD, trash, uploads, payments, analytics.
- **Keep:**
  - public gallery download, favorite and download-zip
  - google-photos authorize, callback, status and notify
  - `settings/avatar/*`
  - `verification.verify`
  - `callbacks/pawapay`
- [x] Delete the rest, plus the controllers that become dead: `Actions/CreateClientAction`, `UpdateClientAction`, `CreatePackageAction`, `Api/V1/Admin/AdminController` (which includes an unthrottled SMTP test-mail endpoint), etc.

### 2.7 Mass assignment hygiene — P3
- `User::$fillable` includes `role`, `plan_id` and `is_active`. Every write path uses `validated()` today, but move these out of `$fillable` and set them explicitly in the admin actions.

---

## Phase 3 — Production readiness & operations

### 3.1 Remove the legacy Next.js frontend — P1
- [x] Remove the `ifotoset` app (Next.js on :3004) from `ecosystem.config.js`. Stop and delete it in pm2 on the server. (Deleted from pm2 2026-10-15.)
- [x] Delete `backend/ifotoset-nextjs-fallback.conf` and the commented Next.js upstream in `nginx.prod.conf:6-7,93`.
- [ ] Delete `frontend/` (it stays in git history), or move it to an archive branch. (Not done: needs your OK, it also removes untracked files.)
- [x] Update the README directory map.

### 3.2 Deploy pipeline — P1
- **Problem:** The webhook deploy script isn't in the repo. Commit it, or at least document it.
- **Target sequence:**
  ```
  git pull
  composer install --no-dev --optimize-autoloader
  npm ci && npm run build          # if public/build stops being committed
  php artisan down --render="errors::503" --retry=15   # optional for risky migrations
  php artisan migrate --force
  php artisan db:seed --class=PlanSeeder --force       # only idempotent seeders
  php artisan optimize              # config, route, view, event cache
  php artisan queue:restart         # workers otherwise keep running old code
  php artisan up
  ```
- [x] Seeder safety: `DatabaseSeeder.php:25-38` **truncates users, galleries and more** whenever `APP_ENV !== 'production'` (e.g. "prod" or "staging"). Guard it with `app()->isLocal() || app()->runningUnitTests()`, or remove the truncation.
- [x] `FeatureSeeder.php:34-46` re-inserts features that admins removed from plans on every deploy. Only seed features when the plan is first created.
- [x] Decide whether `public/build` stays committed. Building on deploy avoids shipping stale assets.
- [x] `backend/Dockerfile` uses `php artisan serve` and `composer:latest`. Label it dev-only.

### 3.3 Configuration — P2
- [x] Add `backend/.env.example` listing every key used in `config/*`.
- [x] Add `config/logging.php` (daily rotation, `LOG_LEVEL=warning` in prod), `config/session.php` and `config/cache.php`, or set `SESSION_DRIVER`, `CACHE_STORE` and `QUEUE_CONNECTION=redis` explicitly in prod `.env`. There are no `sessions`, `cache` or `jobs` migrations, so the `database` defaults would crash.
- [x] Remove `env()` calls outside `config/` (they return null under `config:cache`):
  - the 8 Mail classes
  - `GalleryCachePurgeService.php:27-28`
  - `ProcessVideoJob.php:458,533,599`
- [x] Create `config/media.php`: `config('media.queue')` in `ProcessVideoJob.php:60` always falls back to its default.
- [x] `config/database.php:19` switches to the test database via `$_ENV`/`getenv`, which is fragile under `config:cache`. Move this to `phpunit.xml` env.
- [x] Database queue `retry_after` is 90s against worker timeouts of 300 and 3600s. Raise it, or drop the database queue connection.

### 3.4 Observability & backups — P1
- [ ] Error tracking: `sentry/sentry-laravel` (or Bugsnag/Flare) with release tagging.
- [ ] Uptime monitoring on `/up` and on one photographer subdomain.
- [x] MySQL backups: `/root/ifotoset-db-backup.sh` at 01:30 UTC dumps to `/root/ifotoset-backups/nightly` (14 days) and uploads to B2 bucket `ifotoset-db-backups`. Restore not yet rehearsed.
- [ ] B2: turn on bucket versioning or lifecycle rules for accidental deletes.
- [x] Alerts on `failed_jobs` growth and queue depth. Either adopt Horizon with a gate, or `composer remove laravel/horizon`.

### 3.5 Database — P2
- [x] Add indexes:
  - `subscriptions(status, ends_at)`
  - `payments(status, created_at)`
  - `galleries(user_id, slug)` already exists; once 1.3 lands, lookups use it
- [x] Drop the orphan `albums` table (it has `softDeletes` but no model).
- [x] When a user is soft-deleted, hide their galleries from the public, or cascade.

### 3.6 Nginx — P1
- [x] There are two diverging configs. `nginx.prod.conf:14,21` lacks `*.ifotoset.com` in `server_name` (photographer subdomains would break), and `ifotoset-laravel.conf` has it. Keep one file as the source of truth.
- [x] Make the www redirect a single hop: `return 301 https://ifotoset.com$request_uri`, plus a www 443 block.

### 3.7 Tests & CI — P2
- [x] Add GitHub Actions: `composer install`, `pint --test`, `php artisan test` (MySQL + Redis services), `npm run build`.
- [x] Add tests for the untested areas:
  - public booking and deposit payment
  - `AvailabilityService` slot logic
  - reviews
  - Google Photos authorize and sync
  - analytics
  - suspension and verification enforcement
  - client and package CRUD

### 3.8 User lifecycle / compliance — P2
- [x] Account deletion (delete or anonymise the user, purge galleries and B2 objects through a job) and a data export.
- [x] Terms of Service and Privacy Policy pages. `auth/register.blade.php:99` already claims users agree to them.
- [x] Cleanup of trashed users' B2 objects.

---

## Phase 4 — Notifications & email

### 4.1 Missing transactional emails — P2
| Email | Trigger | Priority |
| --- | --- | --- |
| Payment failed | PawaPay callback or reconcile returns FAILED | P1 |
| Subscription expiring soon | 7 and 1 days before `ends_at` (scheduled) | P1 |
| Subscription expired / downgraded | `subscriptions:expire` | P1 |
| Password changed | Studio or admin password update | P1 (security) |
| Welcome | After email verification | P2 |
| New review received | `PublicReviewController@store` | P2 |
| Booking reminder | 24h before the session (scheduled) | P2 |
| Admin alerts | New signup, failed payment, failed-jobs spike | P3 |

### 4.2 Email quality — P2
- [x] Add plain-text versions (`text:` view) to every Mailable, for deliverability.
- [x] Rebrand `components/email/layout.blade.php:88-100`:
  - Replace the old CSS ring-and-dot logo with an absolute-URL `<img>` of the new mark (PNG, not WebP; some email clients can't show WebP).
  - Move from the old cool palette (`#111113`, `#6b6b72`, `#e8e8e5`, `#f7f7f5`, `#fbfbfa`) to the warm tokens (`--foreground #17110e`, `--border #eee3db`, `--background #fdf8f4`).
- [x] `PaymentReceipt.sent_at` records when the mail was queued, not when it was sent. Rename it or set it from a `MessageSent` listener.
- [x] Add `failed()` handlers to `ProcessVideoJob`, `GenerateGalleryZipJob`, `SyncGooglePhotosJob` and `UploadGooglePhotosChunkJob`. A timeout kill skips `catch`, so records stay stuck in "processing".
- [x] Add backoff to `StorageQuotaAlertJob`.
- [x] (Server) Production uses `MAIL_MAILER=resend` with `RESEND_API_KEY` and `noreply@ifotoset.com`. `RESEND_WEBHOOK_SECRET` is unset; the route then rejects every request (403), so it fails closed. Set it only if Resend webhooks are wanted.

---

## Phase 5 — SEO, performance & accessibility

### 5.1 SEO
- [x] **P1. The sitemap can list unlisted client galleries.** `Gallery::scopePubliclyIndexable` and `isPubliclyIndexable()` (`app/Models/Gallery.php:100-143`) need `show_on_profile = true` and the owner `publiclyIndexable()`.
- [x] **P2. The sitemap index only lists page 1** (`SitemapController.php:27-49`). Paginate the `photographers-N` and `galleries-N` sections, and use `max(updated_at)` as `lastmod` instead of `now()`.
- [x] **P2. Default OG image.** Add a 1200×630 `public/og-default.jpg` to replace the 233×235 `logo.png` (`SeoMetadata.php:98`). Use it as the fallback for missing avatars and covers, and add `og:image:width/height/alt`.
- [x] **P2. Missing noindex.**
  - Mark `public/export.blade.php` and `errors/*` as `noindex, nofollow`.
  - Send `X-Robots-Tag: noindex` on `/{slug}/photos` JSON and download responses.
- [x] **P2. Long descriptions.** `Str::limit(strip_tags($bio), 155)` for photographer meta descriptions (`SeoMetadata.php:116`).
- [x] **P3. Richer structured data.**
  - Photographer page: a `ProfessionalService` node with `areaServed`, `makesOffer` (from packages, which are passed but unused) and `sameAs`.
  - Gallery page: `ImageGallery` instead of `WebPage`.
  - Home page: plan `Offer`s.
- [x] **P3. Alt text fallback.** `"{gallery title} – photo by {photographer}"` in `components/gallery/photo-card.blade.php:48`, kept in sync with `pagination.js`.
- [x] **P3. Deleted galleries.** Return 410 instead of 404 for trashed galleries.

### 5.2 Performance
- [x] **P1.** `public/gallery.blade.php:678-679` loads `hls.js@latest` and `artplayer` unpinned and render-blocking on every gallery. Install them via npm and `import()` them only when a video exists. This also removes a supply-chain risk.
- [x] **P2. N+1 query.** `PublicPhotographerController.php:30` needs `->with(['coverPhoto', 'stats', 'user:id,username'])`.
- [x] **P2. Auth image preload.** `layouts/guest.blade.php:7` preloads `auth.webp` on mobile, where it's hidden. Add `media="(min-width:1024px)"`.
- [x] **P2. Photographer hero images.** `public/photographer.blade.php:86,92,126` need `srcset`/`sizes`, and `loading="lazy"` on the secondary images.
- [x] **P2. nginx compression and caching.**
  - Add `application/javascript` to `gzip_types` and set `gzip_proxied any`.
  - Enable brotli if the module is available.
  - Long-cache `/logo-mark.webp`.
- [ ] **P3. Page caching.** (Skipped: responses set session cookies, so edge caching needs care first.) `Cache-Control: public, s-maxage=300` (Cloudflare) for indexable public pages, or `Cache::remember` on the photographer page queries.
- [x] **P3. Google Analytics scope.** Limit GA (`components/google-tag.blade.php`) to marketing pages. It currently loads on private galleries, studio and admin.

### 5.3 Accessibility
- [x] **P1. Colour contrast below WCAG AA (4.5:1)** in `resources/css/app.css:91,97,100,142`:
  - white on `--primary #ea4a1f` is 3.82
  - white on `--brand #f0532a` is 3.51
  - white on the dark-mode brand `#ff6a3d` is 2.85
  - `--subtle #a8998f` on the background is 2.61
  - Darken the button background to about `#c63d14` and `--subtle` to about `#857469`.
- [x] **P2. Nested interactive elements.** The photo card is `role="button"` with `<button>`s inside it (`photo-card.blade.php:22-99`). Make the image area a real `<button>`, put the actions next to it, and use `group-focus-within:opacity-100`.
- [x] **P2. Focus traps.** The lightbox (`resources/js/gallery/modules/lightbox/index.js`) and the share, download and favourites modals (`gallery.blade.php:502-650`) need a Tab focus trap (or `inert` on the page behind them), and Escape for the modals.
- [x] **P3. Theme toggle.** Use `aria-pressed` instead of `aria-selected` (`components/ui/theme-toggle.blade.php:5`).
- [x] **P3. Skip link.** Add a "Skip to content" link in every layout.

### 5.4 Icons & PWA — P3
- [x] Add a `site.webmanifest` with 192 and 512 icons (including a maskable one).
- [x] Add a 180×180 opaque `apple-touch-icon.png`.
- [x] Regenerate `favicon.svg` from the new mark and link it with `<link rel="icon" type="image/svg+xml">`.

---

## Phase 6 — Design leftovers & cleanup

| # | Item | Where | Fix | Priority |
| --- | --- | --- | --- | --- |
| 6.1 | Admin Support page shows hard-coded sample tickets | `admin/support.blade.php:27`, `Admin/SupportController.php:17` | Hide from the nav until a ticket backend exists | P2 |
| 6.2 | Storage "upgrade" link always points to Basic | `studio/galleries/show.blade.php:79` | Link to the billing index or the next plan up | P2 |
| 6.3 | No styled 429 page (22 throttled routes) | `resources/views/errors/` | Add `errors/429.blade.php` using `x-errors.page` | P2 |
| 6.4 | Receipt print styles use the old palette | `studio/billing/receipt.blade.php:9-21` | Copy the tokens from `app.css:84-109` | P3 |
| 6.5 | Avatar initials glare in dark mode | `components/ui/avatar.blade.php:10` | Add a dark-mode colour (`color-mix` with tokens) | P3 |
| 6.6 | Raw colours bypass tokens | `layouts/guest.blade.php:78,84,91` | `bg-foreground`, `bg-success`, brand tint | P3 |
| 6.7 | Unused Vite entries (one has a `console.log`) | `vite.config.js:11-12`, `resources/js/pages/photographer.js`, `pages/studio-gallery.js` | Remove them, and `sortablejs` if unused | P3 |
| 6.8 | Legacy admin routes | `routes/web.php:232,249`, `layouts/admin.blade.php:20-21` | Delete `users-legacy` and `galleries-legacy` | P3 |
| 6.9 | Unused public files | `public/favicon.svg` (old mark), `placeholder*.{jpg,svg,png}` | Delete or regenerate | P3 |
| 6.10 | Controller variables the views never use | `PublicGalleryController.php:124-127`, `Admin/GalleryController.php:59` | Remove | P3 |
| 6.11 | Export "Back to gallery" goes through an extra 301 | `public/export.blade.php:22,125` | Use `$gallery->public_url` | P3 |
| 6.12 | Architecture PDF committed at repo root | `ifotoset_admin_studio_architecture.pdf` | Move to `docs/` or remove (it's also in `.gitignore`) | P3 |

---

## What is already solid

These were checked and need no work:

- **Routing and views:** all 245 routes resolve and cache. Every `route()` name, `@include`, `<x-…>` component, icon name, Alpine `x-data` function, `$dispatch` event and `fetch()` endpoint resolves. All views compile.
- **Forms:** every POST form has `@csrf` and the right `@method`, and input names match what the controllers validate.
- **Tests:** 208 feature tests, 943 assertions, all passing.
- **Access control:**
  - Studio and API ownership checks hold: every model lookup is user-scoped or goes through a policy.
  - Admin routes are protected. Impersonation blocks admin targets, regenerates the session and is written to the audit log.
- **Other security checks:** no SQL injection (sort fields are whitelisted), `SafeReturnUrl` is sound, Google Photos has no SSRF, nginx blocks dotfiles, and `APP_DEBUG` defaults to false.
- **Queues:** queue names match the workers (`photos`, `default`, `media`). Redis `retry_after` (3900s) exceeds the video timeout.
- **Media:** blurhash placeholders, width/height and aspect-ratio on cards (no layout shift), self-hosted fonts with `swap`, hashed `/build` assets with immutable caching.
- **SEO basics:** robots.txt, canonicals to subdomains, a single `h1` per page, noindex on auth, studio and admin, reduced-motion support.

---

## Deployment log 2026-10-15

**Server changes (root@213.199.55.204):**
- DB and `.env` backed up to `/root/ifotoset-backups/20261015-152536/` before deploying.
- `.env` additions:
  - `IFOTOSET_RELAY_SECRET`: the old hard-coded value, so the relay keeps working.
  - `CLOUDFLARE_CDN_SECRET`: matches the live Worker. Before this, prod signed CDN tokens with `APP_KEY`, which the Worker rejects.
  - `ADMIN_ALERT_EMAILS=ifotoset1@gmail.com`
  - `SESSION_DOMAIN=` (host-only), `SESSION_COOKIE=ifotoset_sid` (new name so old `.ifotoset.com` cookies don't collide; everyone was logged out once), `SESSION_SAME_SITE=lax`
- Removed the legacy `ifotoset` Next.js app from pm2.
- `storage/` and `bootstrap/cache` were partly root-owned (root-run artisan and workers vs www PHP-FPM), which caused past `file_put_contents` errors. Fixed with chown; `deploy.sh` now does this too.
- Nightly B2 DB backup (see 3.4).

**Fixed in `a7fbae0`:** `ecosystem.config.js` was syntactically invalid (missing comma). Removed the pm2 scheduler (cron already exists). Public downloads no longer proxy through PHP when `FILESYSTEM_DISK` is unset.

**Still open (needs you):**
1. Deploy `cdn_cloudflare_worker.js` to Cloudflare with Worker secrets `KEY_ID`, `APP_KEY`, `CDN_SECRET`, then set `CDN_HLS_STREAMING=true` in prod `.env`. Until then, the old Worker (with its CDN bypasses, item 0.4) is live.
2. Rotate the B2 key and CDN secret (0.1). They are in git history.
3. The aaPanel webhook runs its own inline script, not `deploy.sh`. Point it at `deploy.sh` so the chown and `pm2 delete` steps run on every deploy.
4. B2 bucket `ifotoset-db-backups` is set to keep all versions. Add a lifecycle rule (e.g. delete after 30–60 days), and rehearse a restore once.
5. 19 of 28 users are unverified. They are now redirected to the verification notice when they open the studio.
6. Error tracking (Sentry) and uptime monitoring on `/up` (3.4).
7. `ifotoset-queue` runs 2 processes although the config says 3 (`pm2 scale ifotoset-queue 3` if wanted).
8. `GOOGLE_REDIRECT_URI` points at `www.`, which now 301s to the apex. Change it to `https://ifotoset.com/google-photos/callback` here and in Google Cloud Console.

---

## Open questions to confirm on the server

1. Is `PAWAPAY_WEBHOOK_SECRET` set in production? Is the relay in front of the callback the only route in?
2. Does a cron run `schedule:run`? (`crontab -l`)
3. What do `FRONTEND_URL`, `SESSION_DOMAIN`, `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`, `MAIL_MAILER` and `APP_ENV` hold in production?
4. Which nginx file is actually deployed: `nginx.prod.conf` or `ifotoset-laravel.conf`?
5. Is the origin reachable without going through Cloudflare? This matters for the `trustProxies` and IP-throttle point.
6. Where does the deploy webhook script live, and does it run `queue:restart` and `optimize`?
7. Is the Next.js pm2 app still receiving any traffic?

---

## Test plan for the fixes

Add a regression test with each fix:

| Fix | Test |
| --- | --- |
| 0.2 Webhook | A forged callback without a secret or relay header is rejected. A callback is only finalised when the re-fetched status is COMPLETED. |
| 0.3 Script injection | A bio containing `</script>` renders escaped in the JSON-LD. |
| 1.1 Scheduler | `Schedule` contains the four new commands (`schedule:list` assertion). |
| 1.2 Duplicate listeners | A booking queues exactly one email per recipient. |
| 1.3 Slugs | Two users with the same slug each get their own gallery on show, photos, unlock and download. |
| 1.4 Booking form | The subdomain booking POST succeeds and flashes success. |
| 1.5 Password reset | The reset email URL is `route('password.reset')` with no `FRONTEND_URL` set. |
| 1.6 Suspension | A suspended user can't log in, an existing session is logged out, and the public site returns 404. |
| 1.7 Preferences | `new_bookings = false` suppresses the photographer email. Saving the form keeps the other keys. |
| 2.1 Uploads | A `.html` or `text/html` upload request is rejected. |
| 2.2 Throttling | The 6th failed login within a minute returns 429. |
| 5.1 Sitemap | Unlisted galleries and galleries of hidden profiles are absent from the sitemap. |
