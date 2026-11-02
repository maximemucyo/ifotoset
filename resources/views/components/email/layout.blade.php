@props(['title' => 'ifotoset', 'preheader' => null, 'footerNote' => null])
@php
    $homeUrl = config('app.url');
    $font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $title }}</title>
    <!--[if mso]>
    <xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
    <style>
        table { border-collapse: collapse; }
        body, table, td, p, a, h1, span, div { font-family: 'Segoe UI', Arial, sans-serif !important; }
    </style>
    <![endif]-->
    <style>
        /* Headline face for clients that load web fonts (Apple Mail, iOS); the rest fall back to Helvetica/Arial */
        @font-face {
            font-family: 'Bricolage Grotesque';
            font-style: normal;
            font-weight: 200 800;
            font-stretch: 75% 100%;
            src: url('{{ asset('fonts/bricolage-grotesque-latin.woff2') }}') format('woff2');
        }
        :root { color-scheme: light dark; supported-color-schemes: light dark; }
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; -webkit-font-smoothing: antialiased; }
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; font-size: inherit !important; font-family: inherit !important; font-weight: inherit !important; line-height: inherit !important; }
        .em-h1, .em-banner-title { font-stretch: 88%; }
        @media screen and (max-width: 600px) {
            .em-outer { padding: 20px 12px 32px !important; }
            .mobile-padding { padding: 28px 22px 28px !important; }
            .mobile-full-width { width: 100% !important; }
            .em-h1 { font-size: 26px !important; }
            .em-banner { padding: 24px 22px !important; }
            .em-banner-title { font-size: 28px !important; }
            .em-code { font-size: 30px !important; letter-spacing: 6px !important; }
        }
        @media screen and (max-width: 480px) {
            .em-row-label, .em-row-value { display: block !important; width: 100% !important; padding-right: 0 !important; }
            .em-row-label { padding-bottom: 0 !important; }
            .em-row-value { padding-top: 2px !important; border-top: 0 !important; }
        }
        @media (prefers-color-scheme: dark) {
            .em-bg { background-color: #131316 !important; }
            .em-card { background-color: #1b1b1f !important; border-color: #2f2f36 !important; }
            .em-panel { background-color: #222227 !important; border-color: #2f2f36 !important; }
            .em-divider { border-color: #2f2f36 !important; }
            .em-h { color: #fafaf9 !important; }
            .em-p { color: #d4d4d8 !important; }
            .em-muted { color: #b8b8c1 !important; }
            .em-subtle { color: #8a8a95 !important; }
            .em-accent { color: #ff8a66 !important; }
            .em-banner { background-color: #17323b !important; }
            .em-track { background-color: #2f2f36 !important; }
            .em-fill { background-color: #fafaf9 !important; }
            .em-callout-success { background-color: #0d2316 !important; border-color: #14532d !important; color: #86efac !important; }
            .em-callout-danger { background-color: #2a1011 !important; border-color: #7f1d1d !important; color: #fca5a5 !important; }
            .em-callout-warning { background-color: #271a07 !important; border-color: #78350f !important; color: #fcd34d !important; }
        }
        [data-ogsc] .em-h { color: #fafaf9 !important; }
        [data-ogsc] .em-p { color: #d4d4d8 !important; }
        [data-ogsc] .em-muted { color: #b8b8c1 !important; }
        [data-ogsb] .em-bg { background-color: #131316 !important; }
        [data-ogsb] .em-card { background-color: #1b1b1f !important; }
        [data-ogsb] .em-panel { background-color: #222227 !important; }
    </style>
</head>
<body class="em-bg" style="margin: 0; padding: 0; width: 100%; background-color: #f5f6f3; color: #1c201e; font-family: {{ $font }};">
    @if($preheader)
        {{-- Preheader: inbox preview text, hidden in the email body --}}
        <div style="display: none; font-size: 1px; color: #f5f6f3; line-height: 1px; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; mso-hide: all;">
            {{ $preheader }}
            {!! str_repeat('&#847; &zwnj; &nbsp; ', 40) !!}
        </div>
    @endif

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="em-bg" style="background-color: #f5f6f3;">
        <tr>
            <td align="center" class="em-outer" style="padding: 32px 16px 48px;">
                <!--[if mso]><table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" align="center"><tr><td><![endif]-->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; margin: 0 auto;">
                    {{-- Wordmark, set like the site logo --}}
                    <tr>
                        <td style="padding: 0 4px 20px;">
                            <a href="{{ $homeUrl }}" target="_blank" style="text-decoration: none; display: inline-block;">
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td width="32" height="32" valign="middle" style="width: 32px; height: 32px; line-height: 0; font-size: 0;">
                                            {{-- PNG, not WebP: several email clients cannot show WebP --}}
                                            <img src="{{ asset('images/email/logo.png') }}" width="32" height="32" alt="" style="display: block; width: 32px; height: 32px; border: 0;">
                                        </td>
                                        <td style="padding-left: 9px; font-family: {{ $font }}; font-size: 19px; font-weight: 700; letter-spacing: -0.02em; line-height: 32px;">
                                            <span class="em-h" style="color: #1c201e;">ifoto</span><span class="em-accent" style="color: #cc4019;">set</span>
                                        </td>
                                    </tr>
                                </table>
                            </a>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td class="em-card mobile-padding" bgcolor="#ffffff" style="background-color: #ffffff; border: 1px solid #e2e5df; border-radius: 16px; padding: 40px 40px 36px; font-family: {{ $font }}; color: #1c201e;">
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 24px 4px 0;">
                            <x-email.footer :note="$footerNote" />
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
