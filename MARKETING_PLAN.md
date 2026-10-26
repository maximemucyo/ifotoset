# ifotoset Marketing Plan: Rwanda, first 6 months

**Prepared:** 2026-10-22
**Scope:** getting Rwandan photographers onto ifotoset, then getting them to pay
**Budget:** 300,000 RWF maximum in cash (about $205 at roughly 1,460 RWF/USD)
**Constraint:** a solo founder who prefers not to cold-talk to strangers

---

## 0. The short version

1. **Fix what the site promises before spending any money.** The pricing page and FAQ sell features that don't exist yet: custom domains, watermarks, invoices and booking calendars. Mark them "Coming soon" or remove them. One unhappy Kigali photographer in a WhatsApp group will undo a month of marketing.
2. **Sell one thing.** Pitch: *"Stop sending wedding photos on WhatsApp and Google Drive. Send a beautiful private gallery your client can download in full quality, and pay for it with MoMo."* Don't lead with portfolio, booking or the "all-in-one" story.
3. **Your best salesperson is the client gallery.** Every gallery a photographer sends is seen by 10 to 200 people: the couple, their family and the guests. Make the "Powered by ifotoset" mark tasteful and clickable on Free galleries, and growth compounds without you talking to anyone.
4. **Recruit 15 to 20 "Founding Photographers" by text, not by talking.** Use Instagram and WhatsApp DMs. They get Pro free for 6 months in exchange for using ifotoset on real clients and giving honest feedback.
5. **Spend the cash on trusted photographers' voices, not on ads.** Paying 2 or 3 respected local photographers to show ifotoset to their followers beats any amount of Meta ads to a market of this size.
6. **Timing:** it is late October, and the December/January wedding and party season is coming. Photographers onboarded in November will deliver December weddings on ifotoset, and those galleries become your best advertising.

---

## 1. Where we stand today

| Item | Status | Marketing implication |
|---|---|---|
| Users | 28 accounts, 19 unverified (audit, 2026-10-14) | Your first marketing task is fixing activation, not getting more signups. |
| Galleries | Private/PIN, expiry dates, favourites, full ZIP plus single downloads, fast on mobile data | **This is the hero product.** Lead with it. |
| Save to Google Photos | Built, behind a feature flag | Great local hook ("your client's photos go straight into their phone"). Turn it on before promoting it. |
| Video hosting | Basic and above | Matters for wedding photo-plus-video teams. |
| Portfolio | One fixed template at `username.ifotoset.com` | Fine as a "free bonus", too thin to lead with. |
| Booking | Basic request form plus packages | Mention it, don't sell it. |
| MoMo/Airtel subscription billing in RWF | Live via PawaPay | **A real differentiator.** Pixieset and Pic-Time need a card in USD; you don't. |
| Custom domains, watermarks, invoices, calendars | **Not built**, yet advertised | Remove the claims or label them "Coming soon" (see `PORTFOLIO_DOMAIN_BOOKING_ASSESSMENT.md`, Phase 0). |
| Referral codes | Not built | Run referrals manually at first (section 6.4), and build them once proven. |
| Language | English only | Write marketing content in Kinyarwanda and English. The app can stay English for now. |
| Analytics | GA4 tracks `sign_up`, `create_gallery`, `begin_checkout`, `purchase` | Enough to measure the funnel. Add UTM links to everything (section 9). |

### Pricing in context

Basic costs 10,999 RWF a month and Pro 29,999 RWF a month. One Kigali wedding studio publicly prices its wedding packages at **2.5M to 3.5M RWF**. So **a year of Pro (about 300k RWF) is about 10% of the price of one wedding**, and Basic is about 4%. Use this framing in every pitch: *"One wedding pays for a full year."*

---

## 2. Who we sell to (in this order)

Rwanda is a small, tightly connected market. Instagram has about 600k ad-reachable users in Rwanda and Facebook about 1.77M (NapoleonCat, September 2025), and the working photographer community is probably in the low thousands. Everyone knows everyone. That is a weakness for ads and a big strength for word of mouth.

