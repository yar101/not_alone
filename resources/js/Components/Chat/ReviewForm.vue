<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    orderId: { type: Number, required: true },
    idolId:  { type: Number, required: true },
});
const emit = defineEmits(['submitted']);

const rating           = ref(0);
const hovered          = ref(0);
const selectedEpithets = ref([]);
const text             = ref('');
const epithets         = ref([]);
const loadingEpithets  = ref(true);
const submitting       = ref(false);
const warningOpen      = ref(false);
const warnWrapEl       = ref(null);
const poppingHeart     = ref(0);
const isTouch          = ref(false);

function onDocClick(e) {
    if (warningOpen.value && warnWrapEl.value && !warnWrapEl.value.contains(e.target)) {
        warningOpen.value = false;
    }
}

const markTouch = () => {
    isTouch.value = true;
    window.removeEventListener('touchstart', markTouch);
};

onMounted(() => {
    document.addEventListener('click', onDocClick, true);
    window.addEventListener('touchstart', markTouch, { passive: true });
});
onUnmounted(() => {
    document.removeEventListener('click', onDocClick, true);
    window.removeEventListener('touchstart', markTouch);
});

onMounted(async () => {
    const res = await axios.get(route('reviews.epithets'));
    epithets.value = res.data;
    loadingEpithets.value = false;
});

function handleMouseEnter() {
    if (isTouch.value) return;
    warningOpen.value = true;
}

function handleMouseLeave() {
    if (isTouch.value) return;
    warningOpen.value = false;
}

function activeRating(i) {
    return i <= (hovered.value || rating.value);
}

function toggleEpithet(id) {
    const idx = selectedEpithets.value.indexOf(id);
    if (idx !== -1) selectedEpithets.value.splice(idx, 1);
    else selectedEpithets.value.push(id);
}

