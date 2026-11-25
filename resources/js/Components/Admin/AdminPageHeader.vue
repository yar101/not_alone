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
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-5 border-b border-admin-theme">
        <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold tracking-tight text-zinc-100">
                    {{ title }}
                </h1>
                <span
                    v-if="count !== null && count !== undefined"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-admin-primary-light text-admin-accent border border-admin-primary shadow-sm"
                >
                    {{ count }}
                </span>
                <slot name="badge" />
            </div>
            <p v-if="subtitle" class="mt-1 text-sm text-zinc-400">
                {{ subtitle }}
            </p>
        </div>

        <div v-if="$slots.actions" class="flex items-center gap-2.5 flex-wrap shrink-0">
            <slot name="actions" />
        </div>
    </div>
</template>
