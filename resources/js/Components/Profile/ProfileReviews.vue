<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    profileUserId: { type: Number, required: true },
});

const reviews       = ref([]);
const total         = ref(0);
const avgRating     = ref(null);
const epithetCounts = ref([]);
const loading       = ref(true);
const loadingList   = ref(false);
const loadingMore   = ref(false);
const hasMore       = ref(false);
const page          = ref(1);
const sort          = ref('latest');
const sortOpen      = ref(false);
const sortWrapEl    = ref(null);
const sentinel      = ref(null);
const showBackTop   = ref(false);
let observer        = null;
let scrollContainer = null;

const sortOptions = [
    { value: 'latest',      label: 'сначала новые' },
    { value: 'oldest',      label: 'сначала старые' },
    { value: 'rating_desc', label: 'с высокой оценкой' },
    { value: 'rating_asc',  label: 'с низкой оценкой' },
];
const sortLabel = computed(() => sortOptions.find(o => o.value === sort.value)?.label ?? 'сначала новые');

function onSortDocClick(e) {
    if (sortOpen.value && sortWrapEl.value && !sortWrapEl.value.contains(e.target)) {
        sortOpen.value = false;
    }
}
onMounted(() => document.addEventListener('click', onSortDocClick, true));
onUnmounted(() => document.removeEventListener('click', onSortDocClick, true));

async function fetchPage(p = 1) {
    const res = await axios.get(route('users.reviews', props.profileUserId), {
        params: { page: p, sort: sort.value },
    });
    return res.data;
}

async function setSort(value) {
    sortOpen.value = false;
    if (sort.value === value) return;
    sort.value = value;
    observer?.disconnect();
    observer = null;
    page.value = 1;
    reviews.value = [];
    loadingList.value = true;
    try {
        const data = await fetchPage(1);
        reviews.value = data.reviews;
        hasMore.value = data.has_more;
    } finally {
        loadingList.value = false;
        await nextTick();
        setupObserver();
    }
}

onMounted(async () => {
    try {
        const data = await fetchPage(1);
        reviews.value       = data.reviews;
        total.value         = data.total;
        avgRating.value     = data.avg_rating;
        hasMore.value       = data.has_more;
        epithetCounts.value = data.epithet_counts ?? [];
    } finally {
        loading.value = false;
        await nextTick();
        setupObserver();
    }
});

onUnmounted(() => {
    observer?.disconnect();
    scrollContainer?.removeEventListener('scroll', onScroll);
});

function onScroll() {
    showBackTop.value = (scrollContainer?.scrollTop ?? 0) > 300;
}

function scrollToTop() {
    scrollContainer?.scrollTo({ top: 0, behavior: 'smooth' });
}

function getScrollParent(el) {
    while (el && el !== document.body) {
        const { overflowY } = window.getComputedStyle(el);
        if (overflowY === 'auto' || overflowY === 'scroll') return el;
        el = el.parentElement;
    }
    return null;
}

function setupObserver() {
    if (!sentinel.value) return;
    const root = getScrollParent(sentinel.value);
    if (root && !scrollContainer) {
        scrollContainer = root;
        scrollContainer.addEventListener('scroll', onScroll, { passive: true });
    }
    observer = new IntersectionObserver(async ([entry]) => {
        if (!entry.isIntersecting || loadingMore.value || !hasMore.value) return;
        loadingMore.value = true;
        try {
            page.value++;
            const data = await fetchPage(page.value);
            reviews.value.push(...data.reviews);
            hasMore.value = data.has_more;
        } finally {
            loadingMore.value = false;
        }
    }, { root, rootMargin: '120px' });
    observer.observe(sentinel.value);
}

function formatDate(iso) {
    return new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' });
}
</script>

