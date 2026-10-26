# ifotoset — Portfolio, Custom Domain & Booking Assessment

_Assessment date: 2026-10-22 · Scope: public photographer portfolio, custom domain support, client booking flow and the studio booking tools · Read-only review: no code was changed._

**Verdict:** The marketing copy promises more than the product delivers.
- **Custom domains** are not implemented at all. The Professional plan sells them anyway.
- **The portfolio builder** is a single fixed template. None of the promised logo, theme styling, About page or curated collections exist.
- **Booking** works as a basic request form. The scheduling engine and deposit flow are built but never connected to it, and there are several correctness bugs.

The fastest win is to bring the copy in line with the product, then fix booking. The most valuable new work is mobile-money deposits, a real portfolio builder and custom domains, in that order.

---

## Contents

1. [Custom domain](#1-custom-domain)
2. [Portfolio builder](#2-portfolio-builder)
3. [Booking](#3-booking)
4. [Plan gating](#4-plan-gating)
5. [Recommendations & roadmap](#5-recommendations--roadmap)
6. [Quick-win checklist](#6-quick-win-checklist)

---

## 1. Custom domain

### What we promise
| Where | Copy |
|---|---|
| `resources/views/components/landing/pricing.blade.php:23` (Professional) | "Custom domain support" |
| `resources/views/components/landing/faq.blade.php:33` | "…and set custom domain mapping on the Professional plan." |
| `resources/views/components/landing/feature-grid.blade.php:6` | "Your own domain" / "Use your ifotoset subdomain or connect a custom domain on Professional." |
| `resources/views/components/landing/product-suite.blade.php:65` | "Your own subdomain or custom domain" |
| `database/seeders/FeatureSeeder.php:14-17, 37-52` | Feature `custom_domain` ("Custom Domain Support"), attached to the `pro` and `business` plans |

### What exists
Nothing. Only subdomains work:
- `routes/web.php:59` uses `Route::domain('{username}.' . $rootHost)` with a regex for reserved names.
- `routes/web.php:47` redirects www to the apex domain.

| Building block | Status |
|---|---|
| Domain column / model | ❌ None. No migration adds a domain field. |
| Settings UI to enter a domain | ❌ None |
| DNS verification (TXT/CNAME) | ❌ None |
| Middleware mapping a request Host to a photographer | ❌ None |
| SSL provisioning for customer domains | ❌ None. The letsencrypt lines in `ifotoset-laravel.conf:37-38,52-53` are commented out, and TLS ends at Cloudflare. |
| Plan gating | ❌ The `custom_domain` feature row is never read |

The one code hit for "custom domain", `StorageService::getCdnUrl(..., ?string $customDomain)` (`app/Services/StorageService.php:179-181`), is about storage-disk CDN hosts. It has nothing to do with photographer domains.

### What an implementation has to change
- **nginx** (`ifotoset-laravel.conf:25-27,44-47`): `server_name ifotoset.com *.ifotoset.com` only. A catch-all `default_server` block is needed so that foreign hosts reach Laravel.
- **Cloudflare:** the origin is behind Cloudflare (`config/proxies.php` trusts CF IP ranges). That makes **Cloudflare for SaaS custom hostnames** the natural fit:
  - The customer CNAMEs their domain to a fallback origin.
  - Cloudflare issues and renews the certificates.
  - Roughly 100 hostnames are free, then about $0.10 per hostname per month.
  - Caddy on-demand TLS would mean moving TLS termination off Cloudflare.
- **CDN worker** (`cdn_cloudflare_worker.js:272-277`): `isTrustedHost` allows only `ifotoset.com`, `*.ifotoset.com` and localhost referers, so it must accept verified custom domains too. Embedded images and video still pass through the `Sec-Fetch-Dest` check at lines 293-295.
- **Public URLs** (`app/Services/PublicUrlService.php:45-54`): always builds `https://{username}.{public_root_host}`. Gallery, export and share links all derive from it, so it must use the verified custom domain when one exists.
- **CORS** (`config/cors.php:27`): the origin pattern is hardcoded to `*.ifotoset.com`, so the public gallery API would fail from a custom origin.
- **Sanctum** (`config/sanctum.php:8-13`): the stateful domains are only the root host and its subdomains.
- **Session and CSRF:**
  - `SESSION_DOMAIN` is empty (`config/session.php:159`), so cookies are host-only. That is fine for custom hosts.
  - The gallery-unlock and booking POSTs will work as long as the session cookie is issued on the custom host.
  - Exceptions are listed in `bootstrap/app.php:39`.

---

## 2. Portfolio builder

### What we promise
| Where | Copy |
|---|---|
| `pricing.blade.php:11,17` (Free and Basic) | "Portfolio website" |
| `faq.blade.php:33` | "The portfolio builder lets you showcase curated galleries, add your brand logo, adjust theme styling, and set custom domain mapping on the Professional plan." |
| `product-suite.blade.php:60-70` | "A fast, search-friendly site for your best work, with booking built in", "Curated collections from your galleries", "SEO metadata and social previews". The mockup shows **Work / About / Book** navigation (line 43). |
| `how-it-works.blade.php:3` | "add your logo" |
| `studio/billing/index.blade.php:397` | Lists "Custom subdomain portfolio" for paid plans only, but Free users get a subdomain too (`register.blade.php:49`) |

### What exists
- **Routes:**
  - `{username}.{root}/` goes to `PublicPhotographerController@show` (`routes/web.php:69`).
  - `POST /book` goes to `@book` (line 68).
  - `/p/{username}` 301-redirects to the subdomain (lines 115-119).
- **Controller** (`app/Http/Controllers/Web/PublicPhotographerController.php:19-44`):
  - A hidden profile returns 404 to anyone but its owner.
  - It shows galleries that pass `shownOnProfile()`, sorted by **`created_at desc`**.
  - It shows active packages, sorted by `sort_order`.
- **One view** (`resources/views/public/photographer.blade.php`, 286 lines) with fixed sections in this order:
  1. Header: avatar, name and a light/dark toggle (lines 23-32)
  2. Hero: name, bio, location, website and a collage of the first 3 gallery covers (lines 41-100)
  3. "Selected work" gallery grid (lines 107-150)
  4. "Packages" (lines 157-200)
  5. Booking form (lines 209-270)
  6. Footer with "Powered by ifotoset" on Free only (line 279)
- **Data model:**
  - User profile fields: `phone`, `location`, `website`, `bio`, `avatar_path` and `profile_public`. `username` doubles as the slug.
  - Galleries have `show_on_profile` and `featured_order`.
- **Settings UI** (`resources/views/studio/settings/index.blade.php`):
  - The avatar is labelled "Profile photo or logo" (line 109).
  - Bio of up to 1000 chars, location and website (lines 135-150).
  - Username and a public/hidden toggle (lines 173-190).
  - A per-gallery "Show on my public portfolio" checkbox (`studio/partials/gallery-form.blade.php:108-112`). It applies to public galleries only (`GalleryController.php:134`).

### Gaps against the promise
| Promise | Reality |
|---|---|
| Adjust theme styling | ❌ No themes, colours, fonts or layouts. The only option is the visitor's light/dark toggle. |
| Add your brand logo | ⚠️ Only the round avatar, which also serves as the profile photo |
| Curated collections | ⚠️ Galleries can only be switched on or off, always newest first. The `featured_order` column and its scope (`app/Models/Gallery.php:106`) are never used. |
| About page (mockup) | ❌ Single page only; the bio is the only "about" content |
| Custom domain | ❌ See section 1 |
| SEO metadata | ⚠️ Generated automatically: "{name} in {location} - Photography Portfolio \| ifotoset" plus the bio (`app/Support/Seo/SeoMetadata.php:115-117`). Photographers can't edit it. |

**Hardcoded copy and styling:**
- Section headings: "Portfolio / Selected work" (lines 111-112), "Investment / Packages" (lines 162-163), and "Let's make something beautiful" with its 3-step copy (lines 213-220).
- The role label "Photographer" (line 48). Videographers and studios can't change it.
- Serif typography throughout.

**Missing entirely:**
- Cover or hero image, and hero text
- Services and Contact pages, blog
- Social links (Instagram, WhatsApp, TikTok)
- Testimonials
- A general contact form (only the booking form exists)
- Gallery ordering, grouping and captions

---

## 3. Booking

### Client journey today
1. The client scrolls to the bottom of the portfolio page (there's no dedicated booking link).
2. They fill in name, email, an optional phone, a raw `datetime-local` "preferred date & time", a package dropdown and notes (`photographer.blade.php:209-270`). The page says "No payment is taken now."
3. The form POSTs `/book` → `PublicPhotographerController@book` (lines 50-100) → `CreateBookingAction`.
4. The client sees a flash message and receives a "request received" email.
5. They wait for the photographer to confirm by hand. To reschedule, the client has to reply to the email.

### Photographer tools today
- **Bookings** (`studio/bookings/index.blade.php`): a paginated list with status filters and stat cards. The actions are Confirm, Mark completed and Cancel (`partials/business/booking-actions.blade.php`).
- **Availability** (`studio/availability/index.blade.php`): weekly hours, date exceptions, blocked time, timezone and slot interval.
- **Packages:** price, duration, deliverables and a deposit (fixed or %).
- **Emails:**
  - New booking (to the photographer) and request received (to the client).
  - Confirmed or cancelled (to the client).
  - Deposit receipts (never triggered; see bug 2).
  - A 24h reminder (`SendBookingReminders`, runs hourly, confirmed bookings only).

### Core problem: the scheduling engine is never used by the client form
- `AvailabilityService`, `SlotGenerator` and `ConflictDetector` are only referenced from `CreateBookingAction`.
- The public controller hardcodes `'validate_availability' => false` (`PublicPhotographerController.php:87`, verified).
- There is no slots endpoint and no calendar UI.

As a result:
- Clients can request any time, including 3am, Sundays, blocked dates and closed days.
- The availability page wrongly tells photographers that "Clients can only book inside these windows" (`availability/index.blade.php:26`).
- Package buffers (`buffer_before_minutes` / `buffer_after_minutes`) exist in the DB but have no inputs in `package-fields.blade.php`.

### Bugs
| # | Bug | Where | Fix |
|---|---|---|---|
| 1 | Availability isn't enforced on public bookings | `PublicPhotographerController.php:87` | Enable validation; add a slots endpoint and picker (see roadmap) |
| 2 | **Deposits are never collected.** No code creates a `purpose='booking_deposit'` payment. `InitiatePawaPayPayment.php:67` only handles `plan_subscription`. The finalize code (`FinalizePawaPayPayment.php:102`) and `SendBookingDepositReceipts` never run. | Payments | Build the deposit checkout (Phase 2). Until then, hide the deposit wording. |
| 3 | The "Deposit paid" badge never shows. `$booking->deposit_paid` is neither a column nor an accessor. | `studio/bookings/index.blade.php:158,204` | Derive it from payments, or hide it |
| 4 | **Cancelled bookings block the slot forever.** The overlap check doesn't filter on status (verified), yet the cancel dialog says the slot is freed (`booking-actions.blade.php:26`). | `CreateBookingAction.php:72-75` | Filter with `blockingStatuses()` |
| 5 | **Times are off by the timezone offset.** `datetime-local` is parsed with `new Carbon()` under `APP_TIMEZONE=UTC`, while the booking's timezone is Africa/Kigali. The list and most emails show raw UTC. The reminder converts to Kigali, so it shows a time 2h later than every other email. `after:now` is also checked in UTC. | `CreateBookingAction.php:51`, `BookingReminderMail.php:26-27` | Parse in the photographer's timezone, store UTC, and display in local time everywhere |
| 6 | The 24h-notice filter mutates `$nowLocal` (`addHours(24)` without `copy()`), adding 24h more for each candidate. Once availability is wired up, almost no slots would show. | `AvailabilityService.php:56` (verified) | `$nowLocal->copy()->addHours(24)` (line 188 already does this correctly) |
| 7 | Inactive packages can still be booked by UUID | `CreateBookingAction.php:45` | Add `->active()` |
| 8 | Re-submitting Confirm or Cancel emails the client again. The "no change" guard compares a string to an enum. | `Studio/BookingController.php:50-53` | Compare against `->value` |
| 9 | A returning client's name and phone are never updated | `PublicPhotographerController.php:71` (`Client::firstOrCreate`) | Use `updateOrCreate`, or update the blank fields |
| 10 | Booking isn't gated by plan, although pricing sells "Booking manager" and "Secure payments" as Pro-only | `pricing.blade.php:23`, `FeatureSeeder.php:20` | See section 4 |
| 11 | The FAQ promises "booking calendars… and invoices", but there's no calendar view and no invoice model | `faq.blade.php:37` | Change the copy now; build later |

### Gaps against competitors (Pixieset Studio Manager, HoneyBook, Dubsado, Calendly, ShootProof), by impact
1. **No real-time slot picker.** Competitors show a month calendar and then open times in the client's timezone. We offer request-and-wait.
2. **No deposit checkout at booking.** A mobile-money deposit via PawaPay would be our strongest local differentiator, and it's missing.
3. **No self-service for clients.** There's no booking page or tokenised link, so clients can't reschedule, cancel, pay the balance or add the booking to their calendar (no `.ics` file).
4. **No calendar view for photographers**, only a list. There's also no manual "add booking" (although `CreateBookingAction` supports it) and no Google or iCal sync.
5. **No contracts, invoices or balance tracking**, even though the pricing and FAQ imply them.
6. **No questionnaire or custom fields, and no location field.** `Booking.location` exists, but the form never collects it.
7. **No dedicated, shareable booking link** for Instagram and WhatsApp, and the flow isn't broken into steps. Phone/WhatsApp is optional even though it's the main contact channel in Rwanda.
8. **Rigid rules:**
   - Minimum notice (24h) and maximum horizon (365 days) are hardcoded.
   - There's no limit on bookings per day.
   - Only one reminder (24h) is sent, with no 1-week reminder and no SMS/WhatsApp.

---

## 4. Plan gating

- `Plan` has only quota fields (`app/Models/Plan.php:20-34`).
- A `features` / `plan_features` system exists and is seeded with `custom_domain`, `booking_manager` and `priority_support` (`FeatureSeeder.php:14-27`). **No code in `app/` or `routes/` checks these codes**, and `Plan` has no `features` relation.
- The only plan-based difference on the portfolio is the "Powered by ifotoset" footer for Free users (`photographer.blade.php:279`).
- So Free users get everything that Pro is supposed to unlock, including booking. Pro is currently hard to justify.

**Recommendation:**
1. Add a `features()` relation on `Plan` and a `User::hasFeature('code')` helper (cached).
2. Gate routes and UI with it: custom domains, premium templates, deposits, branding removal and booking limits.
3. Remember that seeders run on every deploy, and the feature seeder only attaches plans when it first creates a row. New feature codes must be seeded so they don't overwrite admin-edited plan settings.

---

## 5. Recommendations & roadmap

### Phase 0: Stop overpromising (about 1 day)
Change the copy to match today's product while the features are built. This protects against refunds and complaints.
- Mark custom domains as "Coming soon" on the pricing page, FAQ, feature grid and product suite.
- Remove "adjust theme styling", "invoices" and "booking calendars" from the FAQ.
- Hide the "X% deposit to secure the date" wording and the "Deposit paid" badge.
- Fix the "Clients can only book inside these windows" text, or make it true (Phase 1).
- Fix the billing page's "Custom subdomain portfolio" line, which is wrongly listed as paid-only.

### Phase 1: Make booking correct (about 1 week)
The best payoff for the effort, because most of the engine already exists.
- Fix bugs 4–9. Most are one-line changes.
- Turn on `validate_availability`, and add `GET /book/slots?package=&date=` returning JSON (`AvailabilityService` already returns slots).
- Replace the `datetime-local` box with a date picker plus clickable time chips.
- Add a dedicated, shareable booking page at `username.ifotoset.com/book`, with steps: package → date/time → details → confirmation.
- Require the phone number (labelled as WhatsApp) and add a location field.
- Add buffer inputs to the package form.
- Attach an `.ics` file to the confirmation email.
- Add a manual "New booking" modal in the studio, calling `CreateBookingAction` with `ignore_overlap`.

### Phase 2: Mobile-money deposits via PawaPay (about 1 week)
This is the strongest selling point in our market. Pixieset and HoneyBook don't offer mobile money.
- After the client picks a slot, start a PawaPay deposit with `purpose='booking_deposit'` and `booking_id`.
- Hold the slot briefly (e.g. 15 minutes) while the payment is pending.
- On a successful payment, the existing `FinalizePawaPayPayment` path and the receipt listeners already handle confirmation and emails. Make the booking confirm automatically.
- Show deposit and balance status in the studio. Later, add a balance-payment link.
- Gate deposits behind Pro.

### Phase 3: A real portfolio builder (2–3 weeks)
Use structured customisation, the way Pixieset Website and Format do it. A Squarespace-style drag-and-drop editor would cost far more.
- **Templates:** 3–4 well-designed options (e.g. Minimal, Editorial, Bold, Grid), all rendering the same data. Lock the premium ones to paid plans.
- **Branding:**
  - A logo separate from the avatar.
  - A cover or hero image and an editable hero headline.
  - A brand colour.
  - A choice from 3–4 curated font pairings.
  - An editable role label (Photographer / Videographer / Studio).
- **Sections:** reorder or hide hero, work, about, services/packages, testimonials and contact/booking. Let photographers edit the section headings.
- **Pages:** an About page, plus Contact and the booking page from Phase 1.
- **Curated work:**
  - Use `featured_order` to drag-sort galleries.
  - Let photographers pick a cover per collection and optionally group galleries by category (Weddings, Portraits…).
- **Social:** Instagram, TikTok, Facebook and a WhatsApp click-to-chat button.
- **SEO:** an editable title, description and social share image, with sensible defaults.
- **Storage:** keep settings in a `portfolio_settings` JSON column (or a table) on the user, and render them through Blade components per template.

### Phase 4: Custom domains (1–2 weeks)
- **Data:** a `custom_domains` table with `user_id`, `hostname`, `status` (pending/active/failed), `cf_hostname_id`, `verified_at` and `last_checked_at`.
- **Settings UI:** the photographer enters a domain, sees the CNAME instructions (`domain → customers.ifotoset.com`) and gets live status. Gate it behind the `custom_domain` feature.
- **Provisioning:** call the Cloudflare for SaaS Custom Hostnames API when a domain is added, and poll it with a scheduled job until it's active. Cloudflare handles SSL issuance and renewal.
- **Routing:**
  - Add a catch-all nginx `default_server`.
  - Add Laravel middleware that resolves the Host to a photographer (cached) and serves the portfolio, galleries and booking routes.
  - Redirect `username.ifotoset.com` to the custom domain once it's active.
- **Making it aware of domains:**
  - `PublicUrlService`
  - CORS (`config/cors.php`)
  - Sanctum stateful domains
  - The CDN worker's `isTrustedHost`
  - Sitemaps and canonical URLs
- **Apex domains:** many registrars can't CNAME a root domain. Document a `www.` setup with a redirect, or use Cloudflare's apex proxying option.
- **Cost:** about 100 custom hostnames free, then roughly $0.10 per hostname per month. That's easily covered by the Pro price.

### Later
- A calendar view (month/week) for photographers
- Google Calendar busy-time sync
- Tokenised client pages for rescheduling, cancelling and paying the balance
- Contracts with e-signature and invoices
- Questionnaires and custom booking fields
- Configurable notice and horizon, and daily booking limits
- A 1-week reminder, plus SMS/WhatsApp reminders
- Testimonials and a simple blog

---

## 6. Quick-win checklist

- [ ] Change the copy: custom domains "Coming soon"; remove theme styling, invoices, calendars and the deposit wording (Phase 0)
- [ ] `CreateBookingAction` overlap check: filter by `blockingStatuses()`
- [ ] `CreateBookingAction`: parse `starts_at` in the photographer's timezone; display local time in the list and every email
- [ ] `AvailabilityService.php:56`: `$nowLocal->copy()->addHours(24)`
- [ ] `CreateBookingAction.php:45`: only active packages
- [ ] `Studio/BookingController.php:50-53`: compare the status to `->value`
- [ ] `PublicPhotographerController.php:71`: update details for returning clients
- [ ] Remove or fix the `deposit_paid` badge in `studio/bookings/index.blade.php`
- [ ] Add buffer inputs to `package-fields.blade.php`
- [ ] Add a location field; require phone/WhatsApp
- [ ] Attach `.ics` to the confirmation email
- [ ] Add `Plan::features()` and `User::hasFeature()`, and decide what is gated where
