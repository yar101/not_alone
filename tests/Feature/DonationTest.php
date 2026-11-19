<?php

namespace Tests\Feature;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Donation;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\DonationReceivedNotification;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Cache::flush();

    PlatformSetting::set('platform_fee_percent', '10.0');
    PlatformSetting::set('donation_min_amount', '10');
    PlatformSetting::set('donation_max_amount', '15000');
    PlatformSetting::set('donation_daily_limit', '5');
});

it('sends donation in normal mode: debits blue, credits pink immediately with 10% fee', function () {
    $donor = User::factory()->create(['is_idol' => false]);
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $donorWallet = $walletService->getOrCreateWallet($donor);
    $donorWallet->forceFill(['balance' => 1000.00])->save();

    $idolWallet = $walletService->getOrCreateWallet($idol);
    $idolWallet->forceFill(['pink_balance' => 50.00])->save();

    $response = $this->actingAs($donor)->postJson(route('users.donate', $idol->id), [
        'amount' => 500.00,
        'fee_covered_by_donor' => false,
        'message' => 'Спасибо за прекрасные стримы!',
    ]);

    $response->assertOk();
    $response->assertJsonPath('ok', true);

    $donorWallet->refresh();
    $idolWallet->refresh();

    // Donor charged 500 blue
    expect((float) $donorWallet->balance)->toBe(500.00);

    // Idol credited 500 - 10% (50.00) = 450.00 pink => 50 + 450 = 500
    expect((float) $idolWallet->pink_balance)->toBe(500.00);

    $donation = Donation::first();
    expect($donation)->not->toBeNull();
    expect((float) $donation->amount)->toBe(450.00);
    expect((float) $donation->amount_charged)->toBe(500.00);
    expect((float) $donation->platform_fee)->toBe(50.00);
    expect($donation->fee_covered_by_donor)->toBeFalse();
    expect($donation->message)->toBe('Спасибо за прекрасные стримы!');

    // Transactions
    $donorTx = WalletTransaction::where('wallet_id', $donorWallet->id)
        ->where('type', WalletTransactionType::DonationSent)
        ->first();
    expect($donorTx)->not->toBeNull();
    expect((float) $donorTx->amount)->toBe(-500.00);
    expect($donorTx->currency)->toBe('blue');
    expect($donorTx->status)->toBe(WalletTransactionStatus::Completed);

    $idolTx = WalletTransaction::where('wallet_id', $idolWallet->id)
        ->where('type', WalletTransactionType::DonationReceived)
        ->first();
    expect($idolTx)->not->toBeNull();
    expect((float) $idolTx->amount)->toBe(450.00);
    expect($idolTx->currency)->toBe('pink');
    expect($idolTx->status)->toBe(WalletTransactionStatus::Completed);

    Notification::assertSentTo($idol, DonationReceivedNotification::class);
});

it('sends donation with fee covered by donor: idol receives 100%, donor pays fee', function () {
    $donor = User::factory()->create(['is_idol' => false]);
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $donorWallet = $walletService->getOrCreateWallet($donor);
    $donorWallet->forceFill(['balance' => 2000.00])->save();

    $idolWallet = $walletService->getOrCreateWallet($idol);
    $idolWallet->forceFill(['pink_balance' => 0.00])->save();

    // Idol wants 1000 net. With 10% fee, donor must pay 1000 / 0.9 = 1111.11. Fee is 111.11.
    $response = $this->actingAs($donor)->postJson(route('users.donate', $idol->id), [
        'amount' => 1000.00,
        'fee_covered_by_donor' => true,
        'message' => 'Забирай полную сумму!',
    ]);

    $response->assertOk();

    $donorWallet->refresh();
    $idolWallet->refresh();

    // Donor charged 1111.11
    expect((float) $donorWallet->balance)->toBe(888.89);

    // Idol received exactly 1000.00 pink
    expect((float) $idolWallet->pink_balance)->toBe(1000.00);

    $donation = Donation::first();
    expect((float) $donation->amount)->toBe(1000.00);
    expect((float) $donation->amount_charged)->toBe(1111.11);
    expect((float) $donation->platform_fee)->toBe(111.11);
    expect($donation->fee_covered_by_donor)->toBeTrue();
});

it('forbids donating to oneself', function () {
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $wallet = $walletService->getOrCreateWallet($idol);
    $wallet->forceFill(['balance' => 500.00])->save();

    $response = $this->actingAs($idol)->postJson(route('users.donate', $idol->id), [
        'amount' => 100.00,
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => 'Нельзя отправлять донат самому себе.']);
});

it('forbids donating to a non-idol', function () {
    $donor = User::factory()->create(['is_idol' => false]);
    $recipient = User::factory()->create(['is_idol' => false]);

    $walletService = app(WalletService::class);
    $wallet = $walletService->getOrCreateWallet($donor);
    $wallet->forceFill(['balance' => 500.00])->save();

    $response = $this->actingAs($donor)->postJson(route('users.donate', $recipient->id), [
        'amount' => 100.00,
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => 'Донаты можно отправлять только айдолам.']);
});

