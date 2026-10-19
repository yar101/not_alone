<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    count: {
        type: [Number, String],
        default: null,
    },
});
</script>

<template>
    <div class="admin-page-header">
        <div class="header-main">
            <div class="header-title-wrap">
                <h1 class="header-title">{{ title }}</h1>
                <span v-if="count !== null && count !== undefined" class="header-count-badge">
                    {{ count }}
                </span>
                <slot name="badge" />
            </div>
            <p v-if="subtitle" class="header-subtitle">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="header-actions">
            <slot name="actions" />
        </div>
    </div>
</template>

<style scoped>
.admin-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.25rem;
    margin-bottom: 1.75rem;
    flex-wrap: wrap;
}

.header-main {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.header-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.header-title {
    margin: 0;
    font-size: 1.65rem;
    font-weight: 700;
    color: #fff;
    letter-spacing: -0.02em;
}

.header-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.2rem 0.6rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #c084fc;
    background: rgba(192, 132, 252, 0.12);
    border: 1px solid rgba(192, 132, 252, 0.25);
    border-radius: 9999px;
}

.header-subtitle {
    margin: 0;
    font-size: 0.88rem;
    color: rgba(255, 255, 255, 0.5);
    line-height: 1.4;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}
</style>
