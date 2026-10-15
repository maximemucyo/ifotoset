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
        :root { color-scheme: light dark; supported-color-schemes: light dark; }
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; -webkit-font-smoothing: antialiased; }
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; font-size: inherit !important; font-family: inherit !important; font-weight: inherit !important; line-height: inherit !important; }
        @media screen and (max-width: 600px) {
            .em-outer { padding: 24px 12px 32px !important; }
            .mobile-padding { padding: 28px 22px 28px !important; }
            .mobile-full-width { width: 100% !important; }
            .em-h1 { font-size: 20px !important; }
            .em-code { font-size: 28px !important; letter-spacing: 6px !important; }
        }
        @media screen and (max-width: 480px) {
            .em-row-label, .em-row-value { display: block !important; width: 100% !important; padding-right: 0 !important; }
            .em-row-label { padding-bottom: 0 !important; }
            .em-row-value { padding-top: 2px !important; border-top: 0 !important; }
        }
        @media (prefers-color-scheme: dark) {
            .em-bg { background-color: #09090b !important; }
            .em-card { background-color: #111113 !important; border-color: #232327 !important; }
            .em-panel { background-color: #18181b !important; border-color: #232327 !important; }
            .em-divider { border-color: #26262b !important; }
            .em-h { color: #f5f5f4 !important; }
            .em-p { color: #d4d4d8 !important; }
            .em-muted { color: #a1a1aa !important; }
            .em-subtle { color: #71717a !important; }
            .em-accent { color: #ff6a3d !important; }
            .em-btn { background-color: #f5f5f4 !important; }
            .em-btn-a { color: #0c0c0e !important; }
            .em-mark { background-color: #f5f5f4 !important; }
            .em-ring { border-color: #111113 !important; }
            .em-track { background-color: #26262b !important; }
            .em-fill { background-color: #f5f5f4 !important; }
            .em-fill-accent { background-color: #ff6a3d !important; }
            .em-callout-success { background-color: #0d2316 !important; border-color: #14532d !important; color: #86efac !important; }
            .em-callout-danger { background-color: #2a1011 !important; border-color: #7f1d1d !important; color: #fca5a5 !important; }
            .em-callout-warning { background-color: #271a07 !important; border-color: #78350f !important; color: #fcd34d !important; }
        }
        [data-ogsc] .em-h { color: #f5f5f4 !important; }
        [data-ogsc] .em-p { color: #d4d4d8 !important; }
        [data-ogsc] .em-muted { color: #a1a1aa !important; }
        [data-ogsb] .em-bg { background-color: #09090b !important; }
        [data-ogsb] .em-card { background-color: #111113 !important; }
        [data-ogsb] .em-panel { background-color: #18181b !important; }
    </style>
</head>
<body class="em-bg" style="margin: 0; padding: 0; width: 100%; background-color: #fbfbfa; color: #111113; font-family: {{ $font }};">
    @if($preheader)
        {{-- Preheader: inbox preview text, hidden in the email body --}}
        <div style="display: none; font-size: 1px; color: #fbfbfa; line-height: 1px; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; mso-hide: all;">
            {{ $preheader }}
            {!! str_repeat('&#847; &zwnj; &nbsp; ', 40) !!}
        </div>
    @endif

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="em-bg" style="background-color: #fbfbfa;">
        <tr>
            <td align="center" class="em-outer" style="padding: 40px 16px 48px;">
                <!--[if mso]><table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" align="center"><tr><td><![endif]-->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; margin: 0 auto;">
                    {{-- Wordmark --}}
                    <tr>
                        <td style="padding: 0 4px 24px;">
                            <a href="{{ $homeUrl }}" target="_blank" style="text-decoration: none; display: inline-block;">
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td width="28" height="28" align="center" valign="middle" bgcolor="#111113" class="em-mark" style="width: 28px; height: 28px; background-color: #111113; border-radius: 8px; line-height: 0; font-size: 0;">
                                            <div class="em-ring" style="width: 12px; height: 12px; border: 2px solid #ffffff; border-radius: 50%; margin: 0 auto; line-height: 0; font-size: 0;">
                                                <div style="width: 6px; height: 6px; margin: 3px; background-color: #f0532a; border-radius: 50%; line-height: 0; font-size: 0;">&nbsp;</div>
                                            </div>
                                        </td>
                                        <td class="em-h" style="padding-left: 10px; font-family: {{ $font }}; font-size: 17px; font-weight: 600; letter-spacing: -0.02em; color: #111113; line-height: 28px;">
                                            ifotoset
                                        </td>
                                    </tr>
                                </table>
                            </a>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td class="em-card mobile-padding" bgcolor="#ffffff" style="background-color: #ffffff; border: 1px solid #e8e8e5; border-radius: 12px; padding: 40px 40px 36px; font-family: {{ $font }}; color: #111113;">
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
