<script setup>
import { Link } from '@inertiajs/vue3';
import PublicNav from '@/Components/Site/PublicNav.vue';

defineProps({
    activePage: { type: String, default: 'home' },
});
</script>

<template>
    <header class="site-header">
        <!-- Логотип -->
        <component
            :is="activePage === 'home' ? 'span' : Link"
            :href="activePage !== 'home' ? '/' : undefined"
            class="site-header__logo"
        >
            <svg class="site-header__svg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="50" cy="50" rx="35" ry="35" stroke="#ec4899" stroke-width="2.5"
                         transform="rotate(-15 50 50)"/>
                <line x1="20" y1="60" x2="80" y2="40" stroke="#ec4899" stroke-width="2.5"/>
            </svg>
            <span class="site-header__name">no alone</span>
        </component>

        <!-- Навигация по центру -->
        <PublicNav :activePage="activePage" class="site-header__nav" />

        <!-- Контакты -->
        <div class="site-header__contacts">
            <a href="https://t.me/no_alone" class="site-header__contact" title="Telegram">
                <svg class="site-header__contact-icon" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.12.02-1.96 1.25-5.54 3.67-.52.36-.99.53-1.42.52-.47-.01-1.37-.26-2.03-.48-.82-.27-1.47-.42-1.42-.88.03-.24.37-.48 1.02-.73 3.99-1.74 6.66-2.89 8-3.45 3.81-1.58 4.6-1.85 5.12-1.86.11 0 .37.03.53.16.14.11.18.26.2.37.01.08.03.29.01.45z"/>
                </svg>
                <span class="site-header__contact-label">no_alone</span>
            </a>
            <a href="mailto:adm@na.ru" class="site-header__contact" title="Email">
                <svg class="site-header__contact-icon" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                </svg>
                <span class="site-header__contact-label">adm@na.ru</span>
            </a>
        </div>
    </header>
</template>

<style scoped>
.site-header {
    display: flex; align-items: center; justify-content: space-between;
    gap: 1.5rem; padding: 1.2rem 4rem 0;
    flex-shrink: 0; position: relative; z-index: 10;
    width: 100%;
}

.site-header__logo {
    display: flex; align-items: center; gap: 0.6rem;
    flex-shrink: 0; text-decoration: none; cursor: default;
}
a.site-header__logo { cursor: pointer; }

.site-header__svg { width: 40px; height: 40px; flex-shrink: 0; }

.site-header__name {
    font-family: "Imbue", serif;
    font-size: 1.9rem;
    color: #7070d8;
    text-shadow: 0 0 30px rgba(110,110,210,0.25);
    line-height: 0.9;
    letter-spacing: -0.02em;
}

.site-header__nav {
    position: absolute; left: 50%; transform: translateX(-50%);
}

.site-header__contacts {
    display: flex; align-items: center; gap: 1.25rem; flex-shrink: 0;
}

.site-header__contact {
    display: flex; align-items: center; gap: 0.5rem;
    color: rgba(255,255,255,0.5); font-size: 1rem;
    text-decoration: none; transition: color 0.2s;
    font-family: "Figtree", sans-serif;
}
.site-header__contact:hover { color: rgba(255,255,255,0.85); }

.site-header__contact-icon { width: 18px; height: 18px; flex-shrink: 0; }

@media (max-width: 768px) {
    .site-header { padding: 0.75rem 1.5rem 0; gap: 0.75rem; }
    .site-header__contact-label { display: none; }
    .site-header__svg { width: 28px; height: 28px; }
    .site-header__name { font-size: 1.35rem; }
}
</style>
