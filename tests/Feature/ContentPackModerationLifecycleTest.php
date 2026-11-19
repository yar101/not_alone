<?php

use App\Models\Admin;
use App\Models\ContentPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('Content pack full moderation lifecycle: pending -> remarks -> resubmitted -> approved', function () {
    $admin = Admin::create([
        'name' => 'Admin Moderator',
        'email' => 'mod_'.uniqid().'@not-alone.pw',
        'password' => bcrypt('password'),
    ]);

    $idol = User::factory()->create([
        'is_idol' => true,
    ]);

    // 1. Айдол создал новый пак -> статус pending_review, resubmitted_at null
    $pack = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Оригинальный пак',
        'description' => 'Описание пака',
        'price' => 500,
        'status' => 'pending_review',
        'resubmitted_at' => null,
    ]);

    // Проверяем, что в админке пак отображается в табе pending_review
    $respPending = $this->actingAs($admin, 'admin')->get('/admin/content-packs?status=pending_review');
    $respPending->assertOk();
    $respPending->assertInertia(fn (Assert $page) => $page
        ->component('Admin/ContentPacks/Index')
        ->has('packs.data', 1)
        ->where('packs.data.0.id', $pack->id)
        ->where('counts.pending_review', 1)
        ->where('counts.resubmitted', 0)
    );

    // В табе resubmitted пака быть не должно
    $respResubmittedEmpty = $this->actingAs($admin, 'admin')->get('/admin/content-packs?status=resubmitted');
    $respResubmittedEmpty->assertInertia(fn (Assert $page) => $page
        ->has('packs.data', 0)
    );

    // 2. Админ выставил замечание
    $decideResp = $this->actingAs($admin, 'admin')->post(route('admin.content-packs.decide', $pack), [
        'decision' => 'has_remarks',
        'flagged_fields' => ['title'],
        'field_comments' => ['title' => 'Исправьте заголовок пака'],
    ]);
    $decideResp->assertRedirect();

    $pack->refresh();
    expect($pack->status)->toBe('has_remarks');
    expect($pack->resubmitted_at)->toBeNull();

    // Проверяем, что в админке пак теперь в has_remarks
    $respRemarks = $this->actingAs($admin, 'admin')->get('/admin/content-packs?status=has_remarks');
    $respRemarks->assertInertia(fn (Assert $page) => $page
        ->has('packs.data', 1)
        ->where('packs.data.0.id', $pack->id)
        ->where('counts.has_remarks', 1)
        ->where('counts.pending_review', 0)
        ->where('counts.resubmitted', 0)
    );

    // 3. Айдол исправил замечание и отправил на повторную модерацию
    $updateResp = $this->actingAs($idol)->patch(route('content-packs.update', $pack), [
        'title' => 'Исправленный пак',
    ]);
    $updateResp->assertRedirect();

    $pack->refresh();
    expect($pack->status)->toBe('pending_review');
    expect($pack->resubmitted_at)->not->toBeNull();
    expect($pack->title)->toBe('Исправленный пак');

    // 4. Проверяем отображение в админке:
    // В табе resubmitted пак ДОЛЖЕН отображаться, а счетчик resubmitted равен 1!
    $respResubmitted = $this->actingAs($admin, 'admin')->get('/admin/content-packs?status=resubmitted');
    $respResubmitted->assertInertia(fn (Assert $page) => $page
        ->has('packs.data', 1)
        ->where('packs.data.0.id', $pack->id)
        ->where('packs.data.0.title', 'Исправленный пак')
        ->where('counts.resubmitted', 1)
        ->where('counts.pending_review', 0)
    );

    // В табе pending_review (первичные) пака нет, так как он переподан
    $respPendingAfter = $this->actingAs($admin, 'admin')->get('/admin/content-packs?status=pending_review');
    $respPendingAfter->assertInertia(fn (Assert $page) => $page
        ->has('packs.data', 0)
    );

    // В табе "all" пак также отображается
    $respAll = $this->actingAs($admin, 'admin')->get('/admin/content-packs?status=all');
    $respAll->assertInertia(fn (Assert $page) => $page
        ->has('packs.data', 1)
        ->where('packs.data.0.id', $pack->id)
    );

    // На странице детального просмотра пака resubmitted_at передается
    $respShow = $this->actingAs($admin, 'admin')->get(route('admin.content-packs.show', $pack));
    $respShow->assertOk();
    $respShow->assertInertia(fn (Assert $page) => $page
        ->component('Admin/ContentPacks/Show')
        ->where('pack.id', $pack->id)
        ->where('pack.resubmitted_at', fn ($val) => ! empty($val))
    );

    // 5. Админ одобряет переподанный пак
    $approveResp = $this->actingAs($admin, 'admin')->post(route('admin.content-packs.decide', $pack), [
        'decision' => 'approved',
    ]);
    $approveResp->assertRedirect();

    $pack->refresh();
    expect($pack->status)->toBe('approved');
    expect($pack->resubmitted_at)->toBeNull();

    // Проверяем, что пак переместился в approved, а resubmitted счетчик обнулился
    $respApproved = $this->actingAs($admin, 'admin')->get('/admin/content-packs?status=approved');
    $respApproved->assertInertia(fn (Assert $page) => $page
        ->has('packs.data', 1)
        ->where('packs.data.0.id', $pack->id)
        ->where('counts.approved', 1)
        ->where('counts.resubmitted', 0)
    );
});
