<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your gallery ZIP download is ready</title>
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
        .warning-text {
            color: #b45309;
            background-color: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 6px;
            padding: 12px;
            font-size: 14px;
            margin-bottom: 24px;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="logo">ifotoset</span>
        </div>
        <div class="content">
            <h1>Your Photos are Packed!</h1>
            <p>
                Great news! We have finished packing the photos from gallery <strong>{{ $download->gallery->title }}</strong> into a ZIP archive.
            </p>

            <div class="gallery-box">
                <p class="gallery-title">{{ $download->gallery->title }}</p>
                <p class="gallery-details">
                    Total: {{ $download->processed_photos + $download->failed_photos }} photos |
                    Size: {{ number_format($download->size / 1024 / 1024, 2) }} MB
                </p>
            </div>

            @if($download->failed_photos > 0)
                <div class="warning-text">
                    <strong>Note:</strong> {{ $download->failed_photos }} photo(s) could not be included in this archive due to source extraction issues. The remaining {{ $download->processed_photos }} photos were successfully zipped.
                </div>
            @endif

            <div class="btn-container">
                <a href="{{ $downloadUrl }}" class="btn">Download ZIP Archive</a>
            </div>

            <p>If the button above does not work, you can copy and paste this link into your browser:</p>
            <p style="word-break: break-all; font-size: 14px; color: #78716c;">{{ $downloadUrl }}</p>
        </div>
        <div class="footer">
            <p>This email was sent to you because you requested a full gallery download on <a href="https://ifotoset.com">ifotoset</a>.</p>
            <p>&copy; {{ date('Y') }} ifotoset. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
