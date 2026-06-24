<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Models\User;

interface FrameCondition
{
    /**
     * Возвращает уникальный идентификатор условия (для БД).
     */
    public function getKey(): string;

    /**
     * Описание условия для админки и фронтенда.
     */
    public function getDescription(): string;

    /**
     * Проверяет, выполнил ли пользователь это условие.
     */
    public function check(User $user): bool;
}
