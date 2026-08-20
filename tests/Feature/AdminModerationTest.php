<?php

use App\Models\Admin;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceChangeRequest;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('admin can bulk approve pending services', function () {
    Event::fake();

    $admin = Admin::create([
        'name' => 'Moderator',
        'email' => 'moderator@test.local',
        'password' => bcrypt('password'),
    ]);

    $category = ServiceCategory::create(['name' => ['ru' => 'Категория', 'en' => 'Category'], 'sort_order' => 1]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour'], 'sort_order' => 1]);

    $idol = User::factory()->create(['is_idol' => true]);

    $service1 = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Услуга 1'],
        'price' => 1000,
        'status' => 'pending',
    ]);

    $service2 = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Услуга 2'],
        'price' => 1500,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.services.moderation.bulk-approve'), [
        'ids' => [$service1->id, $service2->id],
    ]);

    $response->assertSessionHas('success');
    expect($service1->fresh()->status)->toBe('approved');
    expect($service2->fresh()->status)->toBe('approved');
});

test('admin can reject a pending service with a reason', function () {
    Event::fake();

    $admin = Admin::create([
        'name' => 'Moderator',
        'email' => 'moderator2@test.local',
        'password' => bcrypt('password'),
    ]);

    $category = ServiceCategory::create(['name' => ['ru' => 'Категория', 'en' => 'Category'], 'sort_order' => 1]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour'], 'sort_order' => 1]);

    $idol = User::factory()->create(['is_idol' => true]);

    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Неподходящая услуга'],
        'price' => 1000,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->from(route('admin.services.moderation.index'))
        ->post(route('admin.services.moderation.decide', $service), [
            'decision' => 'rejected',
            'rejection_reason' => 'Нарушение правил оформления',
        ]);

    $response->assertRedirect(route('admin.services.moderation.index'));
    $response->assertSessionHas('success');
    expect($service->fresh()->status)->toBe('rejected');
    expect($service->fresh()->rejection_reason)->toBe('Нарушение правил оформления');
});

test('admin can approve service change request and apply updated price', function () {
    Event::fake();

    $admin = Admin::create([
        'name' => 'Moderator',
        'email' => 'moderator3@test.local',
        'password' => bcrypt('password'),
    ]);

    $category = ServiceCategory::create(['name' => ['ru' => 'Категория', 'en' => 'Category'], 'sort_order' => 1]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour'], 'sort_order' => 1]);

    $idol = User::factory()->create(['is_idol' => true]);

    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Услуга'],
        'price' => 1000,
        'status' => 'approved',
    ]);

    $changeRequest = ServiceChangeRequest::create([
        'service_id' => $service->id,
        'changed_fields' => ['price'],
        'pending_price' => 2000,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->from(route('admin.services.change-requests.index'))
        ->post(route('admin.services.change-requests.decide', $changeRequest), [
            'decision' => 'approved',
        ]);

    $response->assertRedirect(route('admin.services.change-requests.index'));
    $response->assertSessionHas('success');
    expect($changeRequest->fresh()->status)->toBe('approved');
    expect($service->fresh()->price)->toBe(2000);
});
