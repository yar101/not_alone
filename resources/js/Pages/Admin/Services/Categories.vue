<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminTable from '@/Components/Admin/UI/AdminTable.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminInput from '@/Components/Admin/UI/AdminInput.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import ServicesNavTabs from '@/Components/Admin/ServicesNavTabs.vue';
import { Plus, Edit, Delete, Rank, Close } from '@element-plus/icons-vue';
import { ElMessageBox } from 'element-plus';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    categories: Array,
});

const showForm = ref(false);
const editingId = ref(null);
const imagePreview = ref(null);
const newSuggestion = ref('');

const form = useForm({
    name: '',
    description: '',
    name_suggestions: [],
    is_active: true,
    image: null,
});

function getSuggestions(cat) {
    if (!cat?.name_suggestions) return [];
    if (Array.isArray(cat.name_suggestions)) return cat.name_suggestions;
    if (typeof cat.name_suggestions === 'object') {
        return Object.values(cat.name_suggestions).flat().filter(Boolean);
    }
    return [];
}

function openAdd() {
    editingId.value = null;
    imagePreview.value = null;
    newSuggestion.value = '';
    form.reset();
    form.is_active = true;
    form.name_suggestions = [];
    showForm.value = true;
}

function openEdit(cat) {
    editingId.value = cat.id;
    imagePreview.value = cat.image_path ? `/storage/${cat.image_path}` : null;
    newSuggestion.value = '';
    form.name = typeof cat.name === 'object' ? (cat.name?.ru || cat.name?.en || '') : (cat.name || cat.name_ru || '');
    form.description = typeof cat.description === 'object' ? (cat.description?.ru || cat.description?.en || '') : (cat.description || cat.description_ru || '');
    form.name_suggestions = [...getSuggestions(cat)];
    form.is_active = Boolean(cat.is_active);
    form.image = null;
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingId.value = null;
    imagePreview.value = null;
    newSuggestion.value = '';
    form.reset();
    form.clearErrors();
}

function addSuggestion() {
    const s = newSuggestion.value.trim();
    if (!s || form.name_suggestions.includes(s)) return;
    form.name_suggestions.push(s);
    newSuggestion.value = '';
}

function removeSuggestion(index) {
    form.name_suggestions.splice(index, 1);
}

function onImageChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    form.image = file;
    imagePreview.value = URL.createObjectURL(file);
}

function submit() {
    if (editingId.value) {
        form.patch(route('admin.services.categories.update', editingId.value), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: closeForm,
        });
    } else {
        form.post(route('admin.services.categories.store'), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    }
}

function destroyCategory(id) {
    ElMessageBox.confirm('Удалить эту категорию? Услуги в ней могут остаться без категории.', 'Удаление', {
        confirmButtonText: 'Удалить',
        cancelButtonText: 'Отмена',
        type: 'warning',
        confirmButtonClass: 'el-button--danger',
    }).then(() => {
        router.delete(route('admin.services.categories.destroy', id), { preserveScroll: true });
    }).catch(() => {});
}
</script>

