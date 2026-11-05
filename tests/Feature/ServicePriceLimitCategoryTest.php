<?php

use App\Models\Admin;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePriceLimit;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createPriceLimitTestAdmin(): Admin
{
    return Admin::create([
        'name' => 'Admin User',
        'email' => 'admin_test_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);
}

test('admin can view price limits page with categories and limits', function () {
    $admin = createPriceLimitTestAdmin();
    $category = ServiceCategory::create([
        'name' => ['ru' => 'Консультации', 'en' => 'Consultations'],
        'sort_order' => 1,
        'is_active' => true,
    ]);

    ServicePriceLimit::create([
        'category_id' => $category->id,
        'max_price' => 1000,
        'low_rating_max_price' => 500,
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.services.price-limits.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Services/Index')
            ->where('active_tab', 'price-limits')
            ->has('limits', 1)
            ->where('limits.0.category_id', $category->id)
            ->where('limits.0.max_price', 1000)
            ->where('limits.0.low_rating_max_price', 500)
            ->where('limits.0.category.name', 'Консультации')
            ->has('categories')
        );
});

test('admin can create price limit for category', function () {
    $admin = createPriceLimitTestAdmin();
    $category = ServiceCategory::create([
        'name' => ['ru' => 'Общение', 'en' => 'Chat'],
        'sort_order' => 1,
    ]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.services.price-limits.store'), [
            'category_id' => $category->id,
            'max_price' => 1500,
            'low_rating_max_price' => 800,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $limit = ServicePriceLimit::where('category_id', $category->id)->first();
    expect($limit)->not->toBeNull()
        ->and($limit->max_price)->toBe(1500)
        ->and($limit->low_rating_max_price)->toBe(800);
});

test('admin cannot create price limit with low_rating_max_price exceeding max_price', function () {
    $admin = createPriceLimitTestAdmin();
    $category = ServiceCategory::create([
        'name' => ['ru' => 'Общение', 'en' => 'Chat'],
        'sort_order' => 1,
    ]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.services.price-limits.store'), [
            'category_id' => $category->id,
            'max_price' => 500,
            'low_rating_max_price' => 1000,
        ])
        ->assertSessionHasErrors(['low_rating_max_price']);

    expect(ServicePriceLimit::where('category_id', $category->id)->exists())->toBeFalse();
});

test('admin cannot create price limit without any price specified', function () {
    $admin = createPriceLimitTestAdmin();
    $category = ServiceCategory::create([
        'name' => ['ru' => 'Общение', 'en' => 'Chat'],
        'sort_order' => 1,
    ]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.services.price-limits.store'), [
            'category_id' => $category->id,
            'max_price' => null,
            'low_rating_max_price' => null,
        ])
        ->assertSessionHasErrors(['max_price']);
});

test('admin cannot create duplicate price limit for same category', function () {
    $admin = createPriceLimitTestAdmin();
    $category = ServiceCategory::create([
        'name' => ['ru' => 'Общение', 'en' => 'Chat'],
        'sort_order' => 1,
    ]);

    ServicePriceLimit::create([
        'category_id' => $category->id,
        'max_price' => 1000,
    ]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.services.price-limits.store'), [
            'category_id' => $category->id,
            'max_price' => 2000,
        ])
        ->assertSessionHasErrors(['category_id']);
});

test('admin can update price limit and cannot set low_rating_max_price higher than max_price', function () {
    $admin = createPriceLimitTestAdmin();
    $category = ServiceCategory::create([
        'name' => ['ru' => 'Общение', 'en' => 'Chat'],
        'sort_order' => 1,
    ]);

    $limit = ServicePriceLimit::create([
        'category_id' => $category->id,
        'max_price' => 1000,
        'low_rating_max_price' => 500,
    ]);

    // Invalid update: low_rating > max_price
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.services.price-limits.update', $limit), [
            'category_id' => $category->id,
            'max_price' => 800,
            'low_rating_max_price' => 1200,
        ])
        ->assertSessionHasErrors(['low_rating_max_price']);

    // Valid update
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.services.price-limits.update', $limit), [
            'category_id' => $category->id,
            'max_price' => 2000,
            'low_rating_max_price' => 1000,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $limit->refresh();
    expect($limit->max_price)->toBe(2000)
        ->and($limit->low_rating_max_price)->toBe(1000);
});

test('admin can delete price limit', function () {
    $admin = createPriceLimitTestAdmin();
    $category = ServiceCategory::create([
        'name' => ['ru' => 'Общение', 'en' => 'Chat'],
        'sort_order' => 1,
    ]);

    $limit = ServicePriceLimit::create([
        'category_id' => $category->id,
        'max_price' => 1000,
    ]);

    $this->actingAs($admin, 'admin')
        ->delete(route('admin.services.price-limits.destroy', $limit))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(ServicePriceLimit::where('id', $limit->id)->exists())->toBeFalse();
});

