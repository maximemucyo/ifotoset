{{-- Booking status badge. Vars: $status (string) --}}
@php
    $map = [
        'pending'   => ['warning', 'Pending'],
        'confirmed' => ['success', 'Confirmed'],
        'completed' => ['info', 'Completed'],
        'cancelled' => ['neutral', 'Cancelled'],
    ];
    [$variant, $label] = $map[$status] ?? ['neutral', ucfirst((string) $status)];
@endphp
<x-ui.badge :variant="$variant" dot>{{ $label }}</x-ui.badge>
