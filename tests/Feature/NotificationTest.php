<?php

use App\Models\Admin;
use App\Models\AdminBroadcast;
use App\Models\AdminBroadcastRead;
use App\Models\User;
use App\Notifications\TestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('user can fetch combined notifications with personal notifications and admin broadcasts', function () {
    Event::fake();

    $admin = Admin::create([
        'name' => 'SuperAdmin',
        'email' => 'admin@platform.local',
        'password' => bcrypt('password'),
    ]);

    $user = User::factory()->create();

    // 1. Create a personal DB notification
    $user->notify(new TestNotification('Тестовое уведомление'));

    // 2. Create an admin broadcast for all users
    $broadcast = AdminBroadcast::create([
        'admin_id' => $admin->id,
        'title' => ['ru' => 'Новость платформы', 'en' => 'Platform news'],
        'body' => ['ru' => 'Текст новости', 'en' => 'News body'],
        'target' => 'all',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson(route('notifications.combined'));
    $response->assertOk();

    $items = $response->json('items');
    expect($items)->toHaveCount(2);

    $types = collect($items)->pluck('type')->all();
    expect($types)->toContain('test');
    expect($types)->toContain('admin_broadcast');
});

test('user can mark individual notification as read', function () {
    $user = User::factory()->create();
    $user->notify(new TestNotification('Сообщение'));

    $notification = $user->unreadNotifications()->first();
    expect($notification)->not->toBeNull();

    $response = $this->actingAs($user)->patchJson(route('notifications.read', $notification->id));
    $response->assertOk();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('user can mark all service broadcasts as read in bulk', function () {
    $admin = Admin::create([
        'name' => 'SuperAdmin2',
        'email' => 'admin2@platform.local',
        'password' => bcrypt('password'),
    ]);

    $user = User::factory()->create();

    $bc1 = AdminBroadcast::create([
        'admin_id' => $admin->id,
        'title' => ['ru' => 'Рассылка 1'],
        'body' => ['ru' => 'Текст 1'],
        'target' => 'all',
    ]);

    $bc2 = AdminBroadcast::create([
        'admin_id' => $admin->id,
        'title' => ['ru' => 'Рассылка 2'],
        'body' => ['ru' => 'Текст 2'],
        'target' => 'all',
    ]);

    $response = $this->actingAs($user)->patchJson(route('notifications.service.read-all'));
    $response->assertOk();

    expect(AdminBroadcastRead::where('user_id', $user->id)->count())->toBe(2);
});
