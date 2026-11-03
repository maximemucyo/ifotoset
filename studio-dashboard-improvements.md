# Studio dashboard improvements

An audit of the photographer-facing studio (`/studio/*`) and the client gallery it produces. It covers visual "AI slop", UX problems, settings and features that are missing or broken, and proposals for easier gallery creation and per-gallery templates with cover and device previews.

File references are relative to the repo root. Priority tags: **P0** is broken or misleading today, **P1** is high value for photographers, **P2** is polish.

---

## 1. The core problem: the dashboard reports, it doesn't direct

A photographer opens the studio to answer a few questions. What needs me today? Which clients are waiting on photos? Who picked favourites? What's about to expire? The Overview page answers none of these. It shows four vanity counts, a setup checklist, recent galleries, upcoming sessions, storage, and a "Quick actions" list that repeats the sidebar.

The dashboard's primary job should be **a queue of things that need the photographer**, with everything else secondary.

### Proposed Overview structure

```
┌──────────────────────────────────────────────────────────────────────┐
│ Tuesday, November 3                         [+ New gallery] [Upload] │
├──────────────────────────────────────────────┬───────────────────────┤
│ Needs you                                    │ This week             │
│  • 2 booking requests to confirm        →    │  Thu 10:00  Diane M.  │
│  • "Kevin & Aline" has 0 photos (3 days)     │  Sat 14:00  Wedding   │
│  • Diane picked 48 favourites — view list    │                       │
│  • "Graduation 2026" expires in 4 days       │ Storage  12 / 50 GB   │
│  • 3 invites not opened after 5 days         │ ▓▓▓░░░░░░░            │
├──────────────────────────────────────────────┴───────────────────────┤
│ Galleries in progress (cover strip, with status: Draft/Shared/Viewed)│
├──────────────────────────────────────────────────────────────────────┤
│ Client activity: Diane viewed "Wedding" · 2h ago · downloaded 12     │
└──────────────────────────────────────────────────────────────────────┘
```

- **"Needs you" list (P1).** Build it from data that already exists:
  - pending bookings;
  - galleries with `photo_count = 0` older than a day;
  - favourites recorded in `activity_logs` (`Api/V1/GalleryController:113-129`);
  - unopened invitations (`accepted_at IS NULL`);
  - `expires_at` within 7 days, once expiry can be set (see §3).

  Each row is one action. When the list is empty, say so plainly: "Nothing needs you right now."
- **Remove the KPI row, or demote it to a single line (P2).**
  - The cards are generic.
  - "Photos delivered" counts uploads, not deliveries. That's misleading, because a photo nobody has opened hasn't been delivered.
  - Pending bookings already appears three times on this page: the header meta line, the sidebar badge and a KPI card.
- **Remove "Quick actions" (P2).** It is the sidebar's Business section again, word for word. Use the space for client activity.
- **Show a gallery's lifecycle state on cards (P1).** Today a card only shows visibility. Photographers think in stages: *Draft → Shared → Viewed → Downloaded → Expired/Archived*. See §4.2.
- **Put "Upload" next to "New gallery" in the page actions (P2).** It should open a picker of recent galleries and then the upload modal.

---

## 2. Visual audit: AI-slop tells and generic patterns

The studio is clean and consistent, which is good. But it is assembled almost entirely from the default SaaS kit, so nothing about it says *photography*. The client gallery (`resources/views/public/gallery.blade.php`) has more character, but repeats the same tells.

