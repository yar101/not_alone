<script setup>
import { ref, computed, inject, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Picture } from '@element-plus/icons-vue';
import CreateButton from '@/Components/CreateButton.vue';
import SortDropdown from '@/Components/SortDropdown.vue';
import CreateContentPackModal from '@/Components/Profile/CreateContentPackModal.vue';
import ContentPackRemarksModal from '@/Components/Profile/ContentPackRemarksModal.vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import PackStatusBadge from '@/Components/Profile/PackStatusBadge.vue';

const props = defineProps({
    contentPacks: { default: null },
    purchasedPackIds: { default: () => [] },
    isOwner: { type: Boolean, default: false },
    isIdol: { type: Boolean, default: false },
    profileUser: { type: Object, required: true },
});

const openAuth = inject('openAuth', null);
const addToContentCart = inject('addToContentCart', null);
const cart = inject('cart', null);
const openCart = inject('openCart', null);

function isInCart(packId) {
    return cart?.value?.content?.items?.some(i => i.pack_id === packId) ?? false;
}

const showCreateModal = ref(false);
const showRemarksModal = ref(false);
const selectedPack = ref(null);

// ── Pack detail modal ─────────────────────────────────────
const showDetailModal = ref(false);
const detailPack = ref(null);

function openDetail(pack) {
    detailPack.value = pack;
    showDetailModal.value = true;
}
function closeDetail() {
    showDetailModal.value  = false;
    showCoverPicker.value  = false;
    coverFullscreen.value  = false;
    editingTitle.value     = false;
    editingDesc.value      = false;
    editingPrice.value     = false;
    showEditMenu.value     = false;
    detailPack.value       = null;
}

// ── Cover fullscreen ──────────────────────────────────────
const coverFullscreen = ref(false);

// ── Cover picker (owner, published packs) ─────────────────
const showCoverPicker = ref(false);
const coverUpdating   = ref(false);

// ── Title editing (owner, published packs) ────────────────
const editingTitle  = ref(false);
const titleDraft    = ref('');
const titleUpdating = ref(false);

// ── Edit dropdown menu ────────────────────────────────────
const showEditMenu = ref(false);

// ── Price editing (owner, published packs) ────────────────
const editingPrice  = ref(false);
const priceDraft    = ref('');
const priceUpdating = ref(false);

function startEditPrice() {
    priceDraft.value  = String(detailPack.value.price);
    editingPrice.value = true;
    showEditMenu.value = false;
}
function cancelEditPrice() { editingPrice.value = false; }
async function savePrice() {
    const val = parseInt(priceDraft.value, 10);
    if (!val || val === detailPack.value.price) { editingPrice.value = false; return; }
    priceUpdating.value = true;
    try {
        const { data } = await axios.patch(
            route('content-packs.price', detailPack.value.id),
            { price: val }
        );
        detailPack.value = { ...detailPack.value, price: data.price };
        if (localPacks.value) {
            const idx = localPacks.value.findIndex(p => p.id === detailPack.value.id);
            if (idx !== -1) localPacks.value[idx] = { ...localPacks.value[idx], price: data.price };
        }
        editingPrice.value = false;
    } finally {
        priceUpdating.value = false;
    }
}

// ── Description editing (owner, published packs) ──────────
const editingDesc  = ref(false);
const descDraft    = ref('');
const descUpdating = ref(false);

function startEditDesc() {
    descDraft.value  = detailPack.value.description ?? '';
    editingDesc.value = true;
}
function cancelEditDesc() {
    editingDesc.value = false;
}
async function saveDesc() {
    const trimmed = descDraft.value.trim() || null;
    if (trimmed === (detailPack.value.description ?? null)) { editingDesc.value = false; return; }
    descUpdating.value = true;
    try {
        const { data } = await axios.patch(
            route('content-packs.description', detailPack.value.id),
            { description: trimmed }
        );
        detailPack.value = { ...detailPack.value, description: data.description };
        if (localPacks.value) {
            const idx = localPacks.value.findIndex(p => p.id === detailPack.value.id);
            if (idx !== -1) localPacks.value[idx] = { ...localPacks.value[idx], description: data.description };
        }
        editingDesc.value = false;
    } finally {
        descUpdating.value = false;
    }
}

function startEditTitle() {
    titleDraft.value  = detailPack.value.title;
    editingTitle.value = true;
}
function cancelEditTitle() {
    editingTitle.value = false;
}
async function saveTitle() {
    const trimmed = titleDraft.value.trim();
    if (!trimmed || trimmed === detailPack.value.title) { editingTitle.value = false; return; }
    titleUpdating.value = true;
    try {
        const { data } = await axios.patch(
            route('content-packs.title', detailPack.value.id),
            { title: trimmed }
        );
        detailPack.value = { ...detailPack.value, title: data.title };
        if (localPacks.value) {
            const idx = localPacks.value.findIndex(p => p.id === detailPack.value.id);
            if (idx !== -1) localPacks.value[idx] = { ...localPacks.value[idx], title: data.title };
        }
        editingTitle.value = false;
    } finally {
        titleUpdating.value = false;
    }
}

async function updateCover(photo) {
    if (coverUpdating.value) return;
    coverUpdating.value = true;
    try {
        const { data } = await axios.patch(
            route('content-packs.cover', detailPack.value.id),
            { photo_id: photo.id }
        );
        // Update modal cover immediately
        detailPack.value = { ...detailPack.value, cover_url: data.cover_url };
        // Update card in list
        if (localPacks.value) {
            const idx = localPacks.value.findIndex(p => p.id === detailPack.value.id);
            if (idx !== -1) localPacks.value[idx] = { ...localPacks.value[idx], cover_url: data.cover_url };
        }
        showCoverPicker.value = false;
    } finally {
        coverUpdating.value = false;
    }
}

