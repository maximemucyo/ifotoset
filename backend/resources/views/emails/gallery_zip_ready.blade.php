<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Photos are ready for download</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #121212;
            color: #e5e5e5;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #1e1e1e;
            border: 1px solid #2d2d2d;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }
        .header {
            background-color: #181818;
            padding: 24px 32px;
            text-align: center;
            border-bottom: 1px solid #2d2d2d;
        }
        .notification-tag {
            font-size: 11px;
            font-weight: 600;
            color: #737373;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            margin-bottom: 12px;
            display: block;
        }
        .studio-name {
            font-size: 15px;
            font-weight: 700;
            color: #a3a3a3;
            letter-spacing: 0.2em;
            text-transform: uppercase;
        }
        .content {
            padding: 48px 32px;
            text-align: center;
        }
        h1 {
            font-size: 32px;
            font-weight: 300;
            color: #ffffff;
            margin-top: 0;
            margin-bottom: 24px;
            letter-spacing: -0.02em;
        }
        p {
            font-size: 15px;
            color: #a3a3a3;
            line-height: 1.6;
            margin-bottom: 32px;
            text-align: center;
        }
        .btn-container {
            margin-bottom: 40px;
        }
        .btn {
            display: inline-block;
            background-color: #e5e5e5;
            color: #171717 !important;
            text-decoration: none;
            padding: 16px 36px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #d4d4d4;
        }
        .details-text {
            font-size: 13px;
            color: #737373;
            line-height: 1.6;
            max-width: 460px;
            margin: 0 auto 32px auto;
            border-top: 1px solid #2d2d2d;
            padding-top: 24px;
        }
        .warning-text {
            color: #d97706;
            background-color: rgba(217, 119, 6, 0.1);
            border: 1px solid rgba(217, 119, 6, 0.2);
            border-radius: 4px;
            padding: 12px 16px;
            font-size: 13px;
            margin-bottom: 32px;
            text-align: left;
            display: inline-block;
            max-width: 460px;
        }
        .footer {
            background-color: #181818;
            padding: 32px;
            border-top: 1px solid #2d2d2d;
            font-size: 12px;
            color: #737373;
            text-align: center;
            line-height: 1.6;
        }
        .footer a {
            color: #a3a3a3;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="notification-tag">Download Ready Notification</span>
            <span class="studio-name">{{ strtoupper($download->gallery->user->name) }}</span>
        </div>
        <div class="content">
            <h1>Download Ready</h1>
            <p>
                Your photos for <strong>{{ $download->gallery->title }}</strong> are ready for download. Click the button below to download:
            </p>

            @if($download->failed_photos > 0)
                <div class="warning-text">
                    <strong>Note:</strong> {{ $download->failed_photos }} photo(s) could not be included in this archive due to source extraction issues. The remaining {{ $download->processed_photos }} photos were successfully zipped.
                </div>
            @endif

            <div class="btn-container">
                <a href="{{ $downloadUrl }}" class="btn">Download Photos</a>
            </div>

            <p class="details-text">
                You can use this link to download them again at any time during the next 24 hours. After 24 hours, you can visit the gallery to request a new download.
            </p>
        </div>
        <div class="footer">
            <p style="margin: 0 0 8px 0; font-weight: 600; color: #a3a3a3;">{{ $download->gallery->user->name }}</p>
            <p style="margin: 0 0 16px 0;"><a href="{{ app(\App\Services\PublicUrlService::class)->photographerUrl($download->gallery->user->username) }}">{{ str_replace(['http://', 'https://'], '', app(\App\Services\PublicUrlService::class)->photographerUrl($download->gallery->user->username)) }}</a></p>
            <p style="margin: 0; font-size: 11px;">Questions? Reply to this email.</p>
        </div>
    </div>
</body>
</html>
