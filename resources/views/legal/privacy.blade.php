@extends('legal.layout', [
    'pageTitle' => 'Privacy Policy',
    'pageDescription' => 'What data ifotoset collects, why, and the choices you have.',
    'updated' => 'November 7, 2026',
])

@section('legal')
<section>
    <h2>What we collect</h2>
    <ul>
        <li><strong>Photographers:</strong> name, username, email, password (stored hashed), optional profile details (phone, location, website, bio, avatar), uploaded photos and videos, clients, packages, bookings and billing history.</li>
        <li><strong>Sign in with Google:</strong> if you choose it, Google shares your name, email address and Google account ID with us. We use them only to create and sign in to your account.</li>
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
    <h2>Google Photos</h2>
    <p>If you choose "Save to Google Photos" on a gallery, you sign in with Google and grant ifotoset one permission: to add photos to your Google Photos library.</p>
    <ul>
        <li><strong>What we access:</strong> a temporary access token from Google, and the ID and link of the album we create. We cannot see, list, change or delete anything already in your Google Photos library, and we never receive your Google password.</li>
        <li><strong>How we use it:</strong> only to create an album named after the gallery, upload the photos you selected into it, and show you a link to that album.</li>
        <li><strong>Who we share it with:</strong> no one. The token is sent only to Google. We do not share, sell or transfer Google user data, do not use it for advertising, and do not use it to train AI models.</li>
        <li><strong>How we protect it:</strong> the token is encrypted (AES-256) before it is saved to our database, is only sent over encrypted connections (HTTPS), and is never written to logs.</li>
        <li><strong>How long we keep it:</strong> as soon as the export finishes or fails, we revoke the token with Google and delete it. Tokens from exports that never finish are revoked and deleted within a day. The album ID and link are deleted with the gallery.</li>
        <li>You can also remove ifotoset's access at any time from your Google Account at <a href="https://myaccount.google.com/permissions" rel="noopener">myaccount.google.com/permissions</a>.</li>
    </ul>
    <p>ifotoset's use and transfer of information received from Google APIs will adhere to the <a href="https://developers.google.com/terms/api-services-user-data-policy" rel="noopener">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>
</section>

<section>
    <h2>How we protect your data</h2>
    <ul>
        <li>All traffic to ifotoset uses HTTPS, and browsers are told to never connect without it (HSTS). Sign-in cookies are only sent over HTTPS.</li>
        <li>Passwords are stored hashed, never in plain text.</li>
        <li>Sensitive data, such as Google access tokens, is encrypted with AES-256 before it is stored.</li>
        <li>Original photo files are downloaded through links that expire, and photographers can protect galleries with a password and downloads with a PIN.</li>
        <li>Logins and sensitive actions are rate-limited to block password guessing and abuse.</li>
        <li>Only the ifotoset team can access our servers and database, and only to run and support the service.</li>
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