// ── Sort + Infinite scroll ────────────────────────────────
const sort = ref('newest');
const localPacks = ref(null);   // null = still waiting for Inertia deferred
const localPurchasedIds = ref([]);
const cursor = ref(null);
const hasMore = ref(false);
const loading = ref(false);
const sentinel = ref(null);
let observer = null;

// When Inertia deferred prop arrives, initialize local state
watch(() => props.contentPacks, (val) => {
    if (val !== null && localPacks.value === null) {
        // Initial deferred load - no cursor pagination info, so just store as-is
        localPacks.value = val;
    }
}, { immediate: true });

watch(() => props.purchasedPackIds, (val) => {
    localPurchasedIds.value = val ?? [];
}, { immediate: true });

const displayPacks = () => localPacks.value ?? props.contentPacks;
const displayPurchasedIds = () => localPurchasedIds.value;

// For owner: has_remarks → approved → rest
const STATUS_PRIORITY = { has_remarks: 0, approved: 1 };
const ownerPacksSorted = computed(() => {
    const packs = displayPacks();
    if (!packs) return packs;
    return [...packs].sort((a, b) => {
        const ap = STATUS_PRIORITY[a.status] ?? 2;
        const bp = STATUS_PRIORITY[b.status] ?? 2;
        return ap - bp;
    });
});

async function fetchPacks(reset = false) {
    if (loading.value) return;
    loading.value = true;

    try {
        const params = { sort: sort.value };
        if (!reset && cursor.value) params.cursor = cursor.value;

        const res = await axios.get(route('profile.content-packs.index', props.profileUser), { params });
        const data = res.data;

        if (reset) {
            localPacks.value = data.packs;
            localPurchasedIds.value = data.purchased_ids ?? [];
        } else {
            localPacks.value = [...(localPacks.value ?? []), ...data.packs];
        }

        cursor.value = data.next_cursor ?? null;
        hasMore.value = data.has_more ?? false;
    } finally {
        loading.value = false;
    }
}

function onSortChange() {
    cursor.value = null;
    localPacks.value = [];
    fetchPacks(true);
}

function setupObserver() {
    if (observer) observer.disconnect();
    observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && hasMore.value && !loading.value) {
            fetchPacks(false);
        }
    }, { rootMargin: '200px' });
    if (sentinel.value) observer.observe(sentinel.value);
}

onMounted(() => {
    // sentinel ref is available after mount
    setupObserver();
});
onUnmounted(() => observer?.disconnect());

// ── Modals ────────────────────────────────────────────────
function openRemarks(pack) {
    selectedPack.value = pack;
    showRemarksModal.value = true;
}

function onRemarksSubmitted(packId) {
    updatePackInList(packId, { status: 'pending_review' });
    if (detailPack.value?.id === packId) {
        detailPack.value = { ...detailPack.value, status: 'pending_review' };
    }
}

function updatePackInList(id, changes) {
    if (!localPacks.value) return;
    const idx = localPacks.value.findIndex(p => p.id === id);
    if (idx !== -1) localPacks.value[idx] = { ...localPacks.value[idx], ...changes };
}

function handlePublish(pack) {
    router.post(route('content-packs.publish', pack.id), {}, {
        preserveScroll: true,
        onSuccess: () => fetchPacks(true),
    });
}

const showDeleteConfirm = ref(false);
const packToDelete      = ref(null);

function handleDelete(pack) {
    packToDelete.value     = pack;
    showDeleteConfirm.value = true;
}

function confirmDelete() {
    if (!packToDelete.value) return;
    const id = packToDelete.value.id;
    showDeleteConfirm.value = false;
    packToDelete.value      = null;
    if (localPacks.value) {
        localPacks.value = localPacks.value.filter(p => p.id !== id);
    }
    router.delete(route('content-packs.destroy', id), { preserveScroll: true });
}

function cancelDelete() {
    showDeleteConfirm.value = false;
    packToDelete.value      = null;
}

function handleAddToCart(pack) {
    if (!addToContentCart) {
        openAuth?.('register');
        return;
    }
    addToContentCart(pack);
}

const sortOptions = [
    { value: 'newest', label: 'сначала новые' },
    { value: 'oldest', label: 'сначала старые' },
];

</script>