async function submit() {
    if (!rating.value || submitting.value) return;
    submitting.value = true;
    try {
        await axios.post(route('orders.review.store', props.orderId), {
            rating:   rating.value,
            text:     text.value || null,
            epithets: selectedEpithets.value,
        });
        emit('submitted');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="rv-wrap">
        <!-- Header row: title + warning button -->
        <div class="rv-header-row">
            <div class="rv-header">{{ __('review.title') }}</div>
            <div class="rv-warn-wrap" ref="warnWrapEl" @mouseleave="handleMouseLeave">
            <button
                class="rv-warn-btn"
                :class="{ 'rv-warn-btn--active': warningOpen }"
                @click.stop="warningOpen = !warningOpen"
                @mouseenter="handleMouseEnter"
                type="button"
                :aria-label="__('review.important')"
            >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </button>
            <Transition name="rv-warn">
                <div v-if="warningOpen" class="rv-warn-popup">
                    <p class="rv-warn-popup__text">{{ __('review.only_one') }}</p>
                </div>
            </Transition>
        </div>
        </div>

        <div class="rv-divider"><span></span></div>

        <!-- Hearts (shared gradient defs — rendered once) -->
        <svg width="0" height="0" style="position:absolute">
            <defs>
                <radialGradient id="rv-hg" cx="50%" cy="35%" r="65%">
                    <stop offset="0%" stop-color="rgba(255,190,210,0.95)" />
                    <stop offset="100%" stop-color="rgba(210,50,100,0.9)" />
                </radialGradient>
            </defs>
        </svg>

        <div class="rv-hearts">
            <button
                v-for="i in 5"
                :key="i"
                class="rv-heart"
                :class="{ 'rv-heart--pop': poppingHeart === i }"
                @mouseenter="hovered = i"
                @mouseleave="hovered = 0"
                @click="rating = i; poppingHeart = i; setTimeout(() => poppingHeart = 0, 350)"
                type="button"
                :aria-label="__('review.rating.aria', { value: i })"
            >
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <!-- Filled layer: always present, opacity transitions smoothly -->
                    <path
                        class="rv-heart__fill"
                        :class="{ 'rv-heart__fill--active': activeRating(i) }"
                        d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
                        fill="url(#rv-hg)"
                        stroke="rgba(210,60,100,0.6)"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <!-- Stroke layer: visible when not filled -->
                    <path
                        class="rv-heart__stroke"
                        :class="{ 'rv-heart__stroke--hidden': activeRating(i) }"
                        d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
                        fill="none"
                        stroke="rgba(255,160,180,0.35)"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </button>
        </div>

        <!-- Epithets skeleton -->
        <div v-if="loadingEpithets" class="rv-epithets rv-epithets--skeleton">
            <span v-for="w in [72, 90, 62, 110, 85, 75, 95, 68]" :key="w" class="rv-epithet-skel" :style="{ width: w + 'px' }" />
        </div>

        <!-- Epithets -->
        <div v-else-if="epithets.length" class="rv-epithets">
            <button
                v-for="ep in epithets"
                :key="ep.id"
                class="rv-epithet"
                :class="{ 'rv-epithet--active': selectedEpithets.includes(ep.id) }"
                @click="toggleEpithet(ep.id)"
                type="button"
            >{{ ep.label }}</button>
        </div>

        <!-- Text -->
        <div class="rv-text-wrap">
            <textarea
                v-model="text"
                class="rv-textarea"
                maxlength="250"
                :placeholder="__('review.comment.placeholder')"
                rows="3"
            ></textarea>
            <span class="rv-char-count">{{ text.length }}/250</span>
        </div>

        <!-- Submit -->
        <button
            class="rv-submit"
            :disabled="!rating || submitting"
            @click="submit"
            type="button"
        >
            {{ submitting ? __('common.sending') : __('review.submit') }}
        </button>
    </div>
</template>

<style scoped>
.rv-wrap {
    margin: 1.2rem 1rem 2rem;
    background: rgba(255,120,160,0.04);
    border: 1px dashed rgba(255,120,160,0.22);
    border-radius: 8px;
    padding: 1rem 1.1rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    position: relative;
}

.rv-header-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    position: relative;
}

.rv-header {
    position: absolute;
    left: 0;
    right: 0;
    text-align: center;
    pointer-events: none;
    font-family: 'Courier New', monospace;
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    color: rgba(255,160,185,0.8);
}

.rv-divider {
    display: flex;
    align-items: center;
    margin: 0 -0.1rem;
}
.rv-divider span {
    flex: 1;
    height: 1px;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255,120,160,0.15) 15%,
        rgba(255,120,160,0.45) 50%,
        rgba(255,120,160,0.15) 85%,
        transparent 100%
    );
}

/* ── Hearts ───────────────────────────────── */
.rv-hearts {
    display: flex;
    gap: 0;
    justify-content: center;
}
.rv-heart {
    background: none;
    border: none;
    padding: 8px 4px;
    cursor: pointer;
    width: 44px;
    height: 52px;
    transition: transform 0.12s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}
.rv-heart svg { width: 30px; height: 30px; }
.rv-heart:hover { transform: scale(1.15); }
.rv-heart--pop { animation: rv-heart-pop 0.35s ease; }
@keyframes rv-heart-pop {
    0%   { transform: scale(1); }
    35%  { transform: scale(1.38); }
    65%  { transform: scale(0.92); }
    100% { transform: scale(1); }
}

.rv-heart__fill {
    opacity: 0;
    transition: opacity 0.25s ease;
}
.rv-heart__fill--active {
    opacity: 1;
}
.rv-heart__stroke {
    transition: opacity 0.25s ease;
}
.rv-heart__stroke--hidden {
    opacity: 0;
}

