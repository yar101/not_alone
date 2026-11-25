<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, Close, Warning, ZoomIn } from '@element-plus/icons-vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { formatMoney } from '@/Utils/money';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    changeRequest: Object,
    pack:          Object,
    fields:        Object,
});

const FIELD_LABELS = {
    title:       'Название',
    description: 'Описание',
    price:       'Цена',
};

// Decision state
const isApproved = ref(false);
const submitting = ref(false);
const errors     = ref({});
const showReject = ref(false);
const rejectComment = ref('');

// Per-field flag + comment
const flagged  = reactive({});
const comments = reactive({});
for (const field of Object.keys(props.fields)) {
    flagged[field]  = props.fields[field].flagged ?? false;
    comments[field] = props.fields[field].admin_comment ?? '';
}

const hasAnyFlag = computed(() => Object.values(flagged).some(v => v));
const canSubmit  = computed(() => isApproved.value || hasAnyFlag.value);

function onToggleFlag(field) {
    if (flagged[field]) {
        isApproved.value = false;
    }
}

function submit() {
    if (!canSubmit.value) return;
    errors.value = {};

    const fd = new FormData();
    fd.append('decision', isApproved.value ? 'approved' : 'has_remarks');

    if (!isApproved.value) {
        const flaggedList = Object.keys(flagged).filter(f => flagged[f]);
        flaggedList.forEach(f => fd.append('flagged_fields[]', f));
        flaggedList.forEach(f => {
            if (comments[f]) fd.append(`field_comments[${f}]`, comments[f]);
        });
    }

    submitting.value = true;
    router.post(route('admin.content-packs.change-requests.decide', props.changeRequest.id), fd, {
        forceFormData:  true,
        preserveScroll: true,
        onError: (errs) => { errors.value = errs; submitting.value = false; },
        onFinish: () => { submitting.value = false; },
    });
}

function reject() {
    submitting.value = true;
    router.post(
        route('admin.content-packs.change-requests.decide', props.changeRequest.id),
        { decision: 'rejected', admin_comment: rejectComment.value || null },
        {
            preserveScroll: true,
            onFinish: () => { submitting.value = false; showReject.value = false; },
        }
    );
}

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
};

// Lightbox
const lightboxIndex = ref(null);
const lightboxPhotos = computed(() => {
    const list = [];
    if (props.pack?.cover_url) list.push({ id: 'cover', url: props.pack.cover_url });
    if (props.pack?.photos) list.push(...props.pack.photos);
    return list;
});

function openLightboxByUrl(url) {
    const idx = lightboxPhotos.value.findIndex(p => p.url === url);
    if (idx !== -1) lightboxIndex.value = idx;
}
function closeLightbox() { lightboxIndex.value = null; }
function prevPhoto() { lightboxIndex.value = (lightboxIndex.value - 1 + lightboxPhotos.value.length) % lightboxPhotos.value.length; }
function nextPhoto() { lightboxIndex.value = (lightboxIndex.value + 1) % lightboxPhotos.value.length; }

