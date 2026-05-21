<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from "vue";
import { Link } from "@inertiajs/vue3";
import { useTranslations } from "@/composables/useTranslations";
import { useModalHistory } from "@/composables/useModalHistory";

const { __, locale } = useTranslations();
import axios from "axios";
import SiteModal from "./SiteModal.vue";

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    showDispute: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(["close"]);

// ── Dynamic Help Center data ───────────────────────────────
const faqCategories = ref([]);
const helpLoading = ref(false);
let helpLoaded = false;

async function loadHelpData() {
    if (helpLoaded || helpLoading.value) return;
    helpLoading.value = true;
    try {
        const res = await axios.get(route('help.data'));
        faqCategories.value = res.data.categories ?? [];
        // Auto-select first article
        const firstArticle = faqCategories.value[0]?.questions?.[0] ?? null;
        if (firstArticle && !activeArticle.value) {
            activeArticle.value = firstArticle;
        }
        helpLoaded = true;
    } catch (e) {
        console.error('Failed to load help data', e);
    } finally {
        helpLoading.value = false;
    }
}

watch(() => props.show, (val) => {
    if (val) loadHelpData();
});

// ── Doc-style navigation ───────────────────────────────────
// activeArticle = selected article object { id, q, a }
const activeArticle = ref(null);

// Mobile: 'cats' = sidebar visible, 'answer' = article content visible
const mobileNav = ref('cats');

const isMobileAnswerOpen = computed({
    get: () => mobileNav.value === 'answer',
    set: (v) => { if (!v) mobileNav.value = 'cats'; },
});

useModalHistory(isMobileAnswerOpen, 'help-a');

function selectArticle(item) {
    disputeView.value = false;
    activeArticle.value = item;
    if (window.innerWidth <= 767) {
        mobileNav.value = 'answer';
    }
}

function backToSidebar() {
    disputeView.value = false;
    mobileNav.value = 'cats';
}

function openDisputeFromSidebar() {
    mobileNav.value = 'answer';
    openDisputeForm();
}

// ── Dispute form ──────────────────────────────────────────
const DISPUTE_REASONS = computed(() => [
    __("help.dispute.reason.1"),
    __("help.dispute.reason.2"),
    __("help.dispute.reason.3"),
    __("help.dispute.reason.4"),
    __("help.dispute.reason.5"),
    __("help.dispute.reason.6"),
]);

const disputeView = ref(false);
const disputableOrders = ref([]);
const disputeOrderId = ref(null);
const disputeReason = ref("");
const disputeDetails = ref("");
const disputeSubmitting = ref(false);
const disputeSuccess = ref(false);
const disputeErrors = ref({});
const disputeLoading = ref(false);

// Реактивный тик для таймеров в карточках заказов
const nowTick = ref(Date.now());
let tickInterval = null;

async function openDisputeForm() {
    disputeLoading.value = true;
    disputeView.value = true;
    disputeSuccess.value = false;
    disputeErrors.value = {};
    disputeReason.value = "";
    disputeDetails.value = "";
    try {
        const res = await axios.get(route("orders.disputable"));
        disputableOrders.value = res.data;
        disputeOrderId.value = res.data[0]?.id ?? null;
        if (res.data.length > 0) {
            tickInterval = setInterval(() => {
                nowTick.value = Date.now();
            }, 1000);
        }
    } catch {
        disputeErrors.value = { _general: __("help.fail") };
    } finally {
        disputeLoading.value = false;
    }
}

