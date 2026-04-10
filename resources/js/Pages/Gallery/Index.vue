<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    idols:   { type: Array,   default: () => [] },
    is_idol: { type: Boolean, default: false },
});

// ── Sidebar filter ────────────────────────────────────────
// selectedIdolId: null = все купленные, 'mine' = мои паки (только для айдолов), number = конкретный айдол
const selectedIdolId = ref(null);
const searchQuery    = ref('');

const filteredIdols = computed(() => {
    if (!searchQuery.value) return props.idols;
    const q = searchQuery.value.toLowerCase();
    return props.idols.filter(i => i.name.toLowerCase().includes(q));
});

// Единый список для сайдбара: "Мои" (если айдол) + все айдолы
const sidebarItems = computed(() => {
    const items = [];
    if (props.is_idol) {
        items.push({ id: 'mine', name: 'Мои', type: 'mine', avatar_url: null });
    }
    for (const idol of filteredIdols.value) {
        items.push({ ...idol, type: 'idol' });
    }
    return items;
});

// ── Sidebar pack sub-list ─────────────────────────────────
const selectedPackId = ref(null);
const sidebarPacks   = ref([]);
const packsLoading   = ref(false);

async function loadSidebarPacks() {
    sidebarPacks.value = [];
    if (selectedIdolId.value === null) return;
    packsLoading.value = true;
    try {
        const isMine = selectedIdolId.value === 'mine';
        const { data } = await axios.get(route('gallery.packs'), {
            params: {
                idol_id: (!isMine && selectedIdolId.value) ? selectedIdolId.value : undefined,
                mine:    isMine ? true : undefined,
            },
        });
        sidebarPacks.value = data;
    } catch (e) {
        console.error(e);
    } finally {
        packsLoading.value = false;
    }
}

