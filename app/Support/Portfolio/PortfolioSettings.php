<?php

namespace App\Support\Portfolio;

use App\Models\User;

/**
 * A photographer's portfolio design, read from users.portfolio_settings with defaults filled in.
 *
 * Templates and the studio form both go through this class, so missing keys, unknown values
 * and premium templates on a lapsed plan are handled in one place.
 */
class PortfolioSettings
{
    public const DEFAULT_TEMPLATE = 'editorial';
    public const DEFAULT_FONT = 'classic';
    public const DEFAULT_ROLE = 'Photographer';
    public const MAX_TESTIMONIALS = 6;

    /** Feature code that unlocks the premium templates */
    public const PREMIUM_FEATURE = 'premium_templates';

    public const TEMPLATES = [
        'editorial' => ['name' => 'Editorial', 'description' => 'Serif headlines beside a collage of your latest covers.', 'premium' => false],
        'minimal' => ['name' => 'Minimal', 'description' => 'Centred, quiet and spacious, so the photos lead.', 'premium' => false],
        'bold' => ['name' => 'Bold', 'description' => 'A full-screen cover image with large, confident type.', 'premium' => true],
        'grid' => ['name' => 'Grid', 'description' => 'An image-first mosaic of your collections.', 'premium' => true],
    ];

    /** Heading typefaces; body text stays Inter on every pairing */
    public const FONTS = [
        'classic' => ['name' => 'Classic', 'sample' => 'Instrument Serif', 'family' => "'Instrument Serif', ui-serif, Georgia, serif", 'weight' => 400, 'tracking' => '0em'],
        'elegant' => ['name' => 'Elegant', 'sample' => 'Playfair Display', 'family' => "'Playfair Display Variable', ui-serif, Georgia, serif", 'weight' => 500, 'tracking' => '-0.01em'],
        'modern' => ['name' => 'Modern', 'sample' => 'Inter Tight', 'family' => "'Inter Tight Variable', 'Inter Variable', ui-sans-serif, system-ui, sans-serif", 'weight' => 600, 'tracking' => '-0.03em'],
        'bold' => ['name' => 'Bold', 'sample' => 'Space Grotesk', 'family' => "'Space Grotesk Variable', ui-sans-serif, system-ui, sans-serif", 'weight' => 600, 'tracking' => '-0.03em'],
    ];

    /** Home page sections after the hero, in default order */
    public const SECTIONS = [
        'work' => ['label' => 'Work', 'eyebrow' => 'Portfolio', 'heading' => 'Selected work'],
        'about' => ['label' => 'About', 'eyebrow' => 'About', 'heading' => 'The story behind the camera'],
        'services' => ['label' => 'Packages', 'eyebrow' => 'Investment', 'heading' => 'Packages'],
        'testimonials' => ['label' => 'Testimonials', 'eyebrow' => 'Kind words', 'heading' => 'What clients say'],
        'contact' => ['label' => 'Booking & contact', 'eyebrow' => 'Booking', 'heading' => "Let's make something beautiful."],
    ];

    public const SOCIAL_NETWORKS = [
        'instagram' => ['label' => 'Instagram', 'icon' => 'instagram', 'url' => 'https://www.instagram.com/%s'],
        'tiktok' => ['label' => 'TikTok', 'icon' => 'tiktok', 'url' => 'https://www.tiktok.com/@%s'],
        'facebook' => ['label' => 'Facebook', 'icon' => 'facebook', 'url' => 'https://www.facebook.com/%s'],
        'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'whatsapp', 'url' => 'https://wa.me/%s'],
    ];

    /** Suggestions for the role label field */
    public const ROLE_SUGGESTIONS = ['Photographer', 'Videographer', 'Photographer & filmmaker', 'Photo studio', 'Wedding photographer'];

    public function __construct(
        protected array $data,
        protected bool $premiumAllowed = false,
    ) {}

    public static function for(User $user): self
    {
        return new self($user->portfolio_settings ?? [], $user->hasFeature(self::PREMIUM_FEATURE));
    }

    public static function isPremiumTemplate(string $template): bool
    {
        return (bool) (self::TEMPLATES[$template]['premium'] ?? false);
    }

    /**
     * Raw stored value, for refilling the studio form.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function premiumAllowed(): bool
    {
        return $this->premiumAllowed;
    }

    /**
     * The template to render. A premium template falls back to the default once the plan no longer includes it.
     */
    public function template(): string
    {
        $template = $this->storedTemplate();

        return self::isPremiumTemplate($template) && !$this->premiumAllowed ? self::DEFAULT_TEMPLATE : $template;
    }

    public function storedTemplate(): string
    {
        $template = $this->data['template'] ?? null;

        return isset(self::TEMPLATES[$template]) ? $template : self::DEFAULT_TEMPLATE;
    }

    public function fontKey(): string
    {
        $font = $this->data['font'] ?? null;

        return isset(self::FONTS[$font]) ? $font : self::DEFAULT_FONT;
    }

    /**
     * @return array{name: string, sample: string, family: string, weight: int, tracking: string}
     */
    public function font(): array
    {
        return self::FONTS[$this->fontKey()];
    }

    public function brandColor(): ?string
    {
        return self::normalizeColor($this->data['brand_color'] ?? null);
    }

    /**
     * White or near-black, whichever reads better on the brand colour (WCAG contrast).
     */
    public function brandForeground(): ?string
    {
        $hex = $this->brandColor();
        if (!$hex) {
            return null;
        }

        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $channel = function (int $c): float {
            $c /= 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $luminance = 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);

        // #0c0c0e has a relative luminance of about 0.0037
        $onWhite = 1.05 / ($luminance + 0.05);
        $onDark = ($luminance + 0.05) / 0.0537;

        return $onWhite >= $onDark ? '#ffffff' : '#0c0c0e';
    }

