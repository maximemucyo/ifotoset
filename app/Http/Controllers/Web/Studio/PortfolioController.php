<?php

namespace App\Http\Controllers\Web\Studio;

use App\Actions\Studio\UploadPortfolioImageAction;
use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\User;
use App\Support\Portfolio\PortfolioSettings;
use App\Support\Seo\SeoMetadata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Portfolio builder: template, branding, sections, about, social, SEO and collection order.
 */
class PortfolioController extends Controller
{
    /** Builder tabs saved through update() */
    public const PARTS = ['design', 'branding', 'sections', 'about', 'social', 'seo'];

    public function edit(Request $request): View
    {
        $user = $request->account();

        $galleries = $user->galleries()
            ->where('visibility', 'public')
            ->where('moderation_status', '!=', 'taken_down')
            ->with(['coverPhoto', 'stats'])
            ->portfolioOrder()
            ->get();

        return view('studio.portfolio.edit', [
            'user' => $user,
            'portfolio' => $user->portfolio(),
            'galleries' => $galleries,
            'categories' => $galleries->pluck('portfolio_category')->filter()->unique()->sort()->values(),
            'defaultSeo' => $this->defaultSeo($user),
        ]);
    }

    /**
     * Save one tab of the builder; other tabs' settings are left as they are.
     */
    public function update(Request $request, string $part): RedirectResponse
    {
        abort_unless(in_array($part, self::PARTS, true), 404);

        $user = $request->account();
        $values = match ($part) {
            'design' => $this->design($request),
            'branding' => $request->validate([
                'role_label' => ['nullable', 'string', 'max:40'],
                'hero_headline' => ['nullable', 'string', 'max:120'],
                'hero_text' => ['nullable', 'string', 'max:400'],
            ]),
            'sections' => $this->sections($request),
            'about' => $this->about($request),
            'social' => $this->social($request),
            'seo' => $request->validate([
                'seo_title' => ['nullable', 'string', 'max:70'],
                'seo_description' => ['nullable', 'string', 'max:160'],
            ]),
        };

        $user->update(['portfolio_settings' => array_merge($user->portfolio_settings ?? [], $values)]);
        $user->forgetPortfolio();

        return redirect()->to(route('studio.portfolio.edit') . '#' . $part)->with('success', 'Portfolio updated.');
    }

