<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import SiteModal from '@/Components/Site/SiteModal.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import { useTranslations } from '@/composables/useTranslations';

const { __, locale } = useTranslations();

const tName = (item) => locale.value?.current === 'en' && item?.name_en ? item.name_en : (item?.name_ru ?? '');
const tCat  = (cat)  => locale.value?.current === 'en' && cat?.name_en  ? cat.name_en  : (cat?.name_ru  ?? '');
const tUnit = (svc)  => {
    const ru = svc?.time_unit_ru ?? null;
    const en = svc?.time_unit_en ?? null;
    return locale.value?.current === 'en' && en ? en : ru;
};

const props = defineProps({
    modelValue:     { type: Boolean, default: false },
    conversationId: { type: Number, required: true },
});
const emit = defineEmits(['update:modelValue', 'sent']);

const categories       = ref([]);
const activeCategory   = ref(null);
const selectedServices = ref([]); // [{ id, name, price, category_name }]
const loading          = ref(false);
const sending          = ref(false);
const catsEl           = ref(null);
const catsHasMore      = ref(false);
const catsHasLess      = ref(false);

function onCatsScroll() {
    if (!catsEl.value) return;
    const { scrollTop, scrollHeight, clientHeight } = catsEl.value;
    catsHasMore.value = scrollTop + clientHeight < scrollHeight - 4;
    catsHasLess.value = scrollTop > 4;
}

function checkCatsScroll() {
    nextTick(() => {
        if (!catsEl.value) return;
        const { scrollTop, scrollHeight, clientHeight } = catsEl.value;
        catsHasMore.value = scrollHeight > clientHeight + 4;
        catsHasLess.value = scrollTop > 4;
    });
}

const activeServices = computed(() =>
    categories.value.find(c => c.category.id === activeCategory.value)?.services ?? []
);

const isSelected   = (svcId) => selectedServices.value.some(s => s.id === svcId);
const limitReached = computed(() => selectedServices.value.length >= 2);

function toggleService(svc, categoryName) {
    const idx = selectedServices.value.findIndex(s => s.id === svc.id);
    if (idx !== -1) {
        selectedServices.value.splice(idx, 1);
    } else if (!limitReached.value) {
        selectedServices.value.push({
            ...svc,
            name:          tName(svc),
            time_unit:     tUnit(svc),
            category_name: categoryName,
        });
    }
}

function removeSelected(id) {
    const idx = selectedServices.value.findIndex(s => s.id === id);
    if (idx !== -1) selectedServices.value.splice(idx, 1);
}

async function loadCategories() {
    loading.value = true;
    try {
        const res = await axios.get(route('profile.services.for-offer'));
        categories.value = res.data;
        if (categories.value.length) {
            activeCategory.value = categories.value[0].category.id;
        }
        checkCatsScroll();
    } finally {
        loading.value = false;
    }
}

async function submit() {
    if (!selectedServices.value.length || sending.value) return;
    sending.value = true;
    try {
        const res = await axios.post(route('conversations.offer-services', props.conversationId), {
            services: selectedServices.value.map(s => s.id),
        });
        emit('sent', res.data);
        close();
    } finally {
        sending.value = false;
    }
}

function close() {
    emit('update:modelValue', false);
}

function fmtPrice(p) {
    return (p ?? 0).toLocaleString('ru-RU') + '\u2009₽';
}

const isMobile = ref(typeof window !== 'undefined' && window.innerWidth <= 768);
const onResize = () => { isMobile.value = window.innerWidth <= 768; };
onMounted(() => window.addEventListener('resize', onResize));
onUnmounted(() => window.removeEventListener('resize', onResize));

function toggleAccordion(catId) {
    activeCategory.value = activeCategory.value === catId ? null : catId;
}

watch(() => props.modelValue, (val) => {
    if (val) {
        selectedServices.value = [];
        activeCategory.value   = null;
        categories.value       = [];
        loadCategories();
    }
});
</script>

