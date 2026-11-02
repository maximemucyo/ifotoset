@props(['url', 'label', 'showFallback' => true, 'align' => 'left'])
@php($font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif")
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 28px 0 24px;">
    <tr>
        <td align="{{ $align }}">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    {{-- Same orange as the site's main buttons; white text on it is 4.9:1 in light and dark --}}
                    <td align="center" bgcolor="#cc4019" style="border-radius: 10px; background-color: #cc4019; mso-padding-alt: 14px 26px;">
                        <a href="{{ $url }}" target="_blank" class="em-btn-a" style="display: inline-block; padding: 14px 26px; font-family: {{ $font }}; font-size: 16px; font-weight: 600; line-height: 20px; color: #ffffff; text-align: center; text-decoration: none; border-radius: 10px;">
                            {{ $label }}
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    @if($showFallback)
        <tr>
            <td align="{{ $align }}" style="padding-top: 18px;">
                <p class="em-muted" style="margin: 0; font-family: {{ $font }}; font-size: 13px; color: #5b635e; line-height: 1.6;">
                    If the button doesn't work, paste this link into your browser:<br>
                    <a href="{{ $url }}" target="_blank" class="em-h" style="color: #1c201e; text-decoration: underline; font-size: 13px; word-break: break-all;">{{ $url }}</a>
                </p>
            </td>
        </tr>
    @endif
</table>
