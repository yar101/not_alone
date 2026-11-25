<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    active: {
        type: String,
        default: 'moderation', // moderation | change-requests | categories | price-limits | time-units
    },
});

const page = usePage();
const pendingServicesCount = computed(() => page.props.pending_services_count ?? 0);
const pendingServiceChangesCount = computed(() => page.props.pending_service_changes_count ?? 0);
</script>

<template>
    <div class="flex items-center gap-2 border-b border-zinc-800 pb-3 mb-6 overflow-x-auto select-none">
        <!-- 1. Модерация -->
        <Link
            :href="route('admin.services.moderation.index')"
            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-2 shrink-0"
            :class="[
                active === 'moderation' || route().current('admin.services.moderation.*')
                    ? 'bg-violet-600 text-white shadow-sm'
                    : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60'
            ]"
        >
            <span>Модерация услуг</span>
            <span
                v-if="pendingServicesCount > 0"
                class="inline-flex items-center justify-center px-1.5 py-0.2 text-[10px] font-bold rounded-full"
                :class="active === 'moderation' ? 'bg-white/20 text-white' : 'bg-violet-950 text-violet-300 border border-violet-800'"
            >
                {{ pendingServicesCount }}
            </span>
        </Link>

        <!-- 2. Запросы изменений -->
        <Link
            :href="route('admin.services.change-requests.index')"
            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-2 shrink-0"
            :class="[
                active === 'change-requests' || route().current('admin.services.change-requests.*')
                    ? 'bg-violet-600 text-white shadow-sm'
                    : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60'
            ]"
        >
            <span>Запросы на изменения</span>
            <span
                v-if="pendingServiceChangesCount > 0"
                class="inline-flex items-center justify-center px-1.5 py-0.2 text-[10px] font-bold rounded-full"
                :class="active === 'change-requests' ? 'bg-white/20 text-white' : 'bg-violet-950 text-violet-300 border border-violet-800'"
            >
                {{ pendingServiceChangesCount }}
            </span>
        </Link>

        <!-- 3. Категории -->
        <Link
            :href="route('admin.services.categories.index')"
            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors shrink-0"
            :class="[
                active === 'categories' || route().current('admin.services.categories.*')
                    ? 'bg-violet-600 text-white shadow-sm'
                    : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60'
            ]"
        >
            Категории
        </Link>

        <!-- 4. Лимиты цен -->
        <Link
            :href="route('admin.services.price-limits.index')"
            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors shrink-0"
            :class="[
                active === 'price-limits' || route().current('admin.services.price-limits.*')
                    ? 'bg-violet-600 text-white shadow-sm'
                    : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60'
            ]"
        >
            Лимиты цен
        </Link>

        <!-- 5. Единицы времени -->
        <Link
            :href="route('admin.services.time-units.index')"
            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors shrink-0"
            :class="[
                active === 'time-units' || route().current('admin.services.time-units.*')
                    ? 'bg-violet-600 text-white shadow-sm'
                    : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60'
            ]"
        >
            Единицы времени
        </Link>
    </div>
</template>
