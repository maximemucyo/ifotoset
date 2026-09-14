@props(['label', 'value'])

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 10px;">
    <tr>
        <td valign="top" style="width: 38%; font-size: 14px; color: #78716c; padding-right: 12px; font-weight: 500;">
            {{ $label }}
        </td>
        <td valign="top" style="width: 62%; font-size: 14px; color: #1c1917; font-weight: 600;">
            {{ $value }}
        </td>
    </tr>
</table>
