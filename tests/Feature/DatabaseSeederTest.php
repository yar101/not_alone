<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('TestUsersSeeder seeds users and content pack purchases cleanly even when initial user id is offset', function () {
    // Create pre-existing users so auto-increment is offset and user ID 1 is not the first seeder user
    $preexistingUser = User::factory()->create([
        'email' => 'preexisting@example.com',
        'name' => 'Preexisting User',
    ]);
    expect($preexistingUser->id)->toBeGreaterThan(0);

    // Run TestUsersSeeder
    $this->seed(TestUsersSeeder::class);

    // Verify test users were created
    $u1 = User::where('email', 'u1@test.com')->first();
    expect($u1)->not->toBeNull();
    expect($u1->id)->not->toBe($preexistingUser->id);

    // Verify purchases were created and tied to u1's real user_id
    $purchases = \App\Models\ContentPackPurchase::where('user_id', $u1->id)->get();
    expect($purchases->count())->toBeGreaterThan(0);
});

test('DatabaseSeeder executes successfully and seeds only production system data without fake users', function () {
    $this->seed(DatabaseSeeder::class);

    // Production safety invariant: no mock users seeded by DatabaseSeeder
    expect(User::count())->toBe(0);
    expect(\App\Models\Admin::where('email', 'ia@not-alone.pw')->exists())->toBeTrue();
    expect(\App\Models\IdolQuizQuestion::count())->toBe(55);
    expect(\App\Models\IdolArticleVersion::active()->exists())->toBeTrue();
    expect(\App\Models\PersonalityTrait::count())->toBe(30);
    expect(\App\Models\ServiceCategory::count())->toBe(13);
    expect(\App\Models\ServiceTimeUnit::count())->toBe(9);
});

test('DevDatabaseSeeder executes successfully and seeds full dev environment', function () {
    $this->seed(\Database\Seeders\DevDatabaseSeeder::class);

    expect(User::where('email', 'u1@test.com')->exists())->toBeTrue();
    expect(\App\Models\Service::count())->toBeGreaterThan(0);
    expect(\App\Models\Order::count())->toBeGreaterThan(0);

    // Ensure all seeded orders have positive amounts (no zero orders)
    $orders = \App\Models\Order::with('items')->get();
    foreach ($orders as $order) {
        expect($order->total_price)->toBeGreaterThan(0.0);
    }

    expect(\App\Models\News::count())->toBeGreaterThan(0);
});
