<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Plus } from '@element-plus/icons-vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CreateButton from '@/Components/CreateButton.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    categories: Array,
});

const showForm  = ref(false);
const editingId = ref(null);

const form = useForm({
    category_id: '',
    name_ru:     '',
});

function openAdd() {
    editingId.value    = null;
    form.reset();
    showForm.value     = true;
}

function openEdit(interest) {
    editingId.value    = interest.id;
    form.category_id   = interest.category_id;
    form.name_ru       = interest.name_ru;
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
    if (!confirm('Удалить интерес?')) return;
    router.delete(route('admin.interests.destroy', id), { preserveScroll: true });
}

// Category management
const showCatForm  = ref(false);
const editingCatId = ref(null);
const catForm = useForm({ name_ru: '' });

function openAddCat() {
    editingCatId.value = null;
    catForm.reset();
    showCatForm.value  = true;
}

function openEditCat(cat) {
    editingCatId.value = cat.id;
    catForm.name_ru    = cat.name_ru;
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
    if (!confirm('Удалить категорию и все её интересы?')) return;
    router.delete(route('admin.interests.categories.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <div>
        <div class="page-header">
            <h1 class="page-title">Интересы</h1>
            <div class="header-actions">
                <button class="btn-add-cat" @click="openAddCat">+ Категория</button>
                <CreateButton @click="openAdd">
                    <template #icon><el-icon><Plus /></el-icon></template>
                    Добавить
                </CreateButton>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Пользователей</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="cat in categories" :key="cat.id">
                        <tr class="cat-row">
                            <td class="cat-cell">{{ cat.name_ru }}</td>
                            <td></td>
                            <td>
                                <div class="actions">
                                    <button class="btn-edit" @click="openEditCat(cat)">Переименовать</button>
                                    <button class="btn-danger" @click="destroyCat(cat.id)">Удалить</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="interest in cat.interests" :key="interest.id">
                            <td class="interest-name">{{ interest.name_ru }}</td>
                            <td>{{ interest.users_count }}</td>
                            <td>
                                <div class="actions">
                                    <button class="btn-edit" @click="openEdit(interest)">Изменить</button>
                                    <button class="btn-danger" @click="destroy(interest.id)">Удалить</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!cat.interests.length">
                            <td colspan="3" class="empty-cat-row">— нет интересов</td>
                        </tr>
                    </template>
                    <tr v-if="!categories.length">
                        <td colspan="3" class="empty-row">Категорий нет</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Teleport to="body">
            <div v-if="showForm" class="overlay" @click.self="closeForm">
                <div class="modal">
                    <div class="modal__header">
                        <span>{{ editingId ? 'Редактировать интерес' : 'Новый интерес' }}</span>
                        <button class="modal__close" @click="closeForm">✕</button>
                    </div>
                    <form @submit.prevent="submit" class="modal__body">
                        <div class="field">
                            <label>Категория</label>
                            <select v-model="form.category_id" class="input" :class="{ 'input--err': form.errors.category_id }">
                                <option value="" disabled>Выберите категорию</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name_ru }}</option>
                            </select>
                            <p v-if="form.errors.category_id" class="err">{{ form.errors.category_id }}</p>
                        </div>
                        <div class="field">
                            <label>Название</label>
                            <input v-model="form.name_ru" class="input" :class="{ 'input--err': form.errors.name_ru }" placeholder="Аниме" />
                            <p v-if="form.errors.name_ru" class="err">{{ form.errors.name_ru }}</p>
                        </div>
                        <div class="modal__actions">
                            <button type="button" class="btn-cancel" @click="closeForm">Отмена</button>
                            <button type="submit" class="btn-submit" :disabled="form.processing">Сохранить</button>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="showCatForm" class="overlay" @click.self="closeCatForm">
                <div class="modal">
                    <div class="modal__header">
                        <span>{{ editingCatId ? 'Переименовать категорию' : 'Новая категория' }}</span>
                        <button class="modal__close" @click="closeCatForm">✕</button>
                    </div>
                    <form @submit.prevent="submitCat" class="modal__body">
                        <div class="field">
                            <label>Название</label>
                            <input v-model="catForm.name_ru" class="input" :class="{ 'input--err': catForm.errors.name_ru }" placeholder="Спорт" />
                            <p v-if="catForm.errors.name_ru" class="err">{{ catForm.errors.name_ru }}</p>
                        </div>
                        <div class="modal__actions">
                            <button type="button" class="btn-cancel" @click="closeCatForm">Отмена</button>
                            <button type="submit" class="btn-submit" :disabled="catForm.processing">Сохранить</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.page-title  { font-size: 1.4rem; color: #fff; margin: 0; }
.header-actions { display: flex; align-items: center; gap: 0.75rem; }
.btn-add-cat { padding: 0.45rem 0.9rem; border: 1px solid rgba(190,145,255,0.3); border-radius: 3px; background: rgba(190,145,255,0.07); color: rgba(190,145,255,0.85); font-family: inherit; font-size: 0.82rem; cursor: pointer; transition: background 0.15s, border-color 0.15s; }
.btn-add-cat:hover { background: rgba(190,145,255,0.15); border-color: rgba(190,145,255,0.5); }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 0.6rem 1rem; font-size: 0.72rem; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 0.07em; border-bottom: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.025); }
.data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.07); color: rgba(255,255,255,0.8); font-size: 0.88rem; }
.data-table tr:hover td { background: rgba(255,255,255,0.03); }
.cat-row td { background: rgba(155,110,232,0.06); }
.cat-cell { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(190,145,255,0.7); font-weight: 600; padding: 0.5rem 1rem; }
.interest-name { padding-left: 1.75rem; }
.empty-row, .empty-cat-row { text-align: center; color: rgba(255,255,255,0.25); }
.empty-cat-row { padding-left: 1.75rem; text-align: left; }
.actions { display: flex; gap: 0.5rem; }
.btn-edit, .btn-danger { padding: 0.3rem 0.7rem; border-radius: 3px; font-size: 0.78rem; cursor: pointer; font-family: inherit; transition: background 0.15s; }
.btn-edit   { border: 1px solid rgba(255,255,255,0.15); background: transparent; color: rgba(255,255,255,0.6); }
.btn-edit:hover   { background: rgba(255,255,255,0.08); }
.btn-danger { border: 1px solid rgba(239,68,68,0.3); background: transparent; color: rgba(239,68,68,0.7); }
.btn-danger:hover { background: rgba(239,68,68,0.1); }
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #0a0a0f; border: 1px solid rgba(255,255,255,0.12); border-radius: 3px; width: 100%; max-width: 380px; margin: 1rem; }
.modal__header { display: flex; align-items: center; justify-content: space-between; padding: 0.8rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.07); font-size: 0.88rem; color: rgba(255,255,255,0.85); font-weight: 600; }
.modal__close { background: none; border: none; color: rgba(255,255,255,0.4); cursor: pointer; font-size: 1rem; }
.modal__body { padding: 1rem; display: flex; flex-direction: column; gap: 0.8rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.35); }
.input { padding: 0.45rem 0.7rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 3px; color: rgba(255,255,255,0.85); font-family: inherit; font-size: 0.88rem; outline: none; transition: border-color 0.15s; box-sizing: border-box; width: 100%; }
.input:focus { border-color: rgba(190,145,255,0.45); }
.input--err { border-color: rgba(239,68,68,0.5); }
.err { font-size: 0.75rem; color: rgba(239,68,68,0.8); margin: 0; }
.modal__actions { display: flex; justify-content: flex-end; gap: 0.5rem; padding-top: 0.25rem; }
.btn-cancel { padding: 0.45rem 0.9rem; border: 1px solid rgba(255,255,255,0.12); border-radius: 3px; background: transparent; color: rgba(255,255,255,0.4); font-family: inherit; font-size: 0.82rem; cursor: pointer; }
.btn-submit { padding: 0.45rem 1rem; border: 1px solid rgba(190,145,255,0.45); border-radius: 3px; background: rgba(190,145,255,0.1); color: rgba(255,255,255,0.9); font-family: inherit; font-size: 0.82rem; cursor: pointer; }
.btn-submit:disabled { opacity: 0.5; cursor: default; }
</style>
