<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, Close, Warning, ZoomIn } from '@element-plus/icons-vue';
import { ElMessageBox } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { formatMoney } from '@/Utils/money';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pack: Object,
});

// ── Flag state ───────────────────────────────────────────
const flaggedFields  = reactive({
    title:       false,
    description: false,
    price:       false,
});
const fieldComments  = reactive({ title: '', description: '', price: '' });
const flaggedPhotos  = reactive({});
const photoComments  = reactive({});

const PRESETS = {
    title: [
        'Содержит ссылки / контактную информацию',
        'Нецензурная лексика или оскорбления',
        'Опечатки или некорректный регистр',
    ],
    description: [
        'Содержит ссылки / контактную информацию',
        'Нецензурная лексика или оскорбления',
        'Слишком короткое описание',
        'Грамматические и стилистические ошибки',
    ],
    price: [
        'Некорректная цена пака',
        'Цена не соответствует правилам платформы',
    ],
    photo: [
        'Низкое качество изображения',
        'Наличие водяных знаков / чужих логотипов',
        'Неприемлемый или нарушающий правила контент',
    ],
};

if (props.pack?.photos) {
    props.pack.photos.forEach(p => {
        flaggedPhotos[p.id]  = false;
        photoComments[p.id]  = '';
    });
}

// ── Decision ─────────────────────────────────────────────
const isApproved  = ref(false);
const submitting  = ref(false);
const errors      = ref({});

const hasAnyFlag = computed(() => {
    return Object.values(flaggedFields).some(v => v) ||
           Object.values(flaggedPhotos).some(v => v);
});

const canSubmit = computed(() => isApproved.value || hasAnyFlag.value);

function onToggleFieldFlag(field) {
    if (flaggedFields[field]) {
        isApproved.value = false;
    }
}

function onTogglePhotoFlag(id) {
    if (flaggedPhotos[id]) {
        isApproved.value = false;
    }
}

function reject() {
    ElMessageBox.confirm(
        'Полностью отклонить пак? Айдол получит уведомление.',
        'Отклонение контент-пака',
        {
            confirmButtonText: 'Отклонить',
            cancelButtonText: 'Отмена',
            type: 'warning',
            confirmButtonClass: 'el-button--danger',
        }
    ).then(() => {
        submitting.value = true;
        const fd = new FormData();
        fd.append('decision', 'rejected');
        router.post(route('admin.content-packs.decide', props.pack.id), fd, {
            forceFormData:  true,
            preserveScroll: true,
            onError: (errs) => { errors.value = errs; submitting.value = false; },
            onFinish: () => { submitting.value = false; },
        });
    }).catch(() => {});
}

function submit() {
    if (!canSubmit.value) return;
    errors.value = {};

    const fd = new FormData();
    fd.append('decision', isApproved.value ? 'approved' : 'has_remarks');

    const fFields = Object.keys(flaggedFields).filter(f => flaggedFields[f]);
    fFields.forEach(f => fd.append('flagged_fields[]', f));

    const fComments = {};
    fFields.forEach(f => { if (fieldComments[f]) fComments[f] = fieldComments[f]; });
    if (Object.keys(fComments).length) fd.append('field_comments', JSON.stringify(fComments));

    const fPhotoIds = Object.keys(flaggedPhotos).filter(id => flaggedPhotos[id]);
    fPhotoIds.forEach(id => fd.append('flagged_photo_ids[]', id));

    const fPhotoComments = {};
    fPhotoIds.forEach(id => { if (photoComments[id]) fPhotoComments[id] = photoComments[id]; });
    if (Object.keys(fPhotoComments).length) fd.append('photo_comments', JSON.stringify(fPhotoComments));

    submitting.value = true;
    router.post(route('admin.content-packs.decide', props.pack.id), fd, {
        forceFormData:  true,
        preserveScroll: true,
        onError: (errs) => { errors.value = errs; submitting.value = false; },
        onFinish: () => { submitting.value = false; },
    });
}

// ── Lightbox ─────────────────────────────────────────────
const lightboxIndex = ref(null);
const lightboxPhotos = computed(() => {
    const list = [];
    if (props.pack?.cover_url) list.push({ id: 'cover', url: props.pack.cover_url });
    if (props.pack?.photos) list.push(...props.pack.photos);
    return list;
});

function openLightbox(idx) { lightboxIndex.value = idx; }
function closeLightbox()   { lightboxIndex.value = null; }
function prevPhoto() { lightboxIndex.value = (lightboxIndex.value - 1 + lightboxPhotos.value.length) % lightboxPhotos.value.length; }
function nextPhoto() { lightboxIndex.value = (lightboxIndex.value + 1) % lightboxPhotos.value.length; }

