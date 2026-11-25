<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Delete, EditPen, Plus, Picture, Check, Close } from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    frames: Array,
    conditions: Array,
});

const isDialogVisible = ref(false);
const editingFrame = ref(null);
const generating = ref(false);

const form = useForm({
    name: '',
    description: '',
    image: null,
    type: 'free',
    price: 0,
    condition_class: '',
    is_active: true,
});

const imagePreview = ref(null);
const fileInput = ref(null);

const triggerFileInput = () => {
    fileInput.value?.click();
};

const processFile = (file) => {
    if (!file) return;
    if (file.type && file.type !== 'image/png' && !file.name.toLowerCase().endsWith('.png')) {
        ElMessage.error('Допустимы только файлы формата PNG');
        return;
    }
    form.image = file;
    imagePreview.value = URL.createObjectURL(file);
    if (form.errors.image) {
        form.clearErrors('image');
    }
};

const handleFileChange = (e) => {
    const file = e.target.files?.[0];
    processFile(file);
};

const handleFileDrop = (e) => {
    const file = e.dataTransfer?.files?.[0];
    processFile(file);
};

const openCreateDialog = () => {
    editingFrame.value = null;
    form.reset();
    form.clearErrors();
    imagePreview.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    isDialogVisible.value = true;
};

const openEditDialog = (frame) => {
    editingFrame.value = frame;
    form.clearErrors();
    form.name = frame.name;
    form.description = frame.description || '';
    form.type = frame.type;
    form.price = frame.price;
    form.condition_class = frame.condition_class || '';
    form.is_active = Boolean(frame.is_active);
    form.image = null;
    imagePreview.value = frame.image_url;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    isDialogVisible.value = true;
};

const saveFrame = () => {
    if (editingFrame.value) {
        form.transform((data) => ({
            ...data,
            _method: 'PATCH'
        })).post(route('admin.avatar-frames.update', editingFrame.value.id), {
            onSuccess: () => {
                isDialogVisible.value = false;
                ElMessage.success('Рамка обновлена');
            }
        });
    } else {
        form.post(route('admin.avatar-frames.store'), {
            onSuccess: () => {
                isDialogVisible.value = false;
                ElMessage.success('Рамка добавлена');
            }
        });
    }
};

const deleteFrame = (frame) => {
    ElMessageBox.confirm('Удалить эту рамку навсегда?', 'Внимание', {
        type: 'warning',
        confirmButtonText: 'Удалить',
        cancelButtonText: 'Отмена',
    }).then(() => {
        router.delete(route('admin.avatar-frames.destroy', frame.id), {
            onSuccess: () => ElMessage.success('Рамка удалена')
        });
    }).catch(() => {});
};

const generateTest = () => {
    generating.value = true;
    router.post(route('admin.avatar-frames.generate-test'), {}, {
        onFinish: () => (generating.value = false),
        onSuccess: () => ElMessage.success('Тестовая рамка создана'),
    });
};

function getFrameBadgeVariant(type) {
    switch (type) {
        case 'free': return 'neutral';
        case 'paid': return 'success';
        case 'achievement': return 'warning';
        case 'promo': return 'primary';
        default: return 'neutral';
    }
}
</script>