/* ── Epithets skeleton ────────────────────── */
.rv-epithet-skel {
    display: inline-block;
    height: 28px;
    border-radius: 100px;
    background: linear-gradient(90deg,
        rgba(255,255,255,0.06) 25%,
        rgba(255,255,255,0.11) 50%,
        rgba(255,255,255,0.06) 75%
    );
    background-size: 200% 100%;
    animation: rv-skel-shimmer 1.4s ease-in-out infinite;
}
@keyframes rv-skel-shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* ── Epithets ─────────────────────────────── */
.rv-epithets {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.rv-epithet {
    background: rgba(255, 178, 239,0.05);
    border: 1px solid rgba(255, 178, 239,0.2);
    border-radius: 4px;
    padding: 0.35rem 0.85rem;
    font-size: 0.92rem;
    color: rgba(255, 178, 239,0.6);
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s, color 0.12s;
    font-family: inherit;
}
.rv-epithet:hover { background: rgba(255, 178, 239,0.1); color: rgba(180,180,255,0.9); }
.rv-epithet--active {
    background: rgba(255, 178, 239,0.15);
    border-color: var(--color-base-1);
    color: var(--color-base-1);
}

/* ── Textarea ─────────────────────────────── */
.rv-text-wrap {
    position: relative;
}
.rv-textarea {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 6px;
    padding: 0.6rem 0.75rem;
    color: rgba(255,255,255,0.8);
    font-size: 0.95rem;
    font-family: inherit;
    resize: none;
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
}
.rv-textarea:focus,
.rv-textarea:focus-visible {
    outline: none !important;
    box-shadow: none !important;
    border-color: rgba(255,120,160,0.4);
}
.rv-textarea::placeholder { color: rgba(255,255,255,0.22); }
.rv-char-count {
    position: absolute;
    bottom: 7px;
    right: 9px;
    font-size: 0.7rem;
    color: rgba(255,255,255,0.22);
    pointer-events: none;
}

/* ── Warning button ───────────────────────── */
.rv-warn-wrap {
    position: relative;
    padding: 0.25rem;
    flex-shrink: 0;
}
.rv-warn-btn {
    width: 52px;
    height: 32px;
    border-radius: 6px;
    border: 1.5px solid rgba(255,160,185,0.45);
    background: rgba(255,120,160,0.08);
    color: rgba(255,160,185,0.8);
    font-size: 0.8rem;
    font-weight: 700;
    font-family: 'Courier New', monospace;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
    padding: 0 0.5rem;
    gap: 0.35rem;
}
.rv-warn-btn:hover,
.rv-warn-btn--active {
    background: rgba(255,120,160,0.18);
    border-color: rgba(255,160,185,0.8);
    color: rgba(255,200,215,1);
}
.rv-warn-popup {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 300px;
    background: rgba(18,8,20,0.97);
    border: 1px solid rgba(255,120,160,0.3);
    border-radius: 8px;
    padding: 0.9rem 1rem;
    z-index: 10;
    box-shadow: 0 8px 28px rgba(0,0,0,0.6);
}
.rv-warn-popup::before {
    content: '';
    position: absolute;
    top: -5px;
    right: 8px;
    width: 8px;
    height: 8px;
    background: rgba(18,8,20,0.97);
    border-left: 1px solid rgba(255,120,160,0.3);
    border-top: 1px solid rgba(255,120,160,0.3);
    transform: rotate(45deg);
}
.rv-warn-popup__text {
    margin: 0;
    font-size: 0.92rem;
    color: rgba(255,195,210,0.9);
    line-height: 1.6;
}

/* Popup transition */
.rv-warn-enter-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.rv-warn-leave-active { transition: opacity 0.12s ease, transform 0.1s ease; }
.rv-warn-enter-from  { opacity: 0; transform: translateY(-4px); }
.rv-warn-leave-to    { opacity: 0; transform: translateY(-4px); }

/* ── Submit ───────────────────────────────── */
.rv-submit {
    width: 100%;
    padding: 0.65rem 1rem;
    background: rgba(255,120,160,0.1);
    border: 1px solid rgba(255,120,160,0.3);
    border-radius: 7px;
    color: rgba(255,190,210,0.95);
    font-size: 0.92rem;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Courier New', monospace;
    letter-spacing: 0.06em;
    transition: background 0.15s;
}
.rv-submit:hover:not(:disabled) { background: rgba(255,120,160,0.18); }
.rv-submit:disabled { opacity: 0.35; cursor: default; }
</style>
