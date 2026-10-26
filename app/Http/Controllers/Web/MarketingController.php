<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\Seo\SeoMetadata;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class MarketingController extends Controller
{
    public function galleries(): View
    {
        return view('pages.features.galleries', [
            'seo' => SeoMetadata::forMarketingPage(
                title: 'Client Gallery Software for Photographers | ifotoset',
                description: 'Deliver photos and videos in private online galleries with PINs, expiry dates, favourites and full-resolution downloads. Fast on any phone. Start free.',
                routeName: 'features.galleries',
                name: 'Client galleries',
            ),
        ]);
    }

    public function booking(): View
    {
        return view('pages.features.booking', [
            'seo' => SeoMetadata::forMarketingPage(
                title: 'Online Booking for Photographers with Mobile Money Deposits | ifotoset',
                description: 'Let clients book a photo session from your open time slots and pay the deposit with MTN MoMo or Airtel Money. Packages, availability and reminders included.',
                routeName: 'features.booking',
                name: 'Online booking',
            ),
        ]);
    }

    public function portfolio(): View
    {
        return view('pages.features.portfolio', [
            'seo' => SeoMetadata::forMarketingPage(
                title: 'Photography Portfolio Website Builder | ifotoset',
                description: 'Build a fast, mobile-friendly photography portfolio with your galleries, packages and a booking page built in. Four templates, your branding, no developer needed.',
                routeName: 'features.portfolio',
                name: 'Portfolio website',
            ),
        ]);
    }

    public function pricing(): View
    {
        $plans = Cache::remember(Plan::LANDING_CACHE_KEY, 86400, function () {
            return Plan::orderBy('monthly_price', 'asc')->get();
        });

        return view('pages.pricing', [
            'plans' => $plans,
            'seo' => SeoMetadata::forMarketingPage(
                title: 'Pricing: Plans for Photographers in RWF | ifotoset',
                description: 'Compare ifotoset plans for client galleries, portfolio websites and online booking. Start free, pay monthly or yearly with Mobile Money, cancel anytime.',
                routeName: 'pricing',
                name: 'Pricing',
            ),
        ]);
    }
}
