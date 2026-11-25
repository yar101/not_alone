<script setup>
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import { Plus, EditPen, Delete, Folder, Star } from '@element-plus/icons-vue';
import { ElMessageBox } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    categories: Array,
});

// Interest modal
const showForm  = ref(false);
const editingId = ref(null);

const form = useForm({
    category_id: '',
    name_ru:     '',
    name_en:     '',
});

function openAdd(catId = '') {
    editingId.value    = null;
    form.reset();
    form.category_id   = catId || (props.categories?.[0]?.id ?? '');
    showForm.value     = true;
}

function openEdit(interest) {
    editingId.value    = interest.id;
    form.category_id   = interest.category_id;
    form.name_ru       = interest.name_ru;
    form.name_en       = interest.name_en ?? '';
    showForm.value     = true;
}

function closeForm() {
    showForm.value  = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    if (editingId.value) {
        form.patch(route('admin.interests.update', editingId.value), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    } else {
        form.post(route('admin.interests.store'), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    }
}

function destroy(id) {
    ElMessageBox.confirm('Удалить интерес?', 'Удаление интереса', {
        confirmButtonText: 'Удалить',
        cancelButtonText: 'Отмена',
        type: 'warning',
        confirmButtonClass: 'el-button--danger',
    }).then(() => {
        router.delete(route('admin.interests.destroy', id), { preserveScroll: true });
    }).catch(() => {});
}

// Category management
const showCatForm  = ref(false);
const editingCatId = ref(null);
const catForm = useForm({ name_ru: '', name_en: '' });

function openAddCat() {
    editingCatId.value = null;
    catForm.reset();
    showCatForm.value  = true;
}

function openEditCat(cat) {
    editingCatId.value = cat.id;
    catForm.name_ru    = cat.name_ru;
    catForm.name_en    = cat.name_en ?? '';
    showCatForm.value  = true;
}

function closeCatForm() {
    showCatForm.value  = false;
    editingCatId.value = null;
    catForm.reset();
    catForm.clearErrors();
}

function submitCat() {
    if (editingCatId.value) {
        catForm.patch(route('admin.interests.categories.update', editingCatId.value), {
            preserveScroll: true,
            onSuccess: closeCatForm,
        });
    } else {
        catForm.post(route('admin.interests.categories.store'), {
            preserveScroll: true,
            onSuccess: closeCatForm,
        });
    }
}

function destroyCat(id) {
    ElMessageBox.confirm('Удалить категорию и все интересы в ней?', 'Внимание', {
        confirmButtonText: 'Удалить',
        cancelButtonText: 'Отмена',
        type: 'warning',
        confirmButtonClass: 'el-button--danger',
    }).then(() => {
        router.delete(route('admin.interests.categories.destroy', id), { preserveScroll: true });
    }).catch(() => {});
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-100">Интересы и хобби</h1>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Категории и теги интересов для профилей пользователей и рекомендаций
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('admin.interests.suggestions.index')"
                    class="px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-xs font-medium text-zinc-300 hover:text-white hover:bg-zinc-800 transition"
                >
                    Предложения пользователей →
                </Link>
                <AdminButton variant="outline" size="sm" @click="openAddCat">
                    <el-icon class="mr-1"><Folder /></el-icon>
                    Категория
                </AdminButton>
                <AdminButton variant="primary" size="sm" @click="openAdd()">
                    <el-icon class="mr-1.5"><Plus /></el-icon>
                    Добавить интерес
                </AdminButton>
            </div>
        </div>

        <!-- Categories & Interests Grid -->
        <div class="space-y-6">
            <div
                v-for="cat in categories"
                :key="cat.id"
                class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 backdrop-blur-sm space-y-4"
            >
                <!-- Category Header -->
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div class="flex items-center gap-2.5">
                        <el-icon class="text-violet-400"><Folder /></el-icon>
                        <h2 class="text-sm font-semibold text-zinc-100">{{ cat.name_ru }}</h2>
                        <span v-if="cat.name_en" class="text-xs text-zinc-500 font-mono">({{ cat.name_en }})</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-zinc-800 text-zinc-400">
                            {{ cat.interests?.length || 0 }} тегов
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            class="text-xs text-violet-400 hover:text-violet-300 px-2 py-1 rounded hover:bg-zinc-800 transition"
                            @click="openAdd(cat.id)"
                        >
                            + Интерес
                        </button>
                        <button
                            type="button"
                            class="p-1 text-zinc-500 hover:text-zinc-200 transition"
                            title="Редактировать категорию"
                            @click="openEditCat(cat)"
                        >
                            <el-icon><EditPen /></el-icon>
                        </button>
                        <button
                            type="button"
                            class="p-1 text-zinc-500 hover:text-rose-400 transition"
                            title="Удалить категорию"
                            @click="destroyCat(cat.id)"
                        >
                            <el-icon><Delete /></el-icon>
                        </button>
                    </div>
                </div>

                <!-- Interests Chips Grid -->
                <div v-if="cat.interests?.length" class="flex flex-wrap gap-2">
                    <div
                        v-for="item in cat.interests"
                        :key="item.id"
                        class="group px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-950 flex items-center gap-2 text-xs transition hover:border-zinc-700"
                    >
                        <span class="font-medium text-zinc-200">{{ item.name_ru }}</span>
                        <span v-if="item.name_en" class="text-[10px] text-zinc-500 font-mono">({{ item.name_en }})</span>

                        <div class="flex items-center gap-0.5 opacity-60 group-hover:opacity-100 transition">
                            <button
                                type="button"
                                class="text-zinc-500 hover:text-zinc-200 p-0.5"
                                @click="openEdit(item)"
                            >
                                <el-icon :size="12"><EditPen /></el-icon>
                            </button>
                            <button
                                type="button"
                                class="text-zinc-500 hover:text-rose-400 p-0.5"
                                @click="destroy(item.id)"
                            >
                                <el-icon :size="12"><Delete /></el-icon>
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="text-xs text-zinc-500 py-2">
                    В этой категории пока нет интересов.
                </div>
            </div>

            <div v-if="!categories?.length" class="p-12 text-center text-xs text-zinc-500 rounded-xl border border-zinc-800 bg-zinc-900/60">
                Категорий интересов пока нет. Создайте первую категорию.
            </div>
        </div>

        <!-- Add/Edit Interest Modal -->
        <AdminModal
            :show="showForm"
            :title="editingId ? 'Редактировать интерес' : 'Новый интерес'"
            @close="closeForm"
        >
            <form @submit.prevent="submit" class="space-y-4 text-xs">
                <div class="space-y-1.5">
                    <label class="font-medium text-zinc-300">Категория <span class="text-rose-400">*</span></label>
                    <select
                        v-model="form.category_id"
                        required
                        class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 focus:outline-none focus:border-violet-500"
                    >
                        <option v-for="c in categories" :key="c.id" :value="c.id">
                            {{ c.name_ru }}
                        </option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="font-medium text-zinc-300">Название (RU) <span class="text-rose-400">*</span></label>
                    <input
                        v-model="form.name_ru"
                        type="text"
                        required
                        class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                    />
                    <p v-if="form.errors.name_ru" class="text-rose-400 text-[11px]">{{ form.errors.name_ru }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="font-medium text-zinc-300">Название (EN)</label>
                    <input
                        v-model="form.name_en"
                        type="text"
                        class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                    />
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-800">
                    <AdminButton variant="outline" size="sm" type="button" @click="closeForm">
                        Отмена
                    </AdminButton>
                    <AdminButton variant="primary" size="sm" type="submit" :loading="form.processing">
                        Сохранить
                    </AdminButton>
                </div>
            </form>
        </AdminModal>

        <!-- Add/Edit Category Modal -->
        <AdminModal
            :show="showCatForm"
            :title="editingCatId ? 'Редактировать категорию' : 'Новая категория интересов'"
            @close="closeCatForm"
        >
            <form @submit.prevent="submitCat" class="space-y-4 text-xs">
                <div class="space-y-1.5">
                    <label class="font-medium text-zinc-300">Название категории (RU) <span class="text-rose-400">*</span></label>
                    <input
                        v-model="catForm.name_ru"
                        type="text"
                        required
                        class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                    />
                    <p v-if="catForm.errors.name_ru" class="text-rose-400 text-[11px]">{{ catForm.errors.name_ru }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="font-medium text-zinc-300">Название категории (EN)</label>
                    <input
                        v-model="catForm.name_en"
                        type="text"
                        class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                    />
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-800">
                    <AdminButton variant="outline" size="sm" type="button" @click="closeCatForm">
                        Отмена
                    </AdminButton>
                    <AdminButton variant="primary" size="sm" type="submit" :loading="catForm.processing">
                        Сохранить
                    </AdminButton>
                </div>
            </form>
        </AdminModal>
    </div>
</template>