| Pattern | Where | Why it reads as generated | Fix |
|---|---|---|---|
| Time-of-day greeting ("Good afternoon, Maxime") as the H1 | `dashboard.blade.php:7-9, 44` | The most common generated-dashboard opener, and it spends the most valuable line on nothing | Lead with the "Needs you" count, or with the studio name and today's date |
| Four identical stat cards, each with a label, big number, icon chip and hint | `dashboard.blade.php:96-109`, `components/studio/stat-card.blade.php` | Textbook SaaS-card kit, and the numbers aren't actionable | See §1 |
| An icon in a rounded tinted square on *every* row and card | Quick actions, the phone prompt, the download toggles (`gallery-form:276,290,305`), empty states | Decoration with no information. An icon should earn its place by aiding scanning | Keep icons only where they differentiate (visibility type); drop them from settings rows |
| Meta strings joined with middle dots everywhere | Dashboard meta, gallery card, list view, manager header, bookings | A template tic, and on mobile it wraps badly | Use layout (two lines, or a label/value pair) instead of `·` chains |
| `arrow-right` icon appended to buttons | "View all", "All bookings", "Create gallery", the next-step CTA | Generic-kit tell; adds nothing to a clear verb | Remove; keep the arrow only on links that leave the app (`arrow-up-right`) |
| Hover lift and image zoom on every card | `gallery-card.blade.php:14,18`, photo cards, list thumbnails | Motion on every card is the default "AI" feel and competes with the photos | Keep one hover response: a subtle border change. No scale on photographers' images, since zooming crops their framing |
| Blur glow behind icons, plus a dotted radial mask | Upload dropzone (`show.blade.php`, `bg-brand/10 blur-2xl`, `bg-dots mask-radial`), empty gallery card | Decorative gradient-wash tell | A plain dashed drop area with a real example (a faint contact-sheet grid) says "photos" better |
| Tracked-out ALL-CAPS eyebrows | Client gallery hero `tracking-[0.32em] uppercase` (`gallery.blade.php:105,177,199,233`), "View gallery" button `tracking-[0.18em]` | Template chrome on the page clients see most | Use sentence-case photographer name and date in the serif, set smaller |
| Same border radius and soft shadow on every container | Everything uses `card` + `shadow-xs` + `rounded-xl` | No hierarchy: a settings card, a photo and a modal all look alike | Photos get **no** radius and no shadow (they are the content); chrome gets the radius |
| A progress ring for the setup checklist | `dashboard.blade.php:116-124` | Fine, but generic. The checklist also mixes "studio" setup with "booking" setup, which not every photographer wants | Make booking steps optional, and let photographers dismiss the checklist permanently (today it only collapses for the session) |

**Direction suggestion.** The studio is a darkroom/contact-sheet tool. The photos should be the only colour on screen. Use a neutral, quiet chrome; photo tiles with square corners at their real aspect ratio; filenames and EXIF in small type. Save the brand colour for one primary action per screen. This is a better identity than more cards.

---

## 3. Broken, misleading or half-wired (fix first)

| # | Problem | Evidence | Fix |
|---|---|---|---|
| P0-1 | **Gallery expiry can't be set**, but the app fully enforces it and the header badge expects it | `expires_at` is read by `VerifiesGalleryAccess.php:41`, `Gallery.php` scopes and the header badge (`galleries/partials/header.blade.php:29-35`). There is no form field and no validation rule | Add an "Expires on" date, plus quick picks (1 month, 3 months, 6 months, never), to the gallery form. Add reminder emails (§5) |
| P0-2 | **"Invite clients" and the "Clients & invitations" tab lead to a dead end** for public and PIN galleries | `header.blade.php:71, 133-138` link to `edit#invitations`, where the invite UI only shows when Private (`gallery-form.blade.php:145`) | Allow email invites for **every** visibility: an email with the link (and PIN). Private is just the case where the invite is also the key |
| P0-3 | **The gallery manager's tabs are fake**: Photos, Clients & invitations and Settings look like tabs, but two of them navigate to a separate page that has no tab bar | `header.blade.php:127-141` | Make `edit` render the same header with the right tab active, or turn Settings into a slide-over on the manager page |
| P0-4 | **The same visibility has different names**: the form and list say "Private", the manager header says "Unlisted" | `gallery-form.blade.php:12`, `header.blade.php` `default => 'Unlisted'` | One vocabulary everywhere |
| P0-5 | **"PIN" means two different things**: the gallery "PIN" is a password of at least 6 characters, the download PIN is 4 digits | `gallery-form:133-139`, `GalleryController:116` | Rename the first to "Gallery password", or make both numeric PINs. Clients on phones prefer numbers |
| P0-6 | **"Gallery shares" in Analytics is always 0**, because nothing writes `gallery_shared` | `StudioAnalyticsQuery.php:68-72`; no writer exists | Record it from `resources/js/gallery/modules/share.js`, or remove the stat |
| P0-7 | **`has_explicit_cover` is silently dropped** because it isn't fillable, so a manually chosen cover isn't remembered as manual | `GalleryCoverService` writes it; it is missing from `Gallery::$fillable` (`Gallery.php:32-60`) | Add it to `$fillable` and add a test |
| P0-8 | **Client name is promised to clients but never shown**: the Details section says "What your client sees at the top of their gallery" | `gallery-form.blade.php:41`; `client_name` is never rendered publicly | Either show it ("For Diane & Kevin") or fix the copy |
| P1-9 | **Client favourites are invisible to the photographer.** They live in the client's localStorage. The server stores only a counter and an activity row | `favorites.js:13,24-48`, `Api/V1/GalleryController:99-129` | See §4.4 (proofing). This is the single most-requested feature category in client-gallery products |
| P1-10 | **Captured client emails are never shown.** Favourite and ZIP-request emails are stored but no studio view lists them | `activity_logs.properties`, `GalleryDownload` | A "People" panel per gallery, with an option to save an email to Clients |
| P1-11 | **`password_hint` is displayed on the PIN gate but can never be written** | `VerifiesGalleryAccess.php:70`, `gallery.blade.php:157-160` | Add a hint field under the password, or drop the column |
| P1-12 | **`allow_google_photos` can't be set per gallery** (default on) | `Gallery.php:213-216` | Add a toggle in Downloads |
| P2-13 | The iOS download notice is dead code. Reviving it would double-count downloads | `downloads.js:25,214-245` | Delete it |
| P2-14 | The ZIP's empty check counts hidden photos; the job excludes them | `Api/V1/GalleryController:163-166` vs `GenerateGalleryZipJob:86-110` | Use the same scope in both |
| P2-15 | The ZIP ignores the gallery's photo order | `GenerateGalleryZipJob.php:112` orders by id | Use `GalleryPhotoQuery` ordering, and prefix filenames with an index |
| P2-16 | The manager loads `hls.js@latest` and `artplayer` (unpinned) from jsDelivr | `show.blade.php`, end of file | Pin exact versions, or bundle them through Vite |
| P2-17 | The invite file input accepts `.tsv`, but validation is `mimes:csv,txt` | `gallery-form:166`, `GalleryController:124` | Align them |
| P2-18 | A `POST /studio/galleries/{uuid}/invitations` route exists with no UI caller and no private check | `routes/web.php:225`, `GalleryController:450-473` | Wire it into the new invite panel (P0-2) or remove it |