<template>
    <SiteModal :show="modelValue" variant="blue" compact max-width="min(1000px, 70vw)" @close="close">

        <div class="sof-title">{{ __('chat.offer.title') }}</div>

        <!-- Skeleton Loading -->
        <div v-if="loading" class="sof-skeleton">
            <div v-if="!isMobile" class="sof-skeleton__desktop">
                <div class="sof-skeleton__cats">
                    <div v-for="i in 5" :key="i" class="sof-skeleton__cat skel-pulse" />
                </div>
                <div class="sof-skeleton__services">
                    <div v-for="i in 4" :key="i" class="sof-skeleton__svc">
                        <div class="sof-skeleton__check skel-pulse" />
                        <div class="sof-skeleton__name skel-pulse" />
                        <div class="sof-skeleton__price skel-pulse" />
                    </div>
                </div>
            </div>
            <div v-else class="sof-skeleton__mobile">
                <div v-for="i in 6" :key="i" class="sof-skeleton__acc skel-pulse" />
            </div>
        </div>

        <!-- No services -->
        <div v-else-if="!categories.length" class="sof-empty">
            {{ __('chat.offer.empty') }}<br>
            <span class="sof-empty__hint">{{ __('chat.offer.empty.hint') }}</span>
        </div>

        <template v-else>
            <!-- Desktop: two-column picker -->
            <div v-if="!isMobile" class="sof-picker">
                <!-- Categories -->
                <div class="sof-cats-wrap">
                    <div class="sof-cats" ref="catsEl" @scroll="onCatsScroll">
                        <button
                            v-for="cat in categories"
                            :key="cat.category.id"
                            class="sof-cat"
                            :class="{ 'sof-cat--active': activeCategory === cat.category.id }"
                            @click="activeCategory = cat.category.id"
                        >
                            <span>{{ tCat(cat.category) }}</span>
                            <span
                                v-if="selectedServices.some(s => s.category_name === tCat(cat.category))"
                                class="sof-cat__dot"
                            />
                        </button>
                    </div>
                    <Transition name="sof-fade">
                        <div v-if="catsHasLess" class="sof-cats-fade sof-cats-fade--top">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M2 8l4-4 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </Transition>
                    <Transition name="sof-fade">
                        <div v-if="catsHasMore" class="sof-cats-fade sof-cats-fade--bottom">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </Transition>
                </div>

                <!-- Services -->
                <div class="sof-services">
                    <label
                        v-for="svc in activeServices"
                        :key="svc.id"
                        class="sof-svc"
                        :class="{
                            'sof-svc--checked':  isSelected(svc.id),
                            'sof-svc--disabled': !isSelected(svc.id) && limitReached,
                        }"
                        @click.prevent="toggleService(svc, tCat(categories.find(c => c.category.id === activeCategory)?.category))"
                    >
                        <AppCheckbox
                            :checked="isSelected(svc.id)"
                            :disabled="!isSelected(svc.id) && limitReached"
                        />
                        <span class="sof-svc__name">{{ tName(svc) }}</span>
                        <span class="sof-svc__price">{{ fmtPrice(svc.price) }}<template v-if="tUnit(svc)">&thinsp;/&thinsp;{{ tUnit(svc) }}</template></span>
                    </label>
                </div>
            </div>

            <!-- Mobile: accordion -->
            <div v-else class="sof-accordion">
                <div
                    v-for="cat in categories"
                    :key="cat.category.id"
                    class="sof-acc-item"
                >
                    <button
                        class="sof-acc-header"
                        :class="{ 'sof-acc-header--open': activeCategory === cat.category.id }"
                        @click="toggleAccordion(cat.category.id)"
                    >
                        <span class="sof-acc-header__title">{{ tCat(cat.category) }}</span>
                        <span
                            v-if="selectedServices.some(s => s.category_name === tCat(cat.category))"
                            class="sof-cat__dot"
                        />
                        <svg class="sof-acc-chevron" width="14" height="14" viewBox="0 0 12 12" fill="none">
                            <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div
                        class="sof-acc-body-wrap"
                        :class="{ 'sof-acc-body-wrap--open': activeCategory === cat.category.id }"
                    >
                        <div class="sof-acc-body-inner">
                            <label
                                v-for="svc in cat.services"
                                :key="svc.id"
                                class="sof-svc"
                                :class="{
                                    'sof-svc--checked':  isSelected(svc.id),
                                    'sof-svc--disabled': !isSelected(svc.id) && limitReached,
                                }"
                                @click.prevent="toggleService(svc, tCat(cat.category))"
                            >
                                <AppCheckbox
                                    :checked="isSelected(svc.id)"
                                    :disabled="!isSelected(svc.id) && limitReached"
                                />
                                <span class="sof-svc__name">{{ tName(svc) }}</span>
                                <span class="sof-svc__price">{{ fmtPrice(svc.price) }}<template v-if="tUnit(svc)">&thinsp;/&thinsp;{{ tUnit(svc) }}</template></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer: selected islands + submit (sticky on mobile) -->
            <div class="sof-footer">
                <div v-if="selectedServices.length" class="sof-selected">
                    <span class="sof-selected__label">{{ __('chat.offer.selected', { count: selectedServices.length }) }}</span>
                    <div class="sof-islands">
                        <div
                            v-for="s in selectedServices"
                            :key="s.id"
                            class="sof-island"
                        >
                            <span class="sof-island__name">{{ s.name }}</span>
                            <span class="sof-island__price">{{ fmtPrice(s.price) }}<template v-if="s.time_unit">&thinsp;/&thinsp;{{ s.time_unit }}</template></span>
                            <button class="sof-island__remove" @click="removeSelected(s.id)" :aria-label="__('chat.offer.remove')">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <button
                    class="sof-submit"
                    :disabled="!selectedServices.length || sending"
                    @click="submit"
                >
                    {{ sending ? __('common.sending') : __('chat.offer.send') }}
                </button>
            </div>
        </template>

    </SiteModal>
