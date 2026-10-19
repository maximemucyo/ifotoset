@extends('legal.layout', [
    'pageTitle' => 'Privacy Policy',
    'pageDescription' => 'What data ifotoset collects, why, and the choices you have.',
    'updated' => 'October 15, 2026',
])

@section('legal')
<section>
    <h2>What we collect</h2>
    <ul>
        <li><strong>Photographers:</strong> name, username, email, password (stored hashed), optional profile details (phone, location, website, bio, avatar), uploaded photos and videos, clients, packages, bookings and billing history.</li>
        <li><strong>Gallery visitors and clients:</strong> the email address you give to download, favorite or export photos; booking details you submit; and basic technical data (IP address, browser, pages viewed) used for security and gallery statistics.</li>
    </ul>
</section>

<section>
    <h2>Why we use it</h2>
    <ul>
        <li>To run the service: store and deliver galleries, send the emails you ask for (downloads ready, booking updates, receipts), and process payments.</li>
        <li>To keep the service secure: prevent abuse, rate-limit logins and detect fraud.</li>
        <li>To show photographers how their galleries are used (views, downloads, favorites).</li>
    </ul>
    <p>We do not sell personal data.</p>
</section>

<section>
    <h2>Who processes it for us</h2>
    <ul>
        <li>Backblaze (file storage) and Cloudflare (content delivery and security)</li>
        <li>Resend (transactional email)</li>
        <li>PawaPay (mobile money payments)</li>
        <li>Google Analytics (aggregate site usage on our marketing pages)</li>
        <li>Google Photos, only when a visitor chooses to export photos to their own Google account</li>
    </ul>
</section>

<section>
    <h2>How long we keep it</h2>
    <ul>
        <li>Account data is kept while your account is open. When you delete your account, content and personal details are erased after 30 days.</li>
        <li>Deleted galleries and photos stay in the trash for a short period so you can restore them, then are permanently removed.</li>
        <li>Payment records are kept as long as accounting law requires.</li>
    </ul>
</section>

<section>
    <h2>Your choices</h2>
    <ul>
        <li>Download a copy of your data from Studio → Settings → Security.</li>
        <li>Change which emails you receive in Studio → Settings → Notifications.</li>
        <li>Delete your account from Studio → Settings → Security, or ask us to.</li>
        <li>Gallery visitors can ask the photographer, or us, to remove their email address.</li>
    </ul>
</section>

<section>
    <h2>Cookies</h2>
    <p>We use a session cookie to keep you signed in and to remember gallery access, and Google Analytics cookies on marketing pages. Photo galleries do not load analytics.</p>
</section>
@endsection
