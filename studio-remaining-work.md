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
- the password hint (P1-11).

**Rules for every item**
- Migrations must be additive (nullable or defaulted). The deploy runs them before the code switches.
- One obvious control per setting, with short, plain copy. Most photographers read English as a second language after Kinyarwanda.
- Existing galleries must look and behave the same until the photographer changes something.

---

## Next: sets and search (§4.5, workspace items 4–5)

- [ ] **Sets**, such as "Ceremony / Reception / Portraits".
  - Needs a `gallery_sets` table and a nullable `photos.set_id`.
  - Sets are listed in the side panel, with drag to reorder and an "Add set" button.
  - Photos without a set appear under "All photos".
  - In the bottom bar: "Move to set".
  - The client gallery shows sets as chips.
- [ ] **Search by filename** in the top bar, so the photographer can find "IMG_0412" when a client asks for it.

## Left over from creation (§4.1)

- [ ] **Folder drop.** Drop a folder on the dashboard or the Galleries page: this creates a gallery named after the folder and starts the upload.
- [ ] **New gallery like this one**: copy one gallery's settings into a new one (P2).

## Proofing (§4.4)

- [ ] Store favourites on the server instead of only in the client's browser, in a `gallery_favorites` table keyed by gallery, photo and email.
- [ ] Add a **Favourites** tab in the gallery workspace, grouped by person, with "Copy filenames" for Lightroom and a CSV download.
- [ ] Add a **Favourites** section to Settings (on/off, selection limit). It was left out of the Settings split because there was nothing to set yet.
- [ ] Optional "Submit my selection" for clients. It notifies the photographer and can have a limit ("Choose 30").
- [ ] Add a **People** panel listing the emails clients gave when they favourited or downloaded, with "Save to Clients" (P1-10).

## Dashboard "Needs you" (§1)

This step needs Proofing first, so the list has real signals to show.

- [ ] Replace the greeting and the four stat cards with a "Needs you" list:
  - bookings to confirm;
  - empty galleries;
  - new favourites;
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