function orderTimeLeft(completedAt) {
    const deadline = new Date(completedAt).getTime() + 3600 * 1000;
    const diff = deadline - nowTick.value;
    if (diff <= 0) return __("help.timer.expiring");
    const m = Math.floor(diff / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    return __("help.timer.left", {
        time: `${m}:${s.toString().padStart(2, "0")}`,
    });
}

function fmtDate(iso) {
    if (!iso) return "—";
    const loc = locale.value?.current === "ru" ? "ru-RU" : "en-US";
    return new Date(iso).toLocaleString(loc, {
        day: "2-digit",
        month: "2-digit",
        year: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
    });
}

onMounted(() => {});

onUnmounted(() => {
    clearInterval(tickInterval);
});

async function submitDispute() {
    if (
        !disputeOrderId.value ||
        !disputeReason.value ||
        disputeDetails.value.trim().length < 100
    )
        return;
    disputeSubmitting.value = true;
    disputeErrors.value = {};
    try {
        await axios.post(route("orders.dispute", disputeOrderId.value), {
            reason: disputeReason.value,
            details: disputeDetails.value,
        });
        clearInterval(tickInterval);
        disputeSuccess.value = true;
    } catch (e) {
        const errs = e.response?.data?.errors ?? {};
        if (Object.keys(errs).length) {
            disputeErrors.value = Object.fromEntries(
                Object.entries(errs).map(([k, v]) => [
                    k,
                    Array.isArray(v) ? v[0] : v,
                ]),
            );
        } else {
            disputeErrors.value = {
                _general: e.response?.data?.message ?? __("help.dispute.error"),
            };
        }
    } finally {
        disputeSubmitting.value = false;
    }
}
</script>

<template>
    <SiteModal :show="show" variant="pink" @close="emit('close')">
        <div class="faq-layout">

            <!-- ── Sidebar ── -->
            <nav
                class="faq-sidebar"
                :class="{ 'faq-sidebar--mob-hidden': mobileNav !== 'cats' }"
            >
                <div class="faq-sidebar__label">
                    {{ __("help.sections.label") }}
                </div>

                <!-- Loading skeleton -->
                <div v-if="helpLoading" class="faq-loading">
                    <span>{{ __("help.loading") || "Загрузка..." }}</span>
                </div>

                <!-- Doc tree: section header → articles -->
                <template v-for="cat in faqCategories" :key="cat.id">
                    <!-- Section header — некликабельный -->
                    <div class="faq-section-header">{{ cat.title }}</div>

                    <!-- Articles under this section -->
                    <button
                        v-for="article in cat.questions"
                        :key="article.id"
                        class="faq-article-btn"
                        :class="{ 'faq-article-btn--active': article.id === activeArticle?.id && !disputeView }"
                        @click="selectArticle(article)"
                    >
                        <span class="faq-article-btn__dot" />
                        <span class="faq-article-btn__title">{{ article.q }}</span>
                    </button>
                </template>

                <!-- Sidebar action buttons -->
                <div class="faq-sidebar__actions">
                    <Link
                        :href="route('contacts')"
                        class="faq-action-btn faq-action-btn--support"
                        @click="emit('close')"
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                        </svg>
                        {{ __("help.support.btn") }}
                    </Link>
                    <button
                        v-if="showDispute"
                        class="faq-action-btn faq-action-btn--dispute"
                        :class="{ 'faq-action-btn--dispute-active': disputeView }"
                        @click="openDisputeFromSidebar"
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        {{ __("help.dispute.btn") }}
                    </button>
                </div>
            </nav>

            <!-- ── Divider ── -->
            <div class="faq-divider" />

            <!-- ── Right: Article content / Dispute ── -->
            <div
                class="faq-content"
                :class="{ 'faq-content--mob-visible': mobileNav === 'answer' }"
            >
                <!-- Mobile back to sidebar -->
                <div class="faq-mob-header">
                    <button class="faq-mob-back" @click="backToSidebar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 18l-6-6 6-6" />
                        </svg>
                        {{ __("help.back_to_sections") }}
                    </button>
                </div>

                <Transition name="panel-fade" mode="out-in">

                    <!-- Dispute form -->
                    <div v-if="disputeView" key="dispute" class="faq-content-inner">
                        <div class="faq-content__header">
                            <h3 class="faq-content__title">{{ __("help.dispute.title") }}</h3>
                        </div>
                        <div v-if="disputeLoading" class="dispute-loading">{{ __("help.dispute.loading") }}</div>
                        <div v-else-if="disputeSuccess" class="dispute-success"><p>{{ __("help.dispute.sent") }}</p></div>
                        <div v-else-if="disputableOrders.length === 0" class="dispute-empty">
                            <p>{{ __("help.dispute.no_orders") }}<br />{{ __("help.dispute.time_limit") }}</p>
                        </div>
                        <div v-else class="dispute-form">
                            <p v-if="disputeErrors._general" class="dispute-field-error">{{ disputeErrors._general }}</p>

                            <label class="dispute-label">{{ __("help.dispute.order_label") }}</label>
                            <div class="dispute-orders-list">
                                <div
                                    v-for="o in disputableOrders" :key="o.id"
                                    class="dispute-order-card"
                                    :class="{ 'dispute-order-card--active': disputeOrderId === o.id }"
                                    @click="disputeOrderId = o.id"
                                >
                                    <div class="dispute-order-card__top">
                                        <span class="dispute-order-card__num">#{{ o.id }}</span>
                                        <span class="dispute-order-card__idol">{{ o.idol_name }}</span>
                                        <span class="dispute-order-card__total">{{ o.total }} ₽</span>
                                        <span class="dispute-order-card__timer">{{ orderTimeLeft(o.completed_at) }}</span>
                                    </div>
                                    <div class="dispute-order-card__dates">
                                        <span>{{ __("help.dispute.date.created") }} {{ fmtDate(o.created_at) }}</span>
                                        <span>{{ __("help.dispute.date.completed") }} {{ fmtDate(o.completed_at) }}</span>
                                    </div>
                                </div>
                            </div>

                            <label class="dispute-label" style="margin-top: 0.5rem">{{ __("help.dispute.reason_label") }}</label>
                            <select v-model="disputeReason" class="dispute-select" @change="delete disputeErrors.reason">
                                <option value="">{{ __("help.dispute.reason_select") }}</option>
                                <option v-for="r in DISPUTE_REASONS" :key="r" :value="r">{{ r }}</option>
                            </select>
                            <p v-if="disputeErrors.reason" class="dispute-field-error">{{ disputeErrors.reason }}</p>

                            <label class="dispute-label">{{ __("help.dispute.details_label") }}</label>
                            <textarea v-model="disputeDetails" class="dispute-textarea" :placeholder="__('help.dispute.details_ph')" rows="4" maxlength="2000" @input="delete disputeErrors.details"></textarea>
                            <span class="dispute-charcount" :class="{ 'dispute-charcount--warn': disputeDetails.trim().length > 0 && disputeDetails.trim().length < 100 }">
                                {{ __("help.char_count", { current: disputeDetails.trim().length, min: 100 }) }}
                            </span>
                            <p v-if="disputeErrors.details" class="dispute-field-error">{{ disputeErrors.details }}</p>

                            <button class="dispute-submit" :disabled="!disputeOrderId || !disputeReason || disputeDetails.trim().length < 100 || disputeSubmitting" @click="submitDispute">
                                {{ disputeSubmitting ? __("help.dispute.submitting") : __("help.dispute.submit") }}
                            </button>
                        </div>
                    </div>

                    <!-- Empty state: no article selected yet -->
                    <div v-else-if="!activeArticle" key="empty" class="faq-content-inner faq-empty-state">
                        <p class="faq-empty-state__text">{{ __("help.select_article") || "Выберите раздел слева" }}</p>
                    </div>

                    <!-- Article content -->
                    <div v-else :key="`article-${activeArticle.id}`" class="faq-content-inner">
                        <div class="faq-content__header">
                            <h3 class="faq-content__title">{{ activeArticle.q }}</h3>
                        </div>
                        <div class="faq-answer-body" v-html="activeArticle.a" />
                    </div>

                </Transition>

                <!-- Mobile footer actions -->
                <div class="faq-footer-actions">
                    <Link
                        :href="route('contacts')"
                        class="faq-action-btn faq-action-btn--support"
                        @click="emit('close')"
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                        </svg>
                        {{ __("help.support.btn") }}
                    </Link>
                    <button v-if="showDispute" class="faq-action-btn faq-action-btn--dispute" @click="openDisputeFromSidebar">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        {{ __("help.dispute.btn") }}
                    </button>
                </div>
            </div>

        </div>
    </SiteModal>
</template>

<style scoped>
/* ═══════════════════════════════════════════════
   Layout
   ═══════════════════════════════════════════════ */
.faq-layout {
    display: flex;
    flex-direction: row;
    height: 100%;
    color: #fff;
    min-height: 0;
}

/* ═══════════════════════════════════════════════
   Sidebar
   ═══════════════════════════════════════════════ */
.faq-sidebar {
    width: 36%;
    min-width: 200px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    padding-bottom: 0.5rem;
    background: rgba(255, 255, 255, 0.025);
    border-right: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 12px 0 0 12px;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.07) transparent;
}
.faq-sidebar::-webkit-scrollbar { width: 3px; }
.faq-sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.07);
    border-radius: 3px;
}

