<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Console\Command;

class GenerateTestOrders extends Command
{
    protected $signature = 'orders:generate';
    protected $description = 'Создать тестовые заказы для конкретного пользователя';

    public function handle(): void
    {
        // Customer
        $customerId = (int) $this->ask('ID заказчика (customer)');
        $customer   = User::find($customerId);
        if (!$customer) {
            $this->error("Пользователь #{$customerId} не найден.");
            return;
        }
        $this->line("Заказчик: {$customer->name}");

        // Idol
        $idolIdInput = $this->ask('ID айдола (оставьте пустым для случайного)');
        if ($idolIdInput) {
            $idol = User::where('id', (int) $idolIdInput)->where('is_idol', true)->first();
            if (!$idol) {
                $this->error("Айдол #{$idolIdInput} не найден или не является айдолом.");
                return;
            }
        } else {
            $idol = User::where('is_idol', true)->where('id', '!=', $customerId)->inRandomOrder()->first();
            if (!$idol) {
                $this->error('Нет доступных айдолов.');
                return;
            }
        }
        $this->line("Айдол: {$idol->name}");

        // Count
        $count = (int) $this->ask('Количество заказов', 5);
        if ($count < 1) {
            $this->error('Количество должно быть больше 0.');
            return;
        }

        // Status
        $statuses   = array_column(OrderStatus::cases(), 'value');
        $statusChoice = $this->choice('Статус заказов', $statuses, 'completed');
        $status       = OrderStatus::from($statusChoice);

        // Service for this idol
        $service = Service::where('user_id', $idol->id)->inRandomOrder()->first();
        if (!$service) {
            $this->error("У айдола {$idol->name} нет услуг. Создайте хотя бы одну.");
            return;
        }

        $this->line("Услуга: {$service->name} ({$service->price} ₽)");
        $this->line("Создаю {$count} заказов со статусом «{$status->label()}»…");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $createdAt = now()->subMinutes(rand(30, 10000));

            $attrs = [
                'customer_id' => $customer->id,
                'idol_id'     => $idol->id,
                'status'      => $status,
                'created_at'  => $createdAt,
                'updated_at'  => $createdAt,
            ];

            switch ($status) {
                case OrderStatus::Paid:
                    $attrs['paid_at'] = $createdAt;
                    break;
                case OrderStatus::Completed:
                    // completed_at в последние 55 минут, чтобы заказ попал в окно для спора
                    $completedAt = now()->subMinutes(rand(1, 55));
                    $attrs['paid_at']      = $completedAt->copy()->subHours(rand(2, 10));
                    $attrs['completed_at'] = $completedAt;
                    $attrs['completion_confirmed_by_idol']     = true;
                    $attrs['completion_confirmed_by_customer'] = true;
                    break;
                case OrderStatus::Cancelled:
                    $attrs['cancel_reason'] = 'Тестовая отмена';
                    $attrs['cancelled_by']  = $customer->id;
                    break;
                case OrderStatus::Refunded:
                case OrderStatus::Disputed:
                    $attrs['paid_at']      = $createdAt->copy()->subHours(rand(2, 10));
                    $attrs['completed_at'] = $createdAt->copy()->subMinutes(rand(5, 60));
                    break;
            }

            $order = Order::create($attrs);

            $conversation = Conversation::create([
                'order_id'   => $order->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $order->update(['conversation_id' => $conversation->id]);

            // Добавляем участников, иначе чат вернёт 403
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id'         => $customer->id,
                'created_at'      => $createdAt,
                'updated_at'      => $createdAt,
            ]);
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id'         => $idol->id,
                'created_at'      => $createdAt,
                'updated_at'      => $createdAt,
            ]);

            OrderItem::create([
                'order_id'   => $order->id,
                'service_id' => $service->id,
                'quantity'   => 1,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Готово! Создано {$count} заказов.");
    }
}
