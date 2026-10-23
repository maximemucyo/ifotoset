{{-- Floating WhatsApp click-to-chat button. Vars: $photographer --}}
@if($waUrl = $photographer->portfolio()->whatsappUrl('Hi ' . $photographer->name . ', I found you on your portfolio.'))
    <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp"
       class="fixed bottom-5 right-5 z-40 flex size-13 items-center justify-center rounded-full bg-[#25d366] text-white shadow-lg transition hover:scale-105 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 sm:bottom-6 sm:right-6">
        <x-icon name="whatsapp" class="size-6" />
    </a>
@endif
