<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateBookingAction;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Ramsey\Uuid\Uuid;

class PublicPhotographerController extends Controller
{
    /**
     * Display photographer public portfolio page.
     */
    public function show(string $username): View
    {
        $photographer = User::where('username', strtolower($username))
            ->where('is_active', true)
            ->firstOrFail();

        // Hidden profiles are only visible to their owner (e.g. to preview before going public)
        abort_if($photographer->profile_public === false && auth()->id() !== $photographer->id, 404);

        $galleries = $photographer->galleries()
            ->shownOnProfile()
            ->with(['coverPhoto', 'stats', 'user:id,username'])
            ->orderBy('created_at', 'desc')
            ->get();

        $packages = $photographer->packages()
            ->active()
            ->orderBy('sort_order', 'asc')
            ->get();

        return view('public.photographer', [
            'photographer' => $photographer,
            'galleries' => $galleries,
            'packages' => $packages,
            'seo' => \App\Support\Seo\SeoMetadata::forPhotographer($photographer, $packages->all()),
        ]);
    }

    /**
     * Handle public client booking request with deterministic concurrency locking.
     */
    public function book(Request $request, string $username, CreateBookingAction $createBookingAction): RedirectResponse
    {
        $photographer = User::where('username', strtolower($username))
            ->where('is_active', true)
            ->firstOrFail();

        // Honeypot: real visitors never see or fill this field
        if (filled($request->input('website'))) {
            return back()->with('success', 'Thank you! Your booking request has been submitted successfully. The photographer will review and confirm shortly.');
        }

        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['required', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'package_id' => ['required', 'string'],
            'starts_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Find or create the client for this photographer
        $client = Client::firstOrCreate([
            'user_id' => $photographer->id,
            'email' => strtolower(trim($validated['client_email'])),
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => $validated['client_name'],
            'phone' => $validated['client_phone'] ?? null,
        ]);

        try {
            $booking = $createBookingAction->execute($photographer, [
                'client_id' => $client->uuid,
                'package_id' => $validated['package_id'],
                'title' => "Booking: {$client->name}",
                'starts_at' => $validated['starts_at'],
                'notes' => $validated['notes'] ?? null,
                'validate_availability' => false,
                'status' => 'pending',
            ]);

            return back()->with('success', 'Thank you! Your booking request has been submitted successfully. The photographer will review and confirm shortly.');
        } catch (\App\Exceptions\BookingOverlapException $e) {
            return back()->withInput()->with('error', 'That time is already booked. Please choose another time.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->withInput()->with('error', 'Please choose one of the available packages.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'We could not submit your booking. Please try again.');
        }
    }
}
