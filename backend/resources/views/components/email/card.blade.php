@props(['title' => null])
@php($font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif")
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="em-panel" bgcolor="#f7f7f5" style="background-color: #f7f7f5; border: 1px solid #e8e8e5; border-radius: 10px; margin: 24px 0;">
    @if($title)
        <tr>
            <td class="em-muted" style="padding: 16px 20px 10px; font-family: {{ $font }}; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b6b72;">
                {{ $title }}
            </td>
        </tr>
    @endif
    <tr>
        <td style="padding: {{ $title ? '0 20px 6px' : '16px 20px' }}; font-family: {{ $font }};">
            {{ $slot }}
        </td>
    </tr>
</table>
