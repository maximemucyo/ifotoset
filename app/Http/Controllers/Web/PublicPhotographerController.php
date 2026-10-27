<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\PortfolioContactMail;
use App\Models\User;
use App\Support\Seo\SeoMetadata;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PublicPhotographerController extends Controller
{
    /**
     * Display photographer public portfolio page.
     */
    public function show(string $username): View
    {
        $photographer = $this->photographer($username);
        $packages = $this->packages($photographer);

        $galleries = $photographer->galleries()
            ->shownOnProfile()
            ->with(['coverPhoto', 'stats', 'user:id,username'])
            ->portfolioOrder()
            ->get();

        return view('public.photographer', [
            'photographer' => $photographer,
            'portfolio' => $photographer->portfolio(),
            'galleries' => $galleries,
            'packages' => $packages,
            'seo' => SeoMetadata::forPhotographer($photographer, $packages->all()),
        ]);
    }

    /**
     * Long-form About page.
     */
    public function about(string $username): View
    {
        $photographer = $this->photographer($username);

        return view('public.portfolio.about', [
            'photographer' => $photographer,
            'portfolio' => $photographer->portfolio(),
            'hasPackages' => $this->packages($photographer)->isNotEmpty(),
            'seo' => SeoMetadata::forPortfolioPage($photographer, 'about'),
        ]);
    }

    /**
     * Contact page: links and, unless switched off, a message form.
     */
    public function contact(string $username): View
    {
        $photographer = $this->photographer($username);

        return view('public.portfolio.contact', [
            'photographer' => $photographer,
            'portfolio' => $photographer->portfolio(),
            'hasPackages' => $this->packages($photographer)->isNotEmpty(),
            'seo' => SeoMetadata::forPortfolioPage($photographer, 'contact'),
        ]);
    }

    /**
     * Email a contact-form message to the photographer.
     */
    public function sendMessage(Request $request, string $username): RedirectResponse
    {
        $photographer = $this->photographer($username);
        abort_unless($photographer->portfolio()->contactFormEnabled(), 404);

        $back = redirect()->route('subdomain.contact', ['username' => $photographer->username]);
        $thanks = 'Thank you! Your message has been sent. ' . $photographer->name . ' will reply by email.';

        // Honeypot: real visitors never see or fill this field
        if (filled($request->input('website'))) {
            return $back->with('success', $thanks);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[+0-9 ()-]+$/'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        try {
            Mail::to($photographer->email)->send(new PortfolioContactMail($photographer, [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'message' => $validated['message'],
            ]));
        } catch (\Throwable $e) {
            Log::error("Failed to send portfolio contact message to photographer {$photographer->id}: " . $e->getMessage());
            return back()->withInput()->with('error', 'We could not send your message. Please try again.');
        }

        return $back->with('success', $thanks);
    }

    protected function photographer(string $username): User
    {
        $photographer = User::where('username', strtolower($username))
            ->where('is_active', true)
            ->firstOrFail();

        // Hidden profiles are only visible to their owner (e.g. to preview before going public)
        abort_if(!$photographer->profile_public && auth()->id() !== $photographer->id, response()->view('public.unpublished', [], 404));

        return $photographer;
    }

    protected function packages(User $photographer): Collection
    {
        return $photographer->packages()
            ->active()
            ->orderBy('sort_order', 'asc')
            ->get();
    }
}
