# ifotoset design system (Blade + Tailwind v4 + Alpine)

The UI is a modern, minimal SaaS aesthetic (think Linear / Vercel / Pixieset):
neutral ink-on-paper surfaces, one warm **brand** accent (ember orange), generous
whitespace, crisp 1px borders, soft shadows, and real photography as the hero.

Everything lives in `resources/css/app.css`. Tailwind v4 scans `resources/views`
and `resources/js`, so any utility you use in a Blade file is compiled.

## Typography

| Token | Use |
| --- | --- |
| `font-sans` (Inter Variable) | Default UI text |
| `font-display` (Inter Tight) | Headings, big numbers. `h1–h6` use it automatically |
| `font-serif` (Instrument Serif) | Editorial accents on marketing / public pages only (`<em class="font-serif italic">`) |

Scale: page titles `text-2xl sm:text-[28px] font-semibold tracking-tight`; section
titles `text-[15px] font-semibold`; body `text-sm`; secondary `text-[13px] text-muted-foreground`;
meta `text-xs text-muted-foreground`. Use `tabular-nums` for numbers.

## Color tokens (light + dark automatically)

`background, foreground, card, popover, primary (ink), secondary, muted, muted-foreground,
subtle (placeholder grey), accent, brand, brand-soft, brand-foreground, destructive,
success, warning, info, border, border-strong, input, ring, sidebar*`.

Use them as Tailwind colors: `bg-card`, `text-muted-foreground`, `border-border`,
`bg-brand/10 text-brand`, `text-success`, `bg-warning/10`, etc.

**Rules**
- Primary actions use `btn-primary` (ink). Use `btn-brand` sparingly (marketing CTAs, upgrade).
- Never hard-code hex colours or Tailwind palette colours like `bg-green-500`, `text-amber-700`
  for status: use `success / warning / destructive / info` tokens so dark mode works.
- Never use `text-primary` as a link colour (primary is ink). Use `.link` or `.link-brand`.
- Dark mode is class based (`.dark` on `<html>`), so always check both themes.

## Component classes (prefer these over long utility strings)

- Buttons: `btn` + `btn-primary | btn-brand | btn-secondary | btn-outline | btn-ghost | btn-danger | btn-danger-ghost | btn-link`,
  sizes `btn-xs | btn-sm | (default h-9) | btn-lg | btn-xl`, square `btn-icon`. Icons inside buttons auto-size.
- Cards: `card` (rounded-xl border bg-card shadow-xs), `card-hover`, `card-header`, `card-title`,
  `card-description`, `card-body`, `card-footer`, `surface-muted`, `glass`.
- Forms: `label`, `input`, `select`, `textarea`, `input-sm`, `input-error`, `help`, `error-text`,
  `input-group` (leading `<x-icon>` + `.input`), `checkbox`, `switch` (`<label class="switch"><input type="checkbox" class="sr-only" ...><span></span></label>`).
- Badges: `badge` + `badge-neutral | badge-brand | badge-success | badge-warning | badge-danger | badge-info | badge-solid`, optional `badge-dot`.
- Tables: wrap in `<div class="card overflow-hidden"><div class="table-wrap"><table class="table">…`.
- Tabs: segmented `tabs` > `tab` (`aria-selected="true"` or `.is-active`); underline `tabs-line` > `tab-line`.
- Menus: `menu`, `menu-item`, `menu-item-danger`, `menu-separator`, `menu-label` (with `x-data="dropdown"`).
- Misc: `kbd`, `divider`, `eyebrow`, `avatar`, `skeleton`, `progress > span[style=width]`, `stat-value`,
  `alert alert-success|danger|warning|info`, `text-gradient`, `bg-grid`, `bg-dots`, `mask-fade-b|y|x`, `mask-radial`, `noise`.
- Motion: `animate-fade-up`, `animate-fade-in`, `animate-scale-in`; scroll reveal with `data-reveal`
  (+ optional `style="--reveal-delay: 120ms"`).

## Blade components

| Component | Notes |
| --- | --- |
| `<x-icon name="images" class="size-4" />` | Lucide icons. See `app/Support/Icons.php` for names. Unknown names render nothing, so check the list. |
| `<x-ui.button variant="primary|brand|outline|ghost|secondary|danger" size="xs|sm|md|lg" icon="plus" href="...">` | Renders `<a>` when `href` given |
| `<x-ui.input label name type error hint icon />` | Password inputs get a show/hide toggle |
| `<x-ui.select label name>`, `<x-ui.textarea label name value>` | |
| `<x-ui.badge variant="success" dot>` | |
| `<x-ui.card title description>` with optional `<x-slot:actions>` / `<x-slot:footer>` | |
| `<x-ui.page-header title description eyebrow back>` with `<x-slot:actions>` | Every app page starts with this |
| `<x-ui.empty-state title description icon="images" action-label action-url>` | |
| `<x-ui.modal name="x" title description maxWidth>` | open: `$dispatch('open-modal', 'x')` |
| `<x-ui.avatar name src size>` | Initials fallback with deterministic colour |
| `<x-ui.theme-toggle />` / `variant="segmented"` | |
| `<x-studio.stat-card label value hint change changeType icon href>` | KPI tiles |
| `<x-brand.logo href size :wordmark>` | |

## Layouts

- `layouts.app` (studio) and `layouts.admin` share `layouts/partials/shell.blade.php`: sidebar + inset
  main panel + top bar + ⌘K command palette. Pages `@extends('layouts.app', ['title' => 'Galleries · ifotoset'])`
  and fill `@section('content')`. Optional sections: `breadcrumbs`, `topbar-actions`. Use `@push('scripts')` / `@push('modals')`.
  Content is already inside `max-w-7xl` with padding: don't add another outer container or padding.
- `layouts.guest` is the split-screen auth layout (`heading`, `subheading`, `icon` params).
- `layouts.public` is for marketing and public pages.
- Global toasts: `window.toast('Saved', 'success'|'error'|'warning'|'info')`. Session flashes (`success`, `error`, …) show automatically.
- Clipboard: `@click="$clipboard(url)"` (Alpine magic) copies and toasts.

## Page recipe (studio/admin)

```blade
@extends('layouts.app', ['title' => 'Clients · ifotoset'])
@section('content')
<x-ui.page-header title="Clients" description="Everyone you've worked with, in one place.">
    <x-slot:actions>
        <button class="btn btn-primary" @click="$dispatch('open-modal','new-client')"><x-icon name="plus" /> Add client</button>
    </x-slot:actions>
</x-ui.page-header>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"> …stat cards… </div>
<div class="card mt-6 overflow-hidden"> …toolbar (search/filter) + table… </div>
@endsection
```

Spacing rhythm: `gap-4`/`gap-6` between cards, `mt-6`/`mt-8` between sections, `p-5` inside cards.
Radius: cards `rounded-xl`, large media `rounded-2xl`, buttons/inputs `rounded-lg`.
