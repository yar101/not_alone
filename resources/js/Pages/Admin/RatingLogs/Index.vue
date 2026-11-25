<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    logs:   Object,
    idols:  { type: Array, default: () => [] },
    filter: Object,
});

const EVENT_LABELS = {
    review_5star:            'Отзыв 5★',
    review_4star:            'Отзыв 4★',
    review_3star:            'Отзыв 3★',
    review_2star:            'Отзыв 2★',
    review_1star:            'Отзыв 1★',
    order_completed:         'Заказ выполнен',
    order_refunded:          'Возврат по заказу (спор)',
    strike:                  'Вынесен страйк',
    report_accepted:         'Репорт принят',
    review_dispute_approved: 'Спор выигран',
    admin_manual:            'Ручное изменение',
};

const idolId   = ref(props.filter?.idol_id   ?? '');
const dateFrom = ref(props.filter?.date_from ?? '');
const dateTo   = ref(props.filter?.date_to   ?? '');

function applyFilter() {
    router.get(route('admin.rating-logs.index'), {
        idol_id:   idolId.value   || undefined,
        date_from: dateFrom.value || undefined,
        date_to:   dateTo.value   || undefined,
    }, { preserveState: false });
}

function resetFilter() {
    idolId.value   = '';
    dateFrom.value = '';
    dateTo.value   = '';
    router.get(route('admin.rating-logs.index'), {}, { preserveState: false });
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <AdminPageHeader
            title="Журнал рейтинга айдолов"
            subtitle="Детальная история начислений и штрафных списаний рейтинга по выполненным заказам, отзывам и спорам"
            :count="logs.total"
        />

        <!-- Filters Bar -->
        <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl p-4 shadow-sm">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-[11px] font-semibold uppercase text-zinc-400 mb-1">Айдол</label>
                    <select
                        v-model="idolId"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs text-zinc-200 outline-none focus:border-violet-500"
                        @change="applyFilter"
                    >
                        <option value="">Все айдолы</option>
                        <option v-for="idol in idols" :key="idol.id" :value="idol.id">{{ idol.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold uppercase text-zinc-400 mb-1">Дата от</label>
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs text-zinc-200 outline-none focus:border-violet-500"
                        @change="applyFilter"
                    />
                </div>

                <div>
                    <label class="block text-[11px] font-semibold uppercase text-zinc-400 mb-1">Дата до</label>
                    <input
                        v-model="dateTo"
                        type="date"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs text-zinc-200 outline-none focus:border-violet-500"
                        @change="applyFilter"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <AdminButton variant="primary" size="sm" class="flex-1" @click="applyFilter">
                        Применить
                    </AdminButton>
                    <AdminButton variant="outline" size="sm" @click="resetFilter">
                        Сбросить
                    </AdminButton>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-zinc-950/60 text-zinc-400 font-semibold uppercase tracking-wider border-b border-zinc-800">
                        <tr>
                            <th class="px-4 py-3">Айдол</th>
                            <th class="px-4 py-3">Событие</th>
                            <th class="px-4 py-3">Дельта</th>
                            <th class="px-4 py-3">Примечание</th>
                            <th class="px-4 py-3 text-right">Дата</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60">
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-zinc-800/20 transition-colors">
                            <td class="px-4 py-3">
                                <Link
                                    v-if="log.idol"
                                    :href="route('admin.users.show', log.idol.id)"
                                    class="font-medium text-zinc-200 hover:text-violet-400 transition-colors"
                                >
                                    {{ log.idol.name }}
                                </Link>
                                <span v-else class="text-zinc-500">—</span>
                            </td>

                            <td class="px-4 py-3 text-zinc-300">
                                {{ EVENT_LABELS[log.event] ?? log.event }}
                            </td>

                            <td class="px-4 py-3 font-mono font-bold">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs"
                                    :class="log.delta > 0
                                        ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                        : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'"
                                >
                                    {{ log.delta > 0 ? '+' : '' }}{{ log.delta }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-zinc-400 max-w-sm truncate" :title="log.note ?? ''">
                                {{ log.note ?? '—' }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono text-zinc-500 whitespace-nowrap">
                                {{ formatDate(log.created_at) }}
                            </td>
                        </tr>

                        <tr v-if="!logs.data.length">
                            <td colspan="5" class="px-4 py-12 text-center text-xs text-zinc-500 italic">
                                Записей изменения рейтинга не найдено.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AdminPagination :pagination="logs" />
    </div>
</template>
