<script setup>
import { ref, computed, onMounted, onUnmounted, watch, reactive } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, ArrowRight } from '@element-plus/icons-vue';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();
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
        items.push({ id: 'mine', name: __('gallery.mine'), type: 'mine', avatar_url: null });
    }
    for (const idol of filteredIdols.value) {
        items.push({ ...idol, type: 'idol' });
    }
    return items;
});

// ── Sidebar pack sub-list ─────────────────────────────────
const selectedPackId    = ref(null);
const sidebarPacks      = ref([]);
const packCoverLoaded   = reactive({});
const packsLoading      = ref(false);
const hasMorePacks      = ref(false);
const nextPacksCursor   = ref(null);

// ── Cache (in-memory, живёт пока открыта вкладка) ─────────
const packsCache = {};

async function loadSidebarPacks(append = false) {
    if (selectedIdolId.value === null) return;

    if (!append) {
        const cached = packsCache[selectedIdolId.value];
        if (cached) {
            sidebarPacks.value    = [...cached.packs];
            hasMorePacks.value    = cached.hasMore;
            nextPacksCursor.value = cached.nextCursor;
            return;
        }
        sidebarPacks.value    = [];
        hasMorePacks.value    = false;
        nextPacksCursor.value = null;
    }

    packsLoading.value = true;
    try {
        const isMine = selectedIdolId.value === 'mine';
        const { data } = await axios.get(route('gallery.packs'), {
            params: {
                idol_id: (!isMine && selectedIdolId.value) ? selectedIdolId.value : undefined,
                mine:    isMine ? true : undefined,
                cursor:  append ? nextPacksCursor.value : undefined,
            },
        });
        sidebarPacks.value.push(...data.packs);
        hasMorePacks.value    = data.has_more;
        nextPacksCursor.value = data.next_cursor;
        packsCache[selectedIdolId.value] = {
            packs:      [...sidebarPacks.value],
            hasMore:    hasMorePacks.value,
            nextCursor: nextPacksCursor.value,
        };
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
const photoLoaded = reactive({});
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
    Object.keys(photoLoaded).forEach(k => delete photoLoaded[k]);
    photos.value     = [];
    nextCursor.value = null;
    hasMore.value    = true;
    loadPhotos();
}

// Когда меняется айдол — сбрасываем пак, загружаем список паков и фотки
watch(selectedIdolId, () => {
    selectedPackId.value = null;
    loadSidebarPacks();
    resetAndLoad();
});

// Когда меняется пак — перезагружаем фотки
watch(selectedPackId, () => {
    resetAndLoad();
});

onMounted(() => { resetAndLoad(); });

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

// ── Mobile filter drawer ──────────────────────────────────
const mobileFiltersOpen  = ref(false);
const mobileFilterClosing = ref(false);
let filtersPushed = false;

const activeFilterLabel = computed(() => {
    if (selectedPackId.value) {
        const pack = sidebarPacks.value.find(p => p.id === selectedPackId.value);
        if (pack) return pack.title;
    }
    if (selectedIdolId.value === null) return __('common.all');
    if (selectedIdolId.value === 'mine') return __('gallery.mine');
    const idol = props.idols.find(i => i.id === selectedIdolId.value);
    return idol?.name ?? __('common.all');
});

const isFilterActive = computed(() => selectedIdolId.value !== null || selectedPackId.value !== null);

function openFilters() {
    history.pushState({ modal: 'gallery-filters' }, '');
    filtersPushed = true;
    mobileFiltersOpen.value = true;
}

function closeFilters() {
    if (!mobileFiltersOpen.value) return;
    mobileFilterClosing.value = true;
    filtersPushed = false;
    setTimeout(() => {
        mobileFiltersOpen.value  = false;
        mobileFilterClosing.value = false;
    }, 270);
}

function selectAll() {
    selectedIdolId.value = null;
    closeFilters();
}

function selectIdolItem(id) {
    selectedIdolId.value = id;
    selectedPackId.value = null;
}

function selectPack(id) {
    selectedPackId.value = id;
    closeFilters();
}
</script>

<template>
    <Head :title="__('gallery.title')" />

    <div class="gallery-page">

        <!-- Mobile filter/density bar (hidden on desktop) -->
        <div class="gallery-mob-bar">
            <button
                class="gallery-mob-filter-btn"
                :class="{ 'gallery-mob-filter-btn--active': isFilterActive }"
                @click="openFilters"
            >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/>
                </svg>
                <span class="gallery-mob-filter-label">{{ activeFilterLabel }}</span>
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
            <div class="gallery-density-toggle">
                <button
                    v-for="d in ['compact', 'medium', 'large']"
                    :key="d"
                    class="density-btn"
                    :class="{ 'density-btn--active': gridDensity === d }"
                    @click="gridDensity = d"
                    :aria-label="d"
                >
                    <svg v-if="d === 'compact'" width="15" height="15" viewBox="0 0 15 15" fill="currentColor">
                        <rect x="0"   y="0"   width="4" height="4" rx="0.5"/>
                        <rect x="5.5" y="0"   width="4" height="4" rx="0.5"/>
                        <rect x="11"  y="0"   width="4" height="4" rx="0.5"/>
                        <rect x="0"   y="5.5" width="4" height="4" rx="0.5"/>
                        <rect x="5.5" y="5.5" width="4" height="4" rx="0.5"/>
                        <rect x="11"  y="5.5" width="4" height="4" rx="0.5"/>
                        <rect x="0"   y="11"  width="4" height="4" rx="0.5"/>
                        <rect x="5.5" y="11"  width="4" height="4" rx="0.5"/>
                        <rect x="11"  y="11"  width="4" height="4" rx="0.5"/>
                    </svg>
                    <svg v-else-if="d === 'medium'" width="15" height="15" viewBox="0 0 15 15" fill="currentColor">
                        <rect x="0"   y="0"   width="6.5" height="6.5" rx="0.5"/>
                        <rect x="8.5" y="0"   width="6.5" height="6.5" rx="0.5"/>
                        <rect x="0"   y="8.5" width="6.5" height="6.5" rx="0.5"/>
                        <rect x="8.5" y="8.5" width="6.5" height="6.5" rx="0.5"/>
                    </svg>
                    <svg v-else width="15" height="15" viewBox="0 0 15 15" fill="currentColor">
                        <rect x="0" y="0"   width="15" height="6.5" rx="0.5"/>
                        <rect x="0" y="8.5" width="15" height="6.5" rx="0.5"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Left sidebar (desktop) / bottom sheet drawer (mobile) -->
        <aside
            class="gallery-sidebar"
            :class="{
                'gallery-sidebar--mob-open':    mobileFiltersOpen,
                'gallery-sidebar--mob-closing': mobileFilterClosing,
            }"
        >

            <!-- Mobile drawer header (hidden on desktop) -->
            <div class="gallery-sidebar__mob-header">
                <div class="gallery-sidebar__mob-handle" />
                <span class="gallery-sidebar__mob-title">{{ __('gallery.filter.title') }}</span>
                <button class="gallery-sidebar__mob-close" @click="closeFilters" :aria-label="__('common.close')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <div class="gallery-sidebar__search">
                <input
                    v-model="searchQuery"
                    type="text"
                    class="gallery-search"
                    :placeholder="__('gallery.search')"
                />
                <button
                    v-if="searchQuery"
                    class="gallery-search-clear"
                    @click="searchQuery = ''"
                    :aria-label="__('common.close')"
                    type="button"
                >
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M1 1l10 10M11 1L1 11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>

            <div class="gallery-idol-list">

                <!-- Все -->
                <button
                    class="gallery-idol-item"
                    :class="{ 'gallery-idol-item--active': selectedIdolId === null }"
                    @click="selectAll"
                >
                    <div class="gallery-idol-item__avatar gallery-idol-item__avatar--all">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                            <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
                        </svg>
                    </div>
                    <span class="gallery-idol-item__name">{{ __('common.all') }}</span>
                </button>

                <!-- Мои + айдолы — единый список -->
                <template v-for="item in sidebarItems" :key="item.id">
                    <button
                        class="gallery-idol-item"
                        :class="{ 'gallery-idol-item--active': selectedIdolId === item.id && !selectedPackId }"
                        @click="selectIdolItem(item.id)"
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
                            @click="selectPack(pack.id)"
                        >
                            <div class="gallery-pack-item__cover">
                                <template v-if="pack.cover_url">
                                    <div v-if="!packCoverLoaded[pack.id]" class="gallery-pack-item__cover-shimmer" />
                                    <img
                                        :src="pack.cover_url"
                                        :alt="pack.title"
                                        :class="{ 'gallery-pack-item__cover-img--loaded': packCoverLoaded[pack.id] }"
                                        @load="packCoverLoaded[pack.id] = true"
                                    />
                                </template>
                                <span v-else class="gallery-pack-item__cover-empty" />
                            </div>
                            <div class="gallery-pack-item__info">
                                <span class="gallery-pack-item__title">{{ pack.title }}</span>
                                <span class="gallery-pack-item__count">{{ __('pack.photos', { count: pack.photo_count }) }}</span>
                            </div>
                        </button>
                        <button
                            v-if="hasMorePacks && !packsLoading"
                            class="gallery-pack-more"
                            @click="loadSidebarPacks(true)"
                        >
                            {{ __('gallery.load_more') }}
                        </button>
                        <div v-if="packsLoading && sidebarPacks.length" class="gallery-pack-list__loading">
                            <div class="gallery-loading__spinner--sm" />
                        </div>
                    </div>
                </template>

            </div>
        </aside>

        <!-- Backdrop for mobile filter drawer -->
        <Teleport to="body">
            <Transition name="mob-backdrop">
                <div v-if="mobileFiltersOpen || mobileFilterClosing" class="gallery-mob-backdrop" @click="closeFilters" />
            </Transition>
        </Teleport>

        <!-- Main area -->
        <main class="gallery-main">

            <!-- Toolbar (desktop only) -->
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
                    <p class="gallery-empty__title">{{ __('gallery.empty.my_packs') }}</p>
                    <p class="gallery-empty__sub">{{ __('gallery.empty.my_packs.sub') }}</p>
                </template>
                <template v-else>
                    <p class="gallery-empty__title">{{ __('gallery.empty.purchased') }}</p>
                    <p class="gallery-empty__sub">{{ __('gallery.empty.purchased.sub') }}</p>
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
                    <div v-if="!photoLoaded[photo.id]" class="gallery-photo__shimmer" />
                    <img
                        :src="photo.url"
                        :alt="photo.pack_title"
                        loading="lazy"
                        :class="{ 'gallery-photo__img--loaded': photoLoaded[photo.id] }"
                        @load="photoLoaded[photo.id] = true"
                        @error="photoLoaded[photo.id] = false"
                    />
                    <div class="gallery-photo__overlay">
                        <span class="gallery-photo__overlay-title">{{ photo.pack_title }}</span>
                    </div>
                </div>

                <!-- Skeleton cards while loading -->
                <template v-if="loading">
                    <div
                        v-for="n in 24"
                        :key="`sk-${n}`"
                        class="gallery-photo gallery-photo--skeleton"
                    >
                        <div class="gallery-photo__shimmer" />
                    </div>
                </template>
            </div>

            <!-- Load more -->
            <div v-if="hasMore && !loading" class="gallery-load-more">
                <button class="gallery-load-more__btn" @click="loadPhotos">
                    {{ __('gallery.load_more') }}
                </button>
            </div>
        </main>

        <!-- Lightbox -->
        <Teleport to="body">
            <Transition name="lb-fade">
                <div v-if="lightboxPhoto" class="lb-overlay" @click="closeLightbox">

                    <!-- Counter -->
                    <div class="lb-counter">{{ lightboxIndex + 1 }} / {{ photos.length }}</div>

                    <!-- Close -->
                    <button class="lb-close" @click.stop="closeLightbox" :aria-label="__('common.close')">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>

                    <!-- Prev arrow -->
                    <button class="lb-arrow lb-arrow--prev" @click.stop="prevPhoto" :aria-label="__('gallery.prev')"><el-icon><ArrowLeft /></el-icon></button>

                    <!-- Image -->
                    <div class="lb-content" @click.stop>
                        <img :src="lightboxPhoto.url" :alt="lightboxPhoto.pack_title" class="lb-image" />
                        <div v-if="lightboxPhoto.pack_title" class="lb-caption">{{ lightboxPhoto.pack_title }}</div>
                    </div>

                    <!-- Next arrow -->
                    <button class="lb-arrow lb-arrow--next" @click.stop="nextPhoto" :aria-label="__('gallery.next')"><el-icon><ArrowRight /></el-icon></button>

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
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.gallery-search {
    flex: 1;
    min-width: 0;
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
.gallery-search:focus { border-color: rgba(255, 178, 239,0.3); }
.gallery-search::placeholder { color: rgba(255,255,255,0.25); }

.gallery-search-clear {
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.gallery-search-clear:hover {
    background: rgba(255,80,80,0.08);
    border-color: rgba(255,100,100,0.25);
    color: rgba(255,130,130,0.85);
}

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
.gallery-idol-item--active { background: rgba(255, 178, 239,0.08); color: rgba(200,200,255,0.9); }

.gallery-idol-item__avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background: rgba(255, 178, 239,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 178, 239,0.7);
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
    border-left: 1px solid rgba(255, 178, 239,0.12);
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
.gallery-pack-item--active { color: rgba(255, 178, 239,0.9); background: rgba(255, 178, 239,0.07); }

.gallery-pack-item__cover {
    position: relative;
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
    opacity: 0;
    transition: opacity 0.3s;
}
.gallery-pack-item__cover img.gallery-pack-item__cover-img--loaded { opacity: 1; }
.gallery-pack-item__cover-shimmer {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg,
        rgba(255,255,255,0.04) 25%,
        rgba(255,255,255,0.1)  50%,
        rgba(255,255,255,0.04) 75%);
    background-size: 200% 100%;
    animation: gallery-shimmer 1.5s ease-in-out infinite;
}
.gallery-pack-item__cover-empty {
    display: block;
    width: 100%;
    height: 100%;
    background: rgba(255, 178, 239,0.07);
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

.gallery-pack-more {
    width: 100%;
    padding: 0.35rem 0.75rem;
    background: transparent;
    border: none;
    color: rgba(255, 178, 239,0.5);
    font-size: 0.82rem;
    font-family: inherit;
    cursor: pointer;
    text-align: left;
    transition: color 0.15s;
}
.gallery-pack-more:hover { color: rgba(255, 178, 239,0.9); }

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
.density-btn--active { background: rgba(255, 178, 239,0.15); color: rgba(255, 178, 239,0.9); }

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
    opacity: 0;
    transition: opacity 0.35s ease, transform 0.25s;
}
.gallery-photo img.gallery-photo__img--loaded { opacity: 1; }
.gallery-photo:hover img { transform: scale(1.05); }

.gallery-photo__shimmer {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg,
        rgba(255,255,255,0.04) 25%,
        rgba(255,255,255,0.1)  50%,
        rgba(255,255,255,0.04) 75%);
    background-size: 200% 100%;
    animation: gallery-shimmer 1.5s ease-in-out infinite;
    pointer-events: none;
}