<template>
    <div class="pc-wrap">
        <div>

            <!-- Loading skeleton -->
            <template v-if="displayPacks() === null">
                <div class="pc-grid">
                    <div v-for="n in 6" :key="n" class="pc-skeleton-card">
                        <div class="pc-skeleton-card__cover" />
                        <div class="pc-skeleton-card__body">
                            <div class="pc-skeleton-line pc-skeleton-line--title" />
                            <div class="pc-skeleton-line pc-skeleton-line--short" />
                            <div class="pc-skeleton-line pc-skeleton-line--xshort" />
                        </div>
                    </div>
                </div>
            </template>

            <!-- Owner but not idol yet -->
            <template v-else-if="isOwner && !isIdol">
                <div class="pc-empty">
                    <p>Раздел контента доступен только для айдолов.</p>
                </div>
            </template>

            <!-- Owner + Idol view -->
            <template v-else-if="isOwner && isIdol">
                <div class="pc-toolbar">
                    <SortDropdown :options="sortOptions" v-model="sort" @update:modelValue="onSortChange" />
                    <CreateButton @click="showCreateModal = true">
                        <template #icon>+</template>
                        Создать пак
                    </CreateButton>
                </div>

                <div v-if="ownerPacksSorted !== null && !ownerPacksSorted?.length && !loading" class="pc-empty">
                    <p>У вас пока нет паков. Создайте первый!</p>
                </div>

                <div v-else class="pc-grid">
                    <div v-for="pack in ownerPacksSorted" :key="pack.id" class="pc-card pc-card--owner"
                        @click="openDetail(pack)">
                        <div class="pc-card__cover">
                            <img v-if="pack.cover_url" :src="pack.cover_url" :alt="pack.title" loading="lazy" />
                            <div v-else class="pc-card__cover-placeholder" />
                            <div class="pc-card__photo-badge">
                                <el-icon :size="18">
                                    <Picture />
                                </el-icon>
                                {{ pack.photos_count }}
                            </div>
                            <div class="pc-card__cover-overlay">
                                <button class="pc-card__cover-btn">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="11" cy="11" r="8" />
                                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                    </svg>
                                    Подробнее
                                </button>
                            </div>
                        </div>
                        <div class="pc-card__body">
                            <div class="pc-card__title">{{ pack.title }}</div>
                            <PackStatusBadge :status="pack.status" />
                        </div>
                        <div class="pc-card__footer">
                            <template v-if="pack.status === 'has_remarks'">
                                <button class="pc-btn--details pc-btn--details-warn"
                                    @click.stop="openRemarks(pack)">Посмотреть замечания</button>
                                <button class="pc-btn--details-icon" @click.stop="handleDelete(pack)">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <polyline points="3 6 5 6 21 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M10 11v6M14 11v6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </template>
                            <template v-else>
                                <button v-if="pack.status === 'approved'" class="pc-btn pc-btn--primary pc-btn--grow"
                                    @click.stop="handlePublish(pack)">Опубликовать</button>
                                <button v-if="pack.status !== 'approved'" class="pc-btn--details" @click.stop="openDetail(pack)">Подробнее</button>
                                <button v-if="['approved', 'rejected'].includes(pack.status)"
                                    class="pc-btn--details-icon" @click.stop="handleDelete(pack)">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <polyline points="3 6 5 6 21 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M10 11v6M14 11v6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Visitor view -->
            <template v-else>
                <div class="pc-sort-bar">
                    <SortDropdown :options="sortOptions" v-model="sort" @update:modelValue="onSortChange" />
                </div>

                <div v-if="displayPacks() !== null && !displayPacks().length && !loading" class="pc-empty">
                    <p>Нет опубликованных паков.</p>
                </div>
                <div v-else class="pc-grid">
                    <div v-for="pack in displayPacks()" :key="pack.id" class="pc-card pc-card--visitor"
                        @click="openDetail(pack)">
                        <div class="pc-card__cover">
                            <img v-if="pack.cover_url" :src="pack.cover_url" :alt="pack.title" loading="lazy" />
                            <div v-else class="pc-card__cover-placeholder" />
                            <div class="pc-card__photo-badge">
                                <el-icon :size="18">
                                    <Picture />
                                </el-icon>
                                {{ pack.photos_count }}
                            </div>
                            <div class="pc-card__cover-overlay">
                                <button class="pc-card__cover-btn">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="11" cy="11" r="8" />
                                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                    </svg>
                                    Подробнее
                                </button>
                            </div>
                        </div>
                        <div class="pc-card__body">
                            <div class="pc-card__title">{{ pack.title }}</div>
                        </div>
                        <button class="pc-btn pc-btn--cart-full" :class="{
                            'pc-btn--in-cart': isInCart(pack.id) && !displayPurchasedIds().includes(pack.id),
                            'pc-btn--purchased': displayPurchasedIds().includes(pack.id),
                        }"
                            @click.stop="displayPurchasedIds().includes(pack.id) ? router.visit(route('gallery.index')) : isInCart(pack.id) ? openCart?.('content') : handleAddToCart(pack)">
                            <template v-if="displayPurchasedIds().includes(pack.id)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                Открыт
                            </template>
                            <template v-else-if="isInCart(pack.id)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                Добавлено
                            </template>
                            <template v-else>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                В корзину
                            </template>
                        </button>
                    </div>
                </div>
            </template>

            <!-- Infinite scroll sentinel -->
            <div ref="sentinel" class="pc-sentinel" />

            <!-- Loading indicator -->
            <div v-if="loading" class="pc-loading">
                <span class="pc-loading__dot" /><span class="pc-loading__dot" /><span class="pc-loading__dot" />
            </div>

        </div>

        <!-- Modals -->
        <CreateContentPackModal :show="showCreateModal" @close="showCreateModal = false" @created="fetchPacks(true)" />

        <!-- Delete confirmation -->
        <SiteModal :show="showDeleteConfirm" @close="cancelDelete" compact max-width="400px" variant="pink">
            <div class="pc-delete-confirm">
                <div class="pc-delete-confirm__icon">
                    <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <polyline points="3 6 5 6 21 6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M10 11v6M14 11v6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="pc-delete-confirm__title">Удалить пак?</div>
                <div class="pc-delete-confirm__text">«{{ packToDelete?.title }}» будет удалён без возможности восстановления.</div>
                <div class="pc-delete-confirm__actions">
                    <button class="pc-delete-confirm__btn pc-delete-confirm__btn--cancel" @click="cancelDelete">Отмена</button>
                    <button class="pc-delete-confirm__btn pc-delete-confirm__btn--confirm" @click="confirmDelete">Удалить</button>
                </div>
            </div>
        </SiteModal>

        <ContentPackRemarksModal v-if="selectedPack" :show="showRemarksModal" :pack="selectedPack"
            @close="showRemarksModal = false"
            @submitted="onRemarksSubmitted" />

        <!-- Pack detail modal -->
        <SiteModal :show="showDetailModal" @close="closeDetail" compact max-width="560px" min-height="70vh" variant="pink" hide-close-btn>
            <template v-if="detailPack">

                <!-- ⓪ Top bar: badge (owner) + close button -->
                <div class="pcd-topbar">
                    <PackStatusBadge v-if="isOwner" :status="detailPack.status" />
                    <div class="pcd-topbar__spacer" />
                    <button class="pcd-topbar__close" @click="closeDetail" aria-label="Закрыть">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- ① Cover — full-bleed hero -->
                <div class="pcd-hero" :class="{ 'pcd-hero--clickable': detailPack.cover_url }" @click="detailPack.cover_url && (coverFullscreen = true)">
                    <img v-if="detailPack.cover_url" :src="detailPack.cover_url" :alt="detailPack.title" class="pcd-hero__img" />
                    <div v-else class="pcd-hero__empty">
                        <el-icon :size="36"><Picture /></el-icon>
                    </div>

                    <!-- Scrim: zoom button only -->
                    <div class="pcd-hero__scrim">
                        <div class="pcd-hero__spacer" />
                        <button v-if="detailPack.cover_url" class="pcd-hero__zoom" @click.stop="coverFullscreen = true" title="Открыть">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                        </button>
                    </div>
                </div>

                <!-- ② Cover picker (owner, slide-in) -->
                <Transition name="pcd-slide">
                    <div v-if="showCoverPicker && detailPack.photos?.length" class="pcd-picker">
                        <div class="pcd-picker__label">Выберите обложку</div>
                        <div class="pcd-picker__grid">
                            <button
                                v-for="photo in detailPack.photos"
                                :key="photo.id"
                                class="pcd-picker__item"
                                :class="{ 'pcd-picker__item--active': detailPack.cover_url === photo.url }"
                                :disabled="coverUpdating"
                                @click="updateCover(photo)"
                            >
                                <img :src="photo.url" loading="lazy" />
                                <div v-if="detailPack.cover_url === photo.url" class="pcd-picker__check">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                            </button>
                        </div>
                    </div>
                </Transition>

                <!-- ③ Info body -->
                <div class="pcd-body">

                    <!-- Title -->
                    <div class="pcd-title-wrap">
                        <template v-if="isOwner && detailPack.status === 'published' && editingTitle">
                            <input
                                class="pcd-field-input pcd-field-input--title"
                                v-model="titleDraft"
                                maxlength="120"
                                :disabled="titleUpdating"
                                @keydown.enter="saveTitle"
                                @keydown.esc="cancelEditTitle"
                                autofocus
                            />
                            <div class="pcd-inline-btns">
                                <button class="pcd-inline-btn pcd-inline-btn--save" :disabled="titleUpdating" @click="saveTitle">Сохранить</button>
                                <button class="pcd-inline-btn pcd-inline-btn--cancel" @click="cancelEditTitle">Отмена</button>
                            </div>
                        </template>
                        <h3 v-else class="pcd-title">{{ detailPack.title }}</h3>
                    </div>

                    <!-- Description -->
                    <div class="pcd-desc-wrap">
                        <template v-if="isOwner && detailPack.status === 'published' && editingDesc">
                            <textarea
                                class="pcd-field-input pcd-field-input--desc"
                                v-model="descDraft"
                                maxlength="2000"
                                :disabled="descUpdating"
                                rows="4"
                                placeholder="Описание пака..."
                                @keydown.esc="cancelEditDesc"
                            />
                            <div class="pcd-inline-btns">
                                <button class="pcd-inline-btn pcd-inline-btn--save" :disabled="descUpdating" @click="saveDesc">Сохранить</button>
                                <button class="pcd-inline-btn pcd-inline-btn--cancel" @click="cancelEditDesc">Отмена</button>
                            </div>
                        </template>
                        <p v-else-if="detailPack.description" class="pcd-desc">{{ detailPack.description }}</p>
                        <p v-else-if="isOwner && detailPack.status === 'published'" class="pcd-desc pcd-desc--empty">Описание не добавлено</p>
                    </div>

                    <!-- Meta row: photos + price (price editable) -->
                    <div class="pcd-meta-row">
                        <span class="pcd-meta-count">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            {{ detailPack.photos_count }} фото
                        </span>
                        <template v-if="editingPrice">
                            <div class="pcd-price-edit">
                                <input
                                    class="pcd-price-input"
                                    v-model="priceDraft"
                                    type="number"
                                    min="1"
                                    max="999999"
                                    :disabled="priceUpdating"
                                    @keydown.enter="savePrice"
                                    @keydown.esc="cancelEditPrice"
                                    autofocus
                                />
                                <span class="pcd-price-rub">₽</span>
                                <button class="pcd-inline-btn pcd-inline-btn--save" :disabled="priceUpdating" @click="savePrice">OK</button>
                                <button class="pcd-inline-btn pcd-inline-btn--cancel" @click="cancelEditPrice">✕</button>
                            </div>
                        </template>
                        <span v-else class="pcd-meta-price">{{ detailPack.price }} ₽</span>
                    </div>

                    <!-- Cart / owner management -->
                    <div class="pcd-actions">
                        <template v-if="isOwner">
                            <div v-if="['has_remarks','approved','rejected'].includes(detailPack.status)" class="pcd-owner-btns">
                                <button v-if="detailPack.status === 'approved'" class="pc-btn pc-btn--primary pc-btn--grow" @click="handlePublish(detailPack); closeDetail()">Опубликовать</button>
                                <button v-if="detailPack.status === 'has_remarks'" class="pc-btn pc-btn--warn pc-btn--grow" @click="openRemarks(detailPack); closeDetail()">Посмотреть замечания</button>
                                <button class="pc-btn pc-btn--danger pc-btn--icon" @click="handleDelete(detailPack); closeDetail()">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <polyline points="3 6 5 6 21 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M10 11v6M14 11v6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                        <template v-else>
                            <button
                                class="pcd-cart-btn"
                                :class="{
                                    'pcd-cart-btn--in-cart':   isInCart(detailPack.id) && !displayPurchasedIds().includes(detailPack.id),
                                    'pcd-cart-btn--purchased': displayPurchasedIds().includes(detailPack.id),
                                }"
                                @click="displayPurchasedIds().includes(detailPack.id) ? router.visit(route('gallery.index')) : isInCart(detailPack.id) ? (closeDetail(), openCart?.('content')) : handleAddToCart(detailPack)"
                            >
                                <template v-if="displayPurchasedIds().includes(detailPack.id)">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    Открыт
                                </template>
                                <template v-else-if="isInCart(detailPack.id)">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                    Добавлено
                                </template>
                                <template v-else>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                    В корзину
                                </template>
                            </button>
                        </template>
                    </div>

                    <!-- Edit dropdown (owner + published, bottom) -->
                    <div v-if="isOwner && detailPack.status === 'published' && !editingTitle && !editingDesc && !editingPrice" class="pcd-edit-wrap">
                        <!-- Backdrop to close menu -->
                        <div v-if="showEditMenu" class="pcd-edit-backdrop" @click="showEditMenu = false" />
                        <!-- Menu (opens upward) -->
                        <Transition name="pcd-menu">
                            <div v-if="showEditMenu" class="pcd-edit-menu">
                                <button class="pcd-edit-item" @click="showCoverPicker = !showCoverPicker; showEditMenu = false">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                    Сменить обложку
                                </button>
                                <button class="pcd-edit-item" @click="startEditTitle(); showEditMenu = false">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Изменить название
                                </button>
                                <button class="pcd-edit-item" @click="startEditDesc(); showEditMenu = false">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
                                    Изменить описание
                                </button>
                                <button class="pcd-edit-item" @click="startEditPrice()">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                    Изменить цену
                                </button>
                            </div>
                        </Transition>
                        <button class="pcd-edit-toggle" @click="showEditMenu = !showEditMenu">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Редактировать
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" :style="{ transform: showEditMenu ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s' }"><polyline points="18 15 12 9 6 15"/></svg>
                        </button>
                    </div>

                </div><!-- /pcd-body -->
            </template>
        </SiteModal>

        <!-- Cover fullscreen -->
        <Teleport to="body">
            <Transition name="pc-fs">
                <div v-if="coverFullscreen" class="pc-fs-overlay" @click="coverFullscreen = false">
                    <img :src="detailPack?.cover_url" class="pc-fs-img" @click.stop />
                    <button class="pc-fs-close" @click="coverFullscreen = false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.pc-wrap {
    padding: 0.25rem 0.75rem;
}

