<script setup>
import { Link, router } from '@inertiajs/vue3';

function logout() {
    router.post(route('admin.logout'));
}
</script>

<template>
    <div class="admin-wrap">
        <header class="admin-header">
            <Link href="/admin" class="admin-logo">NoAlone Admin</Link>
            <nav class="admin-nav">
                <Link :href="route('admin.applications.index')" class="admin-nav__link">Заявки</Link>
                <Link :href="route('admin.quiz.questions.index')" class="admin-nav__link">Вопросы теста</Link>
                <Link :href="route('admin.messages.index')" class="admin-nav__link">Рассылки</Link>
                <Link :href="route('admin.users.index')" class="admin-nav__link">Пользователи</Link>
                <Link :href="route('admin.idols.index')" class="admin-nav__link">Айдолы</Link>
                <Link :href="route('admin.services.categories.index')" class="admin-nav__link">Категории</Link>
                <Link :href="route('admin.services.time-units.index')" class="admin-nav__link">Единицы</Link>
                <Link :href="route('admin.services.price-limits.index')" class="admin-nav__link">Лимиты</Link>
                <Link :href="route('admin.settings.index')" class="admin-nav__link">Настройки</Link>
            </nav>
            <button class="admin-logout" @click="logout">Выйти</button>
        </header>
        <main class="admin-main">
            <slot />
        </main>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Imbue:opsz,wght@10..100,300;10..100,400&display=swap');

.admin-wrap {
    min-height: 100vh;
    background: #07070f;
    display: flex;
    flex-direction: column;
    font-family: 'Figtree', sans-serif;
}

.admin-header {
    position: sticky;
    top: 0;
    z-index: 100;
    height: 52px;
    display: flex;
    align-items: center;
    gap: 2rem;
    padding: 0 2rem;
    background: #07070f;
    border-bottom: 1px solid rgba(155, 110, 232, 0.45);
}

.admin-logo {
    font-family: 'Imbue', serif;
    font-size: 1.05rem;
    color: #9B6EE8;
    text-decoration: none;
    white-space: nowrap;
    letter-spacing: 0.02em;
    margin-right: 1rem;
}

.admin-nav {
    display: flex;
    gap: 0;
    flex: 1;
}

.admin-nav__link {
    padding: 0.35rem 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    text-decoration: none;
    font-size: 0.85rem;
    border-right: 1px solid rgba(255,255,255,0.06);
}
.admin-nav__link:first-child { border-left: 1px solid rgba(255,255,255,0.06); }
.admin-nav__link:hover {
    background: rgba(155, 110, 232, 0.1);
    color: rgba(255, 255, 255, 0.9);
}

.admin-logout {
    margin-left: auto;
    padding: 0.3rem 0.85rem;
    border: 1px solid rgba(155, 110, 232, 0.35);
    background: transparent;
    color: rgba(255, 255, 255, 0.45);
    font-size: 0.8rem;
    cursor: pointer;
    font-family: inherit;
}
.admin-logout:hover {
    border-color: rgba(155, 110, 232, 0.65);
    color: rgba(255, 255, 255, 0.85);
    background: rgba(155, 110, 232, 0.1);
}

.admin-main {
    flex: 1;
    padding: 2rem;
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
}
</style>
