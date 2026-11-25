<script setup>
import { ref, computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import AdminTable from '@/Components/Admin/UI/AdminTable.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminDrawer from '@/Components/Admin/UI/AdminDrawer.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import {
    Check, Close, Download, ZoomIn, View,
    ArrowRight, User, Calendar, Warning
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    applications: Object,
    filter_status: String,
    pending_count: Number,
});

const page = usePage();
const pendingCount = computed(() => {
    if (typeof props.pending_count === 'number') return props.pending_count;
    return page.props.pending_applications_count ?? 0;
});

// ─── Selected Application for Slide-over Drawer ──────────────────────────────
const selectedApp = ref(null);
const drawerOpen = ref(false);

function inspectApplication(app) {
    selectedApp.value = app;
    drawerOpen.value = true;
    rejectMode.value = false;
}

// ─── Lightbox for Face Photo ─────────────────────────────────────────────────
const lightboxUrl = ref(null);

function openLightbox(url) {
    lightboxUrl.value = url;
}

// ─── Approve Action ──────────────────────────────────────────────────────────
const showApproveConfirm = ref(false);
const appToApprove = ref(null);

function confirmApprove(app) {
    appToApprove.value = app;
    showApproveConfirm.value = true;
}

function executeApprove() {
    if (!appToApprove.value) return;
    router.patch(route('admin.applications.approve', appToApprove.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showApproveConfirm.value = false;
            if (selectedApp.value?.id === appToApprove.value.id) {
                selectedApp.value.status = 'approved';
            }
            appToApprove.value = null;
        },
    });
}

// ─── Reject Action ───────────────────────────────────────────────────────────
const rejectMode = ref(false);
const rejectForm = useForm({
    rejection_reason: '',
    reset_quiz: false,
});

const rejectChips = [
    'Не видно лица',
    'Плохое качество фото',
    'Фото не вашего лица',
    'Нарушение правил сервиса',
    'Несоответствие возрасту',
];

function startReject(app) {
    if (!drawerOpen.value || selectedApp.value?.id !== app.id) {
        selectedApp.value = app;
        drawerOpen.value = true;
    }
    rejectMode.value = true;
    rejectForm.rejection_reason = '';
    rejectForm.reset_quiz = false;
}

function executeReject() {
    if (!selectedApp.value) return;
    rejectForm.patch(route('admin.applications.reject', selectedApp.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            rejectMode.value = false;
            if (selectedApp.value) {
                selectedApp.value.status = 'rejected';
                selectedApp.value.rejection_reason = rejectForm.rejection_reason;
            }
        },
    });
}

// ─── Filter Statuses ─────────────────────────────────────────────────────────
function filterBy(status) {
    router.get(
        route('admin.applications.index'),
        { status: status || undefined },
        { preserveState: true, replace: true }
    );
}

const statusVariant = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
};

const statusLabel = {
    pending: 'На рассмотрении',
    approved: 'Одобрена',
    rejected: 'Отклонена',
};
</script>

