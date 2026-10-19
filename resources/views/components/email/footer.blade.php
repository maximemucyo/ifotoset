@props(['note' => null])
@php
    $homeUrl = config('app.url');
    $font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
@endphp
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-muted" style="font-family: {{ $font }}; font-size: 12px; line-height: 1.6; color: #6e6159;">
            <p style="margin: 0 0 6px;">
                @if($note)
                    {{ $note }}
                @else
                    You received this email from <a href="{{ $homeUrl }}" target="_blank" class="em-muted" style="color: #6e6159; font-weight: 600; text-decoration: underline;">ifotoset</a>.
                @endif
            </p>
            <p class="em-subtle" style="margin: 0; color: #857469;">
                &copy; {{ date('Y') }} ifotoset &middot; Photo delivery for photographers
            </p>
        </td>
    </tr>
</table>
