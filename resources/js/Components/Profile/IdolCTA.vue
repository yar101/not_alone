<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    eyebrow: { type: String, required: true },
    title: { type: String, required: true },
    tag1: { type: String, required: true },
    tag2: { type: String, required: true },
    applyText: { type: String, required: true },
    applyLink: { type: String, default: '/idol/apply' },
});
</script>

<template>
    <div class="anim-block idol-cta-block">
        <div class="idol-cta-content">
            <div class="idol-cta-left">
                <span class="idol-cta-eyebrow">{{ eyebrow }}</span>
                <p class="idol-cta-title" v-html="title"></p>
                <div class="idol-cta-tags">
                    <span class="idol-cta-tag">{{ tag1 }}</span>
                    <span class="idol-cta-tag">{{ tag2 }}</span>
                </div>
            </div>
            <Link :href="applyLink" class="idol-cta-btn">{{ applyText }}</Link>
        </div>
    </div>
</template>

<style scoped>
.idol-cta-block {
    position: relative;
    overflow: hidden;
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 82%);
    border-top-color: color-mix(in srgb, var(--color-base-1), transparent 70%);
    border-radius: var(--profile-border-radius, 8px);
    background:
        repeating-linear-gradient(
            0deg,
            transparent,
            transparent 23px,
            color-mix(in srgb, var(--color-base-1), transparent 97.5%) 24px
        ),
        linear-gradient(
            120deg,
            color-mix(in srgb, var(--color-base-1), transparent 90%) 0%,
            color-mix(in srgb, var(--color-base-1), transparent 96%) 50%,
            rgba(100, 210, 255, 0.07) 100%
        );
    box-shadow:
        inset 0 1px 0 color-mix(in srgb, var(--color-base-1), transparent 88%),
        inset 0 -1px 0 rgba(0, 0, 0, 0.22),
        0 6px 32px rgba(0, 0, 0, 0.2);
}

.idol-cta-block::before {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(
        ellipse 70% 100% at 100% 50%,
        rgba(100, 210, 255, 0.08) 0%,
        transparent 70%
    );
    pointer-events: none;
}

.idol-cta-content {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 2rem;
    min-height: 140px;
}

.idol-cta-left {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.idol-cta-eyebrow {
    font-size: 0.58rem;
    font-weight: 700;
    letter-spacing: 0.32em;
    text-transform: uppercase;
    color: var(--color-base-1);
    opacity: 0.5;
}

.idol-cta-title {
    font-size: 1.4rem;
    font-weight: 800;
    color: rgba(255, 255, 255, 0.92);
    margin: 0;
    line-height: 1.15;
    letter-spacing: -0.02em;
}

.idol-cta-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.55rem;
}

.idol-cta-tag {
    font-size: 0.7rem;
    font-weight: 500;
    letter-spacing: 0.05em;
    color: var(--color-base-1);
    opacity: 0.8;
    background: color-mix(in srgb, var(--color-base-1), transparent 92%);
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 80%);
    border-radius: var(--profile-border-radius, 8px);
    padding: 0.2rem 0.55rem;
    box-shadow: inset 0 1px 0 color-mix(in srgb, var(--color-base-1), transparent 92%);
}

.idol-cta-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    padding: 0.65rem 1.35rem;
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 70%);
    border-radius: var(--profile-border-radius, 8px);
    background: color-mix(in srgb, var(--color-base-1), transparent 91%);
    color: var(--color-base-1);
    font-size: 0.74rem;
    font-weight: 600;
    letter-spacing: 0.13em;
    text-transform: uppercase;
    text-decoration: none;
    white-space: nowrap;
    box-shadow:
        inset 0 1px 0 color-mix(in srgb, var(--color-base-1), transparent 90%),
        0 2px 12px color-mix(in srgb, var(--color-base-1), transparent 92%);
    transition:
        background 0.2s,
        border-color 0.2s,
        color 0.2s,
        box-shadow 0.2s,
        transform 0.15s;
}

.idol-cta-btn:hover {
    background: color-mix(in srgb, var(--color-base-1), transparent 84%);
    border-color: color-mix(in srgb, var(--color-base-1), transparent 45%);
    color: color-mix(in srgb, var(--color-base-1), white 20%);
    box-shadow:
        inset 0 1px 0 color-mix(in srgb, var(--color-base-1), transparent 85%),
        0 0 20px color-mix(in srgb, var(--color-base-1), transparent 82%),
        0 4px 18px rgba(0, 0, 0, 0.25);
    transform: translateY(-1px);
}

@media (max-width: 600px) {
    .idol-cta-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.25rem;
        padding: 1.5rem;
        min-height: auto;
    }
    .idol-cta-title {
        font-size: 1.15rem;
    }
}
</style>
