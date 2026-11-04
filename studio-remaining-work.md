# Studio: remaining work

Updated November 3, 2026, on the `studio-improvements` branch. The reasons behind each item are in [studio-dashboard-improvements.md](studio-dashboard-improvements.md); the § numbers below point there.

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

- [ ] Replace the greeting and the four stat cards with a "Needs you" list:
  - bookings to confirm;
  - empty galleries;
  - favourites clients sent (`gallery_favorite_lists.submitted_at`);
  - galleries expiring within 7 days;
  - invites nobody opened.
- [ ] When the list is empty, say "Nothing needs you right now."
- [ ] Remove "Quick actions", which repeats the app menu. Show client activity in that space instead.
- [ ] Put an "Upload" button next to "New gallery".
- [ ] Gallery cards show their stage (Draft, Shared, Viewed, Downloaded, Expired) and their activity ("Viewed 2h ago, 48 favourites").
- [ ] On the Galleries page, replace the visibility tabs with Drafts, Shared, Expiring soon, Expired and All (§4.2).
- [ ] Make booking steps optional in the getting-started checklist, and let photographers hide the checklist for good.

## Templates, gallery defaults and branding (§4.3, §5)

- [ ] Four client gallery templates, on the Design tab under Cover: Full bleed (today's look), Framed, Split, No cover. Each also gets a grid style and a light, dark or automatic colour mode.
- [ ] Gallery defaults in Studio settings, so the photographer chooses once: who can open it, Download PIN on/off, expiry length, photo order and template.
- [ ] Portfolio logo and brand colour on client galleries. On paid plans, the studio's own mark replaces the ifotoset footer.

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
