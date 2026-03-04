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
    background: #080812;
    display: flex;
    flex-direction: column;
    font-family: 'Figtree', sans-serif;
}

.admin-header {
    position: sticky;
    top: 0;
    z-index: 100;
    height: 56px;
    display: flex;
    align-items: center;
    gap: 2rem;
    padding: 0 2rem;
    background: rgba(8, 8, 18, 0.97);
    border-bottom: 1px solid rgba(200, 70, 126, 0.2);
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.5);
}

.admin-logo {
    font-family: 'Imbue', serif;
    font-size: 1.1rem;
    color: #C8467E;
    text-decoration: none;
    white-space: nowrap;
    text-shadow: 0 0 20px rgba(200, 70, 126, 0.4);
    margin-right: 1rem;
}

.admin-nav {
    display: flex;
    gap: 0.5rem;
    flex: 1;
}

.admin-nav__link {
    padding: 0.35rem 0.9rem;
    border-radius: 6px;
    color: rgba(255, 255, 255, 0.6);
    text-decoration: none;
    font-size: 0.875rem;
    transition: background 0.15s, color 0.15s;
}
.admin-nav__link:hover {
    background: rgba(200, 70, 126, 0.1);
    color: rgba(255, 255, 255, 0.9);
}

.admin-logout {
    margin-left: auto;
    padding: 0.35rem 0.9rem;
    border-radius: 6px;
    border: 1px solid rgba(200, 70, 126, 0.3);
    background: transparent;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.15s;
}
.admin-logout:hover {
    border-color: rgba(200, 70, 126, 0.6);
    color: rgba(255, 255, 255, 0.8);
    background: rgba(200, 70, 126, 0.08);
}

.admin-main {
    flex: 1;
    padding: 2rem;
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
}
</style>
