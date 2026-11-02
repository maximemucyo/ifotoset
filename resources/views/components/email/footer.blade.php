@props(['note' => null])
@php
    $homeUrl = config('app.url');
    $font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
@endphp
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-muted" style="font-family: {{ $font }}; font-size: 13px; line-height: 1.6; color: #5b635e;">
            <p style="margin: 0 0 6px;">
                @if($note)
                    {{ $note }}
                @else
                    You received this email from <a href="{{ $homeUrl }}" target="_blank" class="em-muted" style="color: #5b635e; font-weight: 600; text-decoration: underline;">ifotoset</a>.
                @endif
            </p>
            <p class="em-subtle" style="margin: 0; color: #646c67;">
                &copy; {{ date('Y') }} ifotoset. Client galleries, portfolios and bookings for photographers.
            </p>
        </td>
    </tr>
</table>
