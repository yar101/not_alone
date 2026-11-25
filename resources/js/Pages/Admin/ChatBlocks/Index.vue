<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import { ChatDotRound, Lock, User, Warning } from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    blocks:      Object,
    total_count: { type: Number, default: 0 },
    top_blocked: Array,
});

function formatDate(dt) {
    if (!dt) return 'Бессрочно';
    return new Date(dt).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function goToBan(userId) {
    router.visit(route('admin.users.show', userId));
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-100 flex items-center gap-2.5">
                    <el-icon class="text-rose-400"><ChatDotRound /></el-icon>
                    <span>Блокировки в чате</span>
                </h1>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Мониторинг персональных и взаимных блокировок между пользователями платформы. Всего записей: <span class="font-mono text-zinc-200">{{ (blocks?.total ?? total_count ?? 0).toLocaleString() }}</span>
                </p>
            </div>
        </div>

        <!-- Top Blocked Users Leaderboard -->
        <div class="p-5 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm space-y-3">
            <h2 class="text-sm font-semibold text-zinc-100 flex items-center gap-2">
                <el-icon class="text-amber-400"><Warning /></el-icon>
                <span>Топ часто блокируемых пользователей (кандидаты на проверку/бан)</span>
            </h2>

            <div v-if="!top_blocked || top_blocked.length === 0" class="py-4 text-xs text-zinc-500">
                Нет пользователей с частыми блокировками.
            </div>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div
                    v-for="(row, i) in top_blocked"
                    :key="row.user.id"
                    class="p-3 rounded-lg border border-zinc-800/80 bg-zinc-950/60 flex items-center justify-between"
                >
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="text-xs font-mono font-bold text-zinc-500">#{{ i + 1 }}</span>
                        <img
                            v-if="row.user.avatar_url"
                            :src="row.user.avatar_url"
                            class="w-7 h-7 rounded-full object-cover border border-zinc-700"
                        />
                        <div v-else class="w-7 h-7 rounded-full bg-zinc-800 text-zinc-300 font-bold flex items-center justify-center text-xs">
                            {{ row.user.name?.charAt(0) ?? '?' }}
                        </div>
                        <div class="min-w-0">
                            <Link
                                :href="route('admin.users.show', row.user.id)"
                                class="text-xs font-semibold text-zinc-200 hover:text-violet-400 truncate block max-w-[120px]"
                            >
                                {{ row.user.name }}
                            </Link>
                            <span class="text-[11px] font-mono text-rose-400 font-semibold">{{ row.block_count }} блокировок</span>
                        </div>
                    </div>

                    <AdminButton variant="outline" size="sm" @click="goToBan(row.user.id)">
                        Профиль
                    </AdminButton>
                </div>
            </div>
        </div>

        <!-- Blocks Table -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 overflow-hidden backdrop-blur-sm">
            <div class="p-4 border-b border-zinc-800 flex items-center justify-between">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Активные блокировки пользователей</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400">
                            <th class="py-3 px-4 font-medium">Кто заблокировал</th>
                            <th class="py-3 px-4 font-medium">Кого заблокировал</th>
                            <th class="py-3 px-4 font-medium">Причина</th>
                            <th class="py-3 px-4 font-medium">Срок блокировки</th>
                            <th class="py-3 px-4 font-medium">Дата создания</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                        <tr v-if="!blocks.data.length">
                            <td colspan="5" class="py-12 text-center text-zinc-500">
                                Активных блокировок в чате нет
                            </td>
                        </tr>
                        <tr
                            v-for="block in blocks.data"
                            :key="block.id"
                            class="hover:bg-zinc-800/30 transition"
                        >
                            <!-- Blocker -->
                            <td class="py-3 px-4">
                                <Link
                                    v-if="block.blocker"
                                    :href="route('admin.users.show', block.blocker_id)"
                                    class="text-zinc-200 hover:text-violet-400 font-medium"
                                >
                                    {{ block.blocker.name }}
                                </Link>
                                <span v-else class="text-zinc-600">—</span>
                            </td>

                            <!-- Blocked -->
                            <td class="py-3 px-4">
                                <Link
                                    v-if="block.blocked"
                                    :href="route('admin.users.show', block.blocked_id)"
                                    class="text-zinc-200 hover:text-violet-400 font-medium"
                                >
                                    {{ block.blocked.name }}
                                </Link>
                                <span v-else class="text-zinc-600">—</span>
                            </td>

                            <!-- Reason -->
                            <td class="py-3 px-4 text-zinc-300">
                                {{ block.reason || '—' }}
                            </td>

                            <!-- Until -->
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-400">
                                {{ formatDate(block.blocked_until) }}
                            </td>

                            <!-- Created At -->
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-500">
                                {{ formatDate(block.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t border-zinc-800">
                <AdminPagination :pagination="blocks" />
            </div>
        </div>
    </div>
</template>
