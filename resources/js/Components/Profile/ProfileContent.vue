<script setup>
import { ref, inject, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Picture } from '@element-plus/icons-vue';
import CreateButton from '@/Components/CreateButton.vue';
import SortDropdown from '@/Components/SortDropdown.vue';
import CreateContentPackModal from '@/Components/Profile/CreateContentPackModal.vue';
import ContentPackRemarksModal from '@/Components/Profile/ContentPackRemarksModal.vue';
import ContentPackDetailModal from '@/Components/Profile/ContentPackDetailModal.vue';

const props = defineProps({
    contentPacks:    { default: null },
    purchasedPackIds:{ default: () => [] },
    isOwner:         { type: Boolean, default: false },
    isIdol:          { type: Boolean, default: false },
    profileUser:     { type: Object, required: true },
});

const openAuth         = inject('openAuth', null);
const addToContentCart = inject('addToContentCart', null);

const showCreateModal  = ref(false);
const showRemarksModal = ref(false);
const showDetailModal  = ref(false);
const selectedPack     = ref(null);

// ── Sort + Infinite scroll ────────────────────────────────
const sort        = ref('newest');
const localPacks  = ref(null);   // null = still waiting for Inertia deferred
const localPurchasedIds = ref([]);
const cursor      = ref(null);
const hasMore     = ref(false);
const loading     = ref(false);
const sentinel    = ref(null);
let   observer    = null;

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

const displayPacks       = () => localPacks.value ?? props.contentPacks;
const displayPurchasedIds = () => localPurchasedIds.value;

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

        cursor.value  = data.next_cursor ?? null;
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

function openDetail(pack) {
    selectedPack.value = pack;
    showDetailModal.value = true;
}

function handlePublish(pack) {
    router.post(route('content-packs.publish', pack.id), {}, { preserveScroll: true });
}

