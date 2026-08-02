<?php

namespace App\Services\AvatarFrames;

use App\Services\AvatarFrames\Conditions\FrameCondition;
use App\Services\AvatarFrames\Conditions\Completed25OrdersCondition;

class ConditionRegistry
{
    /** @var array<string, class-string<FrameCondition>> */
    protected static array $conditions = [
        'completed_25_orders' => Completed25OrdersCondition::class,
    ];

    /**
     * Получить все доступные классы условий (для админки).
     *
     * @return FrameCondition[]
     */
    public static function all(): array
    {
        $instances = [];
        foreach (self::$conditions as $key => $class) {
            $instances[$key] = new $class();
        }
        return $instances;
    }

    /**
     * Получить конкретное условие по его ключу.
     */
    public static function get(string $key): ?FrameCondition
    {
        if (isset(self::$conditions[$key])) {
            $class = self::$conditions[$key];
            return new $class();
        }
        return null;
    }
}
