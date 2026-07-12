# Система ачивок и рамок для аватарок (Avatar Frames)

Система рамок была переписана для обеспечения производительности и гибкости.
Вместо старых тяжеловесных фоновых джобов (Jobs), которые проверяли всех
пользователей через планировщик, новая система основана на событиях
(Event-Driven Architecture).

## Как это работает?

Рамки могут быть разных типов: бесплатные, платные и те, которые выдаются за
достижения (ачивки). Когда рамка имеет тип `achievement`, она автоматически
выдается пользователю, если он выполняет определенное **условие**.

### Событийно-ориентированная выдача

1. Когда в системе происходит какое-то важное действие (например, завершение
   заказа), генерируется событие. В текущей реализации это
   `OrderCompletedEvent`.
2. Слушатель `AchievementListener` ловит это событие (он добавляется в очередь
   `ShouldQueue`, поэтому не блокирует запросы пользователя).
3. Слушатель берет пользователя (например, клиента или айдола) и достает все
   рамки типа `achievement`, которых у него еще нет.
4. Для каждой такой рамки он проверяет её класс-условие (`condition_class`).
5. Если пользователь удовлетворяет условию, рамка привязывается к нему
   (`$user->avatarFrames()->attach(...)`).

## Как добавить новую ачивку?

Добавление новой ачивки и рамки за нее требует создания нового класса-условия.

### Шаг 1. Создание класса условия

Условия лежат в папке `app/Services/AvatarFrames/Conditions/`.
Вам нужно создать новый класс, который реализует интерфейс `FrameCondition`.

Пример `app/Services/AvatarFrames/Conditions/Completed100OrdersCondition.php`:

```php
<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Models\User;
use App\Models\Order;

class Completed100OrdersCondition implements FrameCondition
{
    /**
     * Ключ условия (сохраняется в БД в столбце condition_class)
     */
    public function getKey(): string
    {
        return 'completed_100_orders';
    }

    /**
     * Описание для админки
     */
    public function getDescription(): string
    {
        return 'Завершить 100 заказов';
    }

    /**
     * Логика проверки: выполнил ли пользователь условие?
     */
    public function check(User $user): bool
    {
        $completedCount = Order::where(function ($q) use ($user) {
                $q->where('customer_id', $user->id)
                  ->orWhere('idol_id', $user->id);
            })
            ->where('status', 'completed')
            ->count();

        return $completedCount >= 100;
    }
}
```

### Шаг 2. Регистрация условия

После создания класса его нужно добавить в реестр
`app/Services/AvatarFrames/ConditionRegistry.php`:

```php
    protected static array $conditions = [
        'completed_25_orders' => Completed25OrdersCondition::class,
        'completed_100_orders' => Completed100OrdersCondition::class,
    ];
```

### Шаг 3. Добавление новой рамки в БД

Теперь вы можете зайти в панель администратора и создать новую рамку.

- **Тип**: Укажите "За достижение" (`achievement`).
- **Условие получения**: В выпадающем списке выберите "Завершить 100 заказов"
  (оно автоматически появится благодаря реестру).

### Шаг 4. Генерация событий (опционально)

Слушатель `AchievementListener` по умолчанию привязан к событию
`OrderCompletedEvent` (см. `app/Providers/EventServiceProvider.php` или
атрибуты).

Если ваша новая ачивка зависит от других действий пользователя (например,
"Оставить 50 отзывов"), вам нужно убедиться, что:

1. Вы генерируете событие (например, `ReviewCreatedEvent`) при
   оставлении отзыва.
2. Вы слушаете это событие в `AchievementListener` и вызываете метод
   `evaluateUser` для нужного пользователя.

```php
    // В AchievementListener.php
    public function handleReviewCreated(ReviewCreatedEvent $event): void
    {
        $this->evaluateUser($event->review->user);
    }
```

(Либо вы можете создать отдельные Listeners для разных доменов событий, которые
будут вызывать ту же логику оценки).