<template>
    <div class="pr-wrap anim-block">

        <!-- Initial skeleton -->
        <template v-if="loading">
            <div class="pr-skel-summary">
                <div class="pr-skel-line" style="width:140px;height:22px"></div>
                <div style="display:flex;gap:0.4rem;flex-wrap:wrap">
                    <div v-for="w in [88,110,76,96,82,104]" :key="w" class="pr-skel-line" :style="`width:${w}px;height:28px;border-radius:4px`"></div>
                </div>
            </div>
            <div style="display:flex;gap:1rem">
                <div class="pr-skel-line" style="width:110px;height:32px;border-radius:4px"></div>
                <div class="pr-skel-line" style="width:110px;height:32px;border-radius:4px"></div>
            </div>
            <div class="pr-list">
                <div v-for="i in 3" :key="i" class="pr-skel-card">
                    <div class="pr-skel-card__head">
                        <div class="pr-skel-avatar"></div>
                        <div class="pr-skel-line" style="width:38%;height:14px"></div>
                        <div style="display:flex;gap:3px;margin-left:auto">
                            <div v-for="j in 5" :key="j" class="pr-skel-line" style="width:16px;height:16px;border-radius:50%"></div>
                        </div>
                    </div>
                    <div style="display:flex;gap:0.4rem">
                        <div class="pr-skel-line" style="width:90px;height:26px;border-radius:4px"></div>
                        <div class="pr-skel-line" style="width:70px;height:26px;border-radius:4px"></div>
                    </div>
                    <div class="pr-skel-line" style="width:100%;height:14px"></div>
                    <div class="pr-skel-line" style="width:72%;height:14px"></div>
                </div>
            </div>
        </template>

        <!-- Empty -->
        <div v-else-if="!loading && !loadingList && !total" class="pr-empty">
            <svg class="pr-empty__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            <p class="pr-empty__title">Отзывов пока нет</p>
            <p class="pr-empty__hint">Отзывы появятся после завершения заказов</p>
        </div>

        <template v-else>
            <!-- Summary -->
            <div class="pr-summary">
                <div class="pr-summary__top">
                    <div class="pr-summary__hearts">
                        <svg
                            v-for="i in 5"
                            :key="i"
                            viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg"
                            class="pr-summary__heart"
                        >
                            <defs>
                                <radialGradient :id="`sg-${i}`" cx="50%" cy="35%" r="65%">
                                    <stop offset="0%" stop-color="rgba(255,190,210,0.95)" />
                                    <stop offset="100%" stop-color="rgba(210,50,100,0.9)" />
                                </radialGradient>
                            </defs>
                            <path
                                d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
                                :fill="(avgRating && i <= Math.round(avgRating)) ? `url(#sg-${i})` : 'none'"
                                :stroke="(avgRating && i <= Math.round(avgRating)) ? 'rgba(210,60,100,0.5)' : 'rgba(255,160,180,0.3)'"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </div>
                    <span v-if="avgRating" class="pr-summary__score">{{ avgRating }}</span>
                </div>

                <!-- Epithet cloud -->
                <div v-if="epithetCounts.length" class="pr-summary__epithets">
                    <span
                        v-for="ep in epithetCounts"
                        :key="ep.id"
                        class="pr-summary__epithet"
                    >
                        <span class="pr-summary__epithet-label">{{ ep.label }}</span>
                        <span class="pr-summary__epithet-count">{{ ep.count }}</span>
                    </span>
                </div>
            </div>

            <!-- Sort dropdown -->
            <div class="pr-sort" ref="sortWrapEl">
                <span class="pr-sort__label">Сортировка:</span>
                <div class="pr-sort__trigger">
                    <button class="pr-sort__btn" @click="sortOpen = !sortOpen" type="button">
                        {{ sortLabel }}
                        <svg class="pr-sort__arrow" :class="{ 'pr-sort__arrow--open': sortOpen }" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <Transition name="pr-drop">
                        <div v-if="sortOpen" class="pr-sort__dropdown">
                            <button
                                v-for="opt in sortOptions"
                                :key="opt.value"
                                class="pr-sort__option"
                                :class="{ 'pr-sort__option--active': sort === opt.value }"
                                @click="setSort(opt.value)"
                                type="button"
                            >{{ opt.label }}</button>
                        </div>
                    </Transition>
                </div>
                <span class="pr-sort__total">{{ total }} {{ total === 1 ? 'отзыв' : total < 5 ? 'отзыва' : 'отзывов' }}</span>
            </div>

            <!-- List -->
            <div class="pr-list">
                <!-- Sort skeleton -->
                <template v-if="loadingList">
                    <div v-for="i in 4" :key="i" class="pr-skel-card">
                        <div class="pr-skel-card__head">
                            <div class="pr-skel-avatar"></div>
                            <div class="pr-skel-line" style="width:38%;height:14px"></div>
                            <div style="display:flex;gap:3px;margin-left:auto">
                                <div v-for="j in 5" :key="j" class="pr-skel-line" style="width:16px;height:16px;border-radius:50%"></div>
                            </div>
                        </div>
                        <div style="display:flex;gap:0.4rem">
                            <div class="pr-skel-line" style="width:90px;height:26px;border-radius:4px"></div>
                            <div class="pr-skel-line" style="width:70px;height:26px;border-radius:4px"></div>
                        </div>
                        <div class="pr-skel-line" style="width:100%;height:14px"></div>
                        <div class="pr-skel-line" style="width:65%;height:14px"></div>
                    </div>
                </template>

                <div v-else v-for="r in reviews" :key="r.id" class="pr-card">
                    <!-- Reviewer -->
                    <div class="pr-card__head">
                        <UserAvatar :user="r.reviewer" :size="36" />
                        <div class="pr-card__who">
                            <span class="pr-card__name">{{ r.reviewer.name }}</span>
                        </div>
                        <!-- Hearts -->
                        <div class="pr-card__hearts">
                            <svg
                                v-for="i in 5"
                                :key="i"
                                viewBox="0 0 24 24"
                                xmlns="http://www.w3.org/2000/svg"
                                class="pr-card__heart"
                            >
                                <defs>
                                    <radialGradient :id="`rg-${r.id}-${i}`" cx="50%" cy="35%" r="65%">
                                        <stop offset="0%" stop-color="rgba(255,190,210,0.95)" />
                                        <stop offset="100%" stop-color="rgba(210,50,100,0.9)" />
                                    </radialGradient>
                                </defs>
                                <path
                                    d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
                                    :fill="i <= r.rating ? `url(#rg-${r.id}-${i})` : 'none'"
                                    :stroke="i <= r.rating ? 'rgba(210,60,100,0.5)' : 'rgba(255,160,180,0.25)'"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </div>
                    </div>

                    <!-- Services snapshot -->
                    <div v-if="r.services_snapshot?.length" class="pr-card__services">
                        <span
                            v-for="(svc, idx) in r.services_snapshot"
                            :key="idx"
                            class="pr-card__svc-tag"
                        >{{ svc.name }}</span>
                    </div>

                    <!-- Text + date on same line -->
                    <div class="pr-card__bottom">
                        <p v-if="r.text" class="pr-card__text">{{ r.text }}</p>
                        <span class="pr-card__date">{{ formatDate(r.created_at) }}</span>
                    </div>
                </div>

                <!-- Sentinel for IntersectionObserver -->
                <template v-if="!loadingList">
                    <div ref="sentinel" class="pr-sentinel"></div>
                    <div v-if="loadingMore" class="pr-loading-more">Загрузка…</div>
                </template>
            </div>
        </template>

    <!-- Back to top -->
    <Transition name="pr-backtop">
        <button v-if="showBackTop" class="pr-backtop" @click="scrollToTop" type="button" aria-label="Наверх">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="18 15 12 9 6 15"/>
            </svg>
            Наверх
        </button>
    </Transition>
    </div>