.pc-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.pc-sort-bar {
    margin-bottom: 0.85rem;
}

.pc-sentinel {
    height: 1px;
}

.pc-loading {
    display: flex;
    justify-content: center;
    gap: 6px;
    padding: 1rem 0;
}

.pc-loading__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: rgba(160, 160, 255, 0.5);
    animation: dot-pulse 1.2s ease-in-out infinite;
}

.pc-loading__dot:nth-child(2) {
    animation-delay: 0.2s;
}

.pc-loading__dot:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes dot-pulse {

    0%,
    80%,
    100% {
        transform: scale(0.7);
        opacity: 0.4;
    }

    40% {
        transform: scale(1);
        opacity: 1;
    }
}

.pc-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: rgba(255, 255, 255, 0.35);
    font-size: 0.95rem;
}

.pc-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
}

@media (max-width: 600px) {
    .pc-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* ── Skeleton ────────────────────────────────────────────── */
@keyframes shimmer {
    0% {
        background-position: -200% 0;
    }

    100% {
        background-position: 200% 0;
    }
}

.pc-skeleton-card {
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.06);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.pc-skeleton-card__cover {
    aspect-ratio: 1/1;
    background: linear-gradient(90deg,
            rgba(255, 255, 255, 0.05) 25%,
            rgba(255, 255, 255, 0.1) 50%,
            rgba(255, 255, 255, 0.05) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.6s ease-in-out infinite;
}

.pc-skeleton-card__body {
    padding: 0.5rem 0.6rem 0.65rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.pc-skeleton-line {
    border-radius: 4px;
    background: linear-gradient(90deg,
            rgba(255, 255, 255, 0.05) 25%,
            rgba(255, 255, 255, 0.1) 50%,
            rgba(255, 255, 255, 0.05) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.6s ease-in-out infinite;
}

.pc-skeleton-line--title {
    height: 11px;
    width: 85%;
    animation-delay: 0.1s;
}

.pc-skeleton-line--short {
    height: 10px;
    width: 45%;
    animation-delay: 0.2s;
}

.pc-skeleton-line--xshort {
    height: 9px;
    width: 30%;
    animation-delay: 0.3s;
}

.pc-card {
    border-radius: 12px;
    background: rgba(20, 14, 40, 0.55);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14);
    overflow: hidden;
    isolation: isolate;
    display: flex;
    flex-direction: column;
    padding: 0.85rem;
    gap: 0.5rem;
}

.pc-card--visitor {
    cursor: pointer;
}

.pc-card--visitor:hover {}

.pc-card--owner {
    cursor: pointer;
}

.pc-card--owner:hover {}

.pc-card__cover {
    position: relative;
    aspect-ratio: 1/1;
    background: rgba(255, 255, 255, 0.04);
    overflow: hidden;
    border-radius: 8px;
}

.pc-card__cover-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.25s ease;
    pointer-events: none;
}

.pc-card__cover:hover .pc-card__cover-overlay {
    opacity: 1;
}

.pc-card__cover-btn {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(0, 0, 0, 0.55);
    border: 1px solid rgba(255, 255, 255, 0.22);
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.88rem;
    font-weight: 500;
    font-family: inherit;
    padding: 0.4rem 0.85rem;
    border-radius: 20px;
    cursor: pointer;
    pointer-events: all;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
    transition: background 0.18s, border-color 0.18s, color 0.18s, transform 0.18s;
}

.pc-card__cover-btn:hover {
    background: rgba(255, 255, 255, 0.18);
    border-color: rgba(255, 255, 255, 0.55);
    color: #fff;
}

.pc-card__cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.35s ease;
}

.pc-card__cover:hover img {
    transform: scale(1.07);
}

.pc-card__cover-placeholder {
    width: 100%;
    height: 100%;
    border: 1px dashed rgba(255, 255, 255, 0.12);
    border-radius: 6px;
    box-sizing: border-box;
}

.pc-card__photo-badge {
    position: absolute;
    bottom: 8px;
    left: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    color: rgba(255, 255, 255, 0.95);
    font-size: 1.1rem;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 20px;
}

.pc-card__body {
    padding: 0.5rem 0.6rem 0.4rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex: 1;
}

.pc-card--visitor .pc-card__body {
    padding: 0;
}

.pc-card__footer {
    padding: 0 0.6rem 0.6rem;
    display: flex;
    gap: 0.3rem;
    flex-shrink: 0;
}

.pc-card__title {
    font-size: 1.15rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.88);
    line-height: 1.3;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.pc-card__price {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.75);
    font-weight: 500;
}