    /**
     * Order, visibility and categories of the public galleries shown on the portfolio.
     */
    public function updateCollections(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'galleries' => ['present', 'array', 'max:500'],
            'galleries.*.uuid' => ['required', 'string'],
            'galleries.*.show' => ['nullable', 'boolean'],
            'galleries.*.category' => ['nullable', 'string', 'max:60'],
        ], [
            'galleries.*.category.max' => 'Categories can be up to 60 characters.',
        ]);

        $user = $request->account();
        $rows = collect($validated['galleries'])->values();

        $galleries = $user->galleries()
            ->where('visibility', 'public')
            ->whereIn('uuid', $rows->pluck('uuid')->all())
            ->get()
            ->keyBy(fn (Gallery $gallery) => (string) $gallery->uuid);

        DB::transaction(function () use ($rows, $galleries) {
            foreach ($rows as $index => $row) {
                $gallery = $galleries->get($row['uuid']);
                if (!$gallery) {
                    continue;
                }

                $category = trim((string) ($row['category'] ?? ''));
                $gallery->update([
                    'featured_order' => $index + 1,
                    'show_on_profile' => (bool) ($row['show'] ?? false),
                    'portfolio_category' => $category === '' ? null : $category,
                ]);
            }
        });

        return redirect()->to(route('studio.portfolio.edit') . '#collections')->with('success', 'Collections updated.');
    }

    public function requestImageUpload(Request $request, string $kind, UploadPortfolioImageAction $action): JsonResponse
    {
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'sha256' => ['required', 'string', 'size:64'],
        ]);

        return response()->json($action->requestUpload($request->account(), $kind, $validated['filename'], $validated['sha256']), 201);
    }

    public function confirmImageUpload(Request $request, string $kind, UploadPortfolioImageAction $action): JsonResponse
    {
        $validated = $request->validate([
            'object_key' => ['required', 'string'],
        ]);

        try {
            $url = $action->confirmUpload($request->account(), $kind, $validated['object_key']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'IMAGE_CONFIRMATION_FAILED',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['message' => 'Image updated.', 'url' => $url]);
    }

    public function destroyImage(Request $request, string $kind, UploadPortfolioImageAction $action): JsonResponse
    {
        $action->remove($request->account(), $kind);

        return response()->json(['message' => 'Image removed.']);
    }

    /**
     * The title and description the portfolio gets when the SEO fields are left empty.
     */
    protected function defaultSeo(User $user): SeoMetadata
    {
        $preview = (clone $user)->forceFill([
            'portfolio_settings' => array_merge($user->portfolio_settings ?? [], ['seo_title' => null, 'seo_description' => null]),
        ]);
        $preview->forgetPortfolio();

        return SeoMetadata::forPhotographer($preview);
    }

    protected function design(Request $request): array
    {
        $request->merge(['brand_color' => PortfolioSettings::normalizeColor($request->input('brand_color')) ?? $request->input('brand_color')]);

        $validated = $request->validate([
            'template' => ['required', Rule::in(array_keys(PortfolioSettings::TEMPLATES))],
            'font' => ['required', Rule::in(array_keys(PortfolioSettings::FONTS))],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-f]{6}$/'],
        ], [
            'brand_color.regex' => 'Use a hex colour such as #c2410c.',
        ]);

        if (PortfolioSettings::isPremiumTemplate($validated['template']) && !$request->account()->hasFeature(PortfolioSettings::PREMIUM_FEATURE)) {
            throw ValidationException::withMessages([
                'template' => 'The ' . PortfolioSettings::TEMPLATES[$validated['template']]['name'] . ' template is available on paid plans. Upgrade to use it.',
            ]);
        }

        // An empty colour box means "use the ifotoset default"
        $validated['brand_color'] = $request->boolean('use_brand_color') ? $validated['brand_color'] : null;

        return $validated;
    }

    protected function sections(Request $request): array
    {
        $validated = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.id' => ['required', Rule::in(array_keys(PortfolioSettings::SECTIONS))],
            'sections.*.visible' => ['nullable', 'boolean'],
            'sections.*.eyebrow' => ['nullable', 'string', 'max:40'],
            'sections.*.heading' => ['nullable', 'string', 'max:120'],
        ], [
            'sections.*.eyebrow.max' => 'Labels can be up to 40 characters.',
            'sections.*.heading.max' => 'Headings can be up to 120 characters.',
        ]);

        return [
            'sections' => collect($validated['sections'])
                ->unique('id')
                ->map(fn ($s) => [
                    'id' => $s['id'],
                    'visible' => (bool) ($s['visible'] ?? false),
                    'eyebrow' => $s['eyebrow'] ?? null,
                    'heading' => $s['heading'] ?? null,
                ])
                ->values()
                ->all(),
        ];
    }

    protected function about(Request $request): array
    {
        // Drop the blank rows the form always renders
        $request->merge([
            'testimonials' => collect($request->input('testimonials', []))
                ->filter(fn ($t) => is_array($t) && (filled($t['quote'] ?? null) || filled($t['author'] ?? null)))
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            'about_body' => ['nullable', 'string', 'max:5000'],
            'testimonials' => ['array', 'max:' . PortfolioSettings::MAX_TESTIMONIALS],
            'testimonials.*.quote' => ['required', 'string', 'max:600'],
            'testimonials.*.author' => ['required', 'string', 'max:80'],
            'testimonials.*.context' => ['nullable', 'string', 'max:80'],
        ], [
            'testimonials.max' => 'You can show up to ' . PortfolioSettings::MAX_TESTIMONIALS . ' testimonials.',
            'testimonials.*.quote.required' => 'Each testimonial needs a quote.',
            'testimonials.*.author.required' => 'Each testimonial needs the client\'s name.',
        ]);

        return [
            'about_body' => $validated['about_body'] ?? null,
            'testimonials' => array_values($validated['testimonials'] ?? []),
        ];
    }

    protected function social(Request $request): array
    {
        $networks = array_keys(PortfolioSettings::SOCIAL_NETWORKS);
        foreach ($networks as $network) {
            $request->merge([$network => PortfolioSettings::normalizeSocial($network, $request->input($network))]);
        }

        $validated = $request->validate([
            'instagram' => ['nullable', 'regex:/^[A-Za-z0-9._]{1,30}$/'],
            'tiktok' => ['nullable', 'regex:/^[A-Za-z0-9._]{2,24}$/'],
            'facebook' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\-\/?=&]+$/'],
            'whatsapp' => ['nullable', 'regex:/^[1-9][0-9]{7,14}$/'],
        ], [
            'instagram.regex' => 'Enter your Instagram username, e.g. @yourstudio.',
            'tiktok.regex' => 'Enter your TikTok username, e.g. @yourstudio.',
            'facebook.regex' => 'Enter your Facebook page name or link.',
            'whatsapp.regex' => 'Enter your WhatsApp number with its country code, e.g. +250 788 000 000.',
        ]);

        return [
            'social' => collect($networks)->mapWithKeys(fn ($n) => [$n => $validated[$n] ?? null])->all(),
            'contact_form' => $request->boolean('contact_form'),
        ];
    }
}
