<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import { Warning, Check, Close, Document } from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    reports:       Object,
    filter_status: String,
});

const REASON_LABELS = {
    spam:          'Спам',
    inappropriate: 'Неприемлемый контент',
    fraud:         'Мошенничество',
    harassment:    'Домогательства',
    other:         'Другое',
};

const STATUS_TABS = [
    { key: '', label: 'Все' },
    { key: 'pending', label: 'Ожидают' },
    { key: 'reviewed', label: 'Рассмотрены' },
    { key: 'dismissed', label: 'Отклонены' },
];

function switchStatus(status) {
    router.get(route('admin.reports.index'), { status: status || undefined }, { preserveState: false });
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}

// Review / Dismiss modals
const showModal    = ref(false);
const modalAction  = ref('review');
const modalTarget  = ref(null);
const adminNote    = ref('');
const submitting   = ref(false);

function openActionModal(report, action) {
    modalTarget.value  = report;
    modalAction.value  = action;
    adminNote.value    = '';
    showModal.value    = true;
}

function closeModal() {
    showModal.value = false;
}

function submitModal() {
    submitting.value = true;
    const routeName = modalAction.value === 'review' ? 'admin.reports.review' : 'admin.reports.dismiss';
    router.patch(route(routeName, modalTarget.value.id), {
        admin_note: adminNote.value || null,
    }, {
        preserveScroll: false,
        onSuccess: closeModal,
        onFinish: () => { submitting.value = false; },
    });
}

function getReportBadgeVariant(status) {
    switch (status) {
        case 'pending': return 'warning';
        case 'reviewed': return 'success';
        case 'dismissed': return 'neutral';
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
                    <span>Жалобы пользователей</span>
                </h1>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Модерация пользовательских репортов на спам, мошенничество и неприемлемый контент
                </p>
            </div>
        </div>

        <!-- Status tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
            <button
                v-for="t in STATUS_TABS"
                :key="t.key"
                class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                :class="(filter_status ?? '') === t.key
                    ? 'border-violet-500/50 bg-violet-500/15 text-violet-300'
                    : 'border-zinc-800 bg-zinc-900/60 text-zinc-400 hover:text-zinc-200'"
                @click="switchStatus(t.key)"
            >
                {{ t.label }}
            </button>
        </div>

        <!-- Table -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 overflow-hidden backdrop-blur-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400">
                            <th class="py-3 px-4 font-medium">Отправитель</th>
                            <th class="py-3 px-4 font-medium">На пользователя</th>
                            <th class="py-3 px-4 font-medium">Причина</th>
                            <th class="py-3 px-4 font-medium">Описание</th>
                            <th class="py-3 px-4 font-medium">Статус</th>
                            <th class="py-3 px-4 font-medium">Дата</th>
                            <th class="py-3 px-4 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                        <tr v-if="!reports.data?.length">
                            <td colspan="7" class="py-12 text-center text-zinc-500">
                                Жалоб в выбранной категории нет
                            </td>
                        </tr>
                        <tr
                            v-for="r in reports.data"
                            :key="r.id"
                            class="hover:bg-zinc-800/30 transition cursor-pointer"
                            @click="router.visit(route('admin.reports.show', r.id))"
                        >
                            <!-- Reporter -->
                            <td class="py-3 px-4" @click.stop>
                                <Link
                                    v-if="r.reporter"
                                    :href="route('admin.users.show', r.reporter.id)"
                                    class="font-medium text-zinc-200 hover:text-violet-400 block"
                                >
                                    {{ r.reporter.name }}
                                </Link>
                                <span class="text-[11px] text-zinc-500 font-mono">{{ r.reporter?.email || '—' }}</span>
                            </td>

                            <!-- Reported User -->
                            <td class="py-3 px-4" @click.stop>
                                <Link
                                    v-if="r.reported"
                                    :href="route('admin.users.show', r.reported.id)"
                                    class="font-medium text-zinc-200 hover:text-violet-400 block"
                                >
                                    {{ r.reported.name }}
                                </Link>
                                <span class="text-[11px] text-zinc-500 font-mono">{{ r.reported?.email || '—' }}</span>
                            </td>

                            <!-- Reason -->
                            <td class="py-3 px-4">
                                <AdminBadge variant="warning" size="sm">
                                    {{ REASON_LABELS[r.reason] || r.reason }}
                                </AdminBadge>
                            </td>

                            <!-- Details -->
                            <td class="py-3 px-4 max-w-[200px] truncate text-zinc-300" :title="r.details">
                                {{ r.details || '—' }}
                            </td>

                            <!-- Status -->
                            <td class="py-3 px-4">
                                <AdminBadge :variant="getReportBadgeVariant(r.status)" size="sm">
                                    {{ r.status === 'pending' ? 'Ожидает' : (r.status === 'reviewed' ? 'Рассмотрена' : 'Отклонена') }}
                                </AdminBadge>
                            </td>

                            <!-- Created At -->
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-500 whitespace-nowrap">
                                {{ formatDate(r.created_at) }}
                            </td>

                            <!-- Actions -->
                            <td class="py-3 px-4 text-right whitespace-nowrap" @click.stop>
                                <div class="flex items-center justify-end gap-1.5">
                                    <Link
                                        :href="route('admin.reports.show', r.id)"
                                        class="px-2 py-1 rounded border border-zinc-800 bg-zinc-900 text-zinc-300 hover:text-white text-[11px] transition"
                                    >
                                        Обзор →
                                    </Link>
                                    <template v-if="r.status === 'pending'">
                                        <button
                                            type="button"
                                            class="px-2 py-1 rounded border border-emerald-900/40 bg-emerald-950/20 text-emerald-400 hover:bg-emerald-900/40 text-[11px] transition"
                                            @click="openActionModal(r, 'review')"
                                        >
                                            Принять
                                        </button>
                                        <button
                                            type="button"
                                            class="px-2 py-1 rounded border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-200 text-[11px] transition"
                                            @click="openActionModal(r, 'dismiss')"
                                        >
                                            Отклонить
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t border-zinc-800">
                <AdminPagination :pagination="reports" />
            </div>
        </div>

        <!-- Action Modal -->
        <AdminModal
            :show="showModal"
            :title="modalAction === 'review' ? 'Подтверждение рассмотрения жалобы' : 'Отклонение жалобы'"
            @close="closeModal"
        >
            <div class="space-y-4 text-xs">
                <div class="space-y-1.5">
                    <label class="block font-medium text-zinc-300">Заметка администратора (необязательно)</label>
                    <textarea
                        v-model="adminNote"
                        rows="3"
                        class="w-full px-3 py-2 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                        placeholder="Укажите комментарий для аудита..."
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-800">
                    <AdminButton variant="outline" size="sm" type="button" @click="closeModal">
                        Отмена
                    </AdminButton>
                    <AdminButton
                        :variant="modalAction === 'review' ? 'primary' : 'destructive'"
                        size="sm"
                        :loading="submitting"
                        @click="submitModal"
                    >
                        {{ modalAction === 'review' ? 'Подтвердить рассмотрение' : 'Отклонить жалобу' }}
                    </AdminButton>
                </div>
            </div>
        </AdminModal>
    </div>
</template>
