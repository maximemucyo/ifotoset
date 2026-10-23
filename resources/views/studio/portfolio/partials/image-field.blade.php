{{-- Upload / replace / remove one portfolio image.
     Vars: $kind, $title, $hint, $current, $previewClass, $boxClass, $maxMb, $endpoints (requestUrl, confirmUrl, removeUrl) --}}
<div class="p-5" x-data="portfolioImage(@js(array_merge($endpoints, ['current' => $current, 'maxMb' => $maxMb, 'title' => $title])))">
    <p class="text-sm font-medium text-foreground">{{ $title }}</p>
    <p class="mt-0.5 text-[13px] text-muted-foreground">{{ $hint }}</p>

    <div class="relative mt-3 {{ $boxClass }}">
        <template x-if="url"><img :src="url" alt="{{ $title }}" class="{{ $previewClass }}"></template>
        <div x-show="!url" class="flex size-full items-center justify-center text-subtle"><x-icon name="image" class="size-6" /></div>
        <div x-show="uploading" x-cloak class="absolute inset-0 flex items-center justify-center bg-black/45 text-white">
            <x-icon name="loader-circle" class="size-5 animate-spin" />
        </div>
    </div>

    <p x-show="error" x-cloak class="error-text" x-text="error"></p>
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <button type="button" @click="$refs.file.click()" :disabled="uploading" class="btn btn-outline btn-sm">
            <x-icon name="upload" x-show="!uploading" />
            <x-icon name="loader-circle" class="animate-spin" x-show="uploading" x-cloak />
            <span x-text="uploading ? statusText : (url ? 'Replace' : 'Upload')">Upload</span>
        </button>
        <button type="button" x-show="url && !uploading" x-cloak @click="remove()" class="btn btn-ghost btn-sm text-muted-foreground">Remove</button>
        <span x-show="uploading" x-cloak class="w-24"><span class="progress block"><span :style="`width: ${progress}%`"></span></span></span>
    </div>
    <input type="file" x-ref="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pick($event)" tabindex="-1" aria-hidden="true">
</div>