function handleDelete(pack) {
    if (!confirm('Удалить пак «' + pack.title + '»?')) return;
    router.delete(route('content-packs.destroy', pack.id), { preserveScroll: true });
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

const STATUS_LABELS = {
    pending_review: 'На рассмотрении',
    approved:       'Одобрен',
    published:      'Опубликован',
    has_remarks:    'Есть замечания',
    rejected:       'Отклонён',
};
const STATUS_COLORS = {
    pending_review: '#a0a0ff',
    approved:       '#64d2a0',
    published:      '#64d2ff',
    has_remarks:    '#ff7b7b',
    rejected:       '#ff5555',
};
</script>

<template>
    <div class="pc-wrap">
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

            <div v-if="displayPacks() !== null && !displayPacks().length && !loading" class="pc-empty">
                <p>У вас пока нет паков. Создайте первый!</p>
            </div>

            <div v-else class="pc-grid">
                <div v-for="pack in displayPacks()" :key="pack.id" class="pc-card pc-card--owner" @click="openDetail(pack)">
                    <div class="pc-card__cover">
                        <img v-if="pack.cover_url" :src="pack.cover_url" :alt="pack.title" loading="lazy" />
                        <div v-else class="pc-card__cover-placeholder">
                            <el-icon :size="28"><Picture /></el-icon>
                        </div>
                        <div class="pc-card__photo-badge">
                            <el-icon :size="11"><Picture /></el-icon>
                            {{ pack.photos_count }}
                        </div>
                    </div>
                    <div class="pc-card__body">
                        <div class="pc-card__title">{{ pack.title }}</div>
                        <div class="pc-card__price">{{ pack.price }} ₽</div>
                        <div class="pc-card__status" :style="{ color: STATUS_COLORS[pack.status] }">
                            {{ STATUS_LABELS[pack.status] || pack.status }}
                        </div>
                        <div class="pc-card__actions">
                            <button
                                v-if="pack.status === 'approved'"
                                class="pc-btn pc-btn--primary"
                                @click.stop="handlePublish(pack)"
                            >Опубликовать</button>
                            <button
                                v-if="pack.status === 'has_remarks'"
                                class="pc-btn pc-btn--warn"
                                @click.stop="openRemarks(pack)"
                            >Замечания</button>
                            <button
                                v-if="pack.status === 'has_remarks' || pack.status === 'approved' || pack.status === 'rejected'"
                                class="pc-btn pc-btn--danger"
                                @click.stop="handleDelete(pack)"
                            >Удалить</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Visitor view (published packs only) -->
        <template v-else>
            <div class="pc-sort-bar">
                <SortDropdown :options="sortOptions" v-model="sort" @update:modelValue="onSortChange" />
            </div>

            <div v-if="displayPacks() !== null && !displayPacks().length && !loading" class="pc-empty">
                <p>Нет опубликованных паков.</p>
            </div>
            <div v-else class="pc-grid">
                <div
                    v-for="pack in displayPacks()"
                    :key="pack.id"
                    class="pc-card pc-card--visitor"
                    @click="openDetail(pack)"
                >
                    <div class="pc-card__cover">
                        <img v-if="pack.cover_url" :src="pack.cover_url" :alt="pack.title" loading="lazy" />
                        <div v-else class="pc-card__cover-placeholder">
                            <el-icon :size="28"><Picture /></el-icon>
                        </div>
                        <div class="pc-card__photo-badge">
                            <el-icon :size="11"><Picture /></el-icon>
                            {{ pack.photos_count }}
                        </div>
                    </div>
                    <div class="pc-card__body">
                        <div class="pc-card__title">{{ pack.title }}</div>
                        <div class="pc-card__footer">
                            <span class="pc-card__price">{{ pack.price }} ₽</span>
                            <button
                                class="pc-btn pc-btn--cart"
                                :class="{ 'pc-btn--purchased': displayPurchasedIds().includes(pack.id) }"
                                @click.stop="displayPurchasedIds().includes(pack.id) ? null : handleAddToCart(pack)"
                            >
                                {{ displayPurchasedIds().includes(pack.id) ? 'Куплено' : 'В корзину' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Infinite scroll sentinel -->
        <div ref="sentinel" class="pc-sentinel" />

        <!-- Loading indicator -->
        <div v-if="loading" class="pc-loading">
            <span class="pc-loading__dot" /><span class="pc-loading__dot" /><span class="pc-loading__dot" />
        </div>

        <!-- Modals -->
        <CreateContentPackModal
            :show="showCreateModal"
            @close="showCreateModal = false"
        />

        <ContentPackRemarksModal
            v-if="selectedPack"
            :show="showRemarksModal"
            :pack="selectedPack"
            @close="showRemarksModal = false"
        />

        <ContentPackDetailModal
            v-if="selectedPack"
            :show="showDetailModal"
            :pack="selectedPack"
            :purchased="selectedPack && displayPurchasedIds().includes(selectedPack.id)"
            :is-owner="isOwner"
            @close="showDetailModal = false"
        />
    </div>
</template>

<style scoped>
.pc-wrap {
    padding: 0.25rem 0;
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

.pc-sentinel { height: 1px; }

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
    background: rgba(160,160,255,0.5);
    animation: dot-pulse 1.2s ease-in-out infinite;
}
.pc-loading__dot:nth-child(2) { animation-delay: 0.2s; }
.pc-loading__dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes dot-pulse {
    0%, 80%, 100% { transform: scale(0.7); opacity: 0.4; }
    40%            { transform: scale(1);   opacity: 1; }
}

.pc-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: rgba(255,255,255,0.35);
    font-size: 0.95rem;
}

.pc-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.6rem;
}

@media (max-width: 600px) {
    .pc-grid { grid-template-columns: repeat(2, 1fr); }
}

/* ── Skeleton ────────────────────────────────────────────── */
@keyframes shimmer {
    0%   { background-position: -200% 0; }
    100% { background-position:  200% 0; }
}

.pc-skeleton-card {
    border-radius: 10px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.06);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.pc-skeleton-card__cover {
    aspect-ratio: 1/1;
    background: linear-gradient(90deg,
        rgba(255,255,255,0.05) 25%,
        rgba(255,255,255,0.1)  50%,
        rgba(255,255,255,0.05) 75%
    );
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
        rgba(255,255,255,0.05) 25%,
        rgba(255,255,255,0.1)  50%,
        rgba(255,255,255,0.05) 75%
    );
    background-size: 200% 100%;
    animation: shimmer 1.6s ease-in-out infinite;
}
.pc-skeleton-line--title  { height: 11px; width: 85%; animation-delay: 0.1s; }
.pc-skeleton-line--short  { height: 10px; width: 45%; animation-delay: 0.2s; }
.pc-skeleton-line--xshort { height: 9px;  width: 30%; animation-delay: 0.3s; }