<template>
    <Head title="Рамки аватарок" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-100 flex items-center gap-2.5">
                    <el-icon class="text-violet-400"><Picture /></el-icon>
                    <span>Рамки аватарок</span>
                </h1>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Каталог рамок, условия получения за достижения и платные рамки профиля. Всего: {{ frames.length }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <AdminButton variant="outline" size="sm" :loading="generating" @click="generateTest">
                    🎲 Тест-рамка
                </AdminButton>
                <AdminButton variant="primary" size="sm" @click="openCreateDialog">
                    <el-icon class="mr-1.5"><Plus /></el-icon>
                    Добавить рамку
                </AdminButton>
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 overflow-hidden backdrop-blur-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400">
                            <th class="py-3 px-4 font-medium w-24 text-center">Превью</th>
                            <th class="py-3 px-4 font-medium">Название рамки</th>
                            <th class="py-3 px-4 font-medium">Тип получения</th>
                            <th class="py-3 px-4 font-medium">Условие / Цена</th>
                            <th class="py-3 px-4 font-medium text-center">Статус</th>
                            <th class="py-3 px-4 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                        <tr v-if="!frames.length">
                            <td colspan="6" class="py-12 text-center text-zinc-500">
                                Рамок аватарок пока нет
                            </td>
                        </tr>
                        <tr
                            v-for="frame in frames"
                            :key="frame.id"
                            class="hover:bg-zinc-800/30 transition"
                        >
                            <!-- Frame on Avatar Preview -->
                            <td class="py-3 px-4 text-center">
                                <div class="relative w-12 h-12 mx-auto flex items-center justify-center">
                                    <div class="w-9 h-9 rounded-full bg-zinc-800 border border-zinc-700"></div>
                                    <img :src="frame.image_url" class="absolute inset-0 w-full h-full object-contain pointer-events-none" />
                                </div>
                            </td>

                            <td class="py-3 px-4">
                                <div class="font-semibold text-zinc-100">{{ frame.name }}</div>
                                <div v-if="frame.description" class="text-[11px] text-zinc-400 mt-0.5">{{ frame.description }}</div>
                            </td>

                            <td class="py-3 px-4">
                                <AdminBadge :variant="getFrameBadgeVariant(frame.type)" size="sm">
                                    {{ frame.type === 'free' ? 'Базовая' : (frame.type === 'paid' ? 'Платная' : (frame.type === 'achievement' ? 'Ачивка' : 'Промо')) }}
                                </AdminBadge>
                            </td>

                            <td class="py-3 px-4">
                                <span v-if="frame.type === 'paid'" class="inline-flex items-center gap-1 font-mono font-bold text-zinc-200">
                                    <span>{{ frame.price }}</span>
                                    <CoinIcon color="blue" size="xs" />
                                </span>
                                <span v-else-if="frame.type === 'achievement'" class="text-zinc-300">
                                    {{ conditions.find(c => c.key === frame.condition_class)?.description || frame.condition_class }}
                                </span>
                                <span v-else class="text-zinc-500">—</span>
                            </td>

                            <td class="py-3 px-4 text-center">
                                <AdminBadge :variant="frame.is_active ? 'success' : 'neutral'" size="sm" dot>
                                    {{ frame.is_active ? 'Активна' : 'Отключена' }}
                                </AdminBadge>
                            </td>

                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        class="p-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition"
                                        title="Редактировать"
                                        @click="openEditDialog(frame)"
                                    >
                                        <el-icon><EditPen /></el-icon>
                                    </button>
                                    <button
                                        type="button"
                                        class="p-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-rose-400 hover:bg-zinc-800 transition"
                                        title="Удалить"
                                        @click="deleteFrame(frame)"
                                    >
                                        <el-icon><Delete /></el-icon>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <AdminModal
            :show="isDialogVisible"
            :title="editingFrame ? 'Редактировать рамку' : 'Новая рамка аватарки'"
            @close="isDialogVisible = false"
        >
            <form @submit.prevent="saveFrame" class="space-y-4 text-xs">
                <div class="space-y-1.5">
                    <label class="font-medium text-zinc-300">Название рамки <span class="text-rose-400">*</span></label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                    />
                    <p v-if="form.errors.name" class="text-rose-400 text-[11px]">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="font-medium text-zinc-300">Тип получения <span class="text-rose-400">*</span></label>
                    <select
                        v-model="form.type"
                        class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 focus:outline-none focus:border-violet-500"
                    >
                        <option value="free">Базовая (доступна всем)</option>
                        <option value="paid">Платная</option>
                        <option value="achievement">За достижение</option>
                        <option value="promo">Промо-код</option>
                    </select>
                </div>

                <div v-if="form.type === 'paid'" class="space-y-1.5">
                    <label class="font-medium text-zinc-300">Цена (монет)</label>
                    <input
                        v-model.number="form.price"
                        type="number"
                        min="0"
                        class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs font-mono text-zinc-100 focus:outline-none focus:border-violet-500"
                    />
                </div>

                <div v-if="form.type === 'achievement'" class="space-y-1.5">
                    <label class="font-medium text-zinc-300">Условие получения (Класс)</label>
                    <select
                        v-model="form.condition_class"
                        class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 focus:outline-none focus:border-violet-500"
                    >
                        <option v-for="c in conditions" :key="c.key" :value="c.key">
                            {{ c.description }}
                        </option>
                    </select>
                    <p v-if="form.errors.condition_class" class="text-rose-400 text-[11px]">{{ form.errors.condition_class }}</p>
                </div>

                <!-- Image Uploader -->
                <div class="space-y-1.5">
                    <label class="font-medium text-zinc-300">Изображение рамки (PNG, прозрачный центр)</label>
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/png"
                        class="hidden"
                        @change="handleFileChange"
                    />
                    <div
                        class="p-4 rounded-xl border border-dashed border-zinc-800 hover:border-violet-500 bg-zinc-950/60 cursor-pointer text-center transition flex flex-col items-center justify-center gap-2"
                        @click="triggerFileInput"
                        @dragover.prevent
                        @drop.prevent="handleFileDrop"
                    >
                        <div v-if="imagePreview" class="relative w-16 h-16 flex items-center justify-center">
                            <div class="w-12 h-12 rounded-full bg-zinc-800 border border-zinc-700"></div>
                            <img :src="imagePreview" class="absolute inset-0 w-full h-full object-contain" />
                        </div>
                        <div v-else class="text-zinc-500">
                            <el-icon :size="24" class="mx-auto mb-1"><Picture /></el-icon>
                            <span>Нажмите или перетащите PNG файл</span>
                        </div>
                    </div>
                    <p v-if="form.errors.image" class="text-rose-400 text-[11px]">{{ form.errors.image }}</p>
                </div>

                <label class="flex items-center gap-2 pt-1 cursor-pointer">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="rounded border-zinc-700 bg-zinc-900 text-violet-500 focus:ring-0"
                    />
                    <span class="text-zinc-300">Рамка активна в каталоге</span>
                </label>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-800">
                    <AdminButton variant="outline" size="sm" type="button" @click="isDialogVisible = false">
                        Отмена
                    </AdminButton>
                    <AdminButton variant="primary" size="sm" type="submit" :loading="form.processing">
                        Сохранить
                    </AdminButton>
                </div>
            </form>
        </AdminModal>
    </div>
</template>
