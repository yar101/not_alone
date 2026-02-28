<script setup>
import { ref } from 'vue';

defineProps({
    question: {
        type: String,
        required: true,
    },
    answer: {
        type: String,
        required: true,
    },
});

const isOpen = ref(false);

function onEnter(el) {
    el.style.height = '0';
    el.style.overflow = 'hidden';
    requestAnimationFrame(() => {
        el.style.transition = 'height 260ms cubic-bezier(0.4, 0, 0.2, 1)';
        el.style.height = el.scrollHeight + 'px';
    });
}

function onAfterEnter(el) {
    el.style.height = '';
    el.style.overflow = '';
    el.style.transition = '';
}

function onLeave(el) {
    el.style.height = el.scrollHeight + 'px';
    el.style.overflow = 'hidden';
    requestAnimationFrame(() => {
        el.style.transition = 'height 200ms cubic-bezier(0.4, 0, 0.2, 1)';
        el.style.height = '0';
    });
}

function onAfterLeave(el) {
    el.style.height = '';
    el.style.overflow = '';
    el.style.transition = '';
}
</script>

<template>
    <div class="accordion-item" :class="{ 'accordion-item--open': isOpen }">
        <button class="accordion-trigger" @click="isOpen = !isOpen">
            <span class="accordion-question">{{ question }}</span>
            <span class="accordion-chevron-wrap">
                <svg
                    class="accordion-chevron"
                    :class="{ 'accordion-chevron--open': isOpen }"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M6 9l6 6 6-6" />
                </svg>
            </span>
        </button>

        <Transition
            @enter="onEnter"
            @after-enter="onAfterEnter"
            @leave="onLeave"
            @after-leave="onAfterLeave"
        >
            <div v-if="isOpen" class="accordion-body">
                <div class="accordion-answer" v-html="answer" />
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.accordion-item {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.accordion-item--open {
    border-bottom: none;
}

.accordion-item:last-child {
    border-bottom: none;
}

.accordion-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
    padding: 0.9rem 0;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    transition: opacity 0.15s ease;
}

.accordion-trigger:active {
    opacity: 0.8;
}

.accordion-question {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
    line-height: 1.5;
    flex: 1;
    transition: color 0.25s ease;
}

.accordion-trigger:hover .accordion-question {
    color: rgba(255, 255, 255, 0.92);
}

/* Open state: question turns rose */
.accordion-item--open .accordion-question {
    color: rgba(210, 90, 140, 0.9);
}

/* Chevron wrap: small glowing circle */
.accordion-chevron-wrap {
    width: 24px;
    height: 24px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.07);
    transition: background 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
}

.accordion-item--open .accordion-chevron-wrap {
    background: rgba(200, 70, 126, 0.15);
    border-color: rgba(200, 70, 126, 0.3);
    box-shadow: 0 0 8px rgba(200, 70, 126, 0.2);
}

.accordion-chevron {
    color: rgba(255, 255, 255, 0.3);
    transition: transform 0.28s cubic-bezier(0.34, 1.2, 0.64, 1), color 0.25s ease;
    display: block;
}

.accordion-chevron--open {
    transform: rotate(180deg);
    color: rgba(200, 70, 126, 0.9);
}

/* Answer body */
.accordion-body {
    overflow: hidden;
}

.accordion-answer {
    padding-bottom: 1rem;
    padding-left: 1rem;
    border-left: 1px solid rgba(200, 70, 126, 0.22);
    margin-left: 0.15rem;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    line-height: 1.75;
}

.accordion-answer :deep(p) {
    margin-bottom: 0.65rem;
}

.accordion-answer :deep(p:last-child) {
    margin-bottom: 0;
}

.accordion-answer :deep(strong) {
    color: rgba(255, 255, 255, 0.72);
    font-weight: 500;
}

.accordion-answer :deep(a) {
    color: rgba(200, 70, 126, 0.85);
    text-decoration: none;
    transition: color 0.15s ease;
}

.accordion-answer :deep(a:hover) {
    color: rgb(200, 70, 126);
    text-decoration: underline;
}

/* Large screens */
@media (min-width: 1440px) {
    .accordion-trigger { padding: 1rem 0; }
    .accordion-question { font-size: 1rem; }
    .accordion-chevron-wrap { width: 28px; height: 28px; }
    .accordion-chevron { width: 15px; height: 15px; }
    .accordion-answer { font-size: 0.95rem; padding-bottom: 1.1rem; }
}

@media (min-width: 2000px) {
    .accordion-trigger { padding: 1.15rem 0; }
    .accordion-question { font-size: 1.1rem; }
    .accordion-chevron-wrap { width: 32px; height: 32px; }
    .accordion-chevron { width: 16px; height: 16px; }
    .accordion-answer { font-size: 1.05rem; padding-bottom: 1.2rem; }
}
</style>