| # | Segment | Why they need ifotoset | Where to find them |
|---|---|---|---|
| 1 | **Wedding and ceremony photographers** (gusaba/introduction, civil, church, reception) | 500 to 2,000 photos per event, several ceremonies, families who all want copies. WhatsApp destroys quality, and Drive links expire or hit quotas. | Instagram (#kigaliweddings, #rwandanwedding, #gusaba), WhatsApp vendor groups, wedding planners |
| 2 | **Event and corporate photographers** | Kigali runs a busy conference calendar (KCC, hotels). Corporate clients want a branded, professional link, not a Drive folder. | LinkedIn, event agencies, Instagram |
| 3 | **Graduation and portrait studios** | High volume, many small clients, and each client needs their own private gallery. | University areas, studio storefronts, Instagram |
| 4 | **Photo-plus-video teams / content creators** | Video hosting plus photos in one place. | TikTok, Instagram |
| 5 | **Photography students and hobbyists** | They won't pay now, but they become paying pros later and spread the word. | Kigali Centre for Photography, university clubs, camera shop workshops |

**The ideal first customer:** a Kigali wedding photographer with 2k to 20k Instagram followers who shoots 2 to 6 events a month and currently delivers on Google Drive or WeTransfer.

---

## 3. Positioning and message

**One line:** *ifotoset: private online galleries for Rwandan photographers. Full quality, fast on mobile, paid with MoMo.*

**The pain you name:**
- "Your client's wedding photos lose quality the moment they're forwarded on WhatsApp."
- "Google Drive links look unprofessional, and clients ask 'how do I download all of them?'"
- "WeTransfer links expire after a few days, and then the aunt in Musanze calls you."

**The proof you show (always a screen recording, never just words):**
1. Open a gallery on a phone. It loads instantly with blur placeholders.
2. The client hearts favourites.
3. One tap downloads everything as a ZIP, or saves it to Google Photos.
4. You paid for it with MoMo in 30 seconds.

**Language:** captions in Kinyarwanda first, with English second. For example: *"Ntukongere kohereza amafoto y'ubukwe kuri WhatsApp…"* Have a Rwandan photographer friend check each Kinyarwanda caption before you post it. Local language signals "built here, for us", which no foreign competitor can copy.

**What NOT to say:** "all-in-one", "studio that runs itself", custom domains, invoices. Not until they're real.

---

## 4. Do this before any spending (weeks 1–2, 0 RWF)

| Task | Why |
|---|---|
| Fix the copy on the pricing page and FAQ (remove or "Coming soon" for custom domains, watermarks, invoices and calendars) | Trust is everything in a small market. |
| Turn on Save to Google Photos in production if it is stable | It is your most "wow" local feature. |
| Make the Free-plan gallery footer a tasteful "Delivered with ifotoset · Get your own gallery" link with `?utm_source=gallery_footer` | Client-led growth, explained in section 6.1. |
| Email (and if possible WhatsApp) the 19 unverified users with a short "Here's how to send your first gallery" message plus a 60-second video | It is the cheapest activation you will ever get. |
| Build a **demo gallery** with good, licensed photos (ask a photographer friend for one wedding set) | You'll link it in every DM, ad and post. |
| Record 3 screen-capture videos (no face needed): "Send your first gallery in 2 minutes", "What your client sees", "Pay with MoMo" | They are reusable everywhere. |
| Add a "How did you hear about us?" dropdown at signup, plus an optional "Referred by (username)" field | This is how you measure channels and run referrals without building a full referral system. |

---

## 5. Introvert-friendly ground rules

You don't need to cold-call or pitch on stage. This plan is designed around:

- **Async text over live talk.** DMs, WhatsApp and comments. You can draft, edit and send when ready.
- **Let others do the talking.** Founding Photographers and paid creator posts speak for you.
- **Show, don't sell.** Screen-recorded demos and faceless content.
- **Give first.** Free Pro access, free help migrating an old gallery, free useful content. Giving feels far less awkward than asking.
- **Batch the social stuff.** One "people day" a week (section 10). The rest of the week is building and content.
- **Bring a co-host to events.** When you do attend something, co-host with a photographer or camera shop so they handle the room.

---

## 6. Channels and tactics

### 6.1 Client-led growth through the gallery itself (0 RWF, highest priority)

Pic-Time openly calls its strategy "photography-led growth": the photographer's delivered work is the marketing.

- Every Free gallery shows a small, elegant "Delivered with ifotoset" link. Paid plans can hide it, which gives people a reason to upgrade.
- Add a soft "Are you a photographer? Deliver your galleries like this" link on gallery pages, tracked with UTM.
- **Why this matters in Rwanda:** one wedding gallery gets opened by the couple, both families, the bridal party and often 100+ guests. Some of those guests are photographers, or know one.

### 6.2 Founding Photographers program (0 RWF cash; your main engine for weeks 2–8)

**Offer:** Pro free for 6 months, a "Founding Photographer" badge, direct WhatsApp access to you, and a say in the roadmap.
**Ask:** deliver at least 2 real client galleries on ifotoset, give 15 minutes of feedback by voice note or text, and (optionally) a testimonial and one story or post.

**How to find 40 candidates without talking to strangers:**
1. Start with the warm circle: photographers you, your friends or family already know, and anyone who shot an event you attended. Ask for intros by text: *"Do you know a wedding photographer who'd test something for free?"*
2. Search Instagram for #kigaliwedding, #rwandaweddings, #gusaba, #kigaliphotographer and #rwandanphotographer. Shortlist people with 1k to 20k followers who post consistently.
3. Check Google Maps for "photo studio Kigali" (many listings include a WhatsApp number).
4. Look at the people who tag or follow Kigali Centre for Photography and Cameras Africa Rwanda.

**DM template (English; get the Kinyarwanda version checked by a native speaker):**
> Hi [name], I love your [specific shoot] work. I'm a Rwandan developer and I built ifotoset, a way to deliver client photos in a private gallery (full quality, one-tap download, MoMo payment) instead of WhatsApp or Drive.
> I'm picking 20 Kigali photographers as Founding Photographers: Pro free for 6 months, I set it up for you, you just tell me what's annoying.
> Here's what your client would see: [demo gallery link]. Interested? No pressure at all 🙏

Send 10 a day, Monday to Thursday. Expect roughly a 20 to 30% reply rate, which gives 15 to 20 founders from 60 to 80 DMs.

**Concierge onboarding (text-based):** offer to upload one of their old galleries for them, so their first experience is "wow, that's my work looking great". Doing a few things that don't scale is the classic early-SaaS move, and in a market of this size you can afford it.

**Watch the cost:** Pro includes 1 TB. Twenty founders are unlikely to fill that, but check B2 storage weekly in the admin panel.

### 6.3 Paid creator partnerships (≈100,000 RWF)

Pay **2 or 3 respected Rwandan photographers** (5k to 50k engaged followers, ideally wedding shooters) about 30,000 to 50,000 RWF each to:
- use ifotoset on a real client delivery
- post one Reel or TikTok showing the client gallery, plus 2 or 3 stories with a link and code
- say it in their own words, in Kinyarwanda

Pic-Time's own advice is that recommendations work because they're genuine, so only pay people who actually liked it during the Founding phase. Ideally these are founders who turned out to be enthusiastic.

Give each one a unique "Referred by" username to track signups.

### 6.4 Manual referral program (0 RWF cash, about 15–30k RWF in revenue given up)

- *"Refer a photographer: when they pay for their first month, you both get 1 month free."*
- Track it with the "Referred by" signup field and apply the free month in the admin panel.
- If more than 20% of signups come through referrals by month 3, build a proper referral-code feature (Pic-Time and Pixieset both run tiered referral programs).

### 6.5 Content: Instagram, TikTok, WhatsApp Status (0 RWF; 3 posts a week)

Faceless formats you can produce alone:

| Format | Example |
|---|---|
| Before/after | The same photo sent on WhatsApp and on ifotoset, zoomed in. The quality loss is obvious and very shareable. |
| "What your client sees" | A phone screen recording of a real (permitted) gallery. |
| Business tips for photographers | "How to price gusaba vs a full wedding", "3 things clients complain about after delivery". Carousel posts. |
| Founder build-in-public | "Built in Kigali" short posts about new features. Rwandans like supporting local tech. |
| Founding Photographer spotlights | Repost their work with credit. They share it, so you reach their audience. |

Cross-post to WhatsApp Status and a **WhatsApp Channel** ("ifotoset for photographers"). WhatsApp is the default communication tool in the region, and some ad-tool estimates report 95 to 97% WhatsApp use among mobile internet users in comparable markets (Kenya, Nigeria).

### 6.6 Meta (Instagram/Facebook) ads (≈60,000 RWF, only after you have proof content)

- No Rwanda-specific CPM data is public. The closest regional proxy (Nigeria) shows a **$1.50 average CPM**, and Reels are cheaper than Feed. 60k RWF (about $41) should buy roughly 15k to 30k targeted impressions.
- Target Rwanda, ages 20 to 40, with interests in photography, Canon, Nikon, Sony, Adobe Lightroom and wedding photography. Use Reels and Stories placements.
- Creative: the best-performing organic post from 6.5 (use whatever already works), with captions in Kinyarwanda.
- Run 2 creatives × 7 days × about 4,000 RWF a day, then kill the loser. Optimise for link clicks to the signup page with UTM.
- **Stop rule:** if cost per *activated* signup (first gallery created) is above about 5,000 RWF, stop ads and move the remaining money to creators or events.

### 6.7 Partnerships with places photographers already go (≈30,000 RWF)

| Partner | Ask | Your cost |
|---|---|---|
| **Cameras Africa Rwanda** (camera retailer; runs workshops such as the Mount Kigali University one in June 2026) and other Kigali camera, lens and rental shops | Put a QR card at the counter, and give a free month of Basic to anyone who buys or rents gear | Printed cards (about 15k RWF for 500) |
| **Kigali Centre for Photography** (Jacques Nkinzingabo; hosts Kigali Visual Encounters, which ran Canon Academy workshops in October 2025) | Free Pro accounts for their students and alumni, and a short "deliver your work professionally" segment at a workshop that *they* run | 0 RWF, or co-fund refreshments |
| **Wedding planners and venues** | Planners recommend photographers. A planner who says "my photographers use ifotoset" is gold. Approach them via Instagram DM with the demo gallery. | 0 RWF |
| **Universities with media and photography programs** | A free Basic year for photography clubs | 0 RWF |

These are mostly **text-first asks** (DM or email), and the partner does the in-person part.

### 6.8 One small event, co-hosted (≈60,000 RWF, around month 3)

A **"Photo Walk + Delivery Night"**: a Saturday photo walk in Kigali (Nyamirambo or Kimihurura), then the walk photos are delivered on ifotoset galleries that evening, with drinks.
- Co-host with a Founding Photographer or Cameras Africa. They lead the walk and you handle the tech side.
- Run a small contest for the best shot, with 3 months of Pro as the prize (costs you nothing).
- Budget: venue and refreshments for 20 to 30 people, about 60k RWF.
- Watch for the next Kigali Visual Encounters (it ran in late October in 2025). Ask about being a small supporter or sponsor in-kind with free Pro accounts for participants, rather than speaking.

### 6.9 Search and directory (0 RWF; start the work now, it pays off from month 4)

- Write 4 to 6 helpful articles on ifotoset.com targeting what Rwandan photographers and clients search: "How to send wedding photos without losing quality", "Best way to deliver photos to clients in Rwanda", "Google Drive vs online gallery for photographers".
- **Bigger idea (needs dev work):** a public **"Find a photographer in Rwanda"** directory built from ifotoset portfolios. Clients search for "wedding photographer Kigali" and photographers sign up to be listed. It is a two-sided magnet and a strong reason to join even on Free. Schedule it after the booking fixes (Assessment Phase 1).

---

## 7. Timeline

| When | Focus | Key actions |
|---|---|---|
| **Weeks 1–2** (late Oct–early Nov) | Foundation | Section 4 checklist, demo gallery, 3 videos, fix copy, re-activate existing users |
| **Weeks 3–6** (November) | Founding Photographers | 60 to 80 DMs, onboard 15 to 20 founders *before December weddings*, 3 posts a week |
| **Weeks 7–10** (December) | Live season | Founders deliver real December galleries, collect screenshots and testimonials, start the manual referral program |
| **Weeks 11–14** (January) | Amplify | 2 or 3 paid creator posts, camera shop QR cards, first Meta ads test |
| **Weeks 15–20** (Feb–March) | Convert | Founders' free period ends in month 6. Send a "Founder price" offer (for example 30% off a yearly plan, locked for life) from around month 5. Co-hosted photo walk. |
| **April** | Respectful pause | **Kwibuka** begins April 7. Pause promotional posts and ads for at least the first commemoration week and keep tone sensitive throughout the 100 days. |
| **Month 6 review** | Decide | Double down on the 1 or 2 channels with the best cost per paying customer |

*Season note:* local photographers report that wedding demand peaks around the dry seasons (roughly June–September and December–January). Verify this with your founders, and time your biggest pushes about one month before each peak.

---

## 8. Budget (≤ 300,000 RWF)

| Line | Amount (RWF) | When |
|---|---|---|
| Paid creator partnerships (2–3 photographers) | 100,000 | January |
| Meta ads tests (only with proven creative) | 60,000 | January–February |
| Co-hosted photo walk / delivery night | 60,000 | Around March |
| Printed QR cards for camera shops, studios and events | 30,000 | December |
| **Reserve** (double down on whatever works) | 50,000 | Month 4+ |
| **Total** | **300,000** | |

**Non-cash costs to watch:**
- B2 storage for free Pro founders
- Free months given through referrals
- Your time: about 8 to 10 hours a week on marketing

**Spend rule:** don't release a budget line until the previous stage hits its target (section 9). Unspent money is fine.

---

## 9. Metrics

Use GA4 events that already exist, plus UTM tags on every link (`utm_source=instagram|tiktok|whatsapp|creator_<name>|qr_<shop>|gallery_footer`).

| Funnel step | GA4 event | Month 3 target | Month 6 target |
|---|---|---|---|
| Signups | `sign_up` | 120 | 300 |
| Activated (first gallery created) | `create_gallery` | 50% of signups | 60% |
| Gallery shared with a client | *(add an event)* | 40 photographers | 120 |
| Checkout started | `begin_checkout` | 20 | 60 |
| **Paying photographers** | `purchase` | **10** | **40** |
| Monthly recurring revenue | | about 150k RWF | about 600k RWF |

Treat these targets as **guesses to replace with real data**: look at them weekly and reset them at month 3.

**One number that matters most:** *the number of client galleries delivered per week.* Every one is both retention and free advertising.

Also consider adding a `share_gallery` / `gallery_viewed_by_client` GA4 event. That lets you measure client-led growth (section 6.1).

---

## 10. Your weekly routine (about 8–10 hours)

| Day | Time | Task |
|---|---|---|
| Monday | 1 h | Look at the numbers, plan 3 posts, batch-write DMs |
| Tuesday–Thursday | 30 min a day | Send about 10 DMs a day and reply to everything |
| Wednesday | 2 h | Create and record content for the week (faceless, screen recordings) |
| Friday | 1.5 h | "People day": founder check-ins by WhatsApp voice note or text, ask for intros and testimonials |
| Any day | 1 h | Ship one small fix founders asked for, then tell them it's done (this is the best loyalty tool there is) |

---

## 11. Risks and how to handle them

| Risk | Mitigation |
|---|---|
| Overpromising (custom domains and so on) damages trust | Fix the copy first (section 4). Only market what works today. |
| Photographers say "Drive is free" | Use the quality before/after content, the "one wedding pays for a year" framing, and the fact that clients see a professional brand. |
| Free founders never convert | Set the 6-month end date clearly at signup, offer locked-in founder pricing, and keep the Free plan's 2 GB limit real. |
| Storage costs from free Pro accounts | Watch B2 weekly and cap founders at 20. |
| A key creator posts something off-brand | Brief them, but let them speak in their own voice. Only pay people who genuinely liked the product. |
| Security issues found by early users | Close the open audit items (CDN worker token check, key rotation) before scaling marketing. A leak of private wedding photos would be fatal. |
| Burnout as a solo founder | Stick to the weekly routine, batch everything, and say no to channels outside this plan for 6 months. |

---

## 12. Sources

- DataReportal, Digital 2025 and Digital 2026: Rwanda (about 1.3M social media user identities): https://datareportal.com/reports/digital-2025-rwanda · https://datareportal.com/reports/digital-2026-rwanda
- NapoleonCat, Rwanda social media and Facebook/Instagram audience estimates (September 2025): https://napoleoncat.com/stats/social-media-users-in-rwanda/2025/
- Kigali Centre for Photography (East African): https://www.theeastafrican.co.ke/tea/magazine/rwanda-s-first-photography-centre-now-open--1378904
- Canon Academy at Kigali Visual Encounters 2025: https://techeconomy.ng/canon-academy-kigali-visual-encounters-2025
- Mount Kigali University × Cameras Africa workshop (June 2026): https://mkur.ac.rw/event/mount-kigali-university-to-host-hands-on-photography-workshop-in-partnership-with-cameras-africa/
- Kigali wedding package pricing example (2.5M–3.5M RWF): https://weddingsbykublack.mypixieset.com/pricing/
- East African Wedding Show at KCC (past event; shows a vendor network exists): https://www.newtimes.co.rw/article/163760/Entertainment/debut-wedding-show-to-take-place-this-weekend
- Pic-Time affiliate and referral guidance: https://www.pic-time.com/resources/affiliate-program-for-photographers · https://blog.pic-time.com/insights/sharing-your-referral-code-a-guide-for-photographers/
- Pic-Time "photography-led growth" (November 2025): https://www.barchart.com/story/news/36204718/pic-time-reinvents-the-wedding-gallery-with-upgraded-ai-driven-face-object-and-narrative-search
- Pixieset growth (600k+ users): https://www.privateequitywire.co.uk/pixieset-secures-growth-investment-susquehanna-growth-equity/
- WhatsApp-first business culture in Africa: https://localazy.com/blog/how-africas-whatsapp-first-culture-is-shaping-software-localization-and-digital-business
- Meta ads cost proxy (Nigeria, about $1.50 CPM): https://grey.co/blog/facebook-ads-cost-nigeria
- Comparable emerging-market photo-delivery tools (India; WhatsApp-native delivery positioning): https://fotoowl.ai/blogs/foto-owl-vs-kwikpic-alternative
- WhatsApp recompression and WeTransfer limits (vendor blogs, directional only): https://fotoowl.ai/blogs/how-to-deliver-photos-to-clients-without-compromising-quality · https://blog.arcadina.com/en/are-you-still-delivering-photos-to-clients-via-wetransfer-4-reasons-why-you-may-be-scaring-your-clients-away/

*Research gaps:* there is no public data on the number of working photographers in Rwanda, Rwanda-specific ad costs, or TikTok/WhatsApp usage. The wedding-season timing is based on local convention and needs checking with founders. Treat the related numbers above as estimates.