.pc-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    margin-top: 0.2rem;
}


.pc-btn {
    padding: 0.38rem 0.8rem;
    border-radius: 5px;
    font-size: 0.85rem;
    font-weight: 500;
    border: 1px solid;
    cursor: pointer;
    font-family: inherit;
    transition: opacity 0.15s, background 0.15s;
}

.pc-btn--primary {
    background: rgba(100, 210, 160, 0.15);
    border-color: rgba(100, 210, 160, 0.4);
    border-top: none;
    color: #64d2a0;
    box-shadow: inset 0 1px 0 rgba(120, 240, 175, 0.60);
}

.pc-btn--primary:hover {
    background: rgba(100, 210, 160, 0.25);
}

.pc-btn--warn {
    background: rgba(230, 180, 60, 0.12);
    border-color: rgba(230, 180, 60, 0.35);
    color: rgba(240, 195, 80, 0.9);
}

.pc-btn--warn:hover {
    background: rgba(230, 180, 60, 0.22);
}

.pc-btn--danger {
    background: rgba(180, 60, 60, 0.1);
    border-color: rgba(180, 60, 60, 0.3);
    color: rgba(255, 120, 120, 0.7);
}

.pc-btn--danger:hover {
    background: rgba(180, 60, 60, 0.18);
}

