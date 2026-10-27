<?php

namespace App\Http\Controllers\Web\Studio;

use App\Http\Controllers\Controller;
use App\Models\AvailabilitySetting;
use App\Queries\Studio\StudioDashboardQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Studio photographer dashboard.
     */
    public function index(Request $request, StudioDashboardQuery $query): View
    {
        $user = $request->account();
        $data = $query->get($user);

        // Every recent gallery belongs to this user: hydrate the relation so
        // `public_url` does not lazy-load the owner once per card.
        $data['recentGalleries']->each->setRelation('user', $user);

        $data['totalClients'] = $user->clients()->count();

        // Getting-started checklist, derived from real data (cheap EXISTS queries).
        $data['setup'] = [
            'profile'      => filled($user->username) && filled($user->bio) && filled($user->avatar_path) && filled($user->location) && filled($user->specialties),
            'gallery'      => $data['totalGalleries'] > 0,
            'photos'       => $data['totalPhotos'] > 0,
            'availability' => AvailabilitySetting::where('user_id', $user->id)->where('is_active', true)->exists(),
            'package'      => $user->packages()->exists(),
        ];

        return view('studio.dashboard', $data);
    }
}
