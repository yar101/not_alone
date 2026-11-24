<?php

namespace Tests\Feature;

use App\Models\TaxIdCooldown;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdolOnboardingBaasTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_idol_client_cannot_access_idol_onboarding(): void
    {
        $client = User::factory()->create([
            'is_idol' => false,
        ]);

        $response = $this->actingAs($client)->get(route('profile.idol_onboarding'));

        $response->assertRedirect(route('idol.apply'));
    }

    public function test_idol_can_view_onboarding_page(): void
    {
        $idol = User::factory()->create([
            'is_idol' => true,
            'tax_status' => 'none',
        ]);

        $response = $this->actingAs($idol)->get(route('profile.idol_onboarding'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/IdolOnboarding')
            ->has('sbpBanks')
            ->where('taxStatus', 'none')
            ->where('isPayoutReady', false)
        );
    }

    public function test_submitting_invalid_inn_checksum_is_rejected(): void
    {
        $idol = User::factory()->create(['is_idol' => true]);

        $response = $this->actingAs($idol)->post(route('profile.idol_onboarding.inn'), [
            'inn' => '111111111111', // Invalid checksum
        ]);

        $response->assertSessionHasErrors('inn');
        $this->assertNull($idol->fresh()->inn);
    }

    public function test_submitting_inn_under_active_cooldown_is_rejected(): void
    {
        $idol = User::factory()->create(['is_idol' => true]);
        $otherUser = User::factory()->create();

        $validInn = '500100732259';

        TaxIdCooldown::create([
            'inn' => $validInn,
            'user_id' => $otherUser->id,
            'revoked_at' => now()->subDays(10),
            'cooldown_until' => now()->addDays(355),
            'reason' => 'Профиль закрыт администратором',
        ]);

        $response = $this->actingAs($idol)->post(route('profile.idol_onboarding.inn'), [
            'inn' => $validInn,
        ]);

        $response->assertSessionHasErrors('inn');
        $this->assertNull($idol->fresh()->inn);
    }

    public function test_submitting_inn_already_registered_to_another_user_is_rejected(): void
    {
        $validInn = '500100732259';
        User::factory()->create([
            'is_idol' => true,
            'inn' => $validInn,
        ]);

        $idol = User::factory()->create(['is_idol' => true]);

        $response = $this->actingAs($idol)->post(route('profile.idol_onboarding.inn'), [
            'inn' => $validInn,
        ]);

        $response->assertSessionHasErrors('inn');
        $this->assertNull($idol->fresh()->inn);
    }

    public function test_submitting_valid_inn_verifies_self_employed_status(): void
    {
        $idol = User::factory()->create(['is_idol' => true]);
        $validInn = '500100732259';

        $response = $this->actingAs($idol)->post(route('profile.idol_onboarding.inn'), [
            'inn' => $validInn,
        ]);

        $response->assertSessionHasNoErrors();
        $fresh = $idol->fresh();
        $this->assertSame($validInn, $fresh->inn);
        $this->assertSame('self_employed', $fresh->tax_status);
        $this->assertNotNull($fresh->tax_verified_at);
    }

    public function test_saving_sbp_payout_requisites_activates_payout_ready_when_self_employed(): void
    {
        $idol = User::factory()->create([
            'is_idol' => true,
            'inn' => '500100732259',
            'tax_status' => 'self_employed',
            'tax_verified_at' => now(),
            'is_payout_ready' => false,
        ]);

        $response = $this->actingAs($idol)->post(route('profile.idol_onboarding.payout_requisites'), [
            'phone' => '89998887766',
            'bank_id' => '100000000004',
        ]);

        $response->assertSessionHasNoErrors();
        $fresh = $idol->fresh();
        $this->assertSame('+79998887766', $fresh->payout_phone);
        $this->assertSame('100000000004', $fresh->sbp_bank_id);
        $this->assertTrue($fresh->is_payout_ready);
        $this->assertNotNull($fresh->payout_requisites_changed_at);
    }

    public function test_saving_sbp_requisites_without_self_employed_keeps_payout_not_ready(): void
    {
        $idol = User::factory()->create([
            'is_idol' => true,
            'tax_status' => 'none',
            'is_payout_ready' => false,
        ]);

        $response = $this->actingAs($idol)->post(route('profile.idol_onboarding.payout_requisites'), [
            'phone' => '+79991112233',
            'bank_id' => '100000000008',
        ]);

        $response->assertSessionHasNoErrors();
        $fresh = $idol->fresh();
        $this->assertSame('+79991112233', $fresh->payout_phone);
        $this->assertFalse($fresh->is_payout_ready);
    }

    public function test_sync_tax_status_updates_user_state(): void
    {
        $idol = User::factory()->create([
            'is_idol' => true,
            'inn' => '500100732259',
            'tax_status' => 'pending_binding',
            'payout_phone' => '+79998887766',
            'sbp_bank_id' => '100000000004',
            'is_payout_ready' => false,
        ]);

        $response = $this->actingAs($idol)->post(route('profile.idol_onboarding.sync'));

        $response->assertSessionHasNoErrors();
        $fresh = $idol->fresh();
        $this->assertSame('self_employed', $fresh->tax_status);
        $this->assertTrue($fresh->is_payout_ready);
    }
}