.pc-btn--grow {
    flex: 1;
}

.pc-btn--icon {
    padding: 0.38rem 0.7rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Full-width flush cart button (visitor card) */
.pc-btn--cart-full {
    width: 100%;
    padding: 0.55rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border: none;
    border-top: none;
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s, color 0.15s, transform 0.1s ease;
    background: rgba(100, 160, 255, 0.1);
    color: rgba(160, 200, 255, 0.9);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.pc-btn--cart-full:hover {
    background: rgba(100, 160, 255, 0.2);
}

.pc-btn--cart-full.pc-btn--in-cart {
    background: rgba(50, 255, 150, 0.18);
    color: rgb(80, 255, 170);
    cursor: pointer;
}

.pc-btn--cart-full.pc-btn--in-cart:hover {
    background: rgba(50, 255, 150, 0.26);
}

.pc-btn--cart-full.pc-btn--in-cart:active {
    background: rgba(50, 255, 150, 0.35);
    transform: scale(0.97);
}

.pc-btn--cart-full:not(.pc-btn--in-cart):not(.pc-btn--purchased):active {
    transform: scale(0.97);
    background: rgba(100, 160, 255, 0.3);
}

.pc-btn--cart-full.pc-btn--purchased {
    background: rgba(100, 210, 160, 0.18);
    color: rgb(100, 230, 170);
    cursor: pointer;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.pc-btn--cart-full.pc-btn--purchased:hover {
    background: rgba(100, 210, 160, 0.28);
}

/* Owner card footer buttons — shared base */
.pc-btn--details,
.pc-btn--details-icon,
.pc-btn--details-warn {
    padding: 0.55rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border: 1px solid rgba(255, 255, 255, 0.10);
    border-top: none;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.07);
    color: rgba(255, 255, 255, 0.65);
    font-size: 0.92rem;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    letter-spacing: 0.01em;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.32);
    transition: background 0.15s, color 0.15s;
    flex-shrink: 0;
}

.pc-btn--details {
    flex: 1;
    font-size: 0.85rem;
}

.pc-btn--details:hover {
    background: rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.85);
}

/* Замечания — base-1 акцент */
.pc-btn--details-warn {
    flex: 1;
    border-color: rgba(160, 160, 255, 0.35);
    background: rgba(160, 160, 255, 0.10);
    color: var(--color-base-1);
    box-shadow: inset 0 1px 0 rgba(180, 180, 255, 0.70);
}

.pc-btn--details-warn:hover {
    background: rgba(160, 160, 255, 0.18);
    color: rgba(180, 180, 255, 1);
}

/* Корзина — красный акцент */
.pc-btn--details-icon {
    border-color: rgba(180, 60, 60, 0.3);
    background: rgba(180, 60, 60, 0.08);
    color: rgba(255, 110, 110, 0.75);
    box-shadow: inset 0 1px 0 rgba(255, 120, 120, 0.40);
}