</template>

<style scoped>
.pr-wrap {
    display: flex;
    flex-direction: column;
    gap: 1.2rem;
    padding: 0.5rem 0.75rem 0.5rem 0;
}

/* ── Skeleton ────────────────────────────── */
.pr-skel-summary {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 0.9rem 0;
}
.pr-skel-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 8px;
    padding: 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.pr-skel-card__head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.pr-skel-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    flex-shrink: 0;
    background: rgba(255,255,255,0.06);
    position: relative;
    overflow: hidden;
}
.pr-skel-line {
    background: rgba(255,255,255,0.06);
    position: relative;
    overflow: hidden;
    border-radius: 3px;
}
.pr-skel-line::after,
.pr-skel-avatar::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.07) 50%, transparent 100%);
    transform: translateX(-100%);
    animation: pr-skel-slide 1.3s ease-in-out infinite;
    will-change: transform;
}
@keyframes pr-skel-slide {
    0%   { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.pr-loading, .pr-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: rgba(255,255,255,0.3);
}
.pr-empty__icon {
    width: 40px;
    height: 40px;
    margin: 0 auto 0.8rem;
    display: block;
    opacity: 0.25;
}
.pr-empty__title {
    font-size: 0.95rem;
    font-weight: 600;
    color: rgba(255,255,255,0.45);
    margin: 0 0 0.3rem;
}
.pr-empty__hint {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.22);
    margin: 0;
}