// ── Grid density ──────────────────────────────────────────
const DENSITY_SIZES = { compact: '140px', medium: '220px', large: '300px' };
const gridDensity = ref(localStorage.getItem('gallery_density') ?? 'medium');
watch(gridDensity, val => localStorage.setItem('gallery_density', val));
const gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(auto-fill, minmax(${DENSITY_SIZES[gridDensity.value]}, 1fr))`,
}));

// ── Photos infinite scroll ────────────────────────────────
const photos     = ref([]);
const nextCursor = ref(null);
const hasMore    = ref(true);
const loading    = ref(false);

async function loadPhotos() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    try {
        const isMine = selectedIdolId.value === 'mine';
        const { data } = await axios.get(route('gallery.photos'), {
            params: {
                idol_id: (!isMine && selectedIdolId.value) ? selectedIdolId.value : undefined,
                mine:    isMine ? true : undefined,
                pack_id: selectedPackId.value ?? undefined,
                cursor:  nextCursor.value ?? undefined,
            },
        });
        photos.value.push(...data.photos);
        nextCursor.value = data.next_cursor;
        hasMore.value    = data.has_more;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

function resetAndLoad() {
    lightboxIndex.value = null;
    photos.value        = [];
    nextCursor.value    = null;
    hasMore.value       = true;
    loadPhotos();
}

// Когда меняется айдол — сбрасываем пак, загружаем список паков и фотки
watch(selectedIdolId, () => {
    sidebarPacks.value   = [];
    selectedPackId.value = null; // pack-watcher защищён guard'ом val !== null
    loadSidebarPacks();
    resetAndLoad();
});

// Когда меняется пак — перезагружаем фотки
watch(selectedPackId, () => {
    resetAndLoad();
});

// ── IntersectionObserver sentinel ─────────────────────────
const sentinel = ref(null);
let observer = null;

onMounted(() => {
    resetAndLoad();
    observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) loadPhotos();
    }, { rootMargin: '200px' });
    if (sentinel.value) observer.observe(sentinel.value);
});

onUnmounted(() => { observer?.disconnect(); });

// ── Lightbox ──────────────────────────────────────────────
const lightboxIndex = ref(null);
const lightboxPhoto = computed(() =>
    lightboxIndex.value !== null ? photos.value[lightboxIndex.value] : null
);

function openLightbox(photo) {
    const idx = photos.value.findIndex(p => p.id === photo.id);
    lightboxIndex.value = idx !== -1 ? idx : null;
}
function closeLightbox() { lightboxIndex.value = null; }
function prevPhoto() {
    if (lightboxIndex.value === null) return;
    lightboxIndex.value = (lightboxIndex.value - 1 + photos.value.length) % photos.value.length;
}
function nextPhoto() {
    if (lightboxIndex.value === null) return;
    lightboxIndex.value = (lightboxIndex.value + 1) % photos.value.length;
}

function onLightboxKey(e) {
    if (lightboxIndex.value === null) return;
    if (e.key === 'Escape')     closeLightbox();
    if (e.key === 'ArrowLeft')  prevPhoto();
    if (e.key === 'ArrowRight') nextPhoto();
}

onMounted(() => document.addEventListener('keydown', onLightboxKey));
onUnmounted(() => document.removeEventListener('keydown', onLightboxKey));
</script>

<template>
    <Head title="Галерея" />

    <div class="gallery-page">

        <!-- Left sidebar -->
        <aside class="gallery-sidebar">
            <div class="gallery-sidebar__search">
                <input
                    v-model="searchQuery"
                    type="text"
                    class="gallery-search"
                    placeholder="Поиск айдола..."
                />
            </div>

            <div class="gallery-idol-list">

                <!-- Все -->
                <button
                    class="gallery-idol-item"
                    :class="{ 'gallery-idol-item--active': selectedIdolId === null }"
                    @click="selectedIdolId = null"
                >
                    <div class="gallery-idol-item__avatar gallery-idol-item__avatar--all">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                            <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
                        </svg>
                    </div>
                    <span class="gallery-idol-item__name">Все</span>
                </button>

                <!-- Мои + айдолы — единый список -->
                <template v-for="item in sidebarItems" :key="item.id">
                    <button
                        class="gallery-idol-item"
                        :class="{ 'gallery-idol-item--active': selectedIdolId === item.id && !selectedPackId }"
                        @click="selectedIdolId = item.id; selectedPackId = null"
                    >
                        <!-- Аватар: "Мои" — иконка пользователя, айдол — фото или инициал -->
                        <div
                            class="gallery-idol-item__avatar"
                            :class="item.type === 'mine' ? 'gallery-idol-item__avatar--mine' : ''"
                        >
                            <template v-if="item.type === 'mine'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </template>
                            <template v-else>
                                <img v-if="item.avatar_url" :src="item.avatar_url" :alt="item.name" />
                                <span v-else>{{ item.name?.charAt(0)?.toUpperCase() }}</span>
                            </template>
                        </div>
                        <span class="gallery-idol-item__name">{{ item.name }}</span>
                    </button>

                    <!-- Pack sub-list (одинаковый для всех пунктов) -->
                    <div v-if="selectedIdolId === item.id" class="gallery-pack-list">
                        <div v-if="packsLoading" class="gallery-pack-list__loading">
                            <div class="gallery-loading__spinner gallery-loading__spinner--sm" />
                        </div>
                        <button
                            v-for="pack in sidebarPacks"
                            :key="pack.id"
                            class="gallery-pack-item"
                            :class="{ 'gallery-pack-item--active': selectedPackId === pack.id }"
                            @click="selectedPackId = pack.id"
                        >
                            <div class="gallery-pack-item__cover">
                                <img v-if="pack.cover_url" :src="pack.cover_url" :alt="pack.title" />
                                <span v-else class="gallery-pack-item__cover-empty" />
                            </div>
                            <div class="gallery-pack-item__info">
                                <span class="gallery-pack-item__title">{{ pack.title }}</span>
                                <span class="gallery-pack-item__count">{{ pack.photo_count }} фото</span>
                            </div>
                        </button>
                    </div>
                </template>

            </div>
        </aside>

        <!-- Main area -->
        <main class="gallery-main">

            <!-- Toolbar -->
            <div class="gallery-toolbar">
                <div class="gallery-density-toggle">
                    <button
                        v-for="d in ['compact', 'medium', 'large']"
                        :key="d"
                        class="density-btn"
                        :class="{ 'density-btn--active': gridDensity === d }"
                        @click="gridDensity = d"
                        :aria-label="d"
                    >
                        <!-- compact: 3×3 grid -->
                        <svg v-if="d === 'compact'" width="15" height="15" viewBox="0 0 15 15" fill="currentColor">
                            <rect x="0"  y="0"  width="4" height="4" rx="0.5"/>
                            <rect x="5.5" y="0"  width="4" height="4" rx="0.5"/>
                            <rect x="11" y="0"  width="4" height="4" rx="0.5"/>
                            <rect x="0"  y="5.5" width="4" height="4" rx="0.5"/>
                            <rect x="5.5" y="5.5" width="4" height="4" rx="0.5"/>
                            <rect x="11" y="5.5" width="4" height="4" rx="0.5"/>
                            <rect x="0"  y="11" width="4" height="4" rx="0.5"/>
                            <rect x="5.5" y="11" width="4" height="4" rx="0.5"/>
                            <rect x="11" y="11" width="4" height="4" rx="0.5"/>
                        </svg>
                        <!-- medium: 2×2 grid -->
                        <svg v-else-if="d === 'medium'" width="15" height="15" viewBox="0 0 15 15" fill="currentColor">
                            <rect x="0" y="0"   width="6.5" height="6.5" rx="0.5"/>
                            <rect x="8.5" y="0"   width="6.5" height="6.5" rx="0.5"/>
                            <rect x="0" y="8.5" width="6.5" height="6.5" rx="0.5"/>
                            <rect x="8.5" y="8.5" width="6.5" height="6.5" rx="0.5"/>
                        </svg>
                        <!-- large: 1×2 rows -->
                        <svg v-else width="15" height="15" viewBox="0 0 15 15" fill="currentColor">
                            <rect x="0" y="0"   width="15" height="6.5" rx="0.5"/>
                            <rect x="0" y="8.5" width="15" height="6.5" rx="0.5"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!loading && !photos.length && !hasMore" class="gallery-empty">
                <svg class="gallery-empty__icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
                <template v-if="selectedIdolId === 'mine'">
                    <p class="gallery-empty__title">У вас пока нет контент паков</p>
                    <p class="gallery-empty__sub">Создайте паки в своём профиле, чтобы они появились здесь</p>
                </template>
                <template v-else>
                    <p class="gallery-empty__title">У вас пока нет купленного контента</p>
                    <p class="gallery-empty__sub">Посетите профили айдолов и добавьте паки в корзину</p>
                </template>
            </div>

            <!-- Photo grid -->
            <div v-else class="gallery-grid" :style="gridStyle">
                <div
                    v-for="photo in photos"
                    :key="photo.id"
                    class="gallery-photo"
                    @click="openLightbox(photo)"
                >
                    <img :src="photo.url" :alt="photo.pack_title" loading="lazy" />
                    <div class="gallery-photo__overlay">
                        <span class="gallery-photo__overlay-title">{{ photo.pack_title }}</span>
                    </div>
                </div>
            </div>

            <!-- Loading indicator -->
            <div v-if="loading" class="gallery-loading">
                <div class="gallery-loading__spinner" />
            </div>

            <!-- Sentinel for infinite scroll -->
            <div ref="sentinel" class="gallery-sentinel" />
        </main>

        <!-- Lightbox -->
        <Teleport to="body">
            <Transition name="lb-fade">
                <div v-if="lightboxPhoto" class="lb-overlay" @click="closeLightbox">

                    <!-- Counter -->
                    <div class="lb-counter">{{ lightboxIndex + 1 }} / {{ photos.length }}</div>

                    <!-- Close -->
                    <button class="lb-close" @click.stop="closeLightbox" aria-label="Закрыть">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>

                    <!-- Prev arrow -->
                    <button class="lb-arrow lb-arrow--prev" @click.stop="prevPhoto" aria-label="Предыдущее">&#8249;</button>

                    <!-- Image -->
                    <div class="lb-content" @click.stop>
                        <img :src="lightboxPhoto.url" :alt="lightboxPhoto.pack_title" class="lb-image" />
                        <div v-if="lightboxPhoto.pack_title" class="lb-caption">{{ lightboxPhoto.pack_title }}</div>
                    </div>

                    <!-- Next arrow -->
                    <button class="lb-arrow lb-arrow--next" @click.stop="nextPhoto" aria-label="Следующее">&#8250;</button>

                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
/* Override AppLayout constraints for full-width gallery */
:deep(.app-main) {
    padding: 0 !important;
}

.gallery-page {
    display: flex;
    min-height: calc(100vh - 60px);
    background: #0a0a14;
}

/* ── Sidebar ─────────────────────────────────────────────── */
.gallery-sidebar {
    width: 270px;
    flex-shrink: 0;
    border-right: 1px solid rgba(255, 255, 255, 0.06);
    display: flex;
    flex-direction: column;
    position: sticky;
    top: 60px;
    height: calc(100vh - 60px);
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.08) transparent;
}

.gallery-sidebar__search {
    padding: 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.gallery-search {
    width: 100%;
    box-sizing: border-box;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: rgba(255,255,255,0.8);
    font-size: 0.85rem;
    font-family: inherit;
    padding: 0.45rem 0.65rem;
    outline: none;
    transition: border-color 0.15s;
}
.gallery-search:focus { border-color: rgba(160,160,255,0.3); }
.gallery-search::placeholder { color: rgba(255,255,255,0.25); }

.gallery-idol-list {
    padding: 0.5rem 0;
    flex: 1;
}

.gallery-idol-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
    padding: 0.55rem 1rem;
    background: transparent;
    border: none;
    cursor: pointer;
    color: rgba(255,255,255,0.6);
    font-size: 0.95rem;
    font-family: inherit;
    text-align: left;
    transition: background 0.15s, color 0.15s;
}
.gallery-idol-item:hover { background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.85); }
.gallery-idol-item--active { background: rgba(160,160,255,0.08); color: rgba(200,200,255,0.9); }

.gallery-idol-item__avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background: rgba(160,160,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(160,160,255,0.7);
}
.gallery-idol-item__avatar--all {
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.4);
}
.gallery-idol-item__avatar--mine {
    background: rgba(160,255,200,0.1);
    color: rgba(160,255,200,0.6);
}
.gallery-idol-item__avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.gallery-idol-item__name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ── Sidebar pack sub-list ───────────────────────────────── */
.gallery-pack-list {
    padding: 2px 0 4px;
    border-left: 1px solid rgba(160,160,255,0.12);
    margin-left: 25px;
}

.gallery-pack-list__loading {
    display: flex;
    justify-content: center;
    padding: 0.5rem;
}

.gallery-pack-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.32rem 0.75rem 0.32rem 0.5rem;
    background: transparent;
    border: none;
    cursor: pointer;
    color: rgba(255,255,255,0.45);
    font-family: inherit;
    text-align: left;
    transition: background 0.15s, color 0.15s;
}
.gallery-pack-item:hover { background: rgba(255,255,255,0.03); color: rgba(255,255,255,0.75); }
.gallery-pack-item--active { color: rgba(160,160,255,0.9); background: rgba(160,160,255,0.07); }

.gallery-pack-item__cover {
    width: 26px;
    height: 26px;
    border-radius: 3px;
    overflow: hidden;
    flex-shrink: 0;
    background: rgba(255,255,255,0.06);
}
.gallery-pack-item__cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.gallery-pack-item__cover-empty {
    display: block;
    width: 100%;
    height: 100%;
    background: rgba(160,160,255,0.07);
}

.gallery-pack-item__info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}
.gallery-pack-item__title {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.95rem;
    line-height: 1.3;
}
.gallery-pack-item__count {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.22);
    line-height: 1.2;
}

/* ── Main area ───────────────────────────────────────────── */
.gallery-main {
    flex: 1;
    min-width: 0;
    padding: 1rem;
}

/* ── Toolbar ─────────────────────────────────────────────── */
.gallery-toolbar {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 0 0 0.75rem;
}

.gallery-density-toggle {
    display: flex;
    gap: 3px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 7px;
    padding: 3px;
}

.density-btn {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    color: rgba(255,255,255,0.3);
    transition: background 0.15s, color 0.15s;
}
.density-btn:hover { color: rgba(255,255,255,0.6); background: rgba(255,255,255,0.06); }
.density-btn--active { background: rgba(160,160,255,0.15); color: rgba(160,160,255,0.9); }

/* ── Photo grid ──────────────────────────────────────────── */
.gallery-grid {
    display: grid;
    gap: 6px;
}

.gallery-photo {
    position: relative;
    aspect-ratio: 1;
    overflow: hidden;
    border-radius: 4px;
    cursor: pointer;
    background: rgba(255,255,255,0.04);
}
.gallery-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.25s;
}
.gallery-photo:hover img { transform: scale(1.05); }

/* Hover overlay */
.gallery-photo__overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.65) 0%, transparent 55%);
    opacity: 0;
    transition: opacity 0.2s;
    display: flex;
    align-items: flex-end;
    padding: 0.5rem;
    pointer-events: none;
}
.gallery-photo:hover .gallery-photo__overlay { opacity: 1; }

.gallery-photo__overlay-title {
    font-size: 0.74rem;
    color: rgba(255,255,255,0.9);
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}

/* ── Empty state ─────────────────────────────────────────── */
.gallery-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
    color: rgba(255,255,255,0.3);
}
.gallery-empty__icon { opacity: 0.3; margin-bottom: 1.25rem; }
.gallery-empty__title { font-size: 1.05rem; font-weight: 500; color: rgba(255,255,255,0.45); margin: 0 0 0.5rem; }
.gallery-empty__sub   { font-size: 0.88rem; color: rgba(255,255,255,0.25); margin: 0; }

/* ── Loading ─────────────────────────────────────────────── */
.gallery-loading {
    display: flex;
    justify-content: center;
    padding: 1.5rem;
}
.gallery-loading__spinner {
    width: 28px;
    height: 28px;
    border: 2px solid rgba(160,160,255,0.15);
    border-top-color: rgba(160,160,255,0.6);
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
.gallery-loading__spinner--sm {
    width: 16px;
    height: 16px;
    border-width: 1.5px;
}
@keyframes spin { to { transform: rotate(360deg); } }

.gallery-sentinel { height: 1px; }

/* ── Lightbox ────────────────────────────────────────────── */
.lb-fade-enter-active, .lb-fade-leave-active { transition: opacity 0.22s; }
.lb-fade-enter-from, .lb-fade-leave-to       { opacity: 0; }

.lb-overlay {
    position: fixed;
    inset: 0;
    z-index: 2000;
    background: rgba(0, 0, 0, 0.92);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: zoom-out;
}

.lb-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.7);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s, color 0.15s;
    z-index: 1;
}
.lb-close:hover { background: rgba(255,255,255,0.15); color: #fff; }

.lb-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.7);
    width: 46px;
    height: 46px;
    border-radius: 50%;
    font-size: 2rem;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s, color 0.15s;
    z-index: 1;
    padding-bottom: 2px;
}
.lb-arrow:hover { background: rgba(255,255,255,0.15); color: #fff; }
.lb-arrow--prev { left: 18px; }
.lb-arrow--next { right: 18px; }

.lb-counter {
    position: absolute;
    bottom: 18px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(6px);
    color: rgba(255,255,255,0.6);
    font-size: 0.82rem;
    padding: 4px 14px;
    border-radius: 20px;
    z-index: 1;
    pointer-events: none;
    white-space: nowrap;
}

.lb-content {
    max-width: calc(90vw - 120px);
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    cursor: default;
}

.lb-image {
    max-width: 100%;
    max-height: calc(90vh - 50px);
    object-fit: contain;
    border-radius: 4px;
    display: block;
}

.lb-caption {
    font-size: 0.88rem;
    color: rgba(255,255,255,0.45);
    text-align: center;
}

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 640px) {
    .gallery-sidebar { width: 180px; }
    .gallery-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)) !important; gap: 4px; }
    .gallery-density-toggle { display: none; }
    .lb-arrow { width: 36px; height: 36px; font-size: 1.6rem; }
    .lb-arrow--prev { left: 8px; }
    .lb-arrow--next { right: 8px; }
    .lb-content { max-width: calc(90vw - 90px); }
}
@media (max-width: 480px) {
    .gallery-sidebar { display: none; }
}
</style>
