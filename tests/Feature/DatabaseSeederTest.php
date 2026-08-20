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

test('DatabaseSeeder executes successfully without exceptions', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'u1@test.com')->exists())->toBeTrue();
    expect(\App\Models\Admin::where('email', 'ia@not-alone.pw')->exists())->toBeTrue();
    expect(\App\Models\IdolQuizQuestion::count())->toBe(55);
    expect(\App\Models\IdolArticleVersion::active()->exists())->toBeTrue();
});
