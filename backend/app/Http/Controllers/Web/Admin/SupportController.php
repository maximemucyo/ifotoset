<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportController extends Controller
{
    /**
     * Display the support and customer service desk.
     */
    public function index(Request $request): View
    {
        // Demonstration support inquiries for Rwanda photographers
        $tickets = [
            [
                'id'       => '#TK-0104',
                'subject'  => 'MTN Mobile Money deposit callback confirmation speed',
                'user'     => 'Diane Studio Kigali',
                'email'    => 'diane@example.com',
                'priority' => 'Urgent',
                'status'   => 'In Progress',
                'date'     => '2 hours ago',
                'message'  => 'Client completed the RWF 30,000 deposit on MTN MoMo, webhook was received but gallery unlock took 3 seconds.',
            ],
            [
                'id'       => '#TK-0103',
                'subject'  => 'Custom domain setup for portfolio (ifotoset.com/p/rwandaevents)',
                'user'     => 'Rwanda Events Group',
                'email'    => 'events@example.com',
                'priority' => 'Medium',
                'status'   => 'Open',
                'date'     => '5 hours ago',
                'message'  => 'Inquiring about connecting our CNAME for weddings.rwandaevents.com directly to ifotoset portfolio.',
            ],
            [
                'id'       => '#TK-0102',
                'subject'  => 'Large ZIP download of 450 photos from wedding album',
                'user'     => 'Kevin Portrait Studio',
                'email'    => 'kevin@example.com',
                'priority' => 'Low',
                'status'   => 'Resolved',
                'date'     => 'Yesterday',
                'message'  => 'Tested streaming ZIP generation on 2.4 GB collection, worked smoothly with Cloudflare worker.',
            ],
        ];

        return view('admin.support', [
            'tickets' => $tickets,
        ]);
    }
}
