<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminDrawer from '@/Components/Admin/UI/AdminDrawer.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import { Warning, Check, Close, Search, Star, ChatDotRound } from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    disputes:      Object,
    status_filter: String,
    search:        String,
    counts:        Object,
});

const selected    = ref(null);
const isDrawerOpen = ref(false);
const decision    = ref('');
const adminNote   = ref('');
const submitting  = ref(false);
const searchInput = ref(props.search ?? '');

function openDrawer(dispute) {
    selected.value     = dispute;
    decision.value     = '';
    adminNote.value    = '';
    isDrawerOpen.value = true;
}

function closeDrawer() {
    isDrawerOpen.value = false;
    selected.value     = null;
}

function setFilter(val) {
    router.get(route('admin.review-disputes.index'), { status: val, search: searchInput.value }, { preserveState: false });
}

function doSearch() {
    router.get(route('admin.review-disputes.index'), { status: props.status_filter, search: searchInput.value }, { preserveState: false });
}

function submitResolve() {
    if (!decision.value || submitting.value) return;
    submitting.value = true;
    router.patch(
        route('admin.review-disputes.resolve', selected.value.id),
        { decision: decision.value, admin_note: adminNote.value },
        {
            preserveScroll: true,
            onFinish:  () => { submitting.value = false; },
            onSuccess: () => { closeDrawer(); },
        }
    );
}

const STATUS_LABELS = { pending: 'Ожидает', approved: 'Одобрено (скрыт)', rejected: 'Отклонено' };

