<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    active: {
        type: String,
        default: 'moderation', // moderation | change-requests
    },
});

const page = usePage();
const pendingContentPacksCount = computed(() => page.props.pending_content_packs_count ?? 0);
const pendingContentPackChangesCount = computed(() => page.props.pending_content_pack_changes_count ?? 0);
</script>

<template>
    <div class="flex items-center gap-2 border-b border-zinc-800 pb-3 mb-6 overflow-x-auto select-none">
        <!-- 1. Модерация контент-паков -->
        <Link
            :href="route('admin.content-packs.index')"
            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-2 shrink-0"
            :class="[
                active === 'moderation' || route().current('admin.content-packs.index') || route().current('admin.content-packs.show')
                    ? 'bg-violet-600 text-white shadow-sm'
                    : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60'
            ]"
        >
            <span>Модерация паков</span>
            <span
                v-if="pendingContentPacksCount > 0"
                class="inline-flex items-center justify-center px-1.5 py-0.2 text-[10px] font-bold rounded-full"
                :class="active === 'moderation' ? 'bg-white/20 text-white' : 'bg-violet-950 text-violet-300 border border-violet-800'"
            >
                {{ pendingContentPacksCount }}
            </span>
        </Link>

        <!-- 2. Запросы на изменения -->
        <Link
            :href="route('admin.content-packs.change-requests.index')"
            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-2 shrink-0"
            :class="[
                active === 'change-requests' || route().current('admin.content-packs.change-requests.*')
                    ? 'bg-violet-600 text-white shadow-sm'
                    : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60'
            ]"
        >
            <span>Запросы на изменения</span>
            <span
                v-if="pendingContentPackChangesCount > 0"
                class="inline-flex items-center justify-center px-1.5 py-0.2 text-[10px] font-bold rounded-full"
                :class="active === 'change-requests' ? 'bg-white/20 text-white' : 'bg-violet-950 text-violet-300 border border-violet-800'"
            >
                {{ pendingContentPackChangesCount }}
            </span>
        </Link>
    </div>
</template>
