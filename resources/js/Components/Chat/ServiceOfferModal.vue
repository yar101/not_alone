<script setup>
import { ref, computed, watch, nextTick } from 'vue';
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
    <SiteModal :show="modelValue" variant="cyan" compact max-width="min(910px, 63vw)" @close="close">

        <div class="sof-title">{{ __('chat.offer.title') }}</div>

        <!-- Loading -->
        <div v-if="loading" class="sof-empty">{{ __('common.loading') }}</div>

        <!-- No services -->
        <div v-else-if="!categories.length" class="sof-empty">
            {{ __('chat.offer.empty') }}<br>
            <span class="sof-empty__hint">{{ __('chat.offer.empty.hint') }}</span>
        </div>

        <template v-else>
            <!-- Picker -->
            <div class="sof-picker">
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

            <!-- Selected islands -->
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

            <!-- Submit -->
            <button
                class="sof-submit"
                :disabled="!selectedServices.length || sending"
                @click="submit"
            >
                {{ sending ? __('common.sending') : __('chat.offer.send') }}
            </button>
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
.sof-empty__hint { font-size: 0.75rem; color: rgba(255,255,255,0.18); }

/* ── Picker ──────────────────────────────────────────── */
.sof-picker {
    display: flex;
    gap: 0;
    border: 1px solid rgba(110,110,210,0.15);
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.sof-cats-wrap {
    position: relative;
    width: max-content;
    min-width: 160px;
    max-width: 260px;
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
    color: rgba(100,200,255,0.7);
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
.sof-cats::-webkit-scrollbar-thumb { background: rgba(100,200,255,0.35); border-radius: 3px; }
.sof-cats::-webkit-scrollbar-thumb:hover { background: rgba(100,200,255,0.55); }
.sof-cat {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.7rem 1rem;
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.45);
    font-size: 1.05rem;
    text-align: left;
    cursor: pointer;
    transition: background 0.13s, color 0.13s;
    font-family: inherit;
    gap: 0.4rem;
    white-space: nowrap;
}
.sof-cat:hover { background: rgba(100,200,255,0.06); color: rgba(255,255,255,0.75); }
.sof-cat--active {
    background: rgba(100,200,255,0.1);
    color: rgba(130,220,255,0.95);
}
.sof-cat__dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: #64c8ff;
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
.sof-svc:hover:not(.sof-svc--disabled) { background: rgba(100,200,255,0.06); }
.sof-svc--checked { background: rgba(100,200,255,0.08); }
.sof-svc--disabled { opacity: 0.38; cursor: not-allowed; }

.sof-svc__name { flex: 1 1 auto; min-width: 0; font-size: 1.1rem; color: rgba(255,255,255,0.85); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sof-svc__price {
    flex-shrink: 0;
    font-size: 1rem;
    font-family: 'Courier New', monospace;
    color: rgba(100,200,255,0.75);
}

/* ── Selected islands ────────────────────────────────── */
.sof-selected {
    margin-bottom: 1rem;
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
    background: rgba(100,200,255,0.08);
    border: 1px solid rgba(100,200,255,0.25);
    border-radius: 6px;
}
.sof-island__name { font-size: 0.9rem; color: rgba(255,255,255,0.82); }
.sof-island__price {
    font-size: 0.85rem;
    font-family: 'Courier New', monospace;
    color: rgba(100,200,255,0.7);
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
    background: rgba(100,200,255,0.1);
    border: 1px solid rgba(100,200,255,0.3);
    border-radius: 7px;
    color: rgba(130,220,255,0.95);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.15s;
    letter-spacing: 0.04em;
}
.sof-submit:hover:not(:disabled) { background: rgba(100,200,255,0.18); }
.sof-submit:disabled { opacity: 0.35; cursor: default; }

/* ── Transitions ─────────────────────────────────── */
.sof-fade-enter-active,
.sof-fade-leave-active { transition: opacity 0.2s ease; }
.sof-fade-enter-from,
.sof-fade-leave-to { opacity: 0; }

/* ── Mobile ──────────────────────────────────────── */
@media (max-width: 768px) {
    .sof-picker {
        flex-direction: column;
    }

    .sof-cats-wrap {
        width: 100%;
        max-width: 100%;
        border-right: none;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    .sof-cats {
        display: flex;
        flex-direction: row;
        overflow-x: auto;
        overflow-y: hidden;
        max-height: none;
        scrollbar-width: none;
        white-space: nowrap;
        padding-bottom: 2px;
    }

    .sof-cats::-webkit-scrollbar { display: none; }

    .sof-cat {
        flex-shrink: 0;
        width: auto;
        padding: 0.55rem 0.9rem;
        font-size: 0.9rem;
        border-bottom: 2px solid transparent;
    }

    .sof-cat--active {
        border-bottom-color: rgba(100,200,255,0.7);
    }

    .sof-cats-fade { display: none; }

    .sof-services {
        max-height: 240px;
    }

    .sof-svc__name { font-size: 1rem; }
}
</style>