.faq-sidebar__label {
    padding: 1rem 1rem 0.5rem;
    font-size: 0.58rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: rgba(255, 178, 239, 0.38);
    flex-shrink: 0;
}

.faq-loading {
    padding: 0.75rem 1rem;
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.25);
    font-style: italic;
}

/* ═══════════════════════════════════════════════
   Sidebar doc tree
   ═══════════════════════════════════════════════ */
.faq-section-header {
    padding: 0.9rem 1rem 0.3rem;
    font-size: 0.59rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(255, 178, 239, 0.35);
    pointer-events: none;
    user-select: none;
    flex-shrink: 0;
}

.faq-article-btn {
    display: flex;
    align-items: baseline;
    gap: 0.6rem;
    width: 100%;
    padding: 0.42rem 0.9rem 0.42rem 1.25rem;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    color: rgba(255, 255, 255, 0.42);
    font-family: inherit;
    transition: color 0.15s ease, background 0.15s ease;
    position: relative;
    border-radius: 0;
}
.faq-article-btn:hover {
    color: rgba(255, 255, 255, 0.78);
    background: rgba(255, 178, 239, 0.05);
}
.faq-article-btn--active {
    color: rgba(255, 255, 255, 0.9);
    background: rgba(255, 178, 239, 0.07);
}
.faq-article-btn--active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: rgba(255, 178, 239, 0.7);
    border-radius: 0 2px 2px 0;
}
.faq-article-btn__dot {
    flex-shrink: 0;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.18);
    transition: background 0.15s;
    align-self: flex-start;
    margin-top: 0.45em;
}
.faq-article-btn--active .faq-article-btn__dot {
    background: rgba(255, 178, 239, 0.75);
}
.faq-article-btn__title {
    font-size: 0.79rem;
    line-height: 1.45;
}