.pc-card {
    border-radius: 10px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: border-color 0.18s, box-shadow 0.18s;
}

.pc-card--visitor {
    cursor: pointer;
}

.pc-card--visitor:hover {
    border-color: rgba(160,160,255,0.35);
    box-shadow: 0 4px 20px rgba(100,100,255,0.1);
}

.pc-card--owner {
    cursor: pointer;
}
.pc-card--owner:hover {
    border-color: rgba(160,160,255,0.35);
    box-shadow: 0 4px 20px rgba(100,100,255,0.1);
}

.pc-card__cover {
    position: relative;
    aspect-ratio: 1/1;
    background: rgba(255,255,255,0.04);
    overflow: hidden;
}

.pc-card__cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.pc-card__cover-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.2);
}

.pc-card__photo-badge {
    position: absolute;
    bottom: 6px;
    left: 6px;
    display: flex;
    align-items: center;
    gap: 3px;
    background: rgba(0,0,0,0.55);
    backdrop-filter: blur(4px);
    color: rgba(255,255,255,0.85);
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 20px;
}

.pc-card__body {
    padding: 0.5rem 0.6rem 0.6rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1;
}

.pc-card__title {
    font-size: 0.82rem;
    font-weight: 600;
    color: rgba(255,255,255,0.88);
    line-height: 1.3;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.pc-card__price {
    font-size: 0.8rem;
    color: rgba(160,160,255,0.8);
    font-weight: 500;
}

.pc-card__status {
    font-size: 0.72rem;
    font-weight: 500;
}

.pc-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    margin-top: 0.2rem;
}

.pc-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
    padding-top: 0.2rem;
}

.pc-btn {
    padding: 0.28rem 0.6rem;
    border-radius: 5px;
    font-size: 0.75rem;
    font-weight: 500;
    border: 1px solid;
    cursor: pointer;
    font-family: inherit;
    transition: opacity 0.15s, background 0.15s;
}

.pc-btn--primary {
    background: rgba(100,210,160,0.15);
    border-color: rgba(100,210,160,0.4);
    color: #64d2a0;
}
.pc-btn--primary:hover { background: rgba(100,210,160,0.25); }

.pc-btn--warn {
    background: rgba(255,123,123,0.12);
    border-color: rgba(255,123,123,0.4);
    color: #ff7b7b;
}
.pc-btn--warn:hover { background: rgba(255,123,123,0.22); }

.pc-btn--danger {
    background: rgba(180,60,60,0.1);
    border-color: rgba(180,60,60,0.3);
    color: rgba(255,120,120,0.7);
}
.pc-btn--danger:hover { background: rgba(180,60,60,0.18); }

.pc-btn--cart {
    background: rgba(100,160,255,0.12);
    border-color: rgba(100,160,255,0.35);
    color: rgba(160,200,255,0.9);
    white-space: nowrap;
}
.pc-btn--cart:hover { background: rgba(100,160,255,0.22); }

.pc-btn--purchased {
    background: rgba(100,210,160,0.1);
    border-color: rgba(100,210,160,0.3);
    color: rgba(100,210,160,0.7);
    cursor: default;
}
</style>
