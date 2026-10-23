<?php

namespace App\Services\AvatarFrames;

use App\Services\AvatarFrames\Conditions\Completed10OrdersCondition;
use App\Services\AvatarFrames\Conditions\Completed25OrdersCondition;
use App\Services\AvatarFrames\Conditions\Completed50OrdersCondition;
use App\Services\AvatarFrames\Conditions\Completed100OrdersCondition;
use App\Services\AvatarFrames\Conditions\Created5PacksCondition;
use App\Services\AvatarFrames\Conditions\Earned10000RubCondition;
use App\Services\AvatarFrames\Conditions\Earned100000RubCondition;
use App\Services\AvatarFrames\Conditions\FrameCondition;
use App\Services\AvatarFrames\Conditions\Has10FollowersCondition;
use App\Services\AvatarFrames\Conditions\Has100FollowersCondition;
use App\Services\AvatarFrames\Conditions\HighRating40Condition;
use App\Services\AvatarFrames\Conditions\HighRating50Condition;
use App\Services\AvatarFrames\Conditions\PostLiked100TimesCondition;
use App\Services\AvatarFrames\Conditions\Published10PostsCondition;
use App\Services\AvatarFrames\Conditions\Received50ReviewsCondition;
use App\Services\AvatarFrames\Conditions\SoldFirstPackCondition;
use App\Services\AvatarFrames\Conditions\Sold10PacksCondition;

class ConditionRegistry
{
    /** @var array<string, class-string<FrameCondition>> */
    protected static array $conditions = [
        // Заказы
        'completed_10_orders'  => Completed10OrdersCondition::class,
        'completed_25_orders'  => Completed25OrdersCondition::class,
        'completed_50_orders'  => Completed50OrdersCondition::class,
        'completed_100_orders' => Completed100OrdersCondition::class,

        // Доход
        'earned_10000_rub'  => Earned10000RubCondition::class,
        'earned_100000_rub' => Earned100000RubCondition::class,

        // Рейтинг и отзывы
        'high_rating_40'    => HighRating40Condition::class,
        'high_rating_50'    => HighRating50Condition::class,
        'received_50_reviews' => Received50ReviewsCondition::class,

        // Контент-паки
        'sold_first_pack' => SoldFirstPackCondition::class,
        'sold_10_packs'   => Sold10PacksCondition::class,
        'created_5_packs' => Created5PacksCondition::class,

        // Посты
        'published_10_posts'   => Published10PostsCondition::class,
        'post_liked_100_times' => PostLiked100TimesCondition::class,

        // Подписчики
        'has_10_followers'  => Has10FollowersCondition::class,
        'has_100_followers' => Has100FollowersCondition::class,
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
            $instances[$key] = new $class;
        }

        return $instances;
    }

    public static function register(string $key, string $class): void
    {
        self::$conditions[$key] = $class;
    }

    /**
     * Получить конкретное условие по его ключу.
     */
    public static function get(string $key): ?FrameCondition
    {
        if (isset(self::$conditions[$key])) {
            $class = self::$conditions[$key];

            return new $class;
        }

        return null;
    }
}