/* ═══════════════════════════════════════════════
   Sidebar action buttons
   ═══════════════════════════════════════════════ */
.faq-sidebar__actions {
    margin-top: auto;
    padding: 0.6rem 0.75rem;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    flex-shrink: 0;
}

.faq-action-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.48rem 0.7rem;
    border-radius: 8px;
    border: 1px solid transparent;
    background: transparent;
    color: rgba(255, 255, 255, 0.38);
    font-family: inherit;
    font-size: 0.79rem;
    cursor: pointer;
    text-align: left;
    text-decoration: none;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.faq-action-btn svg {
    flex-shrink: 0;
    opacity: 0.55;
    transition: opacity 0.15s;
}
.faq-action-btn:hover svg { opacity: 0.9; }

.faq-action-btn--support:hover {
    background: rgba(255, 178, 239, 0.07);
    border-color: rgba(255, 178, 239, 0.15);
    color: rgba(255, 210, 245, 0.88);
}
.faq-action-btn--dispute:hover {
    background: rgba(255, 110, 110, 0.06);
    border-color: rgba(255, 110, 110, 0.18);
    color: rgba(255, 140, 140, 0.88);
}
.faq-action-btn--dispute-active {
    background: rgba(255, 110, 110, 0.06);
    border-color: rgba(255, 110, 110, 0.2);
    color: rgba(255, 140, 140, 0.88);
}

/* ═══════════════════════════════════════════════
   Divider
   ═══════════════════════════════════════════════ */
.faq-divider {
    width: 1px;
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.06);
    align-self: stretch;
}

/* ═══════════════════════════════════════════════
   Right content panel
   ═══════════════════════════════════════════════ */
.faq-content {
    flex: 1;
    overflow-y: auto;
    min-width: 0;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.06) transparent;
}
.faq-content::-webkit-scrollbar { width: 3px; }
.faq-content::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.06);
    border-radius: 3px;
}

.faq-content-inner { padding: 1.25rem 1.4rem 1rem; }

