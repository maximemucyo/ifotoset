@props(['title', 'subtitle' => null])
@php($display = "'Bricolage Grotesque', 'Helvetica Neue', Helvetica, Arial, sans-serif")
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 0 0 28px;">
    <tr>
        <td>
            <h1 class="em-h em-h1" style="margin: 0; font-family: {{ $display }}; font-size: 30px; font-weight: 700; letter-spacing: -0.025em; color: #1c201e; line-height: 1.1;">
                {{ $title }}
            </h1>
            @if($subtitle)
                <p class="em-muted" style="margin: 10px 0 0; font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 16px; color: #5b635e; line-height: 1.55;">
                    {{ $subtitle }}
                </p>
            @endif
        </td>
    </tr>
</table>