/* ── Summary ─────────────────────────────── */
.pr-summary {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 0.9rem 0 0;
}
.pr-summary__top {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.pr-summary__hearts {
    display: flex;
    gap: 0.25rem;
}
.pr-summary__heart {
    width: 20px;
    height: 20px;
}
.pr-summary__score {
    font-size: 1.25rem;
    font-weight: 700;
    color: rgba(255,190,210,0.9);
    font-family: 'Courier New', monospace;
    letter-spacing: 0.05em;
}
.pr-summary__epithets {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.pr-summary__epithet {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.8rem;
    background: rgba(160,160,255,0.07);
    border: 1px solid rgba(160,160,255,0.2);
    border-radius: 4px;
    line-height: 1;
}
.pr-summary__epithet-label {
    font-size: 0.95rem;
    color: rgba(200,200,255,0.8);
    line-height: 1;
}
.pr-summary__epithet-count {
    font-size: 0.85rem;
    font-weight: 700;
    font-family: 'Courier New', monospace;
    color: var(--color-base-1);
    line-height: 1;
}

/* ── Sort dropdown ───────────────────────── */
.pr-sort {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    width: 100%;
    padding: 0 0.5rem;
    box-sizing: border-box;
}
.pr-sort__total {
    margin-left: auto;
    font-size: 0.82rem;
    color: rgba(255,255,255,0.55);
    white-space: nowrap;
}
.pr-sort__label {
    font-size: 0.92rem;
    color: rgba(255,255,255,0.55);
    white-space: nowrap;
}
.pr-sort__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: none;
    border: none;
    padding: 0;
    font-size: 0.92rem;
    font-family: inherit;
    color: var(--color-base-1);
    cursor: pointer;
    white-space: nowrap;
}
.pr-sort__btn:hover { opacity: 0.8; }
.pr-sort__arrow {
    transition: transform 0.18s ease;
    opacity: 0.7;
}
.pr-sort__arrow--open { transform: rotate(180deg); }

.pr-sort__trigger {
    position: relative;
}
.pr-sort__dropdown {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    background: rgba(12, 10, 20, 0.97);
    border: 1px solid rgba(160,160,255,0.2);
    border-radius: 6px;
    padding: 0;
    z-index: 20;
    min-width: 180px;
    box-shadow: 0 6px 24px rgba(0,0,0,0.5);
    overflow: hidden;
}
.pr-sort__dropdown::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(255,140,175,0.5) 50%, transparent 100%);
    pointer-events: none;
}
.pr-sort__option {
    display: block;
    width: 100%;
    padding: 0.5rem 0.9rem;
    background: none;
    border: none;
    text-align: left;
    font-size: 0.92rem;
    font-family: inherit;
    color: rgba(255,255,255,0.5);
    cursor: pointer;
    transition: color 0.12s, background 0.12s;
}
.pr-sort__option:hover {
    background: rgba(160,160,255,0.07);
    color: rgba(200,200,255,0.9);
}
.pr-sort__option--active {
    color: var(--color-base-1);
}