---

## 4. Gallery creation and management

### 4.1 Yes: creating a gallery should be much easier

Creating a gallery today means filling a five-section settings form (details, privacy, invitations, photo order, downloads) *before* any photo exists. Upload comes afterwards on another page. The photographer is asked for decisions they can't make yet, such as who to invite before they've culled.

There's also a risk in the current defaults. New galleries are **Public**, so the link works while photos are still uploading, before anyone has culled them.

**Proposal: photos first, settings later.**

```
  Drop a folder anywhere      ┌─────────────────────────────┐
  in the studio  ───────────▶ │ New gallery                 │
                              │ Title   [Kevin & Aline    ] │  ← prefilled from the folder name
  or click "New gallery" ───▶ │ Date    [2026-11-01]        │  ← prefilled from EXIF of the first photo
                              │ Client  [Pick or add ▾]     │  ← links to the Clients CRM
                              │          [Create and upload]│
                              └─────────────────────────────┘
                                             │
                                             ▼
                       Gallery manager, upload already running, state = Draft
                                             │
                                             ▼
                     "Share" button → share panel (visibility, PIN, expiry,
                      invites by email/WhatsApp, link + QR) → state = Shared
```

- **A three-field dialog instead of a page (P1).** The fields are title, date and client. Everything else comes from **gallery defaults** (§5).
- **Drag a folder onto the dashboard or Galleries page (P1).** This creates a gallery named after the folder and starts uploading. The upload pipeline (`studio.uploads.request/confirm`) is already per-gallery, so this is mostly UI.
- **Add a Draft state (P1).** A new gallery isn't reachable by clients until the photographer presses **Share**. This needs one additive column, `published_at` (nullable; existing galleries backfill to `created_at`, so nothing changes for them). The Share step is where visibility, PIN, expiry and invites live.
- **Share panel (P1).**
  - Copy link.
  - **Share on WhatsApp**, with the link and PIN prefilled. For Rwandan clients this matters more than email.
  - Email invite.
  - QR code for printed cards and event displays.
- **Link to a CRM client (P1).** `client_name` is free text today, and `Client` has no galleries relation. Add a nullable `client_id`, keep `client_name` as the fallback, and show the client's galleries on their client page.
- **Duplicate gallery settings (P2).** "New gallery like this one" for recurring event types.

### 4.2 Managing galleries

