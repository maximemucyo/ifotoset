@props(['title', 'subtitle' => null, 'eyebrow' => null])
@php($font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif")
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 0 0 28px;">
    <tr>
        <td style="font-family: {{ $font }};">
            @if($eyebrow)
                <p class="em-accent" style="margin: 0 0 10px; font-size: 12px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: #f0532a; line-height: 1.4;">
                    {{ $eyebrow }}
                </p>
            @endif
            <h1 class="em-h em-h1" style="margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -0.02em; color: #17110e; line-height: 1.3;">
                {{ $title }}
            </h1>
            @if($subtitle)
                <p class="em-muted" style="margin: 8px 0 0; font-size: 15px; color: #6e6159; line-height: 1.55;">
                    {{ $subtitle }}
                </p>
            @endif
        </td>
    </tr>
</table>