<template>
    <div class="space-y-6 max-w-7xl mx-auto">
        <!-- Page Header -->
        <AdminPageHeader
            title="Заявки на статус айдола"
            subtitle="Модерация анкет исполнителей, верификация фотографий лица и проверка вступительного квиза"
            :count="applications?.total"
        >
            <template #actions>
                <a
                    :href="route('admin.export.applications', undefined, false)"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-medium rounded-lg bg-zinc-900 border border-zinc-800 hover:border-zinc-700 text-zinc-300 hover:text-zinc-100 transition-colors shadow-sm"
                    download="applications.csv"
                >
                    <el-icon :size="14"><Download /></el-icon>
                    <span>Экспорт CSV</span>
                </a>
            </template>
        </AdminPageHeader>

        <!-- Status Filter Tabs -->
        <div class="flex items-center gap-2 border-b border-zinc-800 pb-3 overflow-x-auto">
            <button
                type="button"
                class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors"
                :class="[!filter_status ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="filterBy('')"
            >
                Все заявки
            </button>
            <button
                type="button"
                class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-2"
                :class="[filter_status === 'pending' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="filterBy('pending')"
            >
                <span>На рассмотрении</span>
                <span v-if="pendingCount > 0" class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse" :title="`${pendingCount} ожидают`" />
            </button>
            <button
                type="button"
                class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors"
                :class="[filter_status === 'approved' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="filterBy('approved')"
            >
                Одобренные
            </button>
            <button
                type="button"
                class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors"
                :class="[filter_status === 'rejected' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="filterBy('rejected')"
            >
                Отклонённые
            </button>
        </div>

        <!-- Applications Data Table -->
        <AdminTable :empty="!applications.data?.length" empty-title="Заявок не найдено">
            <template #header>
                <tr>
                    <th class="py-3 px-4">Пользователь</th>
                    <th class="py-3 px-4">Фото лица</th>
                    <th class="py-3 px-4">Статус</th>
                    <th class="py-3 px-4">Дата подачи</th>
                    <th class="py-3 px-4 text-right">Действия</th>
                </tr>
            </template>

            <tr
                v-for="app in applications.data"
                :key="app.id"
                class="hover:bg-zinc-800/40 transition-colors cursor-pointer group"
                @click="inspectApplication(app)"
            >
                <!-- User -->
                <td class="py-3 px-4">
                    <div class="flex items-center gap-3">
                        <img
                            v-if="app.user?.avatar_url"
                            :src="app.user.avatar_url"
                            class="w-9 h-9 rounded-full object-cover shrink-0 border border-zinc-700/60"
                            alt=""
                        />
                        <div
                            v-else
                            class="w-9 h-9 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center font-bold text-xs text-zinc-300 shrink-0"
                        >
                            {{ app.user?.name?.[0] ?? 'U' }}
                        </div>

                        <div class="min-w-0">
                            <div class="font-semibold text-zinc-100 group-hover:text-violet-300 transition-colors">
                                {{ app.user?.name }}
                            </div>
                            <div class="text-xs text-zinc-400 truncate">
                                {{ app.user?.email }}
                            </div>
                        </div>
                    </div>
                </td>

                <!-- Face Photo Preview -->
                <td class="py-3 px-4" @click.stop>
                    <div class="relative group/thumb inline-block">
                        <img
                            :src="app.face_photo_url"
                            class="w-12 h-12 rounded-lg object-cover border border-zinc-700/80 cursor-zoom-in hover:border-violet-500 transition-colors"
                            alt="Фото лица"
                            @click="openLightbox(app.face_photo_url)"
                        />
                        <div class="absolute inset-0 bg-black/40 rounded-lg flex items-center justify-center opacity-0 group-hover/thumb:opacity-100 transition-opacity pointer-events-none text-white">
                            <el-icon :size="14"><ZoomIn /></el-icon>
                        </div>
                    </div>
                </td>

                <!-- Status -->
                <td class="py-3 px-4">
                    <AdminBadge :variant="statusVariant[app.status] || 'neutral'" dot size="sm">
                        {{ statusLabel[app.status] ?? app.status }}
                    </AdminBadge>
                </td>

                <!-- Date -->
                <td class="py-3 px-4 text-zinc-400 text-xs whitespace-nowrap">
                    {{ new Date(app.created_at).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                </td>

                <!-- Actions -->
                <td class="py-3 px-4 text-right whitespace-nowrap" @click.stop>
                    <div class="flex items-center justify-end gap-2" v-if="app.status === 'pending'">
                        <AdminButton
                            variant="success"
                            size="sm"
                            @click="confirmApprove(app)"
                        >
                            <template #icon><el-icon :size="12"><Check /></el-icon></template>
                            Одобрить
                        </AdminButton>
                        <AdminButton
                            variant="destructive"
                            size="sm"
                            @click="startReject(app)"
                        >
                            <template #icon><el-icon :size="12"><Close /></el-icon></template>
                            Отклонить
                        </AdminButton>
                    </div>

                    <AdminButton
                        v-else
                        variant="secondary"
                        size="sm"
                        @click="inspectApplication(app)"
                    >
                        Детали
                    </AdminButton>
                </td>
            </tr>

            <template #footer>
                <AdminPagination :pagination="applications" />
            </template>
        </AdminTable>

        <!-- ─── Slide-over Drawer для инспекции анкеты ───────────────────────── -->
        <AdminDrawer
            :show="drawerOpen"
            title="Заявка на статус айдола"
            :subtitle="selectedApp ? `Подана ${new Date(selectedApp.created_at).toLocaleString('ru')}` : ''"
            max-width="max-w-xl"
            @close="drawerOpen = false"
        >
            <template #badge v-if="selectedApp">
                <AdminBadge :variant="statusVariant[selectedApp.status] || 'neutral'" dot size="sm">
                    {{ statusLabel[selectedApp.status] ?? selectedApp.status }}
                </AdminBadge>
            </template>

            <div v-if="selectedApp" class="space-y-6">
                <!-- Кандидат -->
                <div class="flex items-center gap-4 p-4 rounded-xl bg-zinc-900 border border-zinc-800">
                    <img
                        v-if="selectedApp.user?.avatar_url"
                        :src="selectedApp.user.avatar_url"
                        class="w-14 h-14 rounded-full object-cover border border-zinc-700 shrink-0"
                        alt=""
                    />
                    <div
                        v-else
                        class="w-14 h-14 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center font-bold text-lg text-zinc-300 shrink-0"
                    >
                        {{ selectedApp.user?.name?.[0] ?? 'U' }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="text-base font-bold text-zinc-100">{{ selectedApp.user?.name }}</div>
                        <div class="text-xs text-zinc-400">{{ selectedApp.user?.email }}</div>
                        <div class="mt-1 flex items-center gap-3 text-xs text-zinc-500">
                            <span>ID: {{ selectedApp.user?.id }}</span>
                            <span>•</span>
                            <Link
                                :href="route('admin.users.show', selectedApp.user?.id)"
                                class="text-violet-400 hover:underline"
                            >
                                Профиль пользователя ↗
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Фото лица для верификации -->
                <div class="space-y-2">
                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-400 flex items-center justify-between">
                        <span>Верификационное фото лица</span>
                        <span class="text-[11px] text-zinc-500">Клик для зума</span>
                    </div>

                    <div
                        class="relative rounded-2xl overflow-hidden border border-zinc-800 bg-zinc-900 cursor-zoom-in group max-h-96 flex items-center justify-center"
                        @click="openLightbox(selectedApp.face_photo_url)"
                    >
                        <img
                            :src="selectedApp.face_photo_url"
                            class="w-full h-auto max-h-96 object-contain"
                            alt="Фото лица"
                        />
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white gap-2 font-medium text-xs">
                            <el-icon :size="16"><ZoomIn /></el-icon>
                            <span>Открыть в полном размере</span>
                        </div>
                    </div>
                </div>

                <!-- Информация о проверке -->
                <div class="space-y-3 p-4 rounded-xl bg-zinc-900/60 border border-zinc-800 text-xs">
                    <div class="flex items-center justify-between text-zinc-400">
                        <span>Дата подачи:</span>
                        <span class="text-zinc-200 font-mono">{{ new Date(selectedApp.created_at).toLocaleString('ru') }}</span>
                    </div>
                    <div v-if="selectedApp.reviewed_at" class="flex items-center justify-between text-zinc-400">
                        <span>Дата проверки:</span>
                        <span class="text-zinc-200 font-mono">{{ new Date(selectedApp.reviewed_at).toLocaleString('ru') }}</span>
                    </div>
                    <div v-if="selectedApp.reviewer" class="flex items-center justify-between text-zinc-400">
                        <span>Модератор:</span>
                        <span class="text-zinc-200 font-medium">{{ selectedApp.reviewer.name }}</span>
                    </div>
                    <div v-if="selectedApp.rejection_reason" class="pt-2 border-t border-zinc-800 text-rose-400">
                        <span class="font-semibold block mb-0.5">Причина отклонения:</span>
                        <p class="italic bg-rose-950/20 border border-rose-900/40 p-2.5 rounded-lg">{{ selectedApp.rejection_reason }}</p>
                    </div>
                </div>

                <!-- Форма отклонения заявки (внутри Drawer) -->
                <div v-if="rejectMode" class="space-y-4 p-4 rounded-xl bg-rose-950/20 border border-rose-900/40 animate-fade-in">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-rose-400 flex items-center gap-1.5">
                            <el-icon><Warning /></el-icon>
                            Укажите причину отклонения
                        </span>
                        <button
                            type="button"
                            class="text-xs text-zinc-400 hover:text-zinc-200"
                            @click="rejectMode = false"
                        >
                            Отмена
                        </button>
                    </div>

                    <!-- Чипы типовых причин -->
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="chip in rejectChips"
                            :key="chip"
                            type="button"
                            class="px-2.5 py-1 rounded-lg text-xs font-medium border transition-colors"
                            :class="[
                                rejectForm.rejection_reason === chip
                                    ? 'bg-rose-600 border-rose-500 text-white'
                                    : 'bg-zinc-900 border-zinc-800 text-zinc-400 hover:text-zinc-200 hover:border-zinc-700'
                            ]"
                            @click="rejectForm.rejection_reason = chip"
                        >
                            {{ chip }}
                        </button>
                    </div>

                    <!-- Произвольный текст -->
                    <textarea
                        v-model="rejectForm.rejection_reason"
                        rows="3"
                        class="w-full bg-zinc-900 border border-zinc-800 rounded-lg p-3 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500/30"
                        placeholder="Или напишите индивидуальное пояснение кандидату..."
                    />

                    <!-- Сброс квиза -->
                    <label class="flex items-center gap-2 cursor-pointer select-none text-xs text-zinc-300">
                        <input
                            type="checkbox"
                            v-model="rejectForm.reset_quiz"
                            class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-rose-600 focus:ring-rose-500"
                        />
                        <span>Сбросить результаты вступительного теста (сдача с нуля)</span>
                    </label>

                    <AdminButton
                        variant="destructive"
                        class="w-full"
                        :loading="rejectForm.processing"
                        :disabled="!rejectForm.rejection_reason.trim()"
                        @click="executeReject"
                    >
                        Подтвердить отклонение заявки
                    </AdminButton>
                </div>
            </div>

            <!-- Footer Drawer Buttons -->
            <template #footer v-if="selectedApp && selectedApp.status === 'pending' && !rejectMode">
                <div class="flex items-center justify-between w-full">
                    <Link
                        :href="route('admin.applications.show', selectedApp.id)"
                        class="text-xs text-zinc-400 hover:text-zinc-200"
                    >
                        Открыть на отдельной странице ↗
                    </Link>

                    <div class="flex items-center gap-2">
                        <AdminButton
                            variant="destructive"
                            size="md"
                            @click="startReject(selectedApp)"
                        >
                            Отклонить
                        </AdminButton>
                        <AdminButton
                            variant="success"
                            size="md"
                            @click="confirmApprove(selectedApp)"
                        >
                            <template #icon><el-icon><Check /></el-icon></template>
                            Одобрить заявку
                        </AdminButton>
                    </div>
                </div>
            </template>
        </AdminDrawer>

        <!-- ─── Modal подтверждения одобрения ───────────────────────────────── -->
        <AdminModal
            :show="showApproveConfirm"
            title="Одобрить статус айдола?"
            subtitle="Пользователь получит статус айдола, возможность создавать услуги и продавать контент-паки"
            @close="showApproveConfirm = false"
        >
            <div class="text-sm text-zinc-300 space-y-2" v-if="appToApprove">
                <p>Кандидат: <strong class="text-white">{{ appToApprove.user?.name }}</strong> ({{ appToApprove.user?.email }})</p>
                <p class="text-xs text-zinc-500">После одобрения пользователю будет отправлено поздравление и активирован заработанный счет (розовые монеты).</p>
            </div>

            <template #footer>
                <AdminButton variant="ghost" @click="showApproveConfirm = false">
                    Отмена
                </AdminButton>
                <AdminButton variant="success" @click="executeApprove">
                    Да, одобрить
                </AdminButton>
            </template>
        </AdminModal>

        <!-- ─── Lightbox Modal ──────────────────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="lightboxUrl"
                class="fixed inset-0 z-50 bg-black/90 backdrop-blur-md flex items-center justify-center p-4 cursor-zoom-out select-none"
                @click="lightboxUrl = null"
            >
                <img
                    :src="lightboxUrl"
                    class="max-w-full max-h-[90vh] object-contain rounded-xl shadow-2xl border border-zinc-800"
                    alt="Увеличенное фото"
                />
                <button
                    type="button"
                    class="absolute top-6 right-6 p-2 rounded-full bg-zinc-900/80 text-white hover:bg-zinc-800 transition-colors"
                    @click="lightboxUrl = null"
                >
                    <el-icon :size="20"><Close /></el-icon>
                </button>
            </div>
        </Teleport>
    </div>
</template>
