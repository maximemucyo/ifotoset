@php
    $features = [
        ['icon' => 'video', 'title' => 'Video delivery', 'body' => 'Upload highlight films next to the photos. They play smoothly on any device.'],
        ['icon' => 'users', 'title' => 'Client records', 'body' => 'Every contact, booking, gallery and payment linked together.'],
        ['icon' => 'package', 'title' => 'Packages', 'body' => 'Publish your services and prices once, then reuse them for every booking.'],
        ['icon' => 'chart-column', 'title' => 'Gallery activity', 'body' => 'See which galleries get viewed, downloaded and shared.'],
        ['icon' => 'globe', 'title' => 'Your own address', 'body' => 'Every studio gets an ifotoset subdomain. Custom domains are coming soon on Professional.'],
        ['icon' => 'zap', 'title' => 'Light on mobile data', 'body' => 'Photos are resized automatically, so galleries load quickly on any connection.'],
    ];
@endphp

<section class="py-16 sm:py-24">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-16 lg:px-8">
        <h2 class="headline max-w-md">Everything else a studio needs.</h2>

        <ul class="grid gap-x-10 border-t border-border sm:grid-cols-2">
            @foreach($features as $feature)
                <li class="flex gap-3.5 border-b border-border py-5">
                    <x-icon :name="$feature['icon']" class="mt-0.5 size-5 shrink-0 text-brand-strong" />
                    <div>
                        <h3 class="text-[17px] font-semibold">{{ $feature['title'] }}</h3>
                        <p class="mt-1 text-[15px] leading-relaxed text-muted-foreground">{{ $feature['body'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