- **Lifecycle filter tabs (P1).** Replace the visibility tabs (Public / Private / PIN) on `galleries/index.blade.php` with **Drafts · Shared · Expiring soon · Expired · All**. Visibility becomes a badge on the card.
- **Per-gallery activity on cards (P1).** Show "Viewed 2h ago · 48 favourites · 12 downloads". `gallery_stats.favorites_count` and `downloads_count` are already maintained but never read in the studio.
- **Bulk gallery actions (P2).** Select several galleries and then extend expiry, change visibility, move to trash, or archive.
- **Archive (P2).** Hide old galleries from the default list without trashing them.
- **Sets inside a gallery (P2).** "Ceremony / Reception / Portraits" is the norm for weddings. It needs a `gallery_sets` table and a `photos.set_id`. The client gallery shows them as chips.
- **Manual reorder (P2).** Drag to reorder as a fourth photo-order mode ("Custom"), using the existing `sort_order`.

### 4.3 Templates, cover photo and device preview

**Recommendation: yes, but keep the scope small and the system shared.**

Today every client gallery looks the same. There is a full-bleed `h-svh` cover with `object-center`, a serif title and a masonry grid. The portfolio already has templates, fonts and a brand colour (`PortfolioSettings::TEMPLATES`, `studio/portfolio/edit.blade.php:133-163`), but the gallery doesn't read any of it. The gallery is the page clients actually share with family, so it is the photographer's strongest marketing surface.

**What to build:**