test('general limit blocks exceeding price for any idol', function () {
    $category = ServiceCategory::create(['name' => ['ru' => 'Игры', 'en' => 'Games'], 'sort_order' => 1]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour'], 'sort_order' => 1]);

    ServicePriceLimit::create([
        'category_id' => $category->id,
        'max_price' => 1000,
        'low_rating_max_price' => null,
    ]);

    $idol = User::factory()->create([
        'is_idol' => true,
        'rating' => 85.00,
    ]);

    // Exceeds max_price
    $this->actingAs($idol)->post(route('profile.services.store'), [
        'name_ru' => 'Играем вместе',
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'price' => 1200,
    ])
        ->assertSessionHasErrors([
            'price' => 'Цена услуги не может превышать 1000 ₽ для данной категории.',
        ]);

    // Allowed within max_price
    $this->actingAs($idol)->post(route('profile.services.store'), [
        'name_ru' => 'Играем вместе',
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'price' => 1000,
    ])
        ->assertRedirect();
});

test('low rating limit blocks price only when rating is below threshold', function () {
    PlatformSetting::set('rating_low_threshold', 30);

    $category = ServiceCategory::create(['name' => ['ru' => 'Музыка', 'en' => 'Music'], 'sort_order' => 1]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour'], 'sort_order' => 1]);

    ServicePriceLimit::create([
        'category_id' => $category->id,
        'max_price' => 2000,
        'low_rating_max_price' => 500,
    ]);

    $lowRatingIdol = User::factory()->create([
        'is_idol' => true,
        'rating' => 20.00,
    ]);

    // Low rating idol exceeds low_rating_max_price (600 > 500)
    $this->actingAs($lowRatingIdol)->post(route('profile.services.store'), [
        'name_ru' => 'Урок вокала',
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'price' => 600,
    ])
        ->assertSessionHasErrors([
            'price' => 'При вашем рейтинге цена не может превышать 500 ₽ для данной категории.',
        ]);

    // Low rating idol within low_rating_max_price (500 <= 500)
    $this->actingAs($lowRatingIdol)->post(route('profile.services.store'), [
        'name_ru' => 'Урок вокала',
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'price' => 500,
    ])
        ->assertRedirect();
});

test('idol with normal rating can set price above low_rating_max_price but below max_price', function () {
    PlatformSetting::set('rating_low_threshold', 30);

    $category = ServiceCategory::create(['name' => ['ru' => 'Спорт', 'en' => 'Sport'], 'sort_order' => 1]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour'], 'sort_order' => 1]);

    ServicePriceLimit::create([
        'category_id' => $category->id,
        'max_price' => 2000,
        'low_rating_max_price' => 500,
    ]);

    $normalIdol = User::factory()->create([
        'is_idol' => true,
        'rating' => 50.00,
    ]);

    // Normal idol can exceed low_rating_max_price (1500 > 500)
    $this->actingAs($normalIdol)->post(route('profile.services.store'), [
        'name_ru' => 'Тренировка',
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'price' => 1500,
    ])
        ->assertRedirect();

    // Normal idol still cannot exceed max_price (2500 > 2000)
    $this->actingAs($normalIdol)->post(route('profile.services.store'), [
        'name_ru' => 'Тренировка PRO',
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'price' => 2500,
    ])
        ->assertSessionHasErrors([
            'price' => 'Цена услуги не может превышать 2000 ₽ для данной категории.',
        ]);
});

test('service update validates price limit by category', function () {
    $category = ServiceCategory::create(['name' => ['ru' => 'Танцы', 'en' => 'Dance'], 'sort_order' => 1]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour'], 'sort_order' => 1]);

    ServicePriceLimit::create([
        'category_id' => $category->id,
        'max_price' => 1000,
    ]);

    $idol = User::factory()->create(['is_idol' => true, 'rating' => 70.00]);

    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Танцы'],
        'price' => 800,
        'is_active' => true,
        'status' => 'approved',
    ]);

    // Update with price exceeding max_price
    $this->actingAs($idol)->patch(route('profile.services.update', $service), [
        'price' => 1500,
    ])
        ->assertSessionHasErrors([
            'price' => 'Цена услуги не может превышать 1000 ₽ для данной категории.',
        ]);

    // Update within limit
    $this->actingAs($idol)->patch(route('profile.services.update', $service), [
        'price' => 950,
    ])
        ->assertRedirect();

    expect($service->fresh()->pendingChangeRequest)->not->toBeNull();
});
