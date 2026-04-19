<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import UserAvatar from '@/Components/UserAvatar.vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import SortDropdown from '@/Components/SortDropdown.vue';
import { useTranslations } from '@/composables/useTranslations';

const { __, transChoice } = useTranslations();

const props = defineProps({
    profileUserId: { type: Number, required: true },
    isOwner:       { type: Boolean, default: false },
    isIdol:        { type: Boolean, default: false },
});

const reviews       = ref([]);
const total         = ref(0);
const epithetCounts = ref([]);
const loading       = ref(true);
const loadingList   = ref(false);
const loadingMore   = ref(false);
const hasMore       = ref(false);
const page          = ref(1);
const sort          = ref('latest');
const sentinel      = ref(null);
const showBackTop   = ref(false);
let observer        = null;
let scrollContainer = null;

// ── Dispute modal ─────────────────────────────────────────────
const disputeModalOpen  = ref(false);
const disputeReview     = ref(null);
const disputeReason     = ref('');
const disputeSubmitting = ref(false);
const disputeError      = ref('');
const disputeSuccess    = ref(false);

function openDisputeModal(review) {
    disputeReview.value    = review;
    disputeReason.value    = '';
    disputeError.value     = '';
    disputeSuccess.value   = false;
    disputeModalOpen.value = true;
}

function closeDisputeModal() {
    disputeModalOpen.value = false;
}

async function submitDispute() {
    if (disputeSubmitting.value || !disputeReason.value.trim()) return;
    disputeSubmitting.value = true;
    disputeError.value      = '';
    try {
        await axios.post(route('reviews.dispute.store', disputeReview.value.id), {
            reason: disputeReason.value,
        });
        disputeReview.value.dispute_status = 'pending';
        disputeSuccess.value = true;
        setTimeout(closeDisputeModal, 1500);
    } catch (e) {
        const msg = e.response?.data?.message;
        if (msg === 'dispute_pending') {
            disputeError.value = __('reviews.error.reported');
        } else {
            disputeError.value = __('reviews.error.failed');
        }
    } finally {
        disputeSubmitting.value = false;
    }
}

const sortOptions = computed(() => [
    { value: 'latest',      label: __('reviews.sort.newest') },
    { value: 'oldest',      label: __('reviews.sort.oldest') },
    { value: 'rating_desc', label: __('reviews.sort.highest') },
    { value: 'rating_asc',  label: __('reviews.sort.lowest') },
]);

async function fetchPage(p = 1) {
    const res = await axios.get(route('users.reviews', props.profileUserId), {
        params: { page: p, sort: sort.value },
    });
    return res.data;
}

