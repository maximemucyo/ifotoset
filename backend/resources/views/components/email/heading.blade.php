@props(['title', 'subtitle' => null])

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 24px;">
    <tr>
        <td>
            <h1 style="margin: 0 0 8px; font-size: 22px; font-weight: 700; color: #1c1917; line-height: 1.3;">
                {{ $title }}
            </h1>
            @if($subtitle)
                <p style="margin: 0; font-size: 15px; color: #78716c; line-height: 1.5;">
                    {{ $subtitle }}
                </p>
            @endif
        </td>
    </tr>
</table>
