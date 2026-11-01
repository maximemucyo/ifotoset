@props(['label' => 'Studio URL', 'value' => '', 'original' => null, 'autofocus' => false])
@php
    $rootHost = config('app.public_root_host', 'ifotoset.com');
    $error = $errors->first('username');
@endphp

{{-- The studio username field, checked against taken and reserved addresses while the person types. --}}
<div x-data="studioUsername({ initial: @js($value), original: @js($original), url: @js(route('username.availability')) })">
    <label for="username" class="label">{{ $label }}</label>
    <div class="flex h-10 items-stretch overflow-hidden rounded-lg border bg-input text-sm shadow-xs transition focus-within:ring-4"
         :class="status === 'unavailable' || (!edited && @js((bool) $error)) ? 'border-destructive focus-within:border-destructive focus-within:ring-destructive/15' : 'border-border focus-within:border-ring focus-within:ring-ring/15'">
        <input type="text" name="username" id="username" required @if($autofocus) autofocus @endif
               autocomplete="off" autocapitalize="off" spellcheck="false" data-1p-ignore data-lpignore="true" data-bwignore placeholder="yourstudio"
               x-model="username" @input="onInput($event)"
               :aria-invalid="(status === 'unavailable').toString()" aria-describedby="username-status"
               class="min-w-0 flex-1 bg-transparent px-3 text-foreground outline-none placeholder:text-subtle">
        <span class="flex w-8 shrink-0 items-center justify-center" aria-hidden="true">
            <x-icon name="loader-circle" class="size-4 animate-spin text-muted-foreground" x-show="status === 'checking'" x-cloak />
            <x-icon name="circle-check" class="size-4 text-success" x-show="status === 'available'" x-cloak />
            <x-icon name="circle-x" class="size-4 text-destructive" x-show="status === 'unavailable'" x-cloak />
        </span>
        <span class="flex items-center border-l border-border bg-muted px-3 text-[13px] text-muted-foreground">.{{ $rootHost }}</span>
    </div>

    <div id="username-status" aria-live="polite">
        @if($error)
            <p class="error-text" x-show="!edited && status !== 'unavailable'">{{ $error }}</p>
        @endif
        <p class="error-text" x-show="status === 'unavailable'" x-cloak>
            <span x-text="message"></span>
            <template x-if="suggestion">
                <span>Try <button type="button" class="font-semibold underline underline-offset-2 hover:no-underline" @click="useSuggestion()" x-text="suggestion"></button>.</span>
            </template>
        </p>
        <div x-show="status !== 'unavailable' && (edited || @js(!$error))" @if($error) x-cloak @endif>
            @if($original !== null)
                <p class="help" x-show="username === original">This is the link clients use to find your portfolio and galleries.</p>
                <p class="help !text-warning" x-show="username !== original" x-cloak>
                    Changing your address updates every portfolio and gallery link. Links you've already shared will stop working.
                </p>
            @else
                <p class="help">
                    <span class="font-medium text-success" x-show="status === 'available'" x-cloak>Available.</span>
                    Your portfolio: <span class="font-medium text-foreground" x-text="(username || 'yourstudio') + '.{{ $rootHost }}'"></span>
                </p>
            @endif
        </div>
    </div>
</div>
