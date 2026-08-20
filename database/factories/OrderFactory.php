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
}
