<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <title>{{ $title ?? 'ifotoset' }}</title>
    <!--[if mso]>
    <xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
    <style>
        table {border-collapse: collapse;}
        .mso-btn {padding: 12px 24px !important;}
    </style>
    <![endif]-->
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #fafaf9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        a { color: #dd7a53; text-decoration: none; }
        @media screen and (max-width: 600px) {
            .mobile-padding { padding-left: 20px !important; padding-right: 20px !important; }
            .mobile-full-width { width: 100% !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #fafaf9; color: #1c1917;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafaf9;">
        <tr>
            <td align="center" style="padding: 36px 16px 48px;">
                <!-- Main Card Container -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 580px; background-color: #ffffff; border: 1px solid #e7e5e4; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding: 32px 24px; background-color: #fafaf9; border-bottom: 1px solid #e7e5e4;">
                            <a href="{{ config('app.frontend_url', config('app.url')) }}" target="_blank" style="text-decoration: none; display: inline-block;">
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="font-size: 26px; font-weight: 800; color: #dd7a53; letter-spacing: -0.03em; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                                            ifotoset
                                        </td>
                                    </tr>
                                </table>
                            </a>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="mobile-padding" style="padding: 36px 40px 32px;">
                            {{ $slot }}
                        </td>
                    </tr>

                    <!-- Footer Component -->
                    <tr>
                        <td>
                            <x-email.footer />
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
