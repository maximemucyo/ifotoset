# Studio: remaining work

Updated November 4, 2026, on the `studio-improvements` branch. The reasons behind each item are in [studio-dashboard-improvements.md](studio-dashboard-improvements.md); the § numbers below point there.

Already shipped:
- all the P0 fixes;
- cover focal point and live preview;
- photos-first creation with Draft and Share;
- the gallery workspace (side panel, status pill);
- uncropped photo tiles and the new selection;
- downloads as a single Download PIN switch;
- Settings split into sections that save as you change them, and a Design tab for the cover with the live preview;
- the password hint (P1-11);
- sets inside a gallery, shown to clients as tabs, and search by file name;
- Proofing: favourites stored on the server per email, "Copy file names" and CSV, a Favourites settings section (on/off, limit), and "Send my picks" with an email to the photographer;
- an **Activity** tab (Favourites by person with Remove, Downloads with the client's email, Quick links);
- the **Overview "Needs you"** dashboard, gallery stages, and lifecycle tabs on the Galleries page;
- **client gallery layouts** (Full bleed, Framed, Split, No cover) with a photo grid and colours, on the Design tab;
- **gallery defaults** in Studio settings (New galleries);
- **quick share links**: select photos, choose "Share link", and the link is copied. It opens only those photos, even in a draft or private gallery, and stops when deleted or when the gallery expires. Listed in Activity and in the Share panel.

**Rules for every item**
- Migrations must be additive (nullable or defaulted). The deploy runs them before the code switches.
- One obvious control per setting, with short, plain copy. Most photographers read English as a second language after Kinyarwanda.
- Existing galleries must look and behave the same until the photographer changes something.

---

## Left over from creation (§4.1)

- [ ] **Folder drop.** Drop a folder on the dashboard or the Galleries page: this creates a gallery named after the folder and starts the upload.
- [ ] **New gallery like this one**: copy one gallery's settings into a new one (P2).

## Proofing (§4.4)

- [x] Store favourites on the server: one `gallery_favorite_lists` row per gallery and email, and `gallery_favorites` rows for the photos.
- [x] Favourites in the **Activity** tab, grouped by person, with "Copy file names" for Lightroom, a CSV download and Remove.
- [x] **Favourites** section in Settings: on/off and a limit per client.
- [x] "Send my picks" for clients. It emails the photographer (a new switch under Notifications) and shows "Sent" or "changed since" on the tab.
- [ ] Next: "Save to Clients" for the emails in Activity (P1-10). Activity already lists who favourited and downloaded, and shows names from Clients when the email matches.

## Dashboard "Needs you" (§1)

Proofing has shipped, so "new favourites" and "picks sent" are real signals now.

- [x] The date replaces the greeting, and a "Needs you" list replaces the four stat cards. Each row is one action:
  - picks clients sent (`gallery_favorite_lists.submitted_at`, last 30 days). A row leaves the list when the photographer opens those picks in Activity (`seen_at`), and comes back as "changed their favorites" if the client changes them;
  - bookings to confirm;
  - galleries closing within 7 days ("Change date" opens Expiry);
  - invites not opened after 5 days (sent within the last 30);
  - galleries still empty a day after they were made ("Add photos" opens the uploader).
- [x] When the list is empty, it says "Nothing needs you right now."
- [x] "Quick actions" and the Storage card are gone (the sidebar has both). "Client activity" shows the last 7 days, one line per gallery.
- [x] An "Upload" menu sits in the page actions: pick a recent gallery and its uploader opens. "New gallery" stays in the top bar.
- [x] Gallery cards show their stage (Draft, Shared, Viewed, Downloaded, Expired) and their activity ("Viewed 2 hours ago, 48 favorites"). No hover lift or zoom.
- [x] On the Galleries page, the visibility tabs are now All, Drafts, Shared, Expiring soon and Expired (`?stage=`). Visibility stays as a badge.
- [x] The getting-started checklist counts only the studio steps. Booking steps are listed under "Only if you take bookings", and **Hide** removes the checklist for good (`users.setup_checklist_dismissed_at`).

## Templates, gallery defaults and branding (§4.3, §5)

Layouts and defaults are done. **Next: branding.**


- [x] Four client gallery layouts, in a **Layout** card on the Design tab under Cover: Full bleed (today's look), Framed, Split, No cover. Each has a photo grid (Columns, Rows or Squares) and colours (Light, Dark, Same as the phone). They are stored in `galleries.template`, `grid_style` and `color_mode`, and the defaults are today's look. Picking a layout also picks its usual grid. Changes save as they're made and reload the preview, which always shows the gallery's own colours. Clients can still switch between light and dark.
  - The three grids share one script (`resources/js/gallery/grid-layout.js`). The page inlines it to place the first photos before the bundle loads.
  - Framed, Split and No cover set the title on the page, so the toolbar's title only fades in after it scrolls away.
  - Not yet: the portfolio font on gallery titles.
- [x] Gallery defaults in Studio settings (**New galleries**, for owners and managers): who can open them, Download PIN on/off, how long they stay open, photo order, layout and colours. They're stored in `users.gallery_defaults` and read through `App\Support\Gallery\GalleryDefaults`. A new gallery takes them when it's created; the layout brings its usual grid, and a PIN gets its own 4 digits. The expiry is counted from the day the gallery is shared: a draft's Share panel starts with that date filled in. Without defaults, new galleries start exactly as before.
- [ ] **Next:** portfolio logo, brand colour and font on client galleries. On paid plans, the studio's own mark replaces the ifotoset footer.

## Watermark, download sizes, bulk actions (§5, §4.2)

- [ ] **Watermark**: a PNG or text, with position and opacity, applied to previews only. This matters more now that downloads can't be turned off: it's how photographers share proofs before the client pays.
- [ ] **Download size**: web size (2048px) or original. Web size by default saves clients' mobile data.
- [ ] Bulk gallery actions on the Galleries page (extend expiry, change who can open it, trash), and **Archive**.

## Smaller fixes still open (§3, §5)

- [ ] P2-13: delete the unused iOS download notice in `resources/js/gallery/modules/downloads.js`.
- [ ] P2-14: check that the full-gallery download's "empty" check skips hidden photos the way the job does (not verified).
- [ ] P2-15: make the ZIP follow the gallery's photo order instead of `orderBy('id')` (`GenerateGalleryZipJob`), and number the filenames.
- [ ] P2-16: pin `hls.js@latest` and `artplayer` to exact versions in `studio/galleries/show.blade.php`, or bundle them through Vite.
- [ ] Settings from §5 not started yet:
  - a WhatsApp contact button on the client gallery;
  - a welcome message per gallery;
  - "Studio name via ifotoset" as the sender name on emails;
  - more notification choices;
  - timezone under Profile;
  - showing specialties on the portfolio;
  - deciding about the custom domain shown as "Soon" on the pricing page.