.pc-btn--details-icon:hover {
    background: rgba(180, 60, 60, 0.16);
    color: rgba(255, 110, 110, 1);
}

/* ── Pack detail modal ───────────────────────────────────── */

/* Top bar: bleeds to sides, sits where padding-top was */
.pcd-topbar {
    margin: -3rem -2rem 0;
    padding: 0.6rem 0.75rem 0.6rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    background: rgba(10,8,18,0.6);
}
@media (max-width: 768px) {
    .pcd-topbar { margin: -3rem -1.25rem 0; }
}
.pcd-topbar__spacer { flex: 1; }
.pcd-topbar__close {
    width: 34px;
    height: 34px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: rgba(255,255,255,0.28);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: color 0.18s, background 0.18s, transform 0.22s;
}
.pcd-topbar__close:hover {
    color: rgba(220,100,145,0.9);
    background: rgba(110,110,210,0.1);
    transform: rotate(90deg);
}

/* Hero cover — full-bleed, directly below topbar */
.pcd-hero {
    margin: 0 -2rem;
    position: relative;
    overflow: hidden;
    background: rgba(255,255,255,0.03);
    min-height: 180px;
}
@media (max-width: 768px) {
    .pcd-hero { margin: 0 -1.25rem; }
}
.pcd-hero--clickable { cursor: zoom-in; }
.pcd-hero__img {
    width: 100%;
    height: auto;
    display: block;
}
.pcd-hero__empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 180px;
    color: rgba(255,255,255,0.12);
}
/* Scrim: gradient fade from bottom, holds badge + zoom */
.pcd-hero__scrim {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: flex-end;
    padding: 0.75rem;
    background: linear-gradient(
        to top,
        rgba(7,6,11,0.72) 0%,
        rgba(7,6,11,0.18) 40%,
        transparent 70%
    );
    pointer-events: none;
}
.pcd-hero__scrim > * { pointer-events: all; }
.pcd-hero__spacer { flex: 1; }
.pcd-hero__zoom {
    width: 30px;
    height: 30px;
    border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.18);
    background: rgba(0,0,0,0.45);
    color: rgba(255,255,255,0.75);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    opacity: 0;
    transition: opacity 0.2s;
}
.pcd-hero:hover .pcd-hero__zoom { opacity: 1; }
.pcd-hero__zoom:hover {
    background: rgba(255,255,255,0.18);
    color: #fff;
}

/* Slide transition for cover picker */
.pcd-slide-enter-active, .pcd-slide-leave-active {
    transition: max-height 0.22s ease, opacity 0.18s ease;
    overflow: hidden;
}
.pcd-slide-enter-from, .pcd-slide-leave-to { max-height: 0; opacity: 0; }
.pcd-slide-enter-to, .pcd-slide-leave-from { max-height: 400px; opacity: 1; }

/* Body — padded content below hero */
.pcd-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding-top: 1.1rem;
}

/* Title */
.pcd-title-wrap { display: flex; flex-direction: column; gap: 0.4rem; }
.pcd-title {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 700;
    color: rgba(255,255,255,0.96);
    line-height: 1.25;
    letter-spacing: -0.01em;
}

/* Description */
.pcd-desc-wrap { display: flex; flex-direction: column; gap: 0.4rem; }
.pcd-desc {
    margin: 0;
    font-size: 1rem;
    color: rgba(255,255,255,0.75);
    line-height: 1.65;
    white-space: pre-wrap;
}
.pcd-desc--empty {
    font-style: italic;
    color: rgba(255,255,255,0.2);
}

/* Edit fields */
.pcd-field-input {
    width: 100%;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    color: rgba(255,255,255,0.95);
    font-family: inherit;
    padding: 0.5rem 0.75rem;
    outline: none;
    transition: border-color 0.15s;
    resize: none;
}
.pcd-field-input:focus { border-color: rgba(110,110,210,0.55); }
.pcd-field-input:disabled { opacity: 0.5; }
.pcd-field-input--title { font-size: 1.1rem; font-weight: 700; }
.pcd-field-input--desc { font-size: 0.9rem; line-height: 1.55; }
.pcd-inline-btns { display: flex; gap: 0.4rem; }
.pcd-inline-btn {
    padding: 0.3rem 0.8rem;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    border: 1px solid;
    transition: background 0.15s;
}
.pcd-inline-btn:disabled { opacity: 0.5; cursor: default; }
.pcd-inline-btn--save {
    background: rgba(110,110,210,0.18);
    border-color: rgba(110,110,210,0.4);
    color: rgba(160,160,255,0.95);
}
.pcd-inline-btn--save:hover:not(:disabled) { background: rgba(110,110,210,0.3); }
.pcd-inline-btn--cancel {
    background: rgba(255,255,255,0.04);
    border-color: rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.4);
}
.pcd-inline-btn--cancel:hover { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.65); }

/* Price inline edit */
.pcd-price-edit {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin-left: auto;
}
.pcd-price-input {
    width: 90px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 6px;
    color: rgba(255,255,255,0.95);
    font-size: 1rem;
    font-weight: 700;
    font-family: inherit;
    padding: 0.2rem 0.5rem;
    outline: none;
    text-align: right;
    transition: border-color 0.15s;
    /* hide number arrows */
    -moz-appearance: textfield;
}
.pcd-price-input::-webkit-outer-spin-button,
.pcd-price-input::-webkit-inner-spin-button { -webkit-appearance: none; }
.pcd-price-input:focus { border-color: rgba(110,110,210,0.55); }
.pcd-price-rub {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.5);
}

