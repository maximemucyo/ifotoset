<?php

namespace Tests\Feature;

use App\Actions\Billing\FinalizePawaPayPayment;
use App\Contracts\PaymentGateway;
use App\DTO\PaymentStatusData;
use App\Events\BookingStatusChanged;
use App\Mail\BookingConfirmationClientMail;
use App\Mail\BookingDepositPaidClientMail;
use App\Mail\BookingStatusUpdatedMail;
use App\Mail\NewBookingPhotographerMail;
use App\Models\AvailabilitySetting;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Feature;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Support\BookingCalendar;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $photographer;
    protected Plan $freePlan;
    protected Plan $proPlan;
    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday 2 November 2026, 08:00 UTC (10:00 in Kigali)
        Carbon::setTestNow(Carbon::parse('2026-11-02 08:00:00', 'UTC'));

        $this->freePlan = Plan::create([
            'uuid' => Uuid::uuid7()->toString(), 'slug' => 'free', 'name' => 'Free',
            'monthly_price' => 0, 'annual_price' => 0, 'currency' => 'RWF',
            'storage_limit' => 2147483648, 'gallery_limit' => null, 'video_limit' => 0, 'team_limit' => 0,
        ]);
        $this->proPlan = Plan::create([
            'uuid' => Uuid::uuid7()->toString(), 'slug' => 'pro', 'name' => 'Professional',
            'monthly_price' => 29999, 'annual_price' => 299988, 'currency' => 'RWF',
            'storage_limit' => 1000000000000, 'gallery_limit' => null, 'video_limit' => 18000, 'team_limit' => 1,
        ]);
        $deposits = Feature::create(['code' => 'booking_deposits', 'name' => 'Mobile Money Booking Deposits']);
        DB::table('plan_features')->insert(['plan_id' => $this->proPlan->id, 'feature_id' => $deposits->id]);

        $this->photographer = User::forceCreate([
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->freePlan->id,
            'name' => 'Alex Morgan',
            'username' => 'alexmorgan',
            'email' => 'alex@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'timezone' => 'Africa/Kigali',
        ]);

        $this->package = Package::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'name' => 'Portrait',
            'price' => 100000,
            'currency' => 'RWF',
            'duration_minutes' => 60,
            'deliverables' => ['20 edited photos'],
            'is_active' => true,
            'deposit_type' => 'percentage',
            'deposit_amount' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function bookUrl(string $path = ''): string
    {
        return app(\App\Services\PublicUrlService::class)->photographerUrl('alexmorgan') . '/book' . $path;
    }

    protected function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'client_name' => 'Alice Walker',
            'client_email' => 'alice@example.com',
            'client_phone' => '0788123456',
            'package_id' => (string) $this->package->uuid,
            // Wednesday 14:00 Kigali
            'starts_at' => '2026-11-04T14:00:00+02:00',
            'location' => 'Kigali Convention Centre',
            'notes' => 'Family portraits',
        ], $overrides);
    }

    protected function mockGateway(?PaymentStatusData $status = null): void
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('initiateDeposit')->andReturn(['status' => 'ACCEPTED']);
        if ($status) {
            $gateway->shouldReceive('verifyDepositStatus')->andReturn($status);
        }
        $this->app->instance(PaymentGateway::class, $gateway);
    }

    protected function makeBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->photographer->id,
            'package_id' => $this->package->id,
            'title' => 'Existing',
            'starts_at' => Carbon::parse('2026-11-04 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-04 13:00:00', 'UTC'),
            'timezone' => 'Africa/Kigali',
            'status' => 'confirmed',
        ], $attributes));
    }

    // --- Slots & availability ---

    public function test_slots_follow_working_hours_and_are_not_shrunk_by_the_notice_filter(): void
    {
        $response = $this->getJson($this->bookUrl('/slots') . '?package=' . $this->package->uuid . '&date=2026-11-04');

        $response->assertOk();
        $slots = collect($response->json('slots'));

        // Default hours 09:00-17:00, 60 min sessions every 30 min: 09:00 ... 16:00
        $this->assertCount(15, $slots);
        $this->assertSame('2026-11-04T09:00:00+02:00', $slots->first()['starts_at']);
        $this->assertSame('2026-11-04T16:00:00+02:00', $slots->last()['starts_at']);
    }

    public function test_slots_respect_closed_days_and_minimum_notice(): void
    {
        AvailabilitySetting::create(['user_id' => $this->photographer->id, 'day_of_week' => 3, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'is_active' => false]);

        $this->getJson($this->bookUrl('/slots') . '?package=' . $this->package->uuid . '&date=2026-11-04')
            ->assertOk()->assertJsonCount(0, 'slots');

        // Tomorrow: only times at least 24h away (after 10:00 Kigali)
        $tomorrow = collect($this->getJson($this->bookUrl('/slots') . '?package=' . $this->package->uuid . '&date=2026-11-03')->json('slots'));
        $this->assertSame('2026-11-03T10:30:00+02:00', $tomorrow->first()['starts_at']);
    }

    public function test_month_availability_lists_open_days(): void
    {
        $days = $this->getJson($this->bookUrl('/availability') . '?package=' . $this->package->uuid . '&month=2026-11')
            ->assertOk()
            ->json('days');

        $this->assertNotContains('2026-11-02', $days); // today: inside the notice window
        $this->assertContains('2026-11-04', $days);
        $this->assertContains('2026-11-30', $days);
    }

    public function test_buffers_keep_time_free_around_existing_bookings(): void
    {
        $this->package->update(['buffer_after_minutes' => 30]);
        // Existing session 14:00-15:00 Kigali, kept free until 15:30
        $this->makeBooking();

        $slots = collect($this->getJson($this->bookUrl('/slots') . '?package=' . $this->package->uuid . '&date=2026-11-04')->json('slots'))
            ->pluck('starts_at');

        $this->assertContains('2026-11-04T12:30:00+02:00', $slots);
        $this->assertNotContains('2026-11-04T13:00:00+02:00', $slots); // its own buffer would run into 14:00
        $this->assertNotContains('2026-11-04T15:00:00+02:00', $slots); // inside the existing buffer
        // Last start that still finishes (with buffer) by 17:00; the old code kept stretching
        // the existing booking's buffer for every candidate and lost this slot
        $this->assertSame('2026-11-04T15:30:00+02:00', $slots->last());
    }

    // --- Public booking ---

    public function test_booking_is_stored_in_utc_from_the_photographers_timezone(): void
    {
        Mail::fake();

        $this->post($this->bookUrl(), $this->bookingPayload(['starts_at' => '2026-11-04 14:00']))
            ->assertSessionHas('success');

        $booking = Booking::firstOrFail();
        $this->assertSame('2026-11-04 12:00:00', $booking->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('14:00', $booking->localStartsAt()->format('H:i'));
        $this->assertSame('Kigali Convention Centre', $booking->location);
        $this->assertNull($booking->deposit_status);

        Mail::assertQueued(NewBookingPhotographerMail::class);
        Mail::assertQueued(BookingConfirmationClientMail::class);
    }

    public function test_times_outside_availability_are_rejected(): void
    {
        $this->post($this->bookUrl(), $this->bookingPayload(['starts_at' => '2026-11-04T03:00:00+02:00']))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_phone_is_required(): void
    {
        $this->post($this->bookUrl(), $this->bookingPayload(['client_phone' => '']))
            ->assertSessionHasErrors('client_phone');
    }

    public function test_cancelled_bookings_do_not_block_the_slot(): void
    {
        Mail::fake();
        $this->makeBooking(['status' => 'cancelled']);

        $this->post($this->bookUrl(), $this->bookingPayload())->assertSessionHas('success');

        $this->assertSame(2, Booking::count());
    }

    public function test_confirmed_bookings_block_the_slot(): void
    {
        $this->makeBooking();

        $this->post($this->bookUrl(), $this->bookingPayload())->assertSessionHas('error');
        $this->assertSame(1, Booking::count());
    }

    public function test_inactive_packages_cannot_be_booked(): void
    {
        $this->package->update(['is_active' => false]);

        $this->post($this->bookUrl(), $this->bookingPayload())->assertSessionHas('error');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_returning_client_details_are_updated(): void
    {
        Mail::fake();
        Client::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'name' => 'Old Name',
            'email' => 'alice@example.com',
            'phone' => null,
        ]);

        $this->post($this->bookUrl(), $this->bookingPayload())->assertSessionHas('success');

        $client = Client::firstOrFail();
        $this->assertSame(1, Client::count());
        $this->assertSame('Alice Walker', $client->name);
        $this->assertSame('0788123456', $client->phone);
    }

    public function test_booking_page_renders_with_packages(): void
    {
        $this->get($this->bookUrl())
            ->assertOk()
            ->assertSee('Book a session with Alex Morgan')
            ->assertSee('Portrait');
    }

    public function test_status_page_is_only_visible_to_the_visitor_who_booked(): void
    {
        Mail::fake();
        $this->post($this->bookUrl(), $this->bookingPayload());
        $booking = Booking::firstOrFail();

        $this->get($this->bookUrl('/' . $booking->uuid))->assertOk()->assertSee('Request sent');

        $this->flushSession();
        $this->get($this->bookUrl('/' . $booking->uuid))->assertNotFound();
    }

    // --- Deposits ---

    public function test_free_plan_does_not_collect_deposits(): void
    {
        Mail::fake();

        $this->post($this->bookUrl(), $this->bookingPayload())->assertSessionHas('success');

        $this->assertDatabaseCount('payments', 0);
        $this->assertNull(Booking::firstOrFail()->deposit_status);
    }

    public function test_pro_booking_holds_the_slot_and_requests_a_deposit(): void
    {
        Mail::fake();
        $this->photographer->forceFill(['plan_id' => $this->proPlan->id])->save();
        $this->mockGateway();

        $this->post($this->bookUrl(), $this->bookingPayload(['payment_phone' => '0722000111']))
            ->assertRedirect();

        $booking = Booking::firstOrFail();
        $this->assertSame('pending', $booking->status->value);
        $this->assertSame('pending', $booking->deposit_status);
        $this->assertEquals(30000, (float) $booking->deposit_amount);
        $this->assertTrue($booking->hold_expires_at->isFuture());

        $payment = Payment::firstOrFail();
        $this->assertSame('booking_deposit', $payment->purpose);
        $this->assertSame($booking->id, $payment->booking_id);
        $this->assertNull($payment->plan_id);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('0722000111', $payment->phone_number);
        $this->assertEquals(30000, (float) $payment->amount);

        $this->get($this->bookUrl('/' . $booking->uuid))->assertOk()->assertSee('Approve the payment on your phone');

        // Nobody is told about an unpaid hold
        Mail::assertNotQueued(NewBookingPhotographerMail::class);
        Mail::assertNotQueued(BookingConfirmationClientMail::class);

        // The held slot is closed to other clients
        $slots = collect($this->getJson($this->bookUrl('/slots') . '?package=' . $this->package->uuid . '&date=2026-11-04')->json('slots'))->pluck('starts_at');
        $this->assertNotContains('2026-11-04T14:00:00+02:00', $slots);
    }

    public function test_paid_deposit_confirms_the_booking_and_sends_the_receipt_with_a_calendar_invite(): void
    {
        Mail::fake();
        $this->photographer->forceFill(['plan_id' => $this->proPlan->id])->save();
        $this->mockGateway();
        $this->post($this->bookUrl(), $this->bookingPayload());
        $payment = Payment::firstOrFail();

        app(FinalizePawaPayPayment::class)->execute($payment->pawapay_deposit_id, new PaymentStatusData(
            found: true, status: 'COMPLETED', depositId: $payment->pawapay_deposit_id, amount: 30000, currency: 'RWF',
        ));

        $booking = Booking::firstOrFail();
        $this->assertSame('confirmed', $booking->status->value);
        $this->assertTrue($booking->isDepositPaid());
        $this->assertNull($booking->hold_expires_at);
        $this->assertEquals(70000, $booking->balanceDue());

        Mail::assertQueued(BookingDepositPaidClientMail::class, function ($mail) {
            return count($mail->attachments()) === 1;
        });

        // Photographers no longer see a "Deposit paid" badge that never renders
        $this->actingAs($this->photographer)->get(route('studio.bookings.index'))
            ->assertOk()->assertSee('Deposit 30,000 paid')->assertSee('Balance 70,000');
    }

    public function test_failed_deposit_can_be_retried_with_another_number(): void
    {
        Mail::fake();
        $this->photographer->forceFill(['plan_id' => $this->proPlan->id])->save();
        $this->mockGateway();
        $this->post($this->bookUrl(), $this->bookingPayload());
        $first = Payment::firstOrFail();

        app(FinalizePawaPayPayment::class)->execute($first->pawapay_deposit_id, new PaymentStatusData(
            found: true, status: 'FAILED', depositId: $first->pawapay_deposit_id, amount: 30000, currency: 'RWF', failureReason: 'Insufficient balance',
        ));

        $booking = Booking::firstOrFail();
        $this->assertSame('failed', $booking->deposit_status);
        $this->get($this->bookUrl('/' . $booking->uuid))->assertOk()->assertSee("The deposit didn't go through", false);

        $this->post($this->bookUrl('/' . $booking->uuid . '/pay'), ['payment_phone' => '0733000222'])->assertRedirect();

        $this->assertSame(2, Payment::count());
        $this->assertSame('0733000222', Payment::latest('id')->first()->phone_number);
        $this->assertSame('pending', $booking->fresh()->deposit_status);
    }

    public function test_expired_holds_are_released(): void
    {
        $this->photographer->forceFill(['plan_id' => $this->proPlan->id])->save();
        $this->mockGateway(new PaymentStatusData(found: true, status: 'SUBMITTED', depositId: 'x', amount: 30000, currency: 'RWF'));
        $this->post($this->bookUrl(), $this->bookingPayload());

        Carbon::setTestNow(now()->addMinutes(Booking::DEPOSIT_HOLD_MINUTES + 1));

        // An expired hold no longer blocks, even before the cleanup runs
        $slots = collect($this->getJson($this->bookUrl('/slots') . '?package=' . $this->package->uuid . '&date=2026-11-04')->json('slots'))->pluck('starts_at');
        $this->assertContains('2026-11-04T14:00:00+02:00', $slots);

        $this->artisan('bookings:expire-holds')->assertSuccessful();

        $booking = Booking::firstOrFail();
        $this->assertSame('cancelled', $booking->status->value);
        $this->assertSame('expired', $booking->deposit_status);

        // Abandoned checkouts stay out of the photographer's list
        $this->actingAs($this->photographer)->get(route('studio.bookings.index'))
            ->assertOk()->assertSee('No bookings yet');
    }

    public function test_late_payment_reinstates_the_booking_when_the_slot_is_still_free(): void
    {
        Mail::fake();
        $this->photographer->forceFill(['plan_id' => $this->proPlan->id])->save();
        $this->mockGateway(new PaymentStatusData(found: true, status: 'SUBMITTED', depositId: 'x', amount: 30000, currency: 'RWF'));
        $this->post($this->bookUrl(), $this->bookingPayload());
        Carbon::setTestNow(now()->addMinutes(20));
        $this->artisan('bookings:expire-holds');
        $payment = Payment::firstOrFail();

        app(FinalizePawaPayPayment::class)->execute($payment->pawapay_deposit_id, new PaymentStatusData(
            found: true, status: 'COMPLETED', depositId: $payment->pawapay_deposit_id, amount: 30000, currency: 'RWF',
        ));

        $booking = Booking::firstOrFail();
        $this->assertSame('confirmed', $booking->status->value);
        $this->assertSame('paid', $booking->deposit_status);
    }

    public function test_deposits_are_not_counted_as_platform_revenue(): void
    {
        $booking = $this->makeBooking();
        Payment::create([
            'uuid' => Uuid::uuid7()->toString(), 'user_id' => $this->photographer->id, 'booking_id' => $booking->id,
            'purpose' => 'booking_deposit', 'amount' => 30000, 'currency' => 'RWF', 'phone_number' => '250788123456',
            'provider' => 'MTN', 'idempotency_key' => 'k1', 'pawapay_deposit_id' => Uuid::uuid4()->toString(), 'status' => 'completed',
        ]);

        $this->assertEquals(0, app(\App\Queries\Admin\AdminDashboardQuery::class)->get()['totalRevenue']);
    }

    // --- Studio ---

    public function test_resubmitting_a_status_does_not_email_the_client_again(): void
    {
        Event::fake([BookingStatusChanged::class]);
        $booking = $this->makeBooking();

        $this->actingAs($this->photographer)
            ->patch(route('studio.bookings.status', $booking->uuid), ['status' => 'confirmed'])
            ->assertRedirect();

        Event::assertNotDispatched(BookingStatusChanged::class);

        $this->actingAs($this->photographer)
            ->patch(route('studio.bookings.status', $booking->uuid), ['status' => 'cancelled']);

        Event::assertDispatched(BookingStatusChanged::class, fn ($e) => $e->oldStatus === 'confirmed' && $e->newStatus === 'cancelled');
    }

    public function test_photographer_can_add_a_booking_manually(): void
    {
        Mail::fake();

        $this->actingAs($this->photographer)->post(route('studio.bookings.store'), [
            'client_name' => 'Walk In',
            'client_email' => 'walkin@example.com',
            'package_id' => (string) $this->package->uuid,
            'date' => '2026-11-02',
            'time' => '18:30',
            'status' => 'confirmed',
            'notify_client' => '1',
        ])->assertSessionHas('success');

        $booking = Booking::firstOrFail();
        $this->assertSame('confirmed', $booking->status->value);
        $this->assertSame('16:30', $booking->starts_at->utc()->format('H:i'));
        $this->assertSame('walkin@example.com', $booking->client->email);

        Mail::assertNotQueued(NewBookingPhotographerMail::class);
        Mail::assertQueued(BookingStatusUpdatedMail::class, fn ($mail) => $mail->hasTo('walkin@example.com'));
    }

    public function test_manual_booking_warns_about_overlaps_unless_forced(): void
    {
        $this->makeBooking();
        $payload = [
            'client_name' => 'Walk In',
            'date' => '2026-11-04',
            'time' => '14:30',
            'duration_minutes' => 60,
            'status' => 'confirmed',
        ];

        $this->actingAs($this->photographer)->post(route('studio.bookings.store'), $payload)->assertSessionHas('error');
        $this->assertSame(1, Booking::count());

        $this->actingAs($this->photographer)->post(route('studio.bookings.store'), $payload + ['allow_overlap' => '1'])->assertSessionHas('success');
        $this->assertSame(2, Booking::count());
    }

    public function test_package_buffers_are_saved(): void
    {
        $this->actingAs($this->photographer)->post(route('studio.packages.store'), [
            'name' => 'Wedding',
            'price' => 500000,
            'currency' => 'RWF',
            'duration_minutes' => 240,
            'buffer_before_minutes' => 30,
            'buffer_after_minutes' => 45,
            'deposit_type' => 'none',
            'is_active' => '1',
        ])->assertSessionHas('success');

        $package = Package::where('name', 'Wedding')->firstOrFail();
        $this->assertSame(30, $package->buffer_before_minutes);
        $this->assertSame(45, $package->buffer_after_minutes);
    }

    public function test_calendar_file_describes_the_session_in_utc(): void
    {
        $booking = $this->makeBooking(['location' => 'Studio, Kigali']);

        $ics = BookingCalendar::ics($booking);

        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('DTSTART:20261104T120000Z', $ics);
        $this->assertStringContainsString('DTEND:20261104T130000Z', $ics);
        $this->assertStringContainsString('LOCATION:Studio\, Kigali', $ics);
        $this->assertStringContainsString('SUMMARY:Portrait with Alex Morgan', $ics);
    }

    public function test_free_photographers_with_a_deposit_are_prompted_to_upgrade(): void
    {
        $this->actingAs($this->photographer)->get(route('studio.packages.index'))
            ->assertOk()
            ->assertSee('Upgrade to collect deposits')
            ->assertSee(route('studio.billing.checkout', 'pro'), false);

        $this->photographer->forceFill(['plan_id' => $this->proPlan->id])->save();
        $this->actingAs($this->photographer->fresh())->get(route('studio.packages.index'))
            ->assertOk()
            ->assertDontSee('Upgrade to collect deposits')
            ->assertSee('paid by Mobile Money at booking');
    }

    public function test_has_feature_reads_the_plan_matrix(): void
    {
        $this->assertFalse($this->photographer->hasFeature('booking_deposits'));

        $this->photographer->forceFill(['plan_id' => $this->proPlan->id])->save();
        $this->assertTrue($this->photographer->fresh()->hasFeature('booking_deposits'));
    }
}
