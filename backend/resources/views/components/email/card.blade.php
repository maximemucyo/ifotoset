@props(['title' => null])

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafaf9; border: 1px solid #e7e5e4; border-radius: 8px; margin: 24px 0;">
    @if($title)
        <tr>
            <td style="padding: 16px 20px 8px; border-bottom: 1px solid #e7e5e4; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #78716c;">
                {{ $title }}
            </td>
        </tr>
    @endif
    <tr>
        <td style="padding: 20px;">
            {{ $slot }}
        </td>
    </tr>
</table>