    public function roleLabel(): string
    {
        return $this->text('role_label') ?? self::DEFAULT_ROLE;
    }

    public function heroHeadline(User $photographer): string
    {
        return $this->text('hero_headline') ?? $photographer->name;
    }

    public function heroText(User $photographer): ?string
    {
        return $this->text('hero_text') ?? ($photographer->bio ?: null);
    }

    /**
     * Every home page section in display order, with its visibility and headings resolved.
     *
     * @return array<int, array{id: string, label: string, visible: bool, eyebrow: string, heading: string, custom_eyebrow: ?string, custom_heading: ?string}>
     */
    public function sections(): array
    {
        $stored = collect($this->data['sections'] ?? [])
            ->filter(fn ($s) => is_array($s) && isset(self::SECTIONS[$s['id'] ?? null]))
            ->unique('id')
            ->keyBy('id');

        $order = $stored->keys()->merge(array_keys(self::SECTIONS))->unique()->values();

        return $order->map(function (string $id) use ($stored) {
            $saved = $stored->get($id, []);
            $eyebrow = self::cleanText($saved['eyebrow'] ?? null);
            $heading = self::cleanText($saved['heading'] ?? null);

            return [
                'id' => $id,
                'label' => self::SECTIONS[$id]['label'],
                'visible' => (bool) ($saved['visible'] ?? true),
                'eyebrow' => $eyebrow ?? self::SECTIONS[$id]['eyebrow'],
                'heading' => $heading ?? self::SECTIONS[$id]['heading'],
                'custom_eyebrow' => $eyebrow,
                'custom_heading' => $heading,
            ];
        })->all();
    }

    /**
     * @return array{id: string, label: string, visible: bool, eyebrow: string, heading: string, custom_eyebrow: ?string, custom_heading: ?string}
     */
    public function section(string $id): array
    {
        return collect($this->sections())->firstWhere('id', $id);
    }

    public function sectionVisible(string $id): bool
    {
        return $this->section($id)['visible'] ?? false;
    }

    public function aboutBody(): ?string
    {
        return $this->text('about_body');
    }

    /**
     * @return array<int, array{quote: string, author: string, context: ?string}>
     */
    public function testimonials(): array
    {
        return collect($this->data['testimonials'] ?? [])
            ->filter(fn ($t) => is_array($t) && filled($t['quote'] ?? null) && filled($t['author'] ?? null))
            ->map(fn ($t) => [
                'quote' => (string) $t['quote'],
                'author' => (string) $t['author'],
                'context' => self::cleanText($t['context'] ?? null),
            ])
            ->take(self::MAX_TESTIMONIALS)
            ->values()
            ->all();
    }

    public function socialHandle(string $network): ?string
    {
        return self::cleanText($this->data['social'][$network] ?? null);
    }

    /**
     * @return array<int, array{network: string, label: string, icon: string, url: string}>
     */
    public function socialLinks(): array
    {
        $links = [];
        foreach (self::SOCIAL_NETWORKS as $network => $meta) {
            $handle = $this->socialHandle($network);
            if ($handle !== null) {
                $links[] = [
                    'network' => $network,
                    'label' => $meta['label'],
                    'icon' => $meta['icon'],
                    'url' => sprintf($meta['url'], $network === 'facebook' ? $handle : rawurlencode($handle)),
                ];
            }
        }

        return $links;
    }

    public function whatsappUrl(?string $message = null): ?string
    {
        $number = $this->socialHandle('whatsapp');
        if ($number === null) {
            return null;
        }

        return "https://wa.me/{$number}" . ($message ? '?text=' . rawurlencode($message) : '');
    }

    public function contactFormEnabled(): bool
    {
        return (bool) ($this->data['contact_form'] ?? true);
    }

    public function seoTitle(): ?string
    {
        return $this->text('seo_title');
    }

    public function seoDescription(): ?string
    {
        return $this->text('seo_description');
    }

    /**
     * Turn what people paste (a handle, @handle or a profile URL) into the bare handle we store.
     */
    public static function normalizeSocial(string $network, ?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if ($network === 'whatsapp') {
            // Keep digits only; wa.me wants the full international number without "+" or "00"
            $digits = preg_replace('/\D+/', '', preg_replace('#^https?://(wa\.me|api\.whatsapp\.com/send\?phone=)/?#i', '', $value));
            return str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
        }

        $hosts = [
            'instagram' => 'instagram\.com',
            'tiktok' => 'tiktok\.com',
            'facebook' => '(?:facebook|fb)\.com',
        ];
        $value = preg_replace('#^(?:https?://)?(?:www\.|m\.)?' . $hosts[$network] . '/#i', '', $value);

        if ($network === 'facebook') {
            // Keep profile.php?id=… intact; drop tracking query strings and trailing slashes elsewhere
            return str_starts_with($value, 'profile.php') ? $value : rtrim(preg_replace('/[?#].*$/', '', $value), '/');
        }

        return ltrim(rtrim(preg_replace('/[?#].*$/', '', $value), '/'), '@');
    }

    public static function normalizeColor(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if (preg_match('/^#?([0-9a-f]{3})$/', $value, $m)) {
            $value = '#' . preg_replace('/(.)/', '$1$1', $m[1]);
        }

        return preg_match('/^#?[0-9a-f]{6}$/', $value) ? '#' . ltrim($value, '#') : null;
    }

    protected function text(string $key): ?string
    {
        return self::cleanText($this->data[$key] ?? null);
    }

    protected static function cleanText(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
