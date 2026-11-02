@props(['title' => null])
@php($font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif")
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="em-panel" bgcolor="#f2f4f0" style="background-color: #f2f4f0; border: 1px solid #e2e5df; border-radius: 12px; margin: 24px 0;">
    @if($title)
        <tr>
            <td class="em-h" style="padding: 18px 20px 10px; font-family: {{ $font }}; font-size: 15px; font-weight: 600; color: #1c201e;">
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