<template>
    <div class="space-y-6 max-w-7xl mx-auto">
        <!-- Sub-navigation Tabs -->
        <ServicesNavTabs active="categories" />

        <!-- Header -->
        <AdminPageHeader
            title="Категории услуг"
            subtitle="Управление рубрикатором услуг айдолов, описаниями и шаблонами подсказок"
            :count="categories?.length"
        >
            <template #actions>
                <AdminButton variant="primary" @click="openAdd">
                    <template #icon><el-icon><Plus /></el-icon></template>
                    Добавить категорию
                </AdminButton>
            </template>
        </AdminPageHeader>

        <!-- Categories Table -->
        <AdminTable :empty="!categories?.length" empty-title="Категорий пока нет">
            <template #header>
                <tr>
                    <th class="py-3 px-4 w-16">Фото</th>
                    <th class="py-3 px-4">Название</th>
                    <th class="py-3 px-4">Описание</th>
                    <th class="py-3 px-4">Подсказки названий</th>
                    <th class="py-3 px-4">Статус</th>
                    <th class="py-3 px-4 text-right">Действия</th>
                </tr>
            </template>

            <tr
                v-for="cat in categories"
                :key="cat.id"
                class="hover:bg-zinc-800/40 transition-colors"
            >
                <!-- Image -->
                <td class="py-3 px-4">
                    <img
                        v-if="cat.image_path"
                        :src="`/storage/${cat.image_path}`"
                        class="w-10 h-10 rounded-lg object-cover border border-zinc-700"
                        alt=""
                    />
                    <div v-else class="w-10 h-10 rounded-lg bg-zinc-800 border border-zinc-700 flex items-center justify-center text-xs text-zinc-500 font-bold">
                        {{ cat.name?.[0] }}
                    </div>
                </td>

                <!-- Name -->
                <td class="py-3 px-4 font-semibold text-zinc-100">
                    {{ cat.name }}
                    <div class="text-[11px] text-zinc-500 font-mono">ID: #{{ cat.id }}</div>
                </td>

                <!-- Description -->
                <td class="py-3 px-4 text-xs text-zinc-400 max-w-xs truncate">
                    {{ cat.description || '—' }}
                </td>

                <!-- Suggestions chips -->
                <td class="py-3 px-4">
                    <div class="flex flex-wrap gap-1 max-w-sm">
                        <span
                            v-for="s in getSuggestions(cat).slice(0, 3)"
                            :key="s"
                            class="px-2 py-0.5 rounded bg-zinc-800 text-[11px] text-zinc-300 border border-zinc-700/60"
                        >
                            {{ s }}
                        </span>
                        <span
                            v-if="getSuggestions(cat).length > 3"
                            class="px-1.5 py-0.5 rounded bg-zinc-800 text-[10px] text-zinc-500"
                        >
                            +{{ getSuggestions(cat).length - 3 }}
                        </span>
                    </div>
                </td>

                <!-- Status -->
                <td class="py-3 px-4">
                    <AdminBadge :variant="cat.is_active ? 'success' : 'neutral'" size="sm">
                        {{ cat.is_active ? 'Активна' : 'Скрыта' }}
                    </AdminBadge>
                </td>

                <!-- Actions -->
                <td class="py-3 px-4 text-right whitespace-nowrap">
                    <div class="flex items-center justify-end gap-1.5">
                        <AdminButton variant="secondary" size="sm" @click="openEdit(cat)">
                            <template #icon><el-icon :size="12"><Edit /></el-icon></template>
                            Редактировать
                        </AdminButton>
                        <AdminButton variant="ghost" size="sm" class="text-rose-400 hover:text-rose-300" @click="destroyCategory(cat.id)">
                            <template #icon><el-icon :size="12"><Delete /></el-icon></template>
                        </AdminButton>
                    </div>
                </td>
            </tr>
        </AdminTable>

        <!-- Category Edit/Create Modal -->
        <AdminModal
            :show="showForm"
            :title="editingId ? 'Редактировать категорию' : 'Создать категорию услуг'"
            @close="closeForm"
        >
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Название категории *</label>
                    <AdminInput
                        v-model="form.name"
                        placeholder="Например: Игры вместе, Разговоры по душам..."
                        :error="form.errors.name"
                        required
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Описание категории</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-lg p-3 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500/30"
                        placeholder="Краткое пояснение сути категории..."
                    />
                </div>

                <!-- Suggestions -->
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Шаблоны названий (подсказки айдолам)</label>
                    <div class="flex gap-2 mb-2">
                        <AdminInput
                            v-model="newSuggestion"
                            placeholder="Добавить вариант подсказки..."
                            size="sm"
                            @keydown.enter.prevent="addSuggestion"
                        />
                        <AdminButton type="button" variant="secondary" size="sm" @click="addSuggestion">
                            Добавить
                        </AdminButton>
                    </div>

                    <div class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto p-1">
                        <span
                            v-for="(s, idx) in form.name_suggestions"
                            :key="idx"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-800 border border-zinc-700 text-xs text-zinc-200"
                        >
                            <span>{{ s }}</span>
                            <button type="button" class="text-zinc-400 hover:text-rose-400" @click="removeSuggestion(idx)">
                                <el-icon :size="10"><Close /></el-icon>
                            </button>
                        </span>
                    </div>
                </div>

                <!-- Image Upload -->
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Иконка / Изображение</label>
                    <div class="flex items-center gap-3">
                        <img
                            v-if="imagePreview"
                            :src="imagePreview"
                            class="w-12 h-12 rounded-lg object-cover border border-zinc-700"
                            alt=""
                        />
                        <input
                            type="file"
                            accept="image/*"
                            class="text-xs text-zinc-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-zinc-800 file:text-zinc-200 hover:file:bg-zinc-700 cursor-pointer"
                            @change="onImageChange"
                        />
                    </div>
                </div>

                <!-- Active status -->
                <div>
                    <label class="flex items-center gap-2 text-xs text-zinc-300 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            v-model="form.is_active"
                            class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                        />
                        <span>Категория активна и доступна для выбора</span>
                    </label>
                </div>
            </form>

            <template #footer>
                <AdminButton variant="ghost" @click="closeForm">Отмена</AdminButton>
                <AdminButton variant="primary" :loading="form.processing" @click="submit">
                    {{ editingId ? 'Сохранить изменения' : 'Создать категорию' }}
                </AdminButton>
            </template>
        </AdminModal>
    </div>
</template>