.pr-drop-enter-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.pr-drop-leave-active { transition: opacity 0.1s ease, transform 0.1s ease; }
.pr-drop-enter-from, .pr-drop-leave-to { opacity: 0; transform: translateY(-4px); }

/* ── List ────────────────────────────────── */
.pr-list {
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
}

/* ── Card ────────────────────────────────── */
.pr-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 8px;
    padding: 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    position: relative;
    overflow: hidden;
}
.pr-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.13) 50%, transparent 100%);
    pointer-events: none;
}

.pr-card__head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.pr-card__who {
    flex: 1;
    min-width: 0;
}
.pr-card__name {
    font-size: 0.88rem;
    font-weight: 600;
    color: rgba(255,255,255,0.82);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: block;
}
.pr-card__hearts {
    display: flex;
    gap: 0.2rem;
    flex-shrink: 0;
}
.pr-card__heart {
    width: 22px;
    height: 22px;
}

/* Services */
.pr-card__services {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.pr-card__svc-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.9rem;
    background: rgba(160,160,255,0.08);
    border: 1px solid rgba(160,160,255,0.2);
    border-radius: 4px;
    font-size: 0.9rem;
}
.pr-card__svc-tag {
    color: rgba(200,200,255,0.85);
}

/* Epithets */
.pr-card__epithets {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.pr-card__epithet {
    padding: 0.25rem 0.65rem;
    background: rgba(255,120,160,0.08);
    border: 1px solid rgba(255,120,160,0.22);
    border-radius: 4px;
    font-size: 0.78rem;
    color: rgba(255,190,210,0.8);
}

/* Text + date row */
.pr-card__bottom {
    display: flex;
    align-items: flex-end;
    gap: 0.75rem;
    margin-top: 0.25rem;
}
.pr-card__text {
    flex: 1;
    font-size: 1.05rem;
    color: rgba(255,255,255,0.72);
    line-height: 1.6;
    margin: 0;
}
.pr-card__date {
    flex-shrink: 0;
    margin-left: auto;
    font-size: 0.8rem;
    color: rgba(255,255,255,0.45);
    white-space: nowrap;
}

/* ── Back to top ─────────────────────────── */
.pr-backtop {
    position: sticky;
    bottom: 1.5rem;
    align-self: flex-end;
    padding: 0.55rem 1.2rem;
    border-radius: 5px;
    background: linear-gradient(180deg, rgba(200,45,90,0.97) 0%, rgba(160,25,65,0.97) 100%);
    border: 1px solid rgba(255,100,140,0.45);
    box-shadow: 0 2px 12px rgba(180,30,70,0.35), inset 0 1px 0 rgba(255,160,190,0.15);
    color: rgba(255,220,230,0.95);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.88rem;
    font-family: inherit;
    font-weight: 600;
    position: sticky;
    overflow: hidden;
    transition: box-shadow 0.15s, border-color 0.15s;
    margin-top: -34px;
    margin-right: 0.75rem;
}
.pr-backtop::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    border-radius: 5px 5px 0 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255,180,200,0.7) 50%, transparent 100%);
}
.pr-backtop::before {
    content: '';
    position: absolute;
    inset: 2px;
    border-radius: 3px;
    border: 1px dashed rgba(255,120,160,0.2);
    opacity: 0;
    transition: opacity 0.15s;
}
.pr-backtop:hover {
    box-shadow: 0 4px 18px rgba(180,30,70,0.5), inset 0 1px 0 rgba(255,160,190,0.2);
    border-color: rgba(255,120,160,0.65);
}
.pr-backtop:hover::before { opacity: 1; }
.pr-backtop-enter-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.pr-backtop-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.pr-backtop-enter-from, .pr-backtop-leave-to { opacity: 0; transform: translateY(8px); }

/* Sentinel & loader */
.pr-sentinel { height: 1px; }
.pr-loading-more {
    text-align: center;
    font-size: 0.8rem;
    color: rgba(255,255,255,0.25);
    padding: 0.5rem 0;
}
</style>