function handleLbKey(e) {
    if (lightboxIndex.value === null) return;
    if (e.key === 'ArrowLeft')  prevPhoto();
    else if (e.key === 'ArrowRight') nextPhoto();
    else if (e.key === 'Escape') closeLightbox();
}
onMounted(() => window.addEventListener('keydown', handleLbKey));
onUnmounted(() => window.removeEventListener('keydown', handleLbKey));
</script>

<template>
    <div class="space-y-6 max-w-5xl mx-auto">
        <!-- Back Link -->
        <div>
            <Link
                :href="route('admin.content-packs.index')"
                class="inline-flex items-center gap-2 text-xs font-medium text-zinc-400 hover:text-zinc-100 transition-colors"
            >
                <el-icon :size="12"><ArrowLeft /></el-icon>
                <span>Назад ко всем контент-пакам</span>
            </Link>
        </div>

        <!-- Header -->
        <AdminPageHeader
            :title="`Контент-пак: ${pack.title}`"
            :subtitle="`ID #${pack.id} • Подан ${new Date(pack.created_at).toLocaleString('ru')}`"
        />

        <!-- Overview Card -->
        <div class="p-5 rounded-2xl bg-zinc-900/60 border border-zinc-800 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <img
                    v-if="pack.user?.avatar_url"
                    :src="pack.user.avatar_url"
                    class="w-12 h-12 rounded-full object-cover border border-zinc-700 shrink-0"
                    alt=""
                />
                <div class="min-w-0">
                    <div class="text-sm font-bold text-zinc-100">{{ pack.user?.name }}</div>
                    <div class="text-xs text-zinc-400">{{ pack.user?.email }}</div>
                    <div class="mt-1 flex items-center gap-3 text-xs text-zinc-500">
                        <span>Айдол ID: #{{ pack.user?.id }}</span>
                        <span>•</span>
                        <Link :href="route('admin.users.show', pack.user?.id)" class="text-violet-400 hover:underline">
                            Открыть профиль ↗
                        </Link>
                    </div>
                </div>
            </div>

            <div class="text-right">
                <span class="text-[11px] uppercase tracking-wider text-zinc-500 block">Стоимость пака</span>
                <div class="flex items-center gap-1.5 font-bold text-base text-zinc-100">
                    <span>{{ formatMoney(pack.price) }}</span>
                    <CoinIcon color="blue" size="xs" />
                </div>
            </div>
        </div>

        <!-- Media Photos Grid with individual flags -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                    Фотографии в паке ({{ pack.photos?.length ?? 0 }})
                </h3>
                <span class="text-xs text-zinc-500">Кликните на фото для зума или отметьте замечание к кадру</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                <div
                    v-for="(photo, idx) in pack.photos"
                    :key="photo.id"
                    class="p-2.5 rounded-xl border bg-zinc-900/50 space-y-2 transition-all"
                    :class="[
                        flaggedPhotos[photo.id]
                            ? 'border-amber-600/80 bg-amber-950/20'
                            : 'border-zinc-800 hover:border-zinc-700'
                    ]"
                >
                    <div
                        class="relative rounded-lg overflow-hidden border border-zinc-700/80 cursor-zoom-in group aspect-square flex items-center justify-center bg-black"
                        @click="openLightbox(idx)"
                    >
                        <img :src="photo.url" class="w-full h-full object-cover" alt="" />
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs gap-1">
                            <el-icon><ZoomIn /></el-icon>
                            <span>Увеличить</span>
                        </div>
                    </div>

                    <div class="pt-1 flex items-center justify-between">
                        <span class="text-[11px] text-zinc-500 font-mono">#{{ idx + 1 }}</span>
                        <label class="inline-flex items-center gap-1.5 text-[11px] text-zinc-400 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                v-model="flaggedPhotos[photo.id]"
                                class="w-3.5 h-3.5 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500"
                                @change="onTogglePhotoFlag(photo.id)"
                            />
                            <span :class="{ 'text-amber-400 font-semibold': flaggedPhotos[photo.id] }">Замечание</span>
                        </label>
                    </div>

                    <div v-if="flaggedPhotos[photo.id]" class="pt-2 border-t border-zinc-800 space-y-1">
                        <input
                            v-model="photoComments[photo.id]"
                            type="text"
                            class="w-full bg-zinc-950 border border-amber-900/60 rounded p-1.5 text-[11px] text-zinc-100 placeholder-zinc-500 focus:outline-none"
                            placeholder="Причина (размыто, 18+...)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Text Fields Review -->
        <div class="space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                Текстовые параметры пака
            </h3>

            <div class="space-y-3">
                <!-- Title -->
                <div class="p-4 rounded-xl border bg-zinc-900/40 border-zinc-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-zinc-400">Название пака</span>
                        <label class="inline-flex items-center gap-2 text-xs text-zinc-400 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                v-model="flaggedFields.title"
                                class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500"
                                @change="onToggleFieldFlag('title')"
                            />
                            <span :class="{ 'text-amber-400 font-semibold': flaggedFields.title }">Замечание</span>
                        </label>
                    </div>
                    <div class="text-sm font-semibold text-zinc-100">{{ pack.title }}</div>
                    <div v-if="flaggedFields.title" class="mt-3 pt-3 border-t border-zinc-800 space-y-2">
                        <input
                            v-model="fieldComments.title"
                            type="text"
                            class="w-full bg-zinc-950 border border-amber-900/60 rounded-lg p-2 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none"
                            placeholder="Пояснение к замечанию по названию..."
                        />
                    </div>
                </div>

                <!-- Description -->
                <div class="p-4 rounded-xl border bg-zinc-900/40 border-zinc-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-zinc-400">Описание</span>
                        <label class="inline-flex items-center gap-2 text-xs text-zinc-400 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                v-model="flaggedFields.description"
                                class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500"
                                @change="onToggleFieldFlag('description')"
                            />
                            <span :class="{ 'text-amber-400 font-semibold': flaggedFields.description }">Замечание</span>
                        </label>
                    </div>
                    <div class="text-xs text-zinc-300 whitespace-pre-wrap">{{ pack.description || '—' }}</div>
                    <div v-if="flaggedFields.description" class="mt-3 pt-3 border-t border-zinc-800 space-y-2">
                        <input
                            v-model="fieldComments.description"
                            type="text"
                            class="w-full bg-zinc-950 border border-amber-900/60 rounded-lg p-2 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none"
                            placeholder="Пояснение по описанию..."
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Decision Box -->
        <div class="p-5 rounded-2xl bg-zinc-900/60 border border-zinc-800 space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                Решение по паку
            </h3>

            <div class="space-y-3">
                <label
                    class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer select-none transition-all"
                    :class="[
                        isApproved
                            ? 'bg-emerald-950/20 border-emerald-600/80 text-emerald-300'
                            : 'bg-zinc-900/40 border-zinc-800 text-zinc-400 hover:border-zinc-700'
                    ]"
                >
                    <input
                        type="radio"
                        :value="true"
                        v-model="isApproved"
                        class="w-4 h-4 text-emerald-600 focus:ring-emerald-500"
                        @change="Object.keys(flaggedFields).forEach(f => flaggedFields[f] = false); Object.keys(flaggedPhotos).forEach(id => flaggedPhotos[id] = false)"
                    />
                    <div>
                        <div class="font-bold text-xs text-zinc-100">Одобрить контент-пак</div>
                        <div class="text-[11px] text-zinc-400">Пак будет опубликован в галерее и доступен для покупки</div>
                    </div>
                </label>

                <label
                    class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer select-none transition-all"
                    :class="[
                        hasAnyFlag && !isApproved
                            ? 'bg-amber-950/20 border-amber-600/80 text-amber-300'
                            : 'bg-zinc-900/40 border-zinc-800 text-zinc-400 hover:border-zinc-700'
                    ]"
                >
                    <input
                        type="radio"
                        :value="false"
                        v-model="isApproved"
                        class="w-4 h-4 text-amber-500 focus:ring-amber-500"
                    />
                    <div>
                        <div class="font-bold text-xs text-zinc-100">Вернуть на доработку с замечаниями</div>
                        <div class="text-[11px] text-zinc-400">Айдол получит замечания по отмеченным фото и полям</div>
                    </div>
                </label>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-zinc-800">
                <AdminButton variant="destructive" size="sm" @click="reject">
                    Полностью отклонить
                </AdminButton>
                <AdminButton
                    :variant="isApproved ? 'success' : 'warning'"
                    size="md"
                    :disabled="!canSubmit || submitting"
                    :loading="submitting"
                    @click="submit"
                >
                    {{ isApproved ? 'Одобрить пак' : 'Отправить замечания' }}
                </AdminButton>
            </div>
        </div>

        <!-- Lightbox -->
        <Teleport to="body">
            <div
                v-if="lightboxIndex !== null"
                class="fixed inset-0 z-50 bg-black/95 backdrop-blur-md flex items-center justify-center p-4 cursor-zoom-out select-none"
                @click="closeLightbox"
            >
                <button
                    v-if="lightboxPhotos.length > 1"
                    type="button"
                    class="absolute left-6 p-3 rounded-full bg-zinc-900/80 text-white hover:bg-zinc-800"
                    @click.stop="prevPhoto"
                >
                    <el-icon :size="20"><ArrowLeft /></el-icon>
                </button>

                <img
                    :src="lightboxPhotos[lightboxIndex]?.url"
                    class="max-w-full max-h-[90vh] object-contain rounded-xl border border-zinc-800"
                    alt=""
                />

                <button
                    v-if="lightboxPhotos.length > 1"
                    type="button"
                    class="absolute right-6 p-3 rounded-full bg-zinc-900/80 text-white hover:bg-zinc-800"
                    @click.stop="nextPhoto"
                >
                    <el-icon :size="20"><ArrowRight /></el-icon>
                </button>
            </div>
        </Teleport>
    </div>
</template>