it('fails when donor has insufficient funds', function () {
    $donor = User::factory()->create(['is_idol' => false]);
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $wallet = $walletService->getOrCreateWallet($donor);
    $wallet->forceFill(['balance' => 50.00])->save();

    $response = $this->actingAs($donor)->postJson(route('users.donate', $idol->id), [
        'amount' => 100.00,
    ]);

    $response->assertStatus(422);
    expect(Donation::count())->toBe(0);
});

it('respects min and max donation limits', function () {
    $donor = User::factory()->create(['is_idol' => false]);
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $wallet = $walletService->getOrCreateWallet($donor);
    $wallet->forceFill(['balance' => 20000.00])->save();

    // Less than min (10)
    $resMin = $this->actingAs($donor)->postJson(route('users.donate', $idol->id), [
        'amount' => 5.00,
    ]);
    $resMin->assertStatus(422);

    // More than max (15000)
    $resMax = $this->actingAs($donor)->postJson(route('users.donate', $idol->id), [
        'amount' => 16000.00,
    ]);
    $resMax->assertStatus(422);
});

it('respects daily donation limit to the same idol', function () {
    $donor = User::factory()->create(['is_idol' => false]);
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $wallet = $walletService->getOrCreateWallet($donor);
    $wallet->forceFill(['balance' => 2000.00])->save();

    // Send 5 donations (the daily limit)
    for ($i = 0; $i < 5; $i++) {
        $res = $this->actingAs($donor)->postJson(route('users.donate', $idol->id), [
            'amount' => 50.00,
        ]);
        $res->assertOk();
    }

    // 6th donation should fail
    $resFail = $this->actingAs($donor)->postJson(route('users.donate', $idol->id), [
        'amount' => 50.00,
    ]);
    $resFail->assertStatus(422);
    $resFail->assertJsonFragment(['message' => 'Превышен дневной лимит донатов (5 в день на одного айдола).']);
});

it('validates message against links and profanity', function () {
    $donor = User::factory()->create(['is_idol' => false]);
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $wallet = $walletService->getOrCreateWallet($donor);
    $wallet->forceFill(['balance' => 1000.00])->save();

    $response = $this->actingAs($donor)->postJson(route('users.donate', $idol->id), [
        'amount' => 100.00,
        'message' => 'Заходи на https://spam.ru за призами!',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('message');
});

it('handles idempotency smoothly without duplicating transactions', function () {
    $donor = User::factory()->create(['is_idol' => false]);
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $wallet = $walletService->getOrCreateWallet($donor);
    $wallet->forceFill(['balance' => 1000.00])->save();

    $idempotencyKey = 'unique_donation_key_777';

    $res1 = $this->actingAs($donor)->postJson(route('users.donate', $idol->id), [
        'amount' => 200.00,
        'idempotency_key' => $idempotencyKey,
    ]);
    $res1->assertOk();

    $res2 = $this->actingAs($donor)->postJson(route('users.donate', $idol->id), [
        'amount' => 200.00,
        'idempotency_key' => $idempotencyKey,
    ]);
    $res2->assertOk();

    expect(Donation::count())->toBe(1);
    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(800.00);
});

it('restricts /users/{user}/donations index to the idol owner only', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $stranger = User::factory()->create(['is_idol' => false]);

    // Stranger tries to view idol's donations
    $response = $this->actingAs($stranger)->getJson(route('users.donations', $idol->id));
    $response->assertStatus(403);

    // Idol views own donations
    $responseOwner = $this->actingAs($idol)->getJson(route('users.donations', $idol->id));
    $responseOwner->assertOk();
});

it('categorizes donation notification as donation in combined notifications and loads profile and donations list', function () {
    $donor = User::factory()->create(['name' => 'Alice Donor', 'is_idol' => false]);
    $idol = User::factory()->create(['name' => 'Bob Idol', 'is_idol' => true]);

    $walletService = app(WalletService::class);
    $donorWallet = $walletService->getOrCreateWallet($donor);
    $donorWallet->forceFill(['balance' => 500.00])->save();

    $donateRes = $this->actingAs($donor)->postJson(route('users.donate', $idol->id), [
        'amount' => 150.00,
        'message' => 'Спасибо за прекрасное настроение!',
    ]);
    $donateRes->assertOk();

    // 1. Check notification was sent to idol
    Notification::assertSentTo($idol, DonationReceivedNotification::class, function ($notification) use ($donateRes) {
        return $notification->donation->id === $donateRes->json('donation.id');
    });

    // 2. Check /users/{user}/donations index loads without avatar_url SQL error
    $donationsIndexRes = $this->actingAs($idol)->getJson(route('users.donations', $idol->id));
    $donationsIndexRes->assertOk();
    $donationsData = $donationsIndexRes->json('data');
    expect($donationsData)->toHaveCount(1);
    expect($donationsData[0]['donor']['name'])->toBe('Alice Donor');
    expect((float) $donationsData[0]['amount'])->toEqual(135.0);

    // 3. Check profile show view loads with defered donations without crashing
    $profileRes = $this->actingAs($idol)->get(route('profile.show', $idol->id));
    $profileRes->assertOk();
});
