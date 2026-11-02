@props(['label', 'value'])
@php($font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif")
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td valign="top" class="em-muted em-row-label em-divider" style="width: 38%; padding: 12px 12px 12px 0; border-top: 1px solid #e2e5df; font-family: {{ $font }}; font-size: 14px; line-height: 1.5; color: #5b635e;">
            {{ $label }}
        </td>
        <td valign="top" class="em-h em-row-value em-divider" style="width: 62%; padding: 12px 0; border-top: 1px solid #e2e5df; font-family: {{ $font }}; font-size: 15px; line-height: 1.5; color: #1c201e; font-weight: 500; word-break: break-word;">
            {{ $value }}
        </td>
    </tr>
</table>
