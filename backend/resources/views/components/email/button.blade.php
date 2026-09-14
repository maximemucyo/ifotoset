@props(['url', 'label', 'showFallback' => true])

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 32px 0 24px;">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius: 8px; background-color: #dd7a53;">
                        <a href="{{ $url }}" target="_blank" style="font-size: 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-weight: 600; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; border: 1px solid #dd7a53; display: inline-block; box-shadow: 0 4px 6px -1px rgba(221, 122, 83, 0.25);">
                            {{ $label }}
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    @if($showFallback)
        <tr>
            <td align="center" style="padding-top: 16px;">
                <p style="margin: 0; font-size: 12px; color: #a8a29e; line-height: 1.5; word-break: break-all;">
                    Or copy and paste this link in your browser:<br>
                    <a href="{{ $url }}" target="_blank" style="color: #dd7a53; text-decoration: underline; font-size: 12px;">{{ $url }}</a>
                </p>
            </td>
        </tr>
    @endif
</table>
