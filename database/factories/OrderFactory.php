<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'idol_id' => User::factory()->create(['is_idol' => true])->id,
            'conversation_id' => \App\Models\Conversation::factory(),
            'status' => OrderStatus::Pending,
        ];
    }

    public function withItem(?\App\Models\Service $service = null, ?float $price = null, int $quantity = 1): static
    {
        return $this->afterCreating(function (Order $order) use ($service, $price, $quantity) {
            $srv = $service ?? \App\Models\Service::where('user_id', $order->idol_id)->first();
            if (! $srv) {
                $category = \App\Models\ServiceCategory::first() ?? \App\Models\ServiceCategory::create([
                    'name' => ['ru' => 'Тестовая категория', 'en' => 'Test Category'],
                    'accent_color' => '#dc8add',
                    'is_active' => true,
                ]);
                $timeUnit = \App\Models\ServiceTimeUnit::first() ?? \App\Models\ServiceTimeUnit::create([
                    'name' => ['ru' => '1 час', 'en' => '1 hour'],
                    'is_active' => true,
                ]);
                $srv = \App\Models\Service::create([
                    'user_id' => $order->idol_id,
                    'category_id' => $category->id,
                    'time_unit_id' => $timeUnit->id,
                    'name' => 'Тестовая услуга',
                    'price' => 500.00,
                    'is_active' => true,
                    'status' => 'approved',
                ]);
            }
            $itemPrice = $price ?? (float) ($srv->price ?? 500.00);

            $order->items()->create([
                'service_id' => $srv->id,
                'quantity' => $quantity,
                'price' => $itemPrice,
            ]);
        });
    }
}