async function setSort(value) {
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
            <p class="pr-empty__title">{{ __('reviews.empty') }}</p>
            <p class="pr-empty__hint">{{ __('reviews.after_orders') }}</p>
        </div>

        <template v-else>
            <!-- Summary -->
            <div class="pr-summary">
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
            <div class="pr-sort">
                <SortDropdown :options="sortOptions" :model-value="sort" @update:modelValue="setSort" />
                <span class="pr-sort__total">{{ transChoice('reviews.total', total, { count: total }) }}</span>
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

                <div
                    v-else
                    v-for="r in reviews"
                    :key="r.id"
                    class="pr-card"
                    :class="{ 'pr-card--disputable': isOwner && isIdol }"
                >
                    <!-- Dispute overlay (idol only, no pending dispute) -->
                    <div
                        v-if="isOwner && isIdol && !r.dispute_status"
                        class="pr-card__dispute-overlay"
                        @click.stop="openDisputeModal(r)"
                    >
                        <button class="pr-card__dispute-btn" type="button">{{ __('reviews.dispute.btn') }}</button>
                    </div>
                    <!-- Dispute status badges -->
                    <div v-else-if="isOwner && isIdol && r.dispute_status === 'pending'" class="pr-card__dispute-badge pr-card__dispute-badge--pending">
                        {{ __('reviews.dispute.pending') }}
                    </div>
                    <div v-else-if="isOwner && isIdol && r.dispute_status === 'rejected'" class="pr-card__dispute-overlay">
                        <div class="pr-card__dispute-rejected">
                            {{ __('reviews.dispute.rejected') }} <span class="pr-card__dispute-retry" @click.stop="openDisputeModal(r)">{{ __('reviews.dispute.retry') }}</span>
                        </div>
                    </div>

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
                    <div v-if="loadingMore" class="pr-loading-more">{{ __('common.loading') }}</div>
                </template>
            </div>
        </template>

    <!-- Back to top -->
    <Transition name="pr-backtop">
        <button v-if="showBackTop" class="pr-backtop" @click="scrollToTop" type="button" :aria-label="__('reviews.backtop')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="18 15 12 9 6 15"/>
            </svg>
            {{ __('reviews.backtop') }}
        </button>
    </Transition>
    </div>

    <!-- Dispute modal -->
    <SiteModal
        :show="disputeModalOpen"
        variant="pink"
        :compact="true"
        max-width="640px"
        @close="closeDisputeModal"
    >
        <div class="pd-wrap">
            <template v-if="disputeSuccess">
                <div class="pd-success">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    {{ __('reviews.dispute.sent') }}
                </div>
            </template>
            <template v-else>
                <h3 class="pd-title">{{ __('reviews.dispute.title') }}</h3>
                <p class="pd-hint">{{ __('reviews.dispute.hint') }}</p>
                <textarea
                    v-model="disputeReason"
                    class="pd-textarea"
                    maxlength="250"
                    rows="5"
                    :placeholder="__('reviews.dispute.ph')"
                ></textarea>
                <div class="pd-counter">{{ disputeReason.length }} / 250</div>
                <p v-if="disputeError" class="pd-error">{{ disputeError }}</p>
                <button
                    class="pd-submit"
                    type="button"
                    :disabled="disputeSubmitting || !disputeReason.trim()"
                    @click="submitDispute"
                >
                    {{ disputeSubmitting ? __('reviews.dispute.submitting') : __('reviews.dispute.submit') }}
                </button>
            </template>
        </div>
    </SiteModal>
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

/* ── Dispute overlay ─────────────────────── */
.pr-card--disputable {
    cursor: default;
}
.pr-card__dispute-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    pointer-events: none;
    z-index: 2;
}
.pr-card--disputable:hover .pr-card__dispute-overlay {
    pointer-events: auto;
}
.pr-card__dispute-btn {
    margin-top: -1px;
    padding: 0.3rem 1.15rem;
    background: rgba(220,60,60,0.07);
    border: 1px solid rgba(220,60,60,0.35);
    border-top: none;
    border-radius: 0 0 7px 7px;
    color: rgba(255,120,120,0.88);
    font-size: 0.84rem;
    font-weight: 600;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transform: translateY(-100%);
    transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1), background 0.15s, border-color 0.15s;
}
.pr-card__dispute-btn::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(255,150,150,0.6) 50%, transparent 100%);
}
.pr-card--disputable:hover .pr-card__dispute-btn {
    transform: translateY(0);
}
.pr-card__dispute-btn:hover {
    background: rgba(220,60,60,0.15);
    border-color: rgba(220,60,60,0.6);
}
.pr-card__dispute-badge {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    padding: 0.3rem 1.15rem;
    border-top: none;
    border-radius: 0 0 7px 7px;
    font-size: 0.84rem;
    font-weight: 600;
    white-space: nowrap;
    z-index: 2;
    overflow: hidden;
}
.pr-card__dispute-badge--pending {
    background: rgba(160,160,255,0.07);
    border: 1px solid rgba(160,160,255,0.25);
    border-top: none;
    color: rgba(180,180,255,0.85);
}
.pr-card__dispute-badge--pending::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(160,160,255,0.6) 50%, transparent 100%);
}
.pr-card__dispute-rejected {
    margin-top: -1px;
    padding: 0.3rem 1.15rem;
    background: rgba(80,110,160,0.07);
    border: 1px solid rgba(100,140,200,0.22);
    border-top: none;
    border-radius: 0 0 7px 7px;
    color: rgba(150,175,220,0.75);
    font-size: 0.84rem;
    font-weight: 600;
    cursor: default;
    position: relative;
    overflow: hidden;
    transform: translateY(-100%);
    transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.pr-card__dispute-rejected::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(100,150,220,0.5) 50%, transparent 100%);
}
.pr-card--disputable:hover .pr-card__dispute-rejected {
    transform: translateY(0);
}
.pr-card__dispute-retry {
    color: rgba(255,120,120,0.88);
    font-weight: 600;
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 2px;
    text-decoration-color: rgba(255,120,120,0.35);
    transition: color 0.15s;
}
.pr-card__dispute-retry:hover {
    color: rgba(255,150,150,1);
}