.faq-content__header {
    margin-bottom: 1rem;
    padding-bottom: 0.85rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.faq-content__title {
    font-family: "Imbue", serif;
    font-size: 1.4rem;
    font-weight: 200;
    letter-spacing: 0.01em;
    line-height: 1.25;
    color: rgba(255, 255, 255, 0.9);
}

.faq-answer-body {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.52);
    line-height: 1.8;
}
.faq-answer-body :deep(p) { margin-bottom: 0.7rem; }
.faq-answer-body :deep(p:last-child) { margin-bottom: 0; }
.faq-answer-body :deep(strong) {
    color: rgba(255, 255, 255, 0.72);
    font-weight: 500;
}
.faq-answer-body :deep(ul),
.faq-answer-body :deep(ol) {
    padding-left: 1.25rem;
    margin-bottom: 0.7rem;
}
.faq-answer-body :deep(li) { margin-bottom: 0.28rem; }
.faq-answer-body :deep(a) {
    color: rgba(255, 178, 239, 0.82);
    text-decoration: none;
    transition: color 0.15s;
}
.faq-answer-body :deep(a:hover) {
    color: rgba(200, 160, 255, 0.9);
    text-decoration: underline;
}
.faq-answer-body :deep(h2),
.faq-answer-body :deep(h3) {
    font-weight: 500;
    color: rgba(255, 255, 255, 0.72);
    margin: 1rem 0 0.35rem;
    line-height: 1.3;
}
.faq-answer-body :deep(h2) { font-size: 0.95rem; }
.faq-answer-body :deep(h3) { font-size: 0.875rem; }

.faq-empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 140px;
    padding: 2rem;
}
.faq-empty-state__text {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.16);
    font-style: italic;
    text-align: center;
}

/* ═══════════════════════════════════════════════
   Transition
   ═══════════════════════════════════════════════ */
.panel-fade-enter-active,
.panel-fade-leave-active {
    transition: opacity 0.16s ease, transform 0.16s ease;
}
.panel-fade-leave-to { opacity: 0; transform: translateX(-8px); }
.panel-fade-enter-from { opacity: 0; transform: translateX(8px); }

/* ═══════════════════════════════════════════════
   Large screens
   ═══════════════════════════════════════════════ */
@media (min-width: 1440px) {
    .faq-content__title { font-size: 1.6rem; }
    .faq-content-inner { padding: 1.5rem 1.75rem 1.25rem; }
    .faq-article-btn__title { font-size: 0.82rem; }
}
@media (min-width: 2000px) {
    .faq-content__title { font-size: 1.8rem; }
    .faq-article-btn__title { font-size: 0.87rem; }
    .faq-content-inner { padding: 1.75rem 2rem 1.5rem; }
}

/* ═══════════════════════════════════════════════
   Mobile (<=767px)
   ═══════════════════════════════════════════════ */
.faq-footer-actions { display: none; }
.faq-mob-header { display: none; }

