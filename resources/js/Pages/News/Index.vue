<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import SiteHeader from '@/Components/Site/SiteHeader.vue';

const props = defineProps({
    categories: Array,
});

// ── State ─────────────────────────────────────────────────────
const items          = ref([]);
const page           = ref(1);
const hasMore        = ref(true);
const loading        = ref(false);
const search         = ref('');
const activeCategory = ref('');
const sortDir        = ref('desc');
const sentinel       = ref(null);
let   observer       = null;
let   searchTimer    = null;

// ── Format ────────────────────────────────────────────────────
function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' });
}

function categoryColor(item) {
    if (item?.color) return item.color;
    const map = {
        'Обновление':  '#67e8f9',
        'Анонс':       '#be91ff',
        'Событие':     '#f9a8d4',
        'Пресс-релиз': '#86efac',
        'Другое':      '#fcd34d',
    };
    return map[item?.category] || 'rgba(255,255,255,0.35)';
}

function hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r},${g},${b},${alpha})`;
}

function categoryColorDim(item) {
    const color = categoryColor(item);
    if (color.startsWith('#')) return hexToRgba(color, 0.35);
    const dimMap = {
        'Обновление':  'rgba(103,232,249,0.35)',
        'Анонс':       'rgba(190,145,255,0.35)',
        'Событие':     'rgba(249,168,212,0.35)',
        'Пресс-релиз': 'rgba(134,239,172,0.35)',
        'Другое':      'rgba(252,211,77,0.35)',
    };
    return dimMap[item?.category] || 'rgba(255,255,255,0.1)';
}

function previewText(item) {
    const src = item.excerpt || item.body || '';
    return src.length > 160 ? src.slice(0, 160) + '…' : src;
}

// ── Fetch ─────────────────────────────────────────────────────
async function fetchFeed(reset = false) {
    if (loading.value || (!reset && !hasMore.value)) return;

    if (reset) {
        items.value  = [];
        page.value   = 1;
        hasMore.value = true;
    }

    loading.value = true;
    try {
        const { data } = await axios.get(route('news.feed'), {
            params: {
                page:     page.value,
                search:   search.value || undefined,
                category: activeCategory.value || undefined,
                sort:     sortDir.value,
            },
        });
        items.value.push(...data.data);
        hasMore.value = data.current_page < data.last_page;
        page.value++;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

// ── Search debounce ───────────────────────────────────────────
function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => fetchFeed(true), 350);
}

watch(activeCategory, () => fetchFeed(true));
watch(sortDir, () => fetchFeed(true));

// ── Intersection observer ─────────────────────────────────────
onMounted(() => {

    fetchFeed();

    observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) fetchFeed();
    }, { rootMargin: '140px' });

    if (sentinel.value) observer.observe(sentinel.value);
});

onUnmounted(() => {
    observer?.disconnect();
    clearTimeout(searchTimer);
});
</script>

<template>
    <Head>
        <title>Новости — no alone</title>
        <meta name="description" content="Последние новости проекта no alone." />
    </Head>

    <!-- Декор -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="ni-circle ni-circle--1"/><div class="ni-circle ni-circle--2"/><div class="ni-circle ni-circle--3"/>
        <div class="ni-orb ni-orb--pink"/><div class="ni-orb ni-orb--cyan"/>
    </div>

    <div class="ni-shell">

        <SiteHeader activePage="news" />

        <!-- Тулбар: поиск + фильтры -->
        <div class="ni-toolbar">
            <div class="ni-toolbar__inner">

                <!-- Поиск -->
                <div class="ni-search">
                    <input
                        v-model="search"
                        class="ni-search__input"
                        placeholder="Поиск новостей…"
                        @input="onSearchInput"
                    />
                    <button v-if="search" class="ni-search__clear" @click="search = ''; fetchFeed(true)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <path d="M18 6 6 18M6 6l12 12"/>
                        </svg>
                    </button>
                    <svg class="ni-search__icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                </div>

                <!-- Категории + сортировка -->
                <div class="ni-cats-row">
                <div v-if="categories.length" class="ni-cats">
                    <button
                        class="ni-cat"
                        :class="{ 'ni-cat--active': activeCategory === '' }"
                        @click="activeCategory = ''"
                    >Все</button>
                    <button
                        v-for="cat in categories"
                        :key="cat"
                        class="ni-cat"
                        :class="{ 'ni-cat--active': activeCategory === cat }"
                        :style="activeCategory === cat ? { '--cat-color': categoryColor({ category: cat }) } : {}"
                        @click="activeCategory = cat"
                    >{{ cat }}</button>
                </div>

                <button class="ni-sort-btn" @click="sortDir = sortDir === 'desc' ? 'asc' : 'desc'">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                        :style="sortDir === 'asc' ? 'transform: scaleY(-1)' : ''">
                        <path d="M12 5v14M5 12l7 7 7-7"/>
                    </svg>
                    {{ sortDir === 'desc' ? 'Новые' : 'Старые' }}
                </button>

                </div><!-- /ni-cats-row -->

            </div>
        </div>

        <!-- Список -->
        <div class="ni-content">
            <div class="ni-scroll">
                <div class="ni-inner">

                    <template v-if="items.length">
                        <Link
                            v-for="item in items"
                            :key="item.id"
                            :href="route('news.show', item.id)"
                            class="ni-card"
                            :class="{
                                'ni-card--with-img': item.image,
                                'ni-card--pinned':   item.is_pinned,
                            }"
                            :style="{ '--cat-color': categoryColor(item), '--cat-color-dim': categoryColorDim(item) }"
                        >
                            <div v-if="item.image" class="ni-card__img-wrap">
                                <img :src="item.image" :alt="item.title" class="ni-card__img" />
                            </div>

                            <div class="ni-card__content">
                                <div class="ni-card__top">
                                    <time class="ni-card__date">{{ formatDate(item.published_at) }}</time>
                                    <svg class="ni-card__arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M7 17L17 7M7 7h10v10"/>
                                    </svg>
                                </div>

                                <h2 class="ni-card__title">{{ item.title }}</h2>
                                <p class="ni-card__body">{{ previewText(item) }}</p>

                                <div class="ni-card__bottom">
                                    <div class="ni-card__bottom-left">
                                        <div v-if="item.is_pinned" class="ni-pin-badge">
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                            </svg>
                                            <span>Закреплено</span>
                                        </div>
                                    </div>
                                    <span
                                        v-if="item.category"
                                        class="ni-card__cat"
                                        :style="{ color: categoryColor(item), borderColor: categoryColor(item) + '55' }"
                                    >{{ item.category }}</span>
                                </div>
                            </div>
                        </Link>
                    </template>

                    <!-- Пустое состояние -->
                    <div v-else-if="!loading" class="ni-empty">
                        <div class="ni-empty__icon">◌</div>
                        <p>{{ search || activeCategory ? 'Ничего не найдено' : 'Новостей пока нет' }}</p>
                    </div>

                    <!-- Sentinel + спиннер -->
                    <div ref="sentinel" class="ni-sentinel"/>
                    <div v-if="loading" class="ni-spinner">
                        <span/><span/><span/>
                    </div>

                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
/* ── Shell ──────────────────────────────────────────────────── */
.ni-shell {
    width: 100%; height: 100dvh;
    display: flex; flex-direction: column; overflow: hidden;
    position: relative; z-index: 1;
    background: linear-gradient(180deg, rgba(255,42,191,0.07) 0%, rgba(0,0,0,0.65) 100%) fixed;
}

/* ── Декор ──────────────────────────────────────────────────── */
.ni-circle { border-radius: 50%; background: rgba(60,60,190,0.03); box-shadow: inset 0 0 30px rgba(255,255,255,0.015); position: absolute; right: -12%; top: -8%; }
.ni-circle--1 { width: 900px; height: 900px; }
.ni-circle--2 { width: 700px; height: 700px; }
.ni-circle--3 { width: 500px; height: 500px; }
.ni-orb { position: absolute; border-radius: 50%; filter: blur(100px); pointer-events: none; }
.ni-orb--pink { width: 500px; height: 500px; background: radial-gradient(circle, rgba(236,72,153,0.12) 0%, transparent 70%); top: -10%; left: -5%; }
.ni-orb--cyan { width: 400px; height: 400px; background: radial-gradient(circle, rgba(34,211,238,0.08) 0%, transparent 70%); bottom: 5%; right: 5%; }

/* ── Toolbar ─────────────────────────────────────────────────── */
@keyframes ni-fade-in { from { opacity: 0; } to { opacity: 1; } }
.ni-toolbar {
    flex-shrink: 0; padding: 0.85rem 0 0;
    position: relative; z-index: 9;
    animation: ni-fade-in 0.4s ease-out 0.1s both;
}
.ni-toolbar__inner {
    max-width: 900px; margin: 0 auto;
    padding: 0 2.5rem;
    display: flex; flex-direction: column; gap: 0.85rem;
}

/* Поиск */
.ni-search {
    display: flex; align-items: center; gap: 0.55rem;
    background: rgba(255,255,255,0.04);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    padding: 0.45rem 0.9rem;
    transition: border-color 0.15s;
    outline: none;
}
.ni-search:focus-within { border-color: rgba(190,145,255,0.45); }
.ni-search__icon { flex-shrink: 0; color: rgba(255,255,255,0.3); }
.ni-search__input {
    flex: 1; background: none; border: none;
    outline: none !important; box-shadow: none !important;
    color: rgba(255,255,255,0.82); font-family: "Figtree", sans-serif; font-size: 0.97rem;
}
.ni-search__input:focus,
.ni-search__input:focus-visible { outline: none !important; box-shadow: none !important; }
.ni-search__input::placeholder { color: rgba(255,255,255,0.22); }
.ni-search__clear {
    flex-shrink: 0; background: none; border: none; cursor: pointer;
    color: rgba(255,255,255,0.3); padding: 0; line-height: 1;
    transition: color 0.15s;
}
.ni-search__clear:hover { color: rgba(255,255,255,0.6); }

/* Категории */
.ni-cats-row {
    display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;
}
.ni-cats {
    display: flex; flex-wrap: wrap; gap: 0.4rem;
}
.ni-cat {
    padding: 0.3rem 0.85rem; border-radius: 3px;
    border: 1px solid rgba(255,255,255,0.1);
    background: transparent; cursor: pointer;
    font-family: "Figtree", sans-serif; font-size: 0.78rem;
    color: rgba(255,255,255,0.4);
    transition: color 0.2s, border-color 0.2s, background 0.2s;
    white-space: nowrap;
}
.ni-cat:hover { color: rgba(255,255,255,0.65); border-color: rgba(255,255,255,0.35); }
.ni-sort-btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.3rem 0.75rem; border-radius: 3px;
    border: 1px solid rgba(255,255,255,0.1);
    background: transparent; cursor: pointer;
    font-family: "Figtree", sans-serif; font-size: 0.78rem;
    color: rgba(255,255,255,0.4);
    transition: color 0.2s, border-color 0.2s, background 0.2s;
    white-space: nowrap; flex-shrink: 0;
}
.ni-sort-btn:hover { color: rgba(255,255,255,0.65); border-color: rgba(255,255,255,0.35); }
.ni-sort-btn svg { flex-shrink: 0; transition: transform 0.2s; }

.ni-cat--active {
    background: rgba(190,145,255,0.12);
    border-color: var(--cat-color, rgba(190,145,255,0.4));
    color: var(--cat-color, rgba(190,145,255,0.9));
}

/* ── Content ────────────────────────────────────────────────── */
.ni-content { flex: 1; min-height: 0; position: relative; }
.ni-scroll  { position: absolute; inset: 0; overflow-y: auto; }

.ni-inner {
    max-width: 900px; margin: 0 auto;
    padding: 0.85rem 2.5rem 3rem;
    display: flex; flex-direction: column; gap: 1rem;
}

/* ── Card ────────────────────────────────────────────────────── */
.ni-card {
    display: flex; align-items: stretch; position: relative;
    background: rgba(255,255,255,0.025);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 4px; overflow: hidden;
    text-decoration: none; cursor: pointer;
    transition: border-color 0.25s, background 0.25s, box-shadow 0.25s;
}
.ni-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg,
        transparent 0%,
        rgba(255,255,255,0.06) 10%,
        rgba(255,255,255,0.35) 50%,
        rgba(255,255,255,0.06) 90%,
        transparent 100%
    );
    pointer-events: none; z-index: 1;
}
.ni-card:hover {
    border-color: var(--cat-color-dim, rgba(140,110,230,0.35));
    background: rgba(255,255,255,0.045);
    box-shadow: 0 8px 32px rgba(0,0,0,0.35);
}
.ni-card--with-img { display: grid; grid-template-columns: 170px 1fr auto; }
.ni-card:not(.ni-card--with-img) { display: flex; }

/* Закреплённая карточка */
.ni-card--pinned {
    background: rgba(190,145,255,0.04);
    border-color: rgba(190,145,255,0.35);
    box-shadow: 0 0 0 1px rgba(190,145,255,0.06) inset;
}
.ni-card--pinned:hover {
    border-color: var(--cat-color-dim, rgba(190,145,255,0.35));
    box-shadow: 0 8px 32px rgba(0,0,0,0.35);
}
.ni-pin-badge {
    display: inline-flex; align-items: center; gap: 0.32rem;
    padding: 0.15rem 0.55rem;
    border-radius: 3px 3px 0 0;
    border: 1px solid rgba(190,145,255,0.25);
    border-bottom: none;
    color: rgba(190,145,255,0.6);
    font-family: "Figtree", sans-serif;
    font-size: 0.78rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase;
    align-self: flex-end;
    margin-bottom: -1px;
}

/* Изображение */
.ni-card__img-wrap { overflow: hidden; flex-shrink: 0; min-height: 110px; }
.ni-card__img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.4s ease; }
.ni-card:hover .ni-card__img { transform: scale(1.05); }

/* Контент */
.ni-card__content {
    flex: 1; padding: 1.25rem 1.4rem;
    display: flex; flex-direction: column; gap: 0.6rem;
    justify-content: space-between; min-width: 0;
}

.ni-card__top {
    display: flex; align-items: center; justify-content: space-between;
}
.ni-card__date {
    font-family: "Figtree", sans-serif;
    font-size: 0.82rem; color: rgba(255,255,255,0.28); letter-spacing: 0.02em;
}
.ni-card__cat {
    font-family: "Figtree", sans-serif;
    font-size: 0.78rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase;
    padding: 0.15rem 0.55rem;
    border-radius: 3px 3px 0 0;
    border: 1px solid;
    border-bottom: none;
    white-space: nowrap;
    align-self: flex-end;
    margin-bottom: -1px;
}

.ni-card__title {
    font-family: "Brygada 1918", serif;
    font-size: 1.18rem; font-weight: 400;
    color: rgba(255,255,255,0.88); margin: 0; line-height: 1.35;
}
.ni-card__body {
    font-size: 0.93rem; color: rgba(255,255,255,0.38); line-height: 1.58; margin: 0;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

/* Нижняя строка карточки */
.ni-card__bottom {
    display: flex; align-items: flex-end; justify-content: space-between;
    margin: 0.6rem -1.4rem -1.25rem;
    padding: 0 1.4rem;
}
.ni-card__bottom-left {
    display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
}

/* Стрелка */
.ni-card__arrow {
    flex-shrink: 0;
    color: rgba(255,255,255,0.35);
    transition: color 0.2s, transform 0.2s;
}
.ni-card:hover .ni-card__arrow { color: var(--cat-color, rgba(112,112,216,0.7)); transform: translate(2px, -2px); }
.ni-card--with-img .ni-card__arrow { margin-right: 0; }

/* ── Empty ───────────────────────────────────────────────────── */
.ni-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 1rem; padding: 4rem 0; color: rgba(255,255,255,0.25); font-size: 0.9rem;
}
.ni-empty__icon { font-size: 3rem; opacity: 0.3; }

/* ── Sentinel & spinner ──────────────────────────────────────── */
.ni-sentinel { height: 1px; }
.ni-spinner {
    display: flex; justify-content: center; gap: 6px; padding: 1.25rem 0;
}
.ni-spinner span {
    width: 6px; height: 6px; border-radius: 50%;
    background: rgba(190,145,255,0.45);
    animation: ni-pulse 1.2s ease-in-out infinite;
}
.ni-spinner span:nth-child(2) { animation-delay: 0.2s; }
.ni-spinner span:nth-child(3) { animation-delay: 0.4s; }
@keyframes ni-pulse {
    0%, 80%, 100% { opacity: 0.25; transform: scale(0.85); }
    40%           { opacity: 1;    transform: scale(1.1); }
}

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 768px) {
    .ni-topbar { padding: 0.75rem 1.5rem 0; }
    .ni-toolbar__inner { padding: 0 1rem; }
    .ni-inner { padding: 1rem 1rem 2rem; }
    .ni-card--with-img { grid-template-columns: 1fr; }
    .ni-card--with-img .ni-card__img-wrap { height: 150px; }
    .ni-card--with-img .ni-card__arrow { display: none; }
}
</style>