1. **Cover photo with a focal point (P1; do this first, it's cheap and fixes a real bug).**
   - A landscape cover on a phone at `h-svh` with `object-center` shows roughly the middle 35% of the frame. The couple at the left third gets cropped out.
   - Add `cover_focal_x` and `cover_focal_y` (decimals, nullable, default 0.5) to `galleries`, and render `object-position: {x}% {y}%` on the hero.
   - UI: a "Cover" panel in gallery settings with the current cover. Click "Change" to open a photo picker (reusing the manager grid). Click on the image to set the focal point, showing a crosshair and live phone and desktop crops side by side.

2. **Four gallery templates (P1).** Each template must differ in *layout*, not just colour:

   | Template | Cover | Grid | Feel |
   |---|---|---|---|
   | **Full bleed** (current) | Edge-to-edge, title over the image | Masonry | Weddings, events |
   | **Framed** | Image inset with a margin, title below in a serif | Uniform rows (justified) | Editorial, portraits |
   | **Split** | Image on one half, title, date and photographer on the other (stacked on mobile) | Masonry | Brand and commercial |
   | **No cover** | Title only, photos start immediately | Tight grid | Large events, school photos, where clients want to find themselves fast |

   Also add:
   - **Grid style:** masonry, justified rows, or square grid.
   - **Colour mode:** light, dark, or follow the visitor. Today it is visitor-only.
   - **Typography:** inherit the portfolio font by default.

   Store all of this on `galleries` as `template`, `grid_style`, `color_mode`. Mirror `PortfolioSettings` as a `GallerySettings` support class with a `TEMPLATES` constant and thumbnails, reusing `template-thumb.blade.php` patterns. **Default to the studio's choice** (§5), so the photographer chooses once, not per gallery.

3. **Live device preview (P1).**
   - Don't build a separate mock preview. It will drift from the real page. Render the **real** public gallery in an `<iframe>` from a studio-authenticated preview route, e.g. `GET /studio/galleries/{uuid}/preview?template=framed&focal=0.3,0.6`.
   - The route renders `public/gallery.blade.php` with the access gate bypassed and unsaved settings applied from query params, with `noindex`. It must **not** record views, downloads or favourites, so analytics aren't polluted by the photographer.
   - Wrap the iframe in a device frame with a **Phone (390 × 844) / Desktop (1440 × 900)** toggle, scaled with CSS `transform` so desktop fits the panel.
   - Reload the iframe (debounced) when a template, cover or focal-point control changes. Start with a reload; switch to `postMessage` later only if it feels slow.
   - Put the panel on the gallery Settings tab as a two-column layout: controls on the left, sticky preview on the right. On mobile it becomes a "Preview" button that opens a full-screen sheet.

4. **Branding on client galleries (P1, together with templates).** Apply the portfolio logo and brand colour. On paid plans, offer to replace the ifotoset footer mark (`public/partials/gallery-footer.blade.php:5-8`) with the studio's own. This is a natural upgrade lever.

**Rollout note.** The deploy runs migrations before the code switch (`deploy.sh`), so every new column must be nullable or defaulted. The current look should be the default template, so existing galleries render unchanged.

### 4.4 Proofing: favourites the photographer can see

This is the biggest functional gap against Pixieset, Pic-Time and ShootProof.

- Move favourites from localStorage to the server: a `gallery_favorites` table keyed by gallery, photo and visitor email. Email is already collected in the favourites modal.
- Studio view: a **Favourites** tab in the gallery manager, grouped by person. Add a "Copy filenames" button for Lightroom (`IMG_0100 OR IMG_0103 …`) and a "Download list (CSV)" button.
- Optional "Submit my selection" for the client, which notifies the photographer. Optionally add a selection limit ("Choose 30 for the album").

### 4.5 The gallery workspace photographers already know

Step 3 made creating and sharing a gallery easy. The manager page they land on afterwards still feels unfamiliar. Most Rwandan photographers use Pixieset every day, and they expect its layout. We should match the parts of that layout they rely on, and keep what we already do better: the three-field dialog, Draft, WhatsApp/QR sharing, the cover focal point and the live preview.

```
┌───────────────┬──────────────────────────────────────────────────┐
│ ‹ Kevin & Aline   [Draft ▾]      [Search photos]  Preview [Share]│
├───────────────┼──────────────────────────────────────────────────┤
│ [cover image] │ All photos (19)              sort  grid  + Add   │
│  Change cover │ ┌────┐┌────┐┌────┐┌────┐┌────┐┌────┐             │
│ Photos Design │ │ ▬▬ ││ ▮  ││ ▮  ││ ▬▬ ││ ▮  ││ ▬▬ │  ← whole    │
│ Settings Faves│ └────┘└────┘└────┘└────┘└────┘└────┘    photo in │
│───────────────│                                       a grey cell│
│ Sets   + Add  │                                                  │
│ ═ All (19)    │          ┌──────────────────────────────┐        │
│ ═ Ceremony    │          │ ✕ 14 selected ▾  ★ ⧉ ⇥ 🗑 ⋯ │        │
│ |←            │          └──────────────────────────────┘        │
└───────────────┴──────────────────────────────────────────────────┘
```

- **Left rail instead of a tall header (P1).**
  - Today the badges, title, meta line, link bar, storage card and tabs take about 300px before the first photo (`galleries/partials/header.blade.php`).
  - Move the cover (click it to change), the section tabs and the sets list into a collapsible rail, so photos start at the top of the screen.
  - On phones the rail becomes a cover strip with the tabs as a segmented bar.
- **Status pill that is also the control (P1).** Show `Draft ▾` / `Shared ▾` next to the title, and let the photographer change it there. The first Share still publishes. **Preview** replaces "View as client". Upload moves out of the header into the grid toolbar ("Add photos").
- **Uncropped photo tiles (P1).**
  - Show each photo whole, at its real aspect ratio, inside a square neutral cell. No radius, no hover zoom, no footer strip.
  - Today `object-cover` and `group-hover:scale-[1.03]` crop portraits (`show.blade.php` `$gmCardClasses`).
  - This is the contact-sheet direction from §2.
- **Selection that works like Finder and Lightroom (P1, done).**
  - A plain click still opens the photo. Once something is selected, a click adds or removes that photo.
  - Cmd/ctrl-click toggles one photo; shift-click selects a range from the last photo clicked.
  - Drag a box from empty space to select; the page scrolls near the edges. Shift or cmd adds to the selection.
  - Cmd/ctrl+A selects the whole gallery, loading photos not yet scrolled into view. Esc, or a click on empty space, clears it.
  - The dark bottom bar shows "14 selected ▾" (select all, select none), then Cover, Hide, Show and Delete.
  - The Select button stays, for phones.
- **Downloads are one switch (P1, done).**
  - The settings had three switches: single photos, whole gallery, and a download PIN. Photographers found that confusing, and many read English as a second language after Kinyarwanda.
  - Now there is one **Download PIN** switch.
    - Off: anyone who opens the gallery can download.
    - On: a PIN is generated and shown with **Copy** and **New PIN**.
    - Either way, the choice covers single photos and the whole gallery.
  - It's stored in the existing columns (`Gallery::downloadAttributes()`), so there's no migration.
  - A new PIN locks out everyone who unlocked with the old one, because unlocks are tied to a checksum of the PIN.
  - Consequences:
    - There's no longer a "downloads off" setting. A gallery saved after this change allows downloads, so photographers who share proofs before payment will need the watermark (§5).
    - Google Photos stays on by default and gets no studio switch, so P1-12 is dropped.
- **Settings sections that show their state (P1).**
  - Split the long settings form into rail sections: General, Privacy, Expiry, Downloads, Favourites.
  - Show each section's state in the rail. The rail already shows a summary (who can open it, expiry, the download PIN, the client); each row links to its settings section.
  - Save each toggle as it's switched.
  - The live preview moves to a **Design** tab (cover, focal point, later templates), where it's useful. Privacy settings don't change how the gallery looks.
- **Sets (promoted from P2 in §4.2).** Pixieset collections always have at least one set, so photographers already sort their photos this way. It needs one additive `gallery_sets` table and a nullable `photos.set_id`. Photos without a set appear under "All photos".
- **Search by filename (P2).** For when a client asks for "IMG_0412".
- **Not copied:** Store. Pixieset's Activity tab becomes our **Favourites** tab (§4.4).

---

## 5. Settings photographers will look for

Today Studio settings has Profile, Public profile, Security, Notifications, Your data and Delete account. Timezone lives on the Availability page.

| Setting | Status | Notes |
|---|---|---|
| **Gallery defaults** | Missing (P1) | Default visibility, download PIN on/off (§4.5), expiry length, photo order, template, watermark. Today these are hard-coded in `gallery-form.blade.php` and the controller |
| **Gallery expiry and reminders** | Missing (P0/P1) | "Expires in 7 days" email to invited clients, plus a reminder to the photographer. Expiry has to be settable first (P0-1) |
| **Download sizes** | Missing (P1) | Web size (2048px) and/or original. On mobile data in Rwanda, a full-res ZIP is a real cost to clients; offering web size by default is a kindness and a differentiator |
| **Watermark** | Missing (P1) | Upload a PNG or use text; set position and opacity; apply to previews, not to paid downloads. Lets photographers share proofs publicly before payment |
| **Client gallery branding** | Missing (P1) | See §4.3.4 |
| **Contact button on the gallery** | Missing (P2) | WhatsApp or phone button on the client gallery ("Book again" / "Order prints"). The phone number is already collected |
| **Welcome message per gallery** | Missing (P2) | A short note from the photographer under the cover. Needs a `description` column |
| **Password hint** | Read but not writable (P1) | See P1-11 |
| **Save to Google Photos per gallery** | Read but not writable (P1) | See P1-12 |
| **Email sender name / reply-to** | Missing (P2) | Invitations should come from "Studio Name via ifotoset", with replies going to the photographer |
| **Notification granularity** | Partial (P2) | Three global switches today. Add "Email me when a client submits favourites" and "…downloads the full gallery" |
| **Timezone in Settings** | Misplaced (P2) | It affects more than availability (greeting, digests). Show it under Profile too |
| **Specialties** | Saved but never shown (P2) | Used only in JSON-LD. Show them on the portfolio About page or remove the field |
| **Custom domain** | Advertised as "Soon" (P2) | `pricing.blade.php:31`. Either schedule it or remove it from the pricing page |
| **Checklist dismissal** | Missing (P2) | Lets a photographer who doesn't take bookings hide the getting-started card for good |

---

## 6. Suggested order of work

1. **Fix the broken pieces (about a week).** P0-1 to P0-8 in §3. They're small, and several are one-line fixes (fillable, labels, copy). Expiry is the largest.
2. **Cover focal point and device preview.** This is the foundation for templates and fixes mobile cover crops on its own.
3. **Simpler creation and Draft/Share.** The three-field dialog, folder drop, share panel with WhatsApp and QR, and the CRM client link. *(Shipped, except folder drop.)*
   - **3b. Gallery workspace (§4.5).** In this order:
     1. Rail shell and status pill. *(Done.)*
     2. Uncropped tiles, the new selection and the bottom bar. *(Done.)* Downloads as one PIN switch. *(Done.)*
     3. **Next:** settings sections with their state, autosave, and the Design tab with the preview.
     4. Sets.
     5. Search.
4. **Proofing.** Server-side favourites, a Favourites tab, and lists for Lightroom.
5. **Dashboard "Needs you" redesign.** Needs steps 1 and 4 to have real signals to show.
6. **Templates, gallery defaults and branding.**
7. **Watermark, download sizes, bulk gallery actions.** Sets moved into 3b. The watermark matters more now that downloads can't be turned off (§4.5).

Steps 2 and 3 can run in parallel. Each step is independently shippable, and all migrations are additive.
