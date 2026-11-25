<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import { ArrowLeft, Warning, Check, Close, User } from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ report: Object });

const REASON_LABELS = {
    spam:          'Спам',
    inappropriate: 'Неприемлемый контент',
    fraud:         'Мошенничество',
    harassment:    'Домогательства',
    other:         'Другое',
};

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}

const adminNote = ref('');
const showAction = ref(null); // 'review' | 'dismiss'
const submitting = ref(false);

function submit() {
    submitting.value = true;
    const routeName = showAction.value === 'review' ? 'admin.reports.review' : 'admin.reports.dismiss';
    router.patch(route(routeName, props.report.id), { admin_note: adminNote.value || null }, {
        onSuccess: () => { showAction.value = null; },
        onFinish: () => { submitting.value = false; },
    });
}

function getBadgeVariant(status) {
    switch (status) {
        case 'pending': return 'warning';
        case 'reviewed': return 'success';
        case 'dismissed': return 'neutral';
        default: return 'neutral';
    }
}
</script>

<template>
    <div class="space-y-6 max-w-4xl">
        <!-- Top Navigation -->
        <div class="flex items-center gap-3 border-b border-zinc-800 pb-5">
            <Link
                :href="route('admin.reports.index')"
                class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition"
            >
                <el-icon :size="16"><ArrowLeft /></el-icon>
            </Link>
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-xl font-bold text-zinc-100 tracking-tight">Жалоба #{{ report.id }}</h1>
                    <AdminBadge :variant="getBadgeVariant(report.status)" size="sm">
                        {{ report.status === 'pending' ? 'Ожидает решения' : (report.status === 'reviewed' ? 'Рассмотрена' : 'Отклонена') }}
                    </AdminBadge>
                </div>
                <p class="text-xs text-zinc-400 font-mono mt-0.5">Подана {{ formatDate(report.created_at) }}</p>
            </div>
        </div>

        <!-- Participants Card -->
        <div class="p-5 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm space-y-4">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Участники инцидента</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <!-- Reporter -->
                <div class="p-3.5 rounded-lg border border-zinc-800 bg-zinc-950 flex items-center justify-between">
                    <div>
                        <span class="text-[11px] text-zinc-500 block mb-1">Отправитель жалобы:</span>
                        <Link
                            v-if="report.reporter"
                            :href="route('admin.users.show', report.reporter.id)"
                            class="font-semibold text-zinc-100 hover:text-violet-400 transition"
                        >
                            {{ report.reporter.name }}
                        </Link>
                        <span v-else class="text-zinc-600">—</span>
                        <div class="text-[11px] text-zinc-500 font-mono">{{ report.reporter?.email }}</div>
                    </div>
                    <Link
                        v-if="report.reporter"
                        :href="route('admin.users.show', report.reporter.id)"
                        class="text-xs text-violet-400 hover:underline"
                    >
                        Профиль ↗
                    </Link>
                </div>

                <!-- Reported -->
                <div class="p-3.5 rounded-lg border border-zinc-800 bg-zinc-950 flex items-center justify-between">
                    <div>
                        <span class="text-[11px] text-zinc-500 block mb-1">На кого подана жалоба:</span>
                        <Link
                            v-if="report.reported"
                            :href="route('admin.users.show', report.reported.id)"
                            class="font-semibold text-rose-300 hover:text-rose-200 transition"
                        >
                            {{ report.reported.name }}
                        </Link>
                        <span v-else class="text-zinc-600">—</span>
                        <div class="text-[11px] text-zinc-500 font-mono">{{ report.reported?.email }}</div>
                    </div>
                    <Link
                        v-if="report.reported"
                        :href="route('admin.users.show', report.reported.id)"
                        class="text-xs text-violet-400 hover:underline"
                    >
                        Профиль ↗
                    </Link>
                </div>
            </div>

            <div class="pt-2 flex items-center justify-between text-xs text-zinc-400">
                <span>Категория нарушения:</span>
                <AdminBadge variant="warning" size="md">
                    {{ REASON_LABELS[report.reason] || report.reason }}
                </AdminBadge>
            </div>
        </div>

        <!-- Description Details -->
        <div class="p-5 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm space-y-2">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Суть обращения / Описание</h2>
            <div class="p-3 rounded-lg bg-zinc-950 border border-zinc-800 text-xs text-zinc-200 leading-relaxed whitespace-pre-wrap">
                {{ report.details || 'Дополнительные пояснения не указаны.' }}
            </div>
        </div>

        <!-- Admin Note if Reviewed -->
        <div v-if="report.admin_note" class="p-5 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm space-y-2">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Заметка модератора</h2>
            <div class="p-3 rounded-lg bg-zinc-950 border border-zinc-800 text-xs text-zinc-300 leading-relaxed">
                {{ report.admin_note }}
            </div>
            <div v-if="report.reviewer" class="text-[11px] text-zinc-500 font-mono pt-1">
                Рассмотрел: {{ report.reviewer.name }} • {{ formatDate(report.reviewed_at) }}
            </div>
        </div>

        <!-- Actions Panel (if pending) -->
        <div v-if="report.status === 'pending'" class="p-5 rounded-xl border border-violet-800/40 bg-violet-950/20 backdrop-blur-sm space-y-4">
            <h2 class="text-xs font-semibold text-violet-300">Принятие решения по жалобе</h2>

            <div v-if="!showAction" class="flex items-center gap-3">
                <AdminButton variant="primary" size="sm" @click="showAction = 'review'">
                    Принять жалобу (рассмотрено)
                </AdminButton>
                <AdminButton variant="outline" size="sm" @click="showAction = 'dismiss'">
                    Отклонить жалобу
                </AdminButton>
            </div>

            <div v-else class="space-y-3 text-xs">
                <div class="space-y-1">
                    <label class="font-medium text-zinc-300">Заметка администратора (необязательно)</label>
                    <textarea
                        v-model="adminNote"
                        rows="3"
                        class="w-full px-3 py-2 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                        placeholder="Укажите комментарий для аудита..."
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <AdminButton variant="outline" size="sm" @click="showAction = null">
                        Отмена
                    </AdminButton>
                    <AdminButton
                        :variant="showAction === 'review' ? 'primary' : 'destructive'"
                        size="sm"
                        :loading="submitting"
                        @click="submit"
                    >
                        {{ showAction === 'review' ? 'Подтвердить рассмотрение' : 'Подтвердить отклонение' }}
                    </AdminButton>
                </div>
            </div>
        </div>
    </div>
</template>