@keyframes gallery-shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

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

/* ── Loading (small spinner for sidebar packs only) ──────── */
.gallery-loading {
    display: flex;
    justify-content: center;
    padding: 1.5rem;
}
.gallery-loading__spinner--sm {
    width: 16px;
    height: 16px;
    border: 1.5px solid rgba(255, 178, 239,0.15);
    border-top-color: rgba(255, 178, 239,0.6);
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.gallery-photo--skeleton {
    cursor: default;
    pointer-events: none;
}

.gallery-load-more {
    display: flex;
    justify-content: center;
    padding: 1.5rem 1rem;
}

.gallery-load-more__btn {
    padding: 0.55rem 1.75rem;
    background: rgba(255, 178, 239,0.08);
    border: 1px solid rgba(255, 178, 239,0.2);
    border-radius: 8px;
    color: rgba(255, 178, 239,0.85);
    font-size: 0.9rem;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.gallery-load-more__btn:hover {
    background: rgba(255, 178, 239,0.15);
    border-color: rgba(255, 178, 239,0.35);
    color: rgba(200,200,255,0.95);
}

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
    font-size: 1.5rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
    z-index: 1;
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

/* ── Mobile elements (hidden on desktop) ─────────────────── */
.gallery-mob-bar             { display: none; }
.gallery-sidebar__mob-header { display: none; }
.gallery-mob-filter-btn      { display: none; }

/* ── Mobile filter bar + drawer ──────────────────────────── */
@media (max-width: 767px) {
    /* Page layout: column — sidebar removed via display:none when not open */
    .gallery-page {
        flex-direction: column;
    }

    /* Mobile bar: filter button + density toggle */
    .gallery-mob-bar {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.65rem 0.75rem;
        background: #0a0a14;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        position: sticky;
        top: 60px; /* mobile header height */
        z-index: 10;
        flex-shrink: 0;
    }

    .gallery-mob-filter-btn {
        flex: 1;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.75rem;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 6px;
        color: rgba(255,255,255,0.55);
        font-family: inherit;
        font-size: 0.85rem;
        cursor: pointer;
        text-align: left;
        transition: border-color 0.15s, background 0.15s;
    }
    .gallery-mob-filter-btn--active {
        border-color: rgba(255, 178, 239,0.3);
        color: rgba(255, 178, 239,0.9);
        background: rgba(255, 178, 239,0.07);
    }

    .gallery-mob-filter-label {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Sidebar: hidden from layout when not open or closing */
    .gallery-sidebar {
        display: none;
    }

    /* Shared styles for open and closing states */
    .gallery-sidebar--mob-open,
    .gallery-sidebar--mob-closing {
        display: flex;
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        top: auto;
        width: 100%;
        height: 80vh;
        height: 80svh;
        z-index: 2020;
        border-radius: 12px 12px 0 0;
        border-right: none;
        border-top: 1px solid rgba(255, 178, 239,0.15);
        box-shadow:
            0 -20px 60px rgba(0,0,0,0.7),
            inset 0 1px 0 rgba(255, 178, 239,0.08);
        overflow: hidden;
        background: #0a0a14;
        pointer-events: none;
    }

    /* Open: slide up with CSS animation */
    .gallery-sidebar--mob-open {
        transform: translateY(0);
        pointer-events: all;
        animation: gallery-sheet-up 0.28s cubic-bezier(0.2, 0, 0.2, 1);
    }

    /* Closing: slide back down */
    .gallery-sidebar--mob-closing {
        transform: translateY(100%);
        transition: transform 0.26s cubic-bezier(0.4, 0, 1, 1);
    }

    @keyframes gallery-sheet-up {
        from { transform: translateY(100%); }
        to   { transform: translateY(0); }
    }

    /* Mobile drawer header */
    .gallery-sidebar__mob-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1rem 0.75rem;
        border-bottom: 1px solid rgba(255,255,255,0.06);
        flex-shrink: 0;
        position: relative;
    }

    .gallery-sidebar__mob-handle {
        position: absolute;
        top: 0.4rem;
        left: 50%;
        transform: translateX(-50%);
        width: 32px;
        height: 3px;
        background: rgba(255,255,255,0.14);
        border-radius: 2px;
    }

    .gallery-sidebar__mob-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: rgba(255,255,255,0.7);
        letter-spacing: 0.02em;
    }

    .gallery-sidebar__mob-close {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,0.06);
        border: none;
        border-radius: 50%;
        color: rgba(255,255,255,0.45);
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
    }
    .gallery-sidebar__mob-close:hover {
        background: rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.8);
    }

    /* Idol list: fill sheet, scroll independently */
    .gallery-idol-list {
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    /* Hide desktop toolbar (density toggle is in mob-bar) */
    .gallery-toolbar { display: none; }

    /* Main: full width, slightly smaller padding */
    .gallery-main { padding: 0.75rem; }

    /* Lightbox arrows smaller */
    .lb-arrow { width: 36px; height: 36px; font-size: 1.2rem; }
    .lb-arrow--prev { left: 8px; }
    .lb-arrow--next { right: 8px; }
    .lb-content { max-width: calc(90vw - 90px); }
}

/* ── Backdrop for mobile filter drawer ───────────────────── */
.gallery-mob-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(2,1,6,0.6);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    z-index: 2019;
    pointer-events: all;
}

.mob-backdrop-enter-active, .mob-backdrop-leave-active { transition: opacity 0.22s ease; }
.mob-backdrop-enter-from, .mob-backdrop-leave-to { opacity: 0; }
</style>
