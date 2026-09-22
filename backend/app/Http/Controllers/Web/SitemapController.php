<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\User;
use App\Services\PublicUrlService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    protected const CHUNK_SIZE = 1000;

    public function __construct(
        protected PublicUrlService $urlService
    ) {}

    /**
     * Return root sitemap index linking to modular sitemap sections.
     */
    public function index(): Response
    {
        $xml = Cache::remember('sitemap_index_xml', 21600, function () {
            $rootUrl = $this->urlService->home();
            $staticSitemap = rtrim($rootUrl, '/') . '/sitemaps/static.xml';
            $photographersSitemap = rtrim($rootUrl, '/') . '/sitemaps/photographers.xml';
            $galleriesSitemap = rtrim($rootUrl, '/') . '/sitemaps/galleries.xml';

            $now = now()->toIso8601String();

            $output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $output .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            
            $output .= "  <sitemap>\n";
            $output .= "    <loc>{$staticSitemap}</loc>\n";
            $output .= "    <lastmod>{$now}</lastmod>\n";
            $output .= "  </sitemap>\n";

            $output .= "  <sitemap>\n";
            $output .= "    <loc>{$photographersSitemap}</loc>\n";
            $output .= "    <lastmod>{$now}</lastmod>\n";
            $output .= "  </sitemap>\n";

            $output .= "  <sitemap>\n";
            $output .= "    <loc>{$galleriesSitemap}</loc>\n";
            $output .= "    <lastmod>{$now}</lastmod>\n";
            $output .= "  </sitemap>\n";

            $output .= '</sitemapindex>';

            return $output;
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'X-Robots-Tag' => 'noindex', // Sitemap indexes don't need indexing themselves
        ]);
    }

    /**
     * Return specific sitemap section with strict whitelisting.
     */
    public function section(string $section): Response
    {
        // Whitelist section names (e.g. static, photographers, photographers-1, galleries, galleries-1)
        if (!preg_match('/^(static|photographers|photographers-(\d+)|galleries|galleries-(\d+))$/', $section, $matches)) {
            abort(404, 'Sitemap section not found.');
        }

        $baseType = $matches[1];
        $page = isset($matches[2]) && $matches[2] !== '' 
            ? (int) $matches[2] 
            : (isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 1);

        $cacheKey = "sitemap_{$section}_xml";

        $xml = Cache::remember($cacheKey, 21600, function () use ($baseType, $page) {
            if ($baseType === 'static') {
                return $this->generateStaticSitemap();
            }

            if (str_starts_with($baseType, 'photographers')) {
                return $this->generatePhotographersSitemap($page);
            }

            return $this->generateGalleriesSitemap($page);
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }

    /**
     * Generate static marketing pages sitemap.
     */
    protected function generateStaticSitemap(): string
    {
        $homeUrl = htmlspecialchars($this->urlService->home(), ENT_XML1, 'UTF-8');
        $lastMod = now()->toIso8601String();

        $output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $output .= "  <url>\n";
        $output .= "    <loc>{$homeUrl}</loc>\n";
        $output .= "    <lastmod>{$lastMod}</lastmod>\n";
        $output .= "  </url>\n";
        $output .= '</urlset>';

        return $output;
    }

    /**
     * Generate active public photographer profiles sitemap.
     */
    protected function generatePhotographersSitemap(int $page): string
    {
        $query = User::publiclyIndexable()
            ->select(['id', 'username', 'updated_at'])
            ->orderBy('id', 'asc');

        $photographers = $query->skip(($page - 1) * self::CHUNK_SIZE)
            ->take(self::CHUNK_SIZE)
            ->get();

        $output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($photographers as $photographer) {
            $loc = htmlspecialchars($this->urlService->photographer($photographer), ENT_XML1, 'UTF-8');
            $lastmod = ($photographer->updated_at ?? now())->toIso8601String();

            $output .= "  <url>\n";
            $output .= "    <loc>{$loc}</loc>\n";
            $output .= "    <lastmod>{$lastmod}</lastmod>\n";
            $output .= "  </url>\n";
        }

        $output .= '</urlset>';

        return $output;
    }

    /**
     * Generate publicly indexable galleries sitemap with meaningful lastmod.
     */
    protected function generateGalleriesSitemap(int $page): string
    {
        $query = Gallery::publiclyIndexable()
            ->with(['user:id,username'])
            ->select(['id', 'user_id', 'slug', 'updated_at', 'created_at'])
            ->orderBy('id', 'asc');

        $galleries = $query->skip(($page - 1) * self::CHUNK_SIZE)
            ->take(self::CHUNK_SIZE)
            ->get();

        $output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($galleries as $gallery) {
            if (!$gallery->user) {
                continue;
            }

            $loc = htmlspecialchars($this->urlService->gallery($gallery), ENT_XML1, 'UTF-8');
            // Accurate meaningful lastmod based on gallery content updates
            $lastmod = ($gallery->updated_at ?? $gallery->created_at ?? now())->toIso8601String();

            $output .= "  <url>\n";
            $output .= "    <loc>{$loc}</loc>\n";
            $output .= "    <lastmod>{$lastmod}</lastmod>\n";
            $output .= "  </url>\n";
        }

        $output .= '</urlset>';

        return $output;
    }
}
