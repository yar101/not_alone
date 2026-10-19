<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    badge: {
        type: String,
        default: '',
    },
    badgeType: {
        type: String,
        default: 'primary',
    },
    accent: {
        type: String,
        default: 'primary',
    },
    href: {
        type: String,
        default: null,
    },
});

const isLink = computed(() => !!props.href);
</script>

<template>
    <component
        :is="isLink ? Link : 'div'"
        :href="href"
        class="admin-stats-card"
        :class="[`admin-stats-card--${accent}`, { 'admin-stats-card--clickable': isLink }]"
    >
        <div class="card-top">
            <span class="card-title">{{ title }}</span>
            <span v-if="badge" class="card-badge" :class="`badge--${badgeType}`">
                {{ badge }}
            </span>
            <slot name="extra" />
        </div>
        <div class="card-main">
            <div class="card-value">
                {{ typeof value === 'number' ? value.toLocaleString('ru') : value }}
            </div>
            <slot name="icon" />
        </div>
        <div v-if="subtitle || $slots.footer" class="card-footer">
            <span v-if="subtitle" class="card-subtitle">{{ subtitle }}</span>
            <slot name="footer" />
        </div>
    </component>
</template>

<style scoped>
.admin-stats-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 1.15rem 1.25rem;
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(255, 255, 255, 0.08);
    position: relative;
    overflow: hidden;
    text-decoration: none;
    transition: all 0.2s ease;
}

.admin-stats-card--clickable {
    cursor: pointer;
}

.admin-stats-card--clickable:hover {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(192, 132, 252, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}

.admin-stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 3px;
    height: 100%;
}

.admin-stats-card--primary::before { background: #9b6ee8; }
.admin-stats-card--success::before { background: #10b981; }
.admin-stats-card--warning::before { background: #f59e0b; }
.admin-stats-card--danger::before { background: #ef4444; }
.admin-stats-card--info::before { background: #3b82f6; }

.card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.65rem;
}

.card-title {
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(255, 255, 255, 0.5);
}

.card-badge {
    padding: 0.15rem 0.5rem;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    border-radius: 4px;
}

.badge--primary { background: rgba(155, 110, 232, 0.15); color: #c084fc; }
.badge--success { background: rgba(16, 185, 129, 0.15); color: #34d399; }
.badge--warning { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
.badge--danger  { background: rgba(239, 68, 68, 0.15); color: #f87171; }

.card-main {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
}

.card-value {
    font-size: 1.85rem;
    font-weight: 700;
    color: #fff;
    letter-spacing: -0.02em;
    line-height: 1.1;
}

.card-footer {
    margin-top: 0.65rem;
    padding-top: 0.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.04);
}

.card-subtitle {
    font-size: 0.76rem;
    color: rgba(255, 255, 255, 0.4);
}
</style>
