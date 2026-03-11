<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServiceWouldBuy;
use App\Notifications\WouldBuyNotification;
use App\Services\IdolRatingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceWouldBuyController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        $userId = $request->user()->id;

        // Cannot "would buy" own service
        if ($service->user_id === $userId) {
            return back()->with('error', 'Нельзя голосовать за свою услугу.');
        }

        $cooldownDays = (int) PlatformSetting::get('would_buy_cooldown_days', 7);

        $last = ServiceWouldBuy::where('service_id', $service->id)
            ->where('user_id', $userId)
            ->latest('created_at')
            ->first();

        if ($last && $last->created_at->diffInDays(now()) < $cooldownDays) {
            $daysLeft = $cooldownDays - (int) $last->created_at->diffInDays(now());
            return back()->with('error', "Подожди ещё {$daysLeft} " . $this->pluralDays($daysLeft) . '.');
        }

        // Upsert (unique index on service_id, user_id)
        ServiceWouldBuy::updateOrCreate(
            ['service_id' => $service->id, 'user_id' => $userId],
            ['created_at' => now()]
        );

        // Notify idol
        $idol = $service->user;
        $idol->notify(new WouldBuyNotification($service, $request->user()));

        // Adjust rating
        IdolRatingService::adjust($idol, 'would_buy_clicked');

        return back()->with('success', 'Спасибо за сигнал!');
    }

    private function pluralDays(int $n): string
    {
        $mod10  = $n % 10;
        $mod100 = $n % 100;

        if ($mod100 >= 11 && $mod100 <= 14) return 'дней';
        if ($mod10 === 1) return 'день';
        if ($mod10 >= 2 && $mod10 <= 4) return 'дня';
        return 'дней';
    }
}
