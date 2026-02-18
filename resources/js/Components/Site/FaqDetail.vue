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
        el.style.transition = 'height 250ms ease';
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
        el.style.transition = 'height 200ms ease';
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
    <div class="accordion-item">
        <button class="accordion-trigger" @click="isOpen = !isOpen">
            <span class="accordion-question">{{ question }}</span>
            <svg
                class="accordion-chevron"
                :class="{ 'accordion-chevron--open': isOpen }"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M6 9l6 6 6-6" />
            </svg>
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
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
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
    padding: 0.85rem 0;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
}

.accordion-question {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.5;
    flex: 1;
}

.accordion-chevron {
    flex-shrink: 0;
    color: rgba(255, 255, 255, 0.35);
    transition: transform 0.25s ease, color 0.25s ease;
}

.accordion-chevron--open {
    transform: rotate(180deg);
    color: rgba(200, 70, 126, 0.8);
}

.accordion-body {
    overflow: hidden;
}

.accordion-answer {
    padding-bottom: 0.85rem;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.55);
    line-height: 1.7;
}

.accordion-answer :deep(p) {
    margin-bottom: 0.6rem;
}

.accordion-answer :deep(p:last-child) {
    margin-bottom: 0;
}

.accordion-answer :deep(strong) {
    color: rgba(255, 255, 255, 0.75);
    font-weight: 500;
}

.accordion-answer :deep(a) {
    color: rgba(200, 70, 126, 0.85);
    text-decoration: none;
}

.accordion-answer :deep(a:hover) {
    color: rgba(200, 70, 126, 1);
    text-decoration: underline;
}

@media (min-width: 1440px) {
    .accordion-trigger {
        padding: 1rem 0;
    }

    .accordion-question {
        font-size: 1rem;
    }

    .accordion-chevron {
        width: 18px;
        height: 18px;
    }

    .accordion-answer {
        font-size: 0.95rem;
        padding-bottom: 1rem;
    }
}

@media (min-width: 2000px) {
    .accordion-trigger {
        padding: 1.15rem 0;
    }

    .accordion-question {
        font-size: 1.1rem;
    }

    .accordion-chevron {
        width: 20px;
        height: 20px;
    }

    .accordion-answer {
        font-size: 1.05rem;
        padding-bottom: 1.1rem;
    }
}
</style>