</template>

<style scoped>
.sof-title {
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.45);
    margin-bottom: 1rem;
}

.sof-empty {
    text-align: center;
    color: rgba(255,255,255,0.3);
    font-size: 0.85rem;
    padding: 2rem 0;
    line-height: 1.7;
}

/* ── Skeleton ────────────────────────────────────────── */
.sof-skeleton { margin-bottom: 1rem; }
.sof-skeleton__desktop {
    display: flex;
    border: 1px solid rgba(255, 178, 239, 0.08);
    border-radius: 8px;
    height: 260px;
    overflow: hidden;
}
.sof-skeleton__cats {
    width: 240px;
    border-right: 1px solid rgba(255, 255, 255, 0.05);
    padding: 0.6rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.sof-skeleton__cat { height: 32px; width: 100%; border-radius: 4px; }
.sof-skeleton__services {
    flex: 1;
    padding: 0.8rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.sof-skeleton__svc {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}
.sof-skeleton__check { width: 18px; height: 18px; border-radius: 4px; flex-shrink: 0; }
.sof-skeleton__name { height: 18px; flex: 1; border-radius: 4px; }
.sof-skeleton__price { height: 18px; width: 60px; border-radius: 4px; }

.sof-skeleton__mobile {
    display: flex;
    flex-direction: column;
    gap: 1px;
    border: 1px solid rgba(255, 178, 239, 0.08);
    border-radius: 8px;
    overflow: hidden;
}
.sof-skeleton__acc { height: 48px; width: 100%; }

.skel-pulse {
    background: rgba(255, 255, 255, 0.05);
    position: relative;
    overflow: hidden;
}
.skel-pulse::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.06) 50%, transparent 100%);
    transform: translateX(-100%);
    animation: skel-slide 1.5s ease-in-out infinite;
}
@keyframes skel-slide {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.sof-empty__hint { font-size: 0.75rem; color: rgba(255,255,255,0.18); }

/* ── Picker ──────────────────────────────────────────── */
.sof-picker {
    display: flex;
    gap: 0;
    border: 1px solid rgba(255, 178, 239,0.15);
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.sof-cats-wrap {
    position: relative;
    width: max-content;
    min-width: 220px;
    max-width: 340px;
    flex-shrink: 0;
    border-right: 1px solid rgba(255,255,255,0.05);
}
.sof-cats {
    width: 100%;
    max-height: 300px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(100,200,255,0.35) rgba(255,255,255,0.05);
}
.sof-cats-fade {
    position: absolute;
    left: 0;
    right: 0;
    height: 72px;
    pointer-events: none;
    display: flex;
    justify-content: center;
    color: color-mix(in srgb, var(--color-base-2), transparent 30%);
}
.sof-cats-fade--bottom {
    bottom: 0;
    background: linear-gradient(to bottom, transparent, rgba(8,8,20,0.97));
    align-items: flex-end;
    padding-bottom: 8px;
}
.sof-cats-fade--top {
    top: 0;
    background: linear-gradient(to top, transparent, rgba(8,8,20,0.97));
    align-items: flex-start;
    padding-top: 8px;
}
.sof-cats::-webkit-scrollbar { width: 5px; }
.sof-cats::-webkit-scrollbar-track { background: rgba(255,255,255,0.04); }
.sof-cats::-webkit-scrollbar-thumb { background: color-mix(in srgb, var(--color-base-2), transparent 65%); border-radius: 3px; }
.sof-cats::-webkit-scrollbar-thumb:hover { background: color-mix(in srgb, var(--color-base-2), transparent 45%); }
.sof-cat {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.7rem 1rem;
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.45);
    font-size: 0.95rem;
    text-align: left;
    cursor: pointer;
    transition: background 0.13s, color 0.13s;
    font-family: inherit;
    gap: 0.4rem;
    white-space: nowrap;
}
.sof-cat > span:first-child {
    overflow: hidden;
    text-overflow: ellipsis;
}
.sof-cat:hover { background: color-mix(in srgb, var(--color-base-2), transparent 94%); color: rgba(255,255,255,0.75); }
.sof-cat--active {
    background: color-mix(in srgb, var(--color-base-2), transparent 90%);
    color: var(--color-base-2);
}
.sof-cat__dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--color-base-2);
    flex-shrink: 0;
}

