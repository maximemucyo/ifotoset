<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You are invited to view a gallery</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #fafaf9;
            color: #1c1917;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border: 1px solid #e7e5e4;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        .header {
            background-color: #f5f5f4;
            padding: 32px;
            text-align: center;
            border-bottom: 1px solid #e7e5e4;
        }
        .logo {
            font-size: 24px;
            font-weight: 800;
            color: #dd7a53;
            letter-spacing: -0.025em;
        }
        .content {
            padding: 40px 32px;
            line-height: 1.6;
        }
        h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1c1917;
            margin-top: 0;
            margin-bottom: 16px;
        }
        p {
            font-size: 16px;
            color: #44403c;
            margin-bottom: 24px;
        }
        .gallery-box {
            background-color: #fafaf9;
            border: 1px solid #e7e5e4;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 32px;
            text-align: center;
        }
        .gallery-title {
            font-size: 18px;
            font-weight: 700;
            color: #1c1917;
            margin: 0 0 4px 0;
        }
        .gallery-details {
            font-size: 14px;
            color: #78716c;
            margin: 0;
        }
        .btn-container {
            text-align: center;
            margin-bottom: 32px;
        }
        .btn {
            display: inline-block;
            background-color: #dd7a53;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(221, 122, 83, 0.2), 0 2px 4px -1px rgba(221, 122, 83, 0.1);
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #d16b44;
        }
        .footer {
            background-color: #fafaf9;
            padding: 24px 32px;
            border-top: 1px solid #e7e5e4;
            font-size: 12px;
            color: #78716c;
            text-align: center;
        }
        .footer p {
            font-size: 12px;
            color: #78716c;
            margin: 0 0 8px 0;
        }
        .footer p:last-child {
            margin: 0;
        }
        .footer a {
            color: #dd7a53;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">ifotoset</div>
        </div>
        <div class="content">
            <h1>Hello,</h1>
            <p>You have been invited to view a private photo gallery on ifotoset.</p>
            
            <div class="gallery-box">
                <div class="gallery-title">{{ $gallery->title }}</div>
                <div class="gallery-details">Shared by {{ $photographerName }}</div>
            </div>

            <p>This gallery is private and secured. You can view all photos by clicking the button below. Do not share this link with others as it contains your unique access token.</p>

            <div class="btn-container">
                <a href="{{ $inviteUrl }}" class="btn" target="_blank">View Gallery</a>
            </div>

            @if($gallery->expires_at)
                <p style="font-size: 14px; color: #b45309; background-color: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 12px; margin-top: 24px; text-align: center;">
                    <strong>Notice:</strong> This invitation will expire on {{ $gallery->expires_at->toFormattedDateString() }}.
                </p>
            @endif
        </div>
        <div class="footer">
            <p>This email was sent to you because you were invited to view a private gallery on <a href="https://ifotoset.com">ifotoset</a>.</p>
            <p>&copy; {{ date('Y') }} ifotoset. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
