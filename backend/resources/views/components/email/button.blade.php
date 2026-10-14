@props(['url', 'label', 'showFallback' => true, 'align' => 'left'])
@php($font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif")
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 28px 0 24px;">
    <tr>
        <td align="{{ $align }}">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" bgcolor="#111113" class="em-btn" style="border-radius: 8px; background-color: #111113; mso-padding-alt: 12px 22px;">
                        <a href="{{ $url }}" target="_blank" class="em-btn-a" style="display: inline-block; padding: 12px 22px; font-family: {{ $font }}; font-size: 14px; font-weight: 600; line-height: 20px; color: #ffffff; text-decoration: none; border-radius: 8px;">
                            {{ $label }}
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    @if($showFallback)
        <tr>
            <td align="{{ $align }}" style="padding-top: 20px;">
                <p class="em-muted" style="margin: 0; font-family: {{ $font }}; font-size: 12px; color: #6b6b72; line-height: 1.6; word-break: break-all;">
                    Button not working? Paste this link into your browser:<br>
                    <a href="{{ $url }}" target="_blank" class="em-h" style="color: #111113; text-decoration: underline; font-size: 12px;">{{ $url }}</a>
                </p>
            </td>
        </tr>
    @endif
</table>