function getBadgeVariant(status) {
    switch (status) {
        case 'pending': return 'warning';
        case 'approved': return 'success';
        case 'rejected': return 'danger';
        default: return 'neutral';
    }
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-100 flex items-center gap-2.5">
                    <el-icon class="text-amber-400"><Warning /></el-icon>
                    <span>Жалобы айдолов на отзывы</span>
                </h1>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Модерация претензий к клевете, нецензурной лексике и необоснованным оценкам в отзывах
                </p>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="flex items-center gap-1.5 overflow-x-auto text-xs">
                <button
                    v-for="tab in [
                        { key: 'pending',  label: 'Ожидают',    count: counts.pending },
                        { key: 'approved', label: 'Одобрены',   count: counts.approved },
                        { key: 'rejected', label: 'Отклонены',  count: counts.rejected },
                        { key: 'all',      label: 'Все',        count: counts.all ?? (counts.pending + counts.approved + counts.rejected) },
                    ]"
                    :key="tab.key"
                    class="px-3 py-1.5 rounded-lg border text-xs font-medium transition flex items-center gap-2"
                    :class="(status_filter || 'pending') === tab.key
                        ? 'border-violet-500/50 bg-violet-500/15 text-violet-300'
                        : 'border-zinc-800 bg-zinc-900/60 text-zinc-400 hover:text-zinc-200'"
                    @click="setFilter(tab.key)"
                >
                    <span>{{ tab.label }}</span>
                    <span
                        class="px-1.5 py-0.2 rounded text-[10px] font-mono"
                        :class="(status_filter || 'pending') === tab.key ? 'bg-violet-500/30 text-violet-200' : 'bg-zinc-800 text-zinc-500'"
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <div class="relative flex-1 max-w-xs">
                <el-icon class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500"><Search /></el-icon>
                <input
                    v-model="searchInput"
                    type="text"
                    placeholder="Поиск по имени айдола..."
                    class="w-full h-9 pl-9 pr-3 rounded-lg border border-zinc-800 bg-zinc-900/80 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                    @keyup.enter="doSearch"
                />
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 overflow-hidden backdrop-blur-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400">
                            <th class="py-3 px-4 font-medium">#</th>
                            <th class="py-3 px-4 font-medium">Айдол</th>
                            <th class="py-3 px-4 font-medium">Автор отзыва</th>
                            <th class="py-3 px-4 font-medium">Отзыв и оценка</th>
                            <th class="py-3 px-4 font-medium">Причина жалобы</th>
                            <th class="py-3 px-4 font-medium">Статус</th>
                            <th class="py-3 px-4 font-medium">Дата</th>
                            <th class="py-3 px-4 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                        <tr v-if="!disputes.data?.length">
                            <td colspan="8" class="py-12 text-center text-zinc-500">
                                Жалоб на отзывы нет
                            </td>
                        </tr>
                        <tr
                            v-for="d in disputes.data"
                            :key="d.id"
                            class="hover:bg-zinc-800/30 transition cursor-pointer"
                            @click="openDrawer(d)"
                        >
                            <td class="py-3 px-4 font-mono text-zinc-500">#{{ d.id }}</td>

                            <!-- Idol -->
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2">
                                    <img
                                        v-if="d.idol.avatar"
                                        :src="d.idol.avatar"
                                        class="w-6 h-6 rounded-full object-cover border border-pink-500/40"
                                    />
                                    <div v-else class="w-6 h-6 rounded-full bg-pink-900/40 text-pink-300 flex items-center justify-center font-bold text-[10px]">
                                        {{ d.idol.name?.charAt(0) }}
                                    </div>
                                    <span class="text-zinc-200 font-medium truncate max-w-[120px]">{{ d.idol.name }}</span>
                                </div>
                            </td>

                            <!-- Reviewer -->
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2">
                                    <img
                                        v-if="d.review.reviewer.avatar"
                                        :src="d.review.reviewer.avatar"
                                        class="w-6 h-6 rounded-full object-cover border border-zinc-700"
                                    />
                                    <div v-else class="w-6 h-6 rounded-full bg-zinc-800 text-zinc-400 flex items-center justify-center font-bold text-[10px]">
                                        {{ d.review.reviewer.name?.charAt(0) }}
                                    </div>
                                    <span class="text-zinc-200 truncate max-w-[120px]">{{ d.review.reviewer.name }}</span>
                                </div>
                            </td>

                            <!-- Review & Rating -->
                            <td class="py-3 px-4 max-w-[200px]">
                                <div class="flex items-center gap-0.5 text-amber-400 mb-0.5">
                                    <span v-for="i in 5" :key="i" class="text-xs" :class="i <= d.review.rating ? 'text-amber-400' : 'text-zinc-700'">★</span>
                                </div>
                                <div class="truncate text-zinc-300" :title="d.review.text">{{ d.review.text }}</div>
                            </td>

                            <!-- Reason -->
                            <td class="py-3 px-4 max-w-[160px] truncate text-zinc-300" :title="d.reason">
                                {{ d.reason }}
                            </td>

                            <!-- Status -->
                            <td class="py-3 px-4">
                                <AdminBadge :variant="getBadgeVariant(d.status)" size="sm">
                                    {{ STATUS_LABELS[d.status] || d.status }}
                                </AdminBadge>
                            </td>

                            <!-- Date -->
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-500 whitespace-nowrap">
                                {{ d.created_at }}
                            </td>

                            <!-- Actions -->
                            <td class="py-3 px-4 text-right" @click.stop>
                                <button
                                    class="text-violet-400 hover:text-violet-300 font-medium text-xs hover:underline"
                                    @click="openDrawer(d)"
                                >
                                    {{ d.status === 'pending' ? 'Рассмотреть' : 'Детали' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t border-zinc-800">
                <AdminPagination :pagination="disputes" />
            </div>
        </div>

        <!-- ════════════ DRAWER: REVIEW DISPUTE DETAILS ════════════ -->
        <AdminDrawer
            :show="isDrawerOpen"
            :title="`Жалоба на отзыв #${selected?.id || ''}`"
            :subtitle="selected ? `Подана ${selected.created_at}` : ''"
            width="560px"
            @close="closeDrawer"
        >
            <div v-if="selected" class="space-y-5 text-xs">
                <!-- Participants Card -->
                <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-400">Статус модерации:</span>
                        <AdminBadge :variant="getBadgeVariant(selected.status)" size="md">
                            {{ STATUS_LABELS[selected.status] || selected.status }}
                        </AdminBadge>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-3 border-t border-zinc-800">
                        <div>
                            <span class="text-zinc-500 text-[11px]">Жалобу подал (Айдол):</span>
                            <div class="font-semibold text-pink-300 mt-0.5">{{ selected.idol.name }}</div>
                        </div>
                        <div>
                            <span class="text-zinc-500 text-[11px]">Автор отзыва (Клиент):</span>
                            <div class="font-semibold text-zinc-200 mt-0.5">{{ selected.review.reviewer.name }}</div>
                        </div>
                    </div>
                </div>

                <!-- Disputed Review Content -->
                <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">Оспариваемый отзыв</span>
                        <div class="flex items-center gap-0.5 text-amber-400">
                            <span v-for="i in 5" :key="i" class="text-xs" :class="i <= selected.review.rating ? 'text-amber-400' : 'text-zinc-700'">★</span>
                        </div>
                    </div>
                    <div class="p-3 rounded-lg bg-zinc-900 border border-zinc-800 text-zinc-200 leading-relaxed whitespace-pre-wrap">
                        {{ selected.review.text }}
                    </div>
                </div>

                <!-- Reason of Dispute -->
                <div class="p-4 rounded-xl border border-amber-900/40 bg-amber-950/20 space-y-2 text-zinc-200">
                    <div class="text-amber-400 font-semibold text-xs flex items-center gap-1.5">
                        <el-icon><Warning /></el-icon>
                        <span>Причина жалобы айдола</span>
                    </div>
                    <p class="leading-relaxed">{{ selected.reason }}</p>
                </div>

                <!-- Admin note if resolved -->
                <div v-if="selected.status !== 'pending' && selected.admin_note" class="p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 space-y-1.5">
                    <div class="text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">Решение модератора</div>
                    <p class="text-zinc-300">{{ selected.admin_note }}</p>
                </div>

                <!-- Action Form if pending -->
                <div v-if="selected.status === 'pending'" class="p-4 rounded-xl border border-violet-800/40 bg-violet-950/20 space-y-3">
                    <div class="text-xs font-semibold text-violet-300">Принять решение по отзыву</div>

                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            class="p-3 rounded-lg border text-left transition"
                            :class="decision === 'approved'
                                ? 'border-emerald-500 bg-emerald-500/20 text-emerald-300'
                                : 'border-zinc-800 bg-zinc-950 text-zinc-400 hover:text-zinc-200'"
                            @click="decision = 'approved'"
                        >
                            <div class="font-semibold text-xs">Одобрить жалобу</div>
                            <div class="text-[10px] text-zinc-500 mt-1">Отзыв скрывается из публичного профиля, рейтинг пересчитывается</div>
                        </button>

                        <button
                            type="button"
                            class="p-3 rounded-lg border text-left transition"
                            :class="decision === 'rejected'
                                ? 'border-rose-500 bg-rose-500/20 text-rose-300'
                                : 'border-zinc-800 bg-zinc-950 text-zinc-400 hover:text-zinc-200'"
                            @click="decision = 'rejected'"
                        >
                            <div class="font-semibold text-xs">Отклонить жалобу</div>
                            <div class="text-[10px] text-zinc-500 mt-1">Отзыв остаётся без изменений</div>
                        </button>
                    </div>

                    <div class="space-y-1.5 pt-1">
                        <label class="font-medium text-zinc-300">Заметка модератора</label>
                        <textarea
                            v-model="adminNote"
                            rows="2"
                            class="w-full px-3 py-2 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                            placeholder="Укажите обоснование решения..."
                        ></textarea>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <AdminButton variant="outline" size="sm" @click="closeDrawer">
                        Закрыть
                    </AdminButton>
                    <AdminButton
                        v-if="selected?.status === 'pending'"
                        :variant="decision === 'approved' ? 'primary' : 'destructive'"
                        size="sm"
                        :disabled="!decision || submitting"
                        :loading="submitting"
                        @click="submitResolve"
                    >
                        Зафиксировать решение
                    </AdminButton>
                </div>
            </template>
        </AdminDrawer>
    </div>
</template>
