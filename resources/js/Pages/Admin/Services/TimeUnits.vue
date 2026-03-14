<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Plus } from '@element-plus/icons-vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CreateButton from '@/Components/CreateButton.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    timeUnits: Array,
});

const showForm  = ref(false);
const editingId = ref(null);

const form = useForm({
    name:       '',
    sort_order: 0,
    is_active:  true,
});

function openAdd() {
    editingId.value = null;
    form.reset();
    form.is_active = true;
    showForm.value = true;
}

function openEdit(unit) {
    editingId.value = unit.id;
    form.name       = unit.name;
    form.sort_order = unit.sort_order;
    form.is_active  = unit.is_active;
    showForm.value  = true;
}

function closeForm() {
    showForm.value  = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    if (editingId.value) {
        form.patch(route('admin.services.time-units.update', editingId.value), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    } else {
        form.post(route('admin.services.time-units.store'), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    }
}

function destroy(id) {
    if (!confirm('Удалить единицу времени?')) return;
    router.delete(route('admin.services.time-units.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <div>
        <div class="page-header">
            <h1 class="page-title">Единицы времени</h1>
            <CreateButton @click="openAdd">
                <template #icon><el-icon><Plus /></el-icon></template>
                Добавить
            </CreateButton>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Порядок</th>
                        <th>Название</th>
                        <th>Активна</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="unit in timeUnits" :key="unit.id">
                        <td>{{ unit.sort_order }}</td>
                        <td>{{ unit.name }}</td>
                        <td><span :class="['badge', unit.is_active ? 'badge--on' : 'badge--off']">{{ unit.is_active ? 'Да' : 'Нет' }}</span></td>
                        <td>
                            <div class="actions">
                                <button class="btn-edit" @click="openEdit(unit)">Изменить</button>
                                <button class="btn-danger" @click="destroy(unit.id)">Удалить</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!timeUnits.length">
                        <td colspan="4" class="empty-row">Единиц нет</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Teleport to="body">
            <div v-if="showForm" class="overlay" @click.self="closeForm">
                <div class="modal">
                    <div class="modal__header">
                        <span>{{ editingId ? 'Редактировать' : 'Новая единица' }}</span>
                        <button class="modal__close" @click="closeForm">✕</button>
                    </div>
                    <form @submit.prevent="submit" class="modal__body">
                        <div class="field">
                            <label>Название</label>
                            <input v-model="form.name" class="input" :class="{ 'input--err': form.errors.name }" placeholder="15 минут" />
                            <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
                        </div>
                        <div class="field">
                            <label>Порядок сортировки</label>
                            <input v-model.number="form.sort_order" type="number" min="0" class="input" />
                        </div>
                        <div class="field field--row">
                            <label>Активна</label>
                            <input v-model="form.is_active" type="checkbox" />
                        </div>
                        <div class="modal__actions">
                            <button type="button" class="btn-cancel" @click="closeForm">Отмена</button>
                            <button type="submit" class="btn-submit" :disabled="form.processing">Сохранить</button>
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
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 0.6rem 1rem; font-size: 0.72rem; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 0.07em; border-bottom: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.025); }
.data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.07); color: rgba(255,255,255,0.8); font-size: 0.88rem; }
.data-table tr:hover td { background: rgba(255,255,255,0.03); }
.empty-row { text-align: center; color: rgba(255,255,255,0.25); }
.badge { padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.72rem; font-weight: 600; }
.badge--on  { background: rgba(74,222,128,0.1); color: rgba(74,222,128,0.8); }
.badge--off { background: rgba(239,68,68,0.1);  color: rgba(239,68,68,0.7); }
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
.field--row { flex-direction: row; align-items: center; gap: 0.5rem; }
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
