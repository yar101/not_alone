<script setup>
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import {
    Check, Close, ZoomIn, ArrowLeft, Warning,
    User, Calendar, Clock, Star
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    application: Object,
});

const lightboxOpen = ref(false);
const showApproveConfirm = ref(false);
const showRejectForm = ref(false);
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

function approve() {
    router.patch(route('admin.applications.approve', props.application.id), {}, {
        onSuccess: () => {
            showApproveConfirm.value = false;
        },
    });
}

function submitReject() {
    rejectForm.patch(route('admin.applications.reject', props.application.id), {
        onSuccess: () => {
            showRejectForm.value = false;
        },
    });
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
    <div class="space-y-6 max-w-5xl mx-auto">
        <!-- Back Link -->
        <div>
            <Link
                :href="route('admin.applications.index')"
                class="inline-flex items-center gap-2 text-xs font-medium text-zinc-400 hover:text-zinc-100 transition-colors"
            >
                <el-icon :size="12"><ArrowLeft /></el-icon>
                <span>Назад ко всем заявкам</span>
            </Link>
        </div>

        <!-- Header -->
        <AdminPageHeader
            :title="`Заявка: ${application.user?.name}`"
            :subtitle="`Подана ${new Date(application.created_at).toLocaleString('ru')}`"
        >
            <template #badge>
                <AdminBadge :variant="statusVariant[application.status] || 'neutral'" dot size="md">
                    {{ statusLabel[application.status] ?? application.status }}
                </AdminBadge>
            </template>
        </AdminPageHeader>

        <!-- Main Card Grid -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start">
            <!-- Left: Photo Section -->
            <div class="md:col-span-5 space-y-3">
                <div class="text-xs font-semibold uppercase tracking-wider text-zinc-400 flex items-center justify-between">
                    <span>Верификационное фото</span>
                    <span class="text-[11px] text-zinc-500">Клик для зума</span>
                </div>

                <div
                    class="relative rounded-2xl overflow-hidden border border-zinc-800 bg-zinc-900 cursor-zoom-in group shadow-lg"
                    @click="lightboxOpen = true"
                >
                    <img
                        :src="application.face_photo_url"
                        class="w-full h-auto object-cover max-h-[460px]"
                        alt="Фото лица"
                    />
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white gap-2 font-medium text-xs">
                        <el-icon :size="18"><ZoomIn /></el-icon>
                        <span>Увеличить фото</span>
                    </div>
                </div>
            </div>

            <!-- Right: Candidate Info & Review Section -->
            <div class="md:col-span-7 space-y-6">
                <!-- User Profile Summary -->
                <div class="p-5 rounded-2xl bg-zinc-900/60 border border-zinc-800 space-y-4">
                    <div class="flex items-center gap-4">
                        <img
                            v-if="application.user?.avatar_url"
                            :src="application.user.avatar_url"
                            class="w-14 h-14 rounded-full object-cover border border-zinc-700 shrink-0"
                            alt=""
                        />
                        <div
                            v-else
                            class="w-14 h-14 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center font-bold text-lg text-zinc-300 shrink-0"
                        >
                            {{ application.user?.name?.[0] ?? 'U' }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-bold text-zinc-100">{{ application.user?.name }}</h2>
                            <p class="text-xs text-zinc-400">{{ application.user?.email }}</p>
                            <div class="mt-2 flex items-center gap-4 text-xs text-zinc-500">
                                <span>ID: <strong class="text-zinc-400 font-mono">{{ application.user?.id }}</strong></span>
                                <span>•</span>
                                <Link
                                    :href="route('admin.users.show', application.user?.id)"
                                    class="text-violet-400 hover:underline"
                                >
                                    Открыть профиль пользователя ↗
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Meta Details Table -->
                    <div class="pt-4 border-t border-zinc-800 space-y-2 text-xs">
                        <div class="flex items-center justify-between text-zinc-400">
                            <span>Заявка отправлена:</span>
                            <span class="text-zinc-200 font-mono">{{ new Date(application.created_at).toLocaleString('ru') }}</span>
                        </div>
                        <div v-if="application.reviewed_at" class="flex items-center justify-between text-zinc-400">
                            <span>Дата рассмотрения:</span>
                            <span class="text-zinc-200 font-mono">{{ new Date(application.reviewed_at).toLocaleString('ru') }}</span>
                        </div>
                        <div v-if="application.reviewer" class="flex items-center justify-between text-zinc-400">
                            <span>Модератор:</span>
                            <span class="text-zinc-200 font-medium">{{ application.reviewer.name }}</span>
                        </div>
                    </div>

                    <!-- Rejection Reason if any -->
                    <div v-if="application.rejection_reason" class="p-3.5 rounded-xl bg-rose-950/20 border border-rose-900/40 text-xs">
                        <span class="font-semibold text-rose-400 block mb-1">Причина отказа:</span>
                        <p class="text-rose-200 italic">{{ application.rejection_reason }}</p>
                    </div>
                </div>

                <!-- Review Actions Section (when status === 'pending') -->
                <div v-if="application.status === 'pending'" class="p-5 rounded-2xl bg-zinc-900/40 border border-zinc-800 space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                        Решение модератора
                    </h3>

                    <div class="flex items-center gap-3" v-if="!showRejectForm">
                        <AdminButton
                            variant="success"
                            size="md"
                            class="flex-1"
                            @click="showApproveConfirm = true"
                        >
                            <template #icon><el-icon><Check /></el-icon></template>
                            Одобрить статус айдола
                        </AdminButton>

                        <AdminButton
                            variant="destructive"
                            size="md"
                            class="flex-1"
                            @click="showRejectForm = true"
                        >
                            <template #icon><el-icon><Close /></el-icon></template>
                            Отклонить заявку
                        </AdminButton>
                    </div>

                    <!-- Reject Form Expansion -->
                    <div v-else class="space-y-4 p-4 rounded-xl bg-rose-950/20 border border-rose-900/40">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-rose-400 flex items-center gap-1.5">
                                <el-icon><Warning /></el-icon>
                                Выберите или введите причину отклонения
                            </span>
                            <button
                                type="button"
                                class="text-xs text-zinc-400 hover:text-zinc-200"
                                @click="showRejectForm = false"
                            >
                                Отмена
                            </button>
                        </div>

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

                        <textarea
                            v-model="rejectForm.rejection_reason"
                            rows="3"
                            class="w-full bg-zinc-900 border border-zinc-800 rounded-lg p-3 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500/30"
                            placeholder="Или введите подробный комментарий..."
                            required
                        />

                        <label class="flex items-center gap-2 cursor-pointer select-none text-xs text-zinc-300">
                            <input
                                type="checkbox"
                                v-model="rejectForm.reset_quiz"
                                class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-rose-600 focus:ring-rose-500"
                            />
                            <span>Сбросить результаты теста (пользователь пересдаёт с нуля)</span>
                        </label>

                        <AdminButton
                            variant="destructive"
                            class="w-full"
                            :loading="rejectForm.processing"
                            :disabled="!rejectForm.rejection_reason.trim()"
                            @click="submitReject"
                        >
                            Подтвердить отклонение
                        </AdminButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Confirmation Modal for Approval ──────────────────────────────── -->
        <AdminModal
            :show="showApproveConfirm"
            title="Одобрить статус айдола?"
            subtitle="Пользователь получит статус айдола, возможность создавать платные услуги и продавать контент-паки"
            @close="showApproveConfirm = false"
        >
            <div class="text-sm text-zinc-300 space-y-2">
                <p>Кандидат: <strong class="text-white">{{ application.user?.name }}</strong> ({{ application.user?.email }})</p>
                <p class="text-xs text-zinc-500">После одобрения пользователю будет отправлено системное уведомление и активирован заработанный счёт в розовых монетах.</p>
            </div>

            <template #footer>
                <AdminButton variant="ghost" @click="showApproveConfirm = false">
                    Отмена
                </AdminButton>
                <AdminButton variant="success" @click="approve">
                    Да, одобрить
                </AdminButton>
            </template>
        </AdminModal>

        <!-- ─── Lightbox Modal ──────────────────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="lightboxOpen"
                class="fixed inset-0 z-50 bg-black/90 backdrop-blur-md flex items-center justify-center p-4 cursor-zoom-out select-none"
                @click="lightboxOpen = false"
            >
                <img
                    :src="application.face_photo_url"
                    class="max-w-full max-h-[90vh] object-contain rounded-xl shadow-2xl border border-zinc-800"
                    alt="Увеличенное фото"
                />
                <button
                    type="button"
                    class="absolute top-6 right-6 p-2 rounded-full bg-zinc-900/80 text-white hover:bg-zinc-800 transition-colors"
                    @click="lightboxOpen = false"
                >
                    <el-icon :size="20"><Close /></el-icon>
                </button>
            </div>
        </Teleport>
    </div>
</template>