/* ── Dispute modal content ───────────────── */
.pd-wrap {
    padding: 0.25rem 0.25rem 0.5rem;
}
.pd-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: rgba(255,255,255,0.9);
    margin: 0 0 0.5rem;
}
.pd-hint {
    font-size: 0.92rem;
    color: rgba(255,255,255,0.5);
    margin: 0 0 0.85rem;
    line-height: 1.5;
}
.pd-textarea {
    width: 100%;
    box-sizing: border-box;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: rgba(255,255,255,0.85);
    font-size: 1rem;
    line-height: 1.6;
    padding: 0.75rem 0.9rem;
    resize: none;
    outline: none;
    box-shadow: none;
    -webkit-appearance: none;
    transition: border-color 0.15s;
    font-family: inherit;
}
.pd-textarea:focus {
    outline: none;
    box-shadow: none;
    border-color: rgba(160,40,70,0.55);
}
.pd-counter {
    text-align: right;
    font-size: 0.82rem;
    color: rgba(255,255,255,0.35);
    margin-top: 0.3rem;
    margin-bottom: 0.65rem;
}
.pd-error {
    font-size: 0.88rem;
    color: rgba(255,130,110,0.9);
    margin: 0 0 0.65rem;
}
.pd-submit {
    width: 100%;
    padding: 0.7rem 1rem;
    background: rgba(220,60,60,0.07);
    border: 1px solid rgba(220,60,60,0.35);
    border-radius: 6px;
    color: rgba(255,120,120,0.88);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: background 0.15s, border-color 0.15s;
}
.pd-submit::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(255,150,150,0.6) 50%, transparent 100%);
}
.pd-submit:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}
.pd-submit:not(:disabled):hover {
    background: rgba(220,60,60,0.15);
    border-color: rgba(220,60,60,0.6);
}
.pd-success {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    color: rgba(180,255,190,0.9);
    font-size: 0.9rem;
    padding: 0.5rem 0;
}

/* ── Back to top ─────────────────────────── */
.pr-backtop {
    position: sticky;
    bottom: 1.5rem;
    align-self: flex-end;
    padding: 0.55rem 1.2rem;
    border-radius: 5px;
    background: linear-gradient(180deg, rgba(80,70,180,0.97) 0%, rgba(55,48,145,0.97) 100%);
    border: 1px solid rgba(120,115,220,0.45);
    box-shadow: 0 2px 12px rgba(55,48,145,0.45), inset 0 1px 0 rgba(160,155,255,0.15);
    color: rgba(200,200,255,0.95);
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
    background: linear-gradient(90deg, transparent 0%, rgba(200,200,255,0.7) 50%, transparent 100%);
}
.pr-backtop::before {
    content: '';
    position: absolute;
    inset: 2px;
    border-radius: 3px;
    border: 1px dashed rgba(160,155,255,0.2);
    opacity: 0;
    transition: opacity 0.15s;
}
.pr-backtop:hover {
    box-shadow: 0 4px 18px rgba(55,48,145,0.6), inset 0 1px 0 rgba(160,155,255,0.2);
    border-color: rgba(120,115,220,0.7);
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
