<?php

namespace Tests\Feature;

use App\Mail\PortfolioContactMail;
use App\Models\Feature;
use App\Models\Gallery;
use App\Models\Package;
use App\Models\Plan;
use App\Models\User;
use App\Services\PublicUrlService;
use App\Support\Portfolio\PortfolioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class PortfolioBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected User $photographer;
    protected Plan $freePlan;
    protected Plan $basicPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freePlan = Plan::create([
            'uuid' => Uuid::uuid7()->toString(), 'slug' => 'free', 'name' => 'Free',
            'monthly_price' => 0, 'annual_price' => 0, 'currency' => 'RWF',
            'storage_limit' => 2147483648, 'gallery_limit' => null, 'video_limit' => 0, 'team_limit' => 0,
        ]);
        $this->basicPlan = Plan::create([
            'uuid' => Uuid::uuid7()->toString(), 'slug' => 'basic', 'name' => 'Basic',
            'monthly_price' => 10000, 'annual_price' => 100000, 'currency' => 'RWF',
            'storage_limit' => 107374182400, 'gallery_limit' => null, 'video_limit' => 0, 'team_limit' => 0,
        ]);
        $premium = Feature::create(['code' => PortfolioSettings::PREMIUM_FEATURE, 'name' => 'Premium Portfolio Templates']);
        DB::table('plan_features')->insert(['plan_id' => $this->basicPlan->id, 'feature_id' => $premium->id]);

        $this->photographer = User::forceCreate([
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->freePlan->id,
            'name' => 'Alex Morgan',
            'username' => 'alexmorgan',
            'profile_public' => true,
            'email' => 'alex@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'bio' => 'Documentary wedding photographer.',
            'location' => 'Kigali',
        ]);
    }

    protected function url(string $path = ''): string
    {
        return app(PublicUrlService::class)->photographerUrl('alexmorgan') . $path;
    }

    protected function gallery(string $title, array $overrides = []): Gallery
    {
        return Gallery::create(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title),
            'visibility' => 'public',
            'show_on_profile' => true,
        ], $overrides));
    }

    protected function package(): Package
    {
        return Package::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'name' => 'Portrait',
            'price' => 100000,
            'currency' => 'RWF',
            'duration_minutes' => 60,
            'deliverables' => ['20 edited photos'],
            'is_active' => true,
        ]);
    }

    protected function upgrade(): void
    {
        $this->photographer->forceFill(['plan_id' => $this->basicPlan->id])->save();
        $this->photographer->refresh();
        Cache::flush();
    }

    protected function settings(array $settings): void
    {
        $this->photographer->update(['portfolio_settings' => $settings]);
    }

    public function test_default_portfolio_keeps_the_editorial_look(): void
    {
        $this->gallery('Lake Kivu Wedding');

        $this->get($this->url())
            ->assertOk()
            ->assertSee('data-template="editorial"', false)
            ->assertSee('Alex Morgan')
            ->assertSee('Photographer')
            ->assertSee('Selected work')
            ->assertSee('Lake Kivu Wedding')
            ->assertSee('--pf-heading: \'Instrument Serif\'', false)
            ->assertDontSee('--primary:', false);
    }

    public function test_every_template_renders(): void
    {
        $this->upgrade();
        $this->gallery('Lake Kivu Wedding', ['portfolio_category' => 'Weddings']);
        $this->gallery('Studio Portraits', ['portfolio_category' => 'Portraits']);
        $this->package();
        $this->settings(['testimonials' => [['quote' => 'Wonderful day', 'author' => 'Grace']], 'about_body' => 'I shoot weddings.']);

        foreach (array_keys(PortfolioSettings::TEMPLATES) as $template) {
            $this->settings(array_merge($this->photographer->portfolio_settings, ['template' => $template]));
            $this->photographer->forgetPortfolio();

            $this->get($this->url())
                ->assertOk()
                ->assertSee('data-template="' . $template . '"', false)
                ->assertSee('Lake Kivu Wedding')
                ->assertSee('Wonderful day')
                ->assertSee('Weddings');
        }
    }

    public function test_free_plan_cannot_choose_a_premium_template(): void
    {
        $this->actingAs($this->photographer)
            ->post(route('studio.portfolio.update', 'design'), ['template' => 'bold', 'font' => 'classic'])
            ->assertSessionHasErrors('template');

        $this->assertNull($this->photographer->fresh()->portfolio_settings);

        $this->upgrade();
        $this->actingAs($this->photographer)
            ->post(route('studio.portfolio.update', 'design'), ['template' => 'bold', 'font' => 'bold', 'use_brand_color' => '1', 'brand_color' => '#0F766E'])
            ->assertSessionHasNoErrors();

        $settings = $this->photographer->fresh()->portfolio_settings;
        $this->assertSame('bold', $settings['template']);
        $this->assertSame('#0f766e', $settings['brand_color']);
    }

    public function test_premium_template_falls_back_after_downgrade(): void
    {
        $this->settings(['template' => 'grid']);

        $this->get($this->url())->assertOk()->assertSee('data-template="editorial"', false);
    }

    public function test_brand_colour_and_font_are_applied(): void
    {
        $this->settings(['font' => 'elegant', 'brand_color' => '#fde047']);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('--primary: #fde047', false)
            // Light yellow needs dark text
            ->assertSee('--primary-foreground: #0c0c0e', false)
            ->assertSee('Playfair Display Variable', false)
            ->assertSee('/build/assets/portfolio-', false);

        $this->assertSame('#ffffff', (new PortfolioSettings(['brand_color' => '#1d4ed8']))->brandForeground());
    }

    public function test_sections_can_be_reordered_hidden_and_renamed(): void
    {
        $this->gallery('Lake Kivu Wedding');
        $this->package();

        $this->actingAs($this->photographer)
            ->post(route('studio.portfolio.update', 'sections'), [
                'sections' => [
                    ['id' => 'services', 'visible' => '1', 'eyebrow' => 'Pricing', 'heading' => 'Ways to work together'],
                    ['id' => 'contact', 'visible' => '1'],
                    ['id' => 'work', 'eyebrow' => 'Hidden but kept', 'heading' => ''],
                ],
            ])
            ->assertSessionHasNoErrors();

        $sections = $this->photographer->fresh()->portfolio()->sections();
        $this->assertSame(['services', 'contact', 'work', 'about', 'testimonials'], array_column($sections, 'id'));
        $this->assertFalse($sections[2]['visible']);
        $this->assertSame('Hidden but kept', $sections[2]['custom_eyebrow']);

        $html = $this->get($this->url())->assertOk()->getContent();
        $this->assertStringContainsString('Ways to work together', $html);
        $this->assertStringNotContainsString('id="work"', $html);
        $this->assertLessThan(strpos($html, 'id="contact"'), strpos($html, 'id="packages"'));
    }

    public function test_branding_text_replaces_defaults(): void
    {
        $this->actingAs($this->photographer)
            ->post(route('studio.portfolio.update', 'branding'), [
                'role_label' => 'Videographer',
                'hero_headline' => 'Films that feel like home',
                'hero_text' => 'Weddings across Rwanda.',
            ])
            ->assertSessionHasNoErrors();

        $this->get($this->url())
            ->assertOk()
            ->assertSee('Videographer')
            ->assertSee('Films that feel like home')
            ->assertSee('Weddings across Rwanda.');
    }

    public function test_social_handles_are_normalised_and_validated(): void
    {
        $this->actingAs($this->photographer)
            ->post(route('studio.portfolio.update', 'social'), [
                'instagram' => 'https://www.instagram.com/alex.morgan/?hl=en',
                'tiktok' => '@alexfilms',
                'facebook' => 'https://facebook.com/AlexMorganStudio/',
                'whatsapp' => '+250 788 123 456',
                'contact_form' => '1',
            ])
            ->assertSessionHasNoErrors();

        $portfolio = $this->photographer->fresh()->portfolio();
        $this->assertSame('alex.morgan', $portfolio->socialHandle('instagram'));
        $this->assertSame('alexfilms', $portfolio->socialHandle('tiktok'));
        $this->assertSame('AlexMorganStudio', $portfolio->socialHandle('facebook'));
        $this->assertSame('250788123456', $portfolio->socialHandle('whatsapp'));

        $this->get($this->url())
            ->assertOk()
            ->assertSee('https://www.instagram.com/alex.morgan', false)
            ->assertSee('https://www.tiktok.com/@alexfilms', false)
            ->assertSee('https://wa.me/250788123456', false);

        // Local numbers without a country code are rejected
        $this->actingAs($this->photographer)
            ->post(route('studio.portfolio.update', 'social'), ['whatsapp' => '0788123456'])
            ->assertSessionHasErrors('whatsapp');
    }

    public function test_about_page_shows_story_and_testimonials(): void
    {
        $this->actingAs($this->photographer)
            ->post(route('studio.portfolio.update', 'about'), [
                'about_body' => "I started shooting in 2015.\n\nToday I photograph weddings.",
                'testimonials' => [
                    ['quote' => 'Alex made us feel at ease.', 'author' => 'Grace & Eric', 'context' => 'Wedding, 2026'],
                    ['quote' => '', 'author' => '', 'context' => ''],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $this->photographer->fresh()->portfolio()->testimonials());

        $this->get($this->url('/about'))
            ->assertOk()
            ->assertSee('I started shooting in 2015.')
            ->assertSee('Today I photograph weddings.')
            ->assertSee('Grace &amp; Eric', false)
            ->assertSee('<link rel="canonical" href="' . $this->url('/about') . '"', false);

        // Home page shows the teaser and links to the About page
        $this->get($this->url())->assertSee('id="about"', false)->assertSee($this->url('/about'), false);

        $this->actingAs($this->photographer)
            ->post(route('studio.portfolio.update', 'about'), [
                'testimonials' => [['quote' => 'Great', 'author' => '']],
            ])
            ->assertSessionHasErrors('testimonials.0.author');
    }

    public function test_contact_form_emails_the_photographer(): void
    {
        Mail::fake();

        $this->get($this->url('/contact'))->assertOk()->assertSee('Send message');

        $this->post($this->url('/contact'), [
            'name' => 'Alice Walker',
            'email' => 'alice@example.com',
            'phone' => '+250 788 000 111',
            'message' => 'We are getting married in June and would love to talk.',
        ])->assertRedirect($this->url('/contact'));

        Mail::assertQueued(PortfolioContactMail::class, function (PortfolioContactMail $mail) {
            return $mail->hasTo('alex@example.com')
                && $mail->hasReplyTo('alice@example.com')
                && $mail->inquiry['name'] === 'Alice Walker';
        });
    }

    public function test_contact_form_honeypot_and_switch(): void
    {
        Mail::fake();

        $this->post($this->url('/contact'), [
            'name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy cheap followers now', 'website' => 'http://spam.test',
        ])->assertRedirect();
        Mail::assertNothingQueued();

        $this->settings(['contact_form' => false]);
        $this->get($this->url('/contact'))->assertOk()->assertDontSee('Send message');
        $this->post($this->url('/contact'), [
            'name' => 'Alice', 'email' => 'alice@example.com', 'message' => 'Hello there, are you free?',
        ])->assertNotFound();
    }

    public function test_collections_are_ordered_filtered_and_categorised(): void
    {
        $first = $this->gallery('First Gallery');
        $second = $this->gallery('Second Gallery');
        $third = $this->gallery('Third Gallery', ['show_on_profile' => false]);
        $private = $this->gallery('Private Gallery', ['visibility' => 'private', 'show_on_profile' => false]);

        $this->actingAs($this->photographer)
            ->post(route('studio.portfolio.collections'), [
                'galleries' => [
                    ['uuid' => (string) $third->uuid, 'show' => '1', 'category' => 'Portraits'],
                    ['uuid' => (string) $first->uuid, 'show' => '1', 'category' => 'Weddings'],
                    ['uuid' => (string) $second->uuid, 'category' => ''],
                    ['uuid' => (string) $private->uuid, 'show' => '1'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $third->fresh()->featured_order);
        $this->assertTrue($third->fresh()->show_on_profile);
        $this->assertSame('Weddings', $first->fresh()->portfolio_category);
        $this->assertFalse($second->fresh()->show_on_profile);
        // Only public galleries can be featured
        $this->assertFalse($private->fresh()->show_on_profile);
        $this->assertNull($private->fresh()->featured_order);

        $html = $this->get($this->url())->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'First Gallery'), strpos($html, 'Third Gallery'));
        $this->assertStringNotContainsString('Second Gallery', $html);
        $this->assertStringContainsString('Filter collections', $html);
    }

    public function test_seo_fields_and_share_image_are_used(): void
    {
        $this->settings(['seo_title' => 'Alex Morgan | Kigali Wedding Photographer', 'seo_description' => 'Natural, joyful wedding photos.']);
        $this->photographer->update(['share_image_path' => 'portfolio/x/share/1_share.jpg']);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('<title>Alex Morgan | Kigali Wedding Photographer</title>', false)
            ->assertSee('Natural, joyful wedding photos.')
            ->assertSee('portfolio/x/share/1_share.jpg', false);
    }

    public function test_reserved_paths_are_portfolio_pages_not_galleries(): void
    {
        $this->gallery('About', ['slug' => 'about']);

        $this->get($this->url('/about'))->assertOk()->assertSee('The story behind the camera');
    }

    public function test_logo_upload_is_resized_and_replaces_the_old_one(): void
    {
        Storage::fake('b2');
        $this->photographer->update(['logo_path' => 'portfolio/old/logo.webp']);
        Storage::disk('b2')->put('portfolio/old/logo.webp', 'old');

        $key = "portfolio/{$this->photographer->uuid}/logo/123_logo.png";
        $png = (string) (new ImageManager(new Driver()))->create(1600, 400)->fill('ff0000')->toPng();
        Storage::disk('b2')->put($key, $png);

        $this->actingAs($this->photographer)
            ->postJson(route('studio.portfolio.images.confirm', 'logo'), ['object_key' => $key])
            ->assertOk()
            ->assertJsonPath('url', fn ($url) => str_ends_with($url, '123_logo_logo.webp'));

        $path = $this->photographer->fresh()->logo_path;
        $this->assertSame("portfolio/{$this->photographer->uuid}/logo/123_logo_logo.webp", $path);
        Storage::disk('b2')->assertExists($path);
        Storage::disk('b2')->assertMissing($key);
        Storage::disk('b2')->assertMissing('portfolio/old/logo.webp');

        [$width, $height] = getimagesizefromstring(Storage::disk('b2')->get($path));
        $this->assertSame([800, 200], [$width, $height]);

        // Keys issued to someone else are refused
        $this->actingAs($this->photographer)
            ->postJson(route('studio.portfolio.images.confirm', 'logo'), ['object_key' => 'portfolio/other-user/logo/1_x.png'])
            ->assertStatus(422);

        $this->actingAs($this->photographer)
            ->deleteJson(route('studio.portfolio.images.destroy', 'logo'))
            ->assertOk();
        $this->assertNull($this->photographer->fresh()->logo_path);
        Storage::disk('b2')->assertMissing($path);
    }

    public function test_studio_builder_page_renders(): void
    {
        $this->gallery('Lake Kivu Wedding', ['portfolio_category' => 'Weddings']);

        $this->actingAs($this->photographer)
            ->get(route('studio.portfolio.edit'))
            ->assertOk()
            ->assertSee('Portfolio')
            ->assertSee('Lake Kivu Wedding')
            ->assertSee('Paid plans');
    }

    public function test_booking_page_carries_the_brand(): void
    {
        $this->package();
        $this->settings(['brand_color' => '#0f766e']);

        $this->get($this->url('/book'))->assertOk()->assertSee('--primary: #0f766e', false);
    }
}
