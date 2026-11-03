{{--
    Client field linked to the CRM: pick an existing client or type a new name, which is added to Clients on save.
    Posts client_uuid (when picked) and client_name. Expects $id (unique per page), $uuid and $name (current values).
--}}
<div x-data="{
        name: @js($name ?? ''),
        uuid: @js($uuid ?? ''),
        results: [],
        open: false,
        active: -1,
        async search() {
            try {
                const url = new URL(@js(route('studio.clients.lookup')), location.origin);
                url.searchParams.set('q', this.name.trim());
                const res = await fetch(url, { headers: { Accept: 'application/json' } });
                this.results = (await res.json()).data || [];
            } catch (e) {
                this.results = [];
            }
            this.active = -1;
        },
        pick(client) {
            this.name = client.name;
            this.uuid = client.uuid;
            this.open = false;
        },
        get isNew() {
            const typed = this.name.trim().toLowerCase();
            return typed !== '' && !this.uuid && !this.results.some(c => c.name.toLowerCase() === typed);
        },
     }"
     @click.outside="open = false">
    <label for="{{ $id }}" class="label">Client <span class="font-normal text-muted-foreground">(optional)</span></label>
    <input id="{{ $id }}" type="text" name="client_name" class="input" autocomplete="off"
           role="combobox" aria-autocomplete="list" :aria-expanded="open" aria-controls="{{ $id }}-list"
           placeholder="Search your clients or type a new name"
           x-model="name" value="{{ $name ?? '' }}"
           @focus="open = true; search()"
           @input.debounce.200ms="uuid = ''; open = true; search()"
           @keydown.arrow-down.prevent="open = true; active = Math.min(active + 1, results.length - 1)"
           @keydown.arrow-up.prevent="active = Math.max(active - 1, 0)"
           @keydown.enter="if (open && active >= 0 && results[active]) { $event.preventDefault(); pick(results[active]) }"
           @keydown.escape="open = false">
    <input type="hidden" name="client_uuid" :value="uuid" value="{{ $uuid ?? '' }}">

    <ul id="{{ $id }}-list" role="listbox" x-show="open && results.length" x-cloak
        class="menu mt-1 max-h-60 overflow-y-auto">
        <template x-for="(client, i) in results" :key="client.uuid">
            <li role="option" :aria-selected="active === i" class="menu-item cursor-pointer flex-col !items-start !gap-0"
                :class="active === i && 'bg-accent'"
                @mousedown.prevent="pick(client)" @mouseenter="active = i">
                <span class="text-[13px] text-foreground" x-text="client.name"></span>
                <span class="text-[11px] text-muted-foreground" x-show="client.email" x-text="client.email"></span>
            </li>
        </template>
    </ul>
    <p class="help" x-show="isNew && !(open && results.length)" x-cloak>New name: it will be added to your clients.</p>
    <p class="help" x-show="uuid" x-cloak>Linked to this client's record.</p>
</div>