function handleLbKey(e) {
    if (lightboxIndex.value === null) return;
    if (e.key === 'ArrowLeft') prevPhoto();
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
                :href="route('admin.content-packs.change-requests.index')"
                class="inline-flex items-center gap-2 text-xs font-medium text-zinc-400 hover:text-zinc-100 transition-colors"
            >
                <el-icon :size="12"><ArrowLeft /></el-icon>
                <span>Назад к запросам на изменение паков</span>
            </Link>
        </div>

        <!-- Header -->
        <AdminPageHeader
            :title="`Изменения в паке: ${pack?.title}`"
            :subtitle="`Запрос #${changeRequest.id} • Подан ${new Date(changeRequest.created_at).toLocaleString('ru')}`"
        />

        <!-- Layout Grid -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start">
            <!-- Left Column: Pack Preview -->
            <div class="md:col-span-5 space-y-4">
                <!-- Idol Card -->
                <div class="p-4 rounded-xl bg-zinc-900/60 border border-zinc-800 flex items-center gap-3">
                    <img
                        v-if="pack?.user?.avatar_url"
                        :src="pack.user.avatar_url"
                        class="w-10 h-10 rounded-full object-cover border border-zinc-700 shrink-0"
                        alt=""
                    />
                    <div
                        v-else
                        class="w-10 h-10 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center font-bold text-xs text-zinc-300 shrink-0"
                    >
                        {{ pack?.user?.name?.[0] }}
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-zinc-100">{{ pack?.user?.name }}</div>
                        <div class="text-xs text-zinc-400">{{ pack?.user?.email }}</div>
                    </div>
                </div>

                <!-- Cover Preview -->
                <div class="p-4 rounded-xl bg-zinc-900/60 border border-zinc-800 space-y-2">
                    <span class="text-xs font-semibold text-zinc-400 block uppercase tracking-wider">Обложка пака</span>
                    <div
                        v-if="pack?.cover_url"
                        class="relative rounded-lg overflow-hidden border border-zinc-700 cursor-zoom-in group"
                        @click="openLightboxByUrl(pack.cover_url)"
                    >
                        <img :src="pack.cover_url" class="w-full h-48 object-cover" alt="" />
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs gap-1">
                            <el-icon><ZoomIn /></el-icon>
                            <span>Увеличить</span>
                        </div>
                    </div>
                </div>

                <!-- Photo Gallery Preview -->
                <div v-if="pack?.photos?.length" class="p-4 rounded-xl bg-zinc-900/60 border border-zinc-800 space-y-2">
                    <span class="text-xs font-semibold text-zinc-400 block uppercase tracking-wider">
                        Медиафайлы пака ({{ pack.photos.length }})
                    </span>
                    <div class="grid grid-cols-4 gap-2">
                        <img
                            v-for="photo in pack.photos"
                            :key="photo.id"
                            :src="photo.url"
                            class="w-full h-16 rounded-lg object-cover border border-zinc-800 hover:border-violet-500 cursor-zoom-in transition-colors"
                            alt=""
                            @click="openLightboxByUrl(photo.url)"
                        />
                    </div>
                </div>
            </div>

            <!-- Right Column: Diff & Decision -->
            <div class="md:col-span-7 space-y-5">
                <div v-if="Object.keys(errors).length" class="p-3 bg-rose-950/40 border border-rose-800 rounded-xl text-xs text-rose-300">
                    <div v-for="(msg, key) in errors" :key="key">{{ msg }}</div>
                </div>

                <!-- Fields Diff -->
                <div class="space-y-3">
                    <div
                        v-for="(vals, field) in fields"
                        :key="field"
                        class="p-4 rounded-xl border transition-all"
                        :class="[
                            flagged[field]
                                ? 'bg-amber-950/20 border-amber-800/80'
                                : 'bg-zinc-900/60 border-zinc-800'
                        ]"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-zinc-200">
                                {{ FIELD_LABELS[field] ?? field }}
                            </span>
                            <label class="inline-flex items-center gap-2 text-xs text-zinc-400 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    v-model="flagged[field]"
                                    class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500"
                                    @change="onToggleFlag(field)"
                                />
                                <span :class="{ 'text-amber-400 font-semibold': flagged[field] }">Замечание</span>
                            </label>
                        </div>

                        <!-- Diff current vs pending -->
                        <div class="grid grid-cols-2 gap-3 text-xs font-mono">
                            <div class="p-2.5 bg-zinc-950 border border-zinc-800/80 rounded-lg">
                                <span class="text-[10px] text-zinc-500 uppercase block mb-0.5">Было:</span>
                                <div class="text-rose-400 font-semibold flex items-center gap-1">
                                    <span>{{ field === 'price' ? formatMoney(vals.current) : (vals.current || '—') }}</span>
                                    <CoinIcon v-if="field === 'price' && vals.current != null" color="blue" size="xs" />
                                </div>
                            </div>

                            <div class="p-2.5 bg-zinc-950 border border-zinc-800/80 rounded-lg">
                                <span class="text-[10px] text-zinc-500 uppercase block mb-0.5">Стало:</span>
                                <div class="text-emerald-400 font-semibold flex items-center gap-1">
                                    <span>{{ field === 'price' ? formatMoney(vals.pending) : (vals.pending || '—') }}</span>
                                    <CoinIcon v-if="field === 'price' && vals.pending != null" color="blue" size="xs" />
                                </div>
                            </div>
                        </div>

                        <!-- Remarks Input -->
                        <div v-if="flagged[field]" class="mt-3 pt-3 border-t border-zinc-800 space-y-2">
                            <div class="flex flex-wrap gap-1" v-if="PRESETS[field]">
                                <button
                                    v-for="p in PRESETS[field]"
                                    :key="p"
                                    type="button"
                                    class="text-[10px] px-2 py-0.5 rounded bg-zinc-800 text-zinc-300 hover:text-white"
                                    @click="comments[field] = p"
                                >
                                    + {{ p }}
                                </button>
                            </div>
                            <input
                                v-model="comments[field]"
                                type="text"
                                class="w-full bg-zinc-950 border border-amber-900/60 rounded-lg p-2 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none"
                                placeholder="Комментарий к замечанию..."
                            />
                        </div>
                    </div>
                </div>

                <!-- Decision Card -->
                <div class="p-5 rounded-2xl bg-zinc-900/60 border border-zinc-800 space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                        Решение по запросу
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
                                @change="Object.keys(flagged).forEach(f => flagged[f] = false)"
                            />
                            <div>
                                <div class="font-bold text-xs text-zinc-100">Одобрить изменения</div>
                                <div class="text-[11px] text-zinc-400">Новые параметры пака будут моментально применены</div>
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
                                <div class="text-[11px] text-zinc-400">Айдол получит замечания по отмеченным полям</div>
                            </div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-zinc-800">
                        <AdminButton variant="destructive" size="sm" @click="showReject = true">
                            Полностью отклонить
                        </AdminButton>
                        <AdminButton
                            :variant="isApproved ? 'success' : 'warning'"
                            size="md"
                            :disabled="!canSubmit || submitting"
                            :loading="submitting"
                            @click="submit"
                        >
                            {{ isApproved ? 'Применить изменения' : 'Отправить замечания' }}
                        </AdminButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <AdminModal
            :show="showReject"
            title="Отклонить запрос на изменение?"
            subtitle="Изменения будут аннулированы, айдол получит уведомление"
            @close="showReject = false"
        >
            <div class="space-y-3">
                <label class="text-xs font-semibold text-zinc-300">Причина отклонения:</label>
                <textarea
                    v-model="rejectComment"
                    rows="3"
                    class="w-full bg-zinc-950 border border-zinc-800 rounded-lg p-3 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-rose-500"
                    placeholder="Укажите причину..."
                />
            </div>

            <template #footer>
                <AdminButton variant="ghost" @click="showReject = false">Отмена</AdminButton>
                <AdminButton variant="destructive" :loading="submitting" @click="reject">
                    Подтвердить отклонение
                </AdminButton>
            </template>
        </AdminModal>

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
