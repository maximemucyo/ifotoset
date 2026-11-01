<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\StudioUsername;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsernameAvailabilityController extends Controller
{
    /**
     * Whether a studio username is free, checked while someone types it on a sign-up or settings form.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $username = strtolower(trim((string) $request->query('username')));

        return response()
            ->json(StudioUsername::check($username))
            ->header('Cache-Control', 'no-store');
    }
}