/* Edit dropdown */
.pcd-edit-wrap {
    position: relative;
    margin-top: -0.5rem;
}
.pcd-edit-backdrop {
    position: fixed;
    inset: 0;
    z-index: 10;
}
.pcd-edit-menu {
    position: absolute;
    bottom: calc(100% + 6px);
    left: 0;
    right: 0;
    z-index: 20;
    background: rgb(18, 14, 28);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 -8px 32px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.07);
}
.pcd-edit-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
    padding: 0.7rem 1rem;
    background: none;
    border: none;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: rgba(255,255,255,0.62);
    font-size: 0.88rem;
    font-family: inherit;
    cursor: pointer;
    text-align: left;
    transition: background 0.12s, color 0.12s;
}
.pcd-edit-item:last-child { border-bottom: none; }
.pcd-edit-item:hover {
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.92);
}
.pcd-edit-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.65rem 1rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-top: none;
    border-radius: 8px;
    color: rgba(255,255,255,0.55);
    font-size: 0.88rem;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.1);
    transition: background 0.15s, color 0.15s;
}
.pcd-edit-toggle:hover {
    background: rgba(255,255,255,0.09);
    color: rgba(255,255,255,0.82);
}

/* Menu transition */
.pcd-menu-enter-active { transition: opacity 0.14s ease, transform 0.14s ease; }
.pcd-menu-leave-active { transition: opacity 0.1s ease, transform 0.1s ease; }
.pcd-menu-enter-from, .pcd-menu-leave-to { opacity: 0; transform: translateY(6px); }
.pcd-menu-enter-to, .pcd-menu-leave-from { opacity: 1; transform: translateY(0); }

/* Meta row: count + price */
.pcd-meta-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.pcd-meta-count {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.95rem;
    color: rgba(255,255,255,0.62);
}
.pcd-meta-price {
    margin-left: auto;
    font-size: 1.15rem;
    font-weight: 700;
    color: rgba(255,255,255,0.9);
    letter-spacing: -0.01em;
}


/* Cover fullscreen overlay */
.pc-fs-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0, 0, 0, 0.92);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: zoom-out;
    padding: 1rem;
}
.pc-fs-img {
    max-width: 100%;
    max-height: 90vh;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 24px 80px rgba(0, 0, 0, 0.7);
    cursor: default;
}
.pc-fs-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.7);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s, color 0.15s;
}
.pc-fs-close:hover { background: rgba(255, 255, 255, 0.16); color: #fff; }
.pc-fs-enter-active, .pc-fs-leave-active { transition: opacity 0.18s ease; }
.pc-fs-enter-from, .pc-fs-leave-to { opacity: 0; }

/* Cover picker */
.pcd-picker {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.pcd-picker__label {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.35);
    text-align: center;
}
.pcd-picker__grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 5px;
}
.pcd-picker__item {
    position: relative;
    aspect-ratio: 3/4;
    border-radius: 6px;
    overflow: hidden;
    border: 2px solid transparent;
    cursor: pointer;
    padding: 0;
    background: none;
    transition: border-color 0.15s, opacity 0.15s;
}
.pcd-picker__item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.pcd-picker__item:hover { border-color: rgba(255,255,255,0.3); }
.pcd-picker__item--active { border-color: rgba(110,110,210,0.8); }
.pcd-picker__item:disabled { opacity: 0.5; cursor: default; }
.pcd-picker__check {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(110,110,210,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}


/* Actions */
.pcd-actions {
    display: flex;
    flex-direction: column;
    padding-bottom: 0.25rem;
}

.pcd-owner-btns {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pcd-cart-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.8rem 1rem;
    border: none;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s, transform 0.1s;
    background: rgba(100, 160, 255, 0.15);
    color: rgba(160, 200, 255, 0.95);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.pcd-cart-btn:hover {
    background: rgba(100, 160, 255, 0.26);
}

.pcd-cart-btn:active {
    transform: scale(0.98);
}

.pcd-cart-btn--in-cart {
    background: rgba(50, 255, 150, 0.16);
    color: rgb(80, 255, 170);
}

.pcd-cart-btn--in-cart:hover {
    background: rgba(50, 255, 150, 0.24);
}

.pcd-cart-btn--purchased {
    background: rgba(100, 210, 160, 0.16);
    color: rgb(100, 230, 170);
}

.pcd-cart-btn--purchased:hover {
    background: rgba(100, 210, 160, 0.26);
}

/* ── Delete confirmation modal ───────────────────────────── */
.pc-delete-confirm {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 1rem;
    padding: 0.5rem 0 1rem;
}

.pc-delete-confirm__icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(180, 60, 60, 0.12);
    border: 1px solid rgba(180, 60, 60, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 110, 110, 0.85);
}

.pc-delete-confirm__title {
    font-size: 1.2rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.92);
}

.pc-delete-confirm__text {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.5);
    line-height: 1.5;
    max-width: 280px;
}

.pc-delete-confirm__actions {
    display: flex;
    gap: 0.6rem;
    width: 100%;
    margin-top: 0.5rem;
}

.pc-delete-confirm__btn {
    flex: 1;
    padding: 0.6rem 1rem;
    border-radius: 6px;
    border: 1px solid;
    font-size: 0.9rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s;
}

.pc-delete-confirm__btn--cancel {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.6);
}

.pc-delete-confirm__btn--cancel:hover {
    background: rgba(255, 255, 255, 0.09);
}

.pc-delete-confirm__btn--confirm {
    background: rgba(180, 60, 60, 0.15);
    border-color: rgba(180, 60, 60, 0.4);
    color: rgba(255, 110, 110, 0.9);
}

.pc-delete-confirm__btn--confirm:hover {
    background: rgba(180, 60, 60, 0.26);
}

</style>
