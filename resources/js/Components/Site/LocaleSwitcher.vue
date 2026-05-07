<script setup>
import { ref, nextTick } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { locale, switchLocale } = useTranslations();

const visualLocale = ref(null);
const localeEls    = ref([]);
const indicator    = ref(null);
let switching = false;

function moveIndicator(code, animate) {
    const codes = Object.keys(locale.value?.available ?? {});
    const idx = codes.indexOf(code);
    const el  = localeEls.value[idx];
    const ind = indicator.value;
    if (!el || !ind) return;

    const parentRect = el.parentElement.getBoundingClientRect();
    const elRect     = el.getBoundingClientRect();
    const border     = parseFloat(getComputedStyle(el.parentElement).borderLeftWidth) || 0;
    const x = elRect.left - parentRect.left - border;
    const w = elRect.width;

    if (!animate) {
        ind.style.transition = 'none';
        ind.style.setProperty('--loc-x', `${x}px`);
        ind.style.setProperty('--loc-w', `${w}px`);
        requestAnimationFrame(() => requestAnimationFrame(() => { ind.style.transition = ''; }));
        return;
    }
    ind.style.setProperty('--loc-x', `${x}px`);
    ind.style.setProperty('--loc-w', `${w}px`);
}

function onLocaleClick(code) {
    if (code === (visualLocale.value ?? locale.value?.current) || switching) return;
    switching = true;
    visualLocale.value = code;
    nextTick(() => moveIndicator(code, true));
    setTimeout(() => { switchLocale(code); switching = false; }, 300);
}

function init() {
    nextTick(() => moveIndicator(locale.value?.current, false));
}
</script>

<template>
    <div class="locale-sw" @vue:mounted="init">
        <span ref="indicator" class="locale-sw__indicator" aria-hidden="true" />
        <button
            v-for="(label, code, i) in locale?.available"
            :key="code"
            :ref="el => localeEls[i] = el"
            class="locale-sw__btn"
            :class="{ 'locale-sw__btn--active': (visualLocale ?? locale?.current) === code }"
            @click="onLocaleClick(code)"
        >
            <span>{{ code === 'ru' ? '🇷🇺' : '🇬🇧' }}</span>
            {{ code.toUpperCase() }}
        </button>
    </div>
</template>

<style scoped>
.locale-sw {
    display: inline-flex;
    gap: 0.25rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    padding: 0.3rem;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    position: relative;
}

.locale-sw__indicator {
    position: absolute;
    top: 0.3rem; bottom: 0.3rem; left: 0;
    border-radius: 7px;
    background: rgba(255, 178, 239, 0.15);
    border: 1px solid rgba(255, 178, 239, 0.22);
    pointer-events: none;
    z-index: 0;
    width: var(--loc-w, 0px);
    transform: translateX(var(--loc-x, 0px));
    transition: transform 0.28s cubic-bezier(0.45, 0, 0.55, 1),
                width     0.28s cubic-bezier(0.45, 0, 0.55, 1);
}

.locale-sw__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.75rem;
    border-radius: 7px;
    border: none;
    background: transparent;
    color: rgba(255, 255, 255, 0.4);
    font-family: 'Rubik', sans-serif;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: color 0.22s;
    white-space: nowrap;
    position: relative;
    z-index: 1;
}
.locale-sw__btn:hover       { color: rgba(255, 255, 255, 0.7); }
.locale-sw__btn--active     { color: rgba(255, 255, 255, 0.92); }
</style>