@media (max-width: 767px) {
    .faq-layout {
        position: relative;
        overflow: hidden;
    }
    .faq-sidebar {
        position: absolute;
        inset: 0;
        width: 100%;
        border-right: none;
        border-radius: 0;
        background: transparent;
        z-index: 2;
        transform: translateX(0);
        transition: transform 0.26s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .faq-sidebar--mob-hidden {
        transform: translateX(-100%);
        pointer-events: none;
    }
    .faq-sidebar__label { padding: 0.75rem 1.1rem 0.5rem; }
    .faq-sidebar__actions { padding: 0.6rem 1rem; }
    .faq-section-header {
        padding: 0.9rem 1.1rem 0.3rem;
        font-size: 0.6rem;
    }
    .faq-article-btn {
        padding: 0.65rem 1.1rem 0.65rem 1.4rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }
    .faq-article-btn__title { font-size: 0.88rem; }
    .faq-article-btn--active {
        background: transparent;
        color: rgba(255, 255, 255, 0.65);
    }
    .faq-article-btn--active::before { display: none; }

    .faq-divider { display: none; }
    .faq-content {
        position: absolute;
        inset: 0;
        width: 100%;
        z-index: 1;
        transform: translateX(100%);
        transition: transform 0.26s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .faq-content--mob-visible { transform: translateX(0); }
    .faq-content-inner { padding: 0 1rem 1rem; }
    .faq-content__title { font-size: 1.2rem; }

    .faq-mob-header {
        display: flex;
        align-items: center;
        padding: 0.85rem 0 0.65rem;
        flex-shrink: 0;
    }
    .faq-mob-back {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.38rem 0.65rem 0.38rem 0.4rem;
        border-radius: 7px;
        border: 1px solid rgba(255, 255, 255, 0.09);
        background: rgba(255, 255, 255, 0.04);
        color: rgba(255, 255, 255, 0.48);
        font-size: 0.8rem;
        font-family: inherit;
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
        min-height: 36px;
    }
    .faq-mob-back:active {
        background: rgba(255, 178, 239, 0.09);
        color: rgba(255, 255, 255, 0.82);
    }
}

/* ═══════════════════════════════════════════════
   Dispute form
   ═══════════════════════════════════════════════ */
.dispute-loading,
.dispute-empty {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.38);
    padding: 0.75rem 0;
    line-height: 1.65;
}
.dispute-success {
    font-size: 0.82rem;
    color: rgba(100, 230, 160, 0.85);
    padding: 0.75rem 0;
    line-height: 1.65;
}
.dispute-form {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}
.dispute-label {
    font-size: 0.67rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255, 255, 255, 0.33);
    margin-top: 0.35rem;
}
.dispute-select,
.dispute-textarea {
    width: 100%;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.09);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.8rem;
    padding: 0.48rem 0.65rem;
    outline: none;
    font-family: inherit;
    transition: border-color 0.15s;
}
.dispute-select:focus,
.dispute-textarea:focus {
    border-color: rgba(255, 178, 239, 0.4);
}
.dispute-select option { background: #1a1a2e; }
.dispute-textarea { resize: vertical; min-height: 78px; }

.dispute-orders-list {
    display: flex;
    flex-direction: column;
    gap: 0.32rem;
    max-height: 200px;
    overflow-y: auto;
    padding-right: 3px;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
}
.dispute-order-card {
    padding: 0.48rem 0.65rem;
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 8px;
    cursor: pointer;
    background: rgba(255, 255, 255, 0.02);
    transition: border-color 0.15s, background 0.15s;
    flex-shrink: 0;
}
.dispute-order-card:hover {
    background: rgba(255, 178, 239, 0.04);
    border-color: rgba(255, 178, 239, 0.22);
}
.dispute-order-card--active {
    border-color: rgba(255, 178, 239, 0.45);
    background: rgba(255, 178, 239, 0.06);
}
.dispute-order-card__top {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.18rem;
}
.dispute-order-card__num {
    font-weight: 700;
    color: rgba(255, 255, 255, 0.38);
    font-size: 0.73rem;
    flex-shrink: 0;
}
.dispute-order-card__idol {
    flex: 1;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.8);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dispute-order-card__total {
    font-size: 0.74rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.52);
    white-space: nowrap;
    flex-shrink: 0;
}
.dispute-order-card__timer {
    font-size: 0.66rem;
    color: rgba(251, 191, 36, 0.82);
    white-space: nowrap;
    flex-shrink: 0;
}
.dispute-order-card__dates {
    display: flex;
    gap: 1rem;
    font-size: 0.66rem;
    color: rgba(255, 255, 255, 0.26);
}
.dispute-field-error {
    font-size: 0.69rem;
    color: rgba(255, 100, 100, 0.82);
    margin: -0.05rem 0 0.1rem;
}
.dispute-charcount {
    font-size: 0.67rem;
    color: rgba(255, 255, 255, 0.26);
    text-align: right;
    margin-top: -0.18rem;
}
.dispute-charcount--warn { color: rgba(255, 185, 60, 0.78); }
.dispute-submit {
    margin-top: 0.4rem;
    padding: 0.48rem 1.1rem;
    background: rgba(255, 178, 239, 0.07);
    border: 1px solid rgba(255, 178, 239, 0.24);
    color: rgba(220, 180, 255, 0.88);
    font-size: 0.77rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.15s, border-color 0.15s;
}
.dispute-submit:hover:not(:disabled) {
    background: rgba(255, 178, 239, 0.13);
    border-color: rgba(255, 178, 239, 0.45);
}
.dispute-submit:disabled { opacity: 0.35; cursor: default; }
</style>
