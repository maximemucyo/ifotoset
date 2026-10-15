@props(['label', 'value'])
@php($font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif")
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td valign="top" class="em-muted em-row-label em-divider" style="width: 38%; padding: 11px 12px 11px 0; border-top: 1px solid #eee3db; font-family: {{ $font }}; font-size: 14px; line-height: 1.5; color: #6e6159;">
            {{ $label }}
        </td>
        <td valign="top" class="em-h em-row-value em-divider" style="width: 62%; padding: 11px 0; border-top: 1px solid #eee3db; font-family: {{ $font }}; font-size: 14px; line-height: 1.5; color: #17110e; font-weight: 500; word-break: break-word;">
            {{ $value }}
        </td>
    </tr>
</table>