.sof-services {
    flex: 1;
    max-height: 300px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.05) transparent;
}
.sof-svc {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.7rem 1rem;
    cursor: pointer;
    transition: background 0.12s;
    border-bottom: 1px solid rgba(255,255,255,0.03);
    user-select: none;
}
.sof-svc:hover:not(.sof-svc--disabled) { background: color-mix(in srgb, var(--color-base-2), transparent 94%); }
.sof-svc--checked { background: color-mix(in srgb, var(--color-base-2), transparent 92%); }
.sof-svc--disabled { opacity: 0.38; cursor: not-allowed; }

.sof-svc__name { flex: 1 1 auto; min-width: 0; font-size: 1rem; color: rgba(255,255,255,0.85); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sof-svc__price {
    flex-shrink: 0;
    font-size: 0.9rem;
    font-family: 'Courier New', monospace;
    color: color-mix(in srgb, var(--color-base-2), transparent 25%);
}

/* ── Selected islands ────────────────────────────────── */
.sof-selected {
    margin-bottom: 0.75rem;
}
.sof-selected__label {
    display: block;
    font-size: 0.7rem;
    color: rgba(255,255,255,0.28);
    margin-bottom: 0.5rem;
    letter-spacing: 0.04em;
}
.sof-islands {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.sof-island {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.65rem;
    background: color-mix(in srgb, var(--color-base-2), transparent 92%);
    border: 1px solid color-mix(in srgb, var(--color-base-2), transparent 75%);
    border-radius: 6px;
}
.sof-island__name { font-size: 0.9rem; color: rgba(255,255,255,0.82); }
.sof-island__price {
    font-size: 0.85rem;
    font-family: 'Courier New', monospace;
    color: color-mix(in srgb, var(--color-base-2), transparent 30%);
}
.sof-island__remove {
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.3);
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    line-height: 1;
    transition: color 0.15s;
}
.sof-island__remove:hover { color: rgba(255,100,100,0.7); }

/* ── Submit ──────────────────────────────────────────── */
.sof-submit {
    width: 100%;
    padding: 0.75rem 1rem;
    background: color-mix(in srgb, var(--color-base-2), transparent 90%);
    border: 1px solid color-mix(in srgb, var(--color-base-2), transparent 70%);
    border-radius: 7px;
    color: var(--color-base-2);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.15s;
    letter-spacing: 0.04em;
}
.sof-submit:hover:not(:disabled) { background: color-mix(in srgb, var(--color-base-2), transparent 82%); }
.sof-submit:disabled { opacity: 0.35; cursor: default; }

/* ── Transitions ─────────────────────────────────── */
.sof-fade-enter-active,
.sof-fade-leave-active { transition: opacity 0.2s ease; }
.sof-fade-enter-from,
.sof-fade-leave-to { opacity: 0; }

/* ── Accordion (mobile only) ─────────────────────── */
.sof-accordion {
    border: 1px solid rgba(255, 178, 239,0.15);
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.sof-acc-item {
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.sof-acc-item:last-child { border-bottom: none; }

.sof-acc-header {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.8rem 1rem;
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.55);
    font-size: 0.95rem;
    font-family: inherit;
    text-align: left;
    cursor: pointer;
    transition: background 0.13s, color 0.13s;
}
.sof-acc-header:hover { background: color-mix(in srgb, var(--color-base-2), transparent 95%); color: rgba(255,255,255,0.8); }
.sof-acc-header--open { background: color-mix(in srgb, var(--color-base-2), transparent 92%); color: var(--color-base-2); }

.sof-acc-header__title { flex: 1; }

.sof-acc-chevron {
    flex-shrink: 0;
    color: rgba(255,255,255,0.3);
    transition: transform 0.2s ease;
}
.sof-acc-header--open .sof-acc-chevron { transform: rotate(180deg); }

.sof-acc-body-wrap {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 0.24s cubic-bezier(0.4, 0, 0.2, 1);
}
.sof-acc-body-wrap--open {
    grid-template-rows: 1fr;
}
.sof-acc-body-inner {
    overflow: hidden;
}

/* ── Footer (selected + submit) ──────────────────── */
.sof-footer {
    margin: 1rem -2rem -2rem;
    padding: 1rem 2rem 1.5rem;
    background: rgba(7,6,11,0.82);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-top: 1px solid rgba(255,255,255,0.05);
}

@media (max-width: 768px) {
    .sof-footer {
        position: sticky;
        bottom: -1.25rem;
        margin: 0 -1.25rem -1.25rem;
        padding: 0.75rem 1.25rem calc(1.25rem + env(safe-area-inset-bottom));
    }
}
</style>
