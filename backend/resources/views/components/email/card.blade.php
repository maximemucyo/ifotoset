@props(['title' => null])
@php($font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif")
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="em-panel" bgcolor="#f7efe9" style="background-color: #f7efe9; border: 1px solid #eee3db; border-radius: 10px; margin: 24px 0;">
    @if($title)
        <tr>
            <td class="em-muted" style="padding: 16px 20px 10px; font-family: {{ $font }}; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6e6159;">
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
