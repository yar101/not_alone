<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    limits:    Array,
    timeUnits: Array,
});

const showForm  = ref(false);
const editingId = ref(null);

const form = useForm({
    time_unit_id: null,
    max_price:    '',
});

function openAdd() {
    editingId.value    = null;
    form.time_unit_id  = null;
    form.max_price     = '';
    showForm.value     = true;
}

function openEdit(limit) {
    editingId.value   = limit.id;
    form.time_unit_id = limit.time_unit_id;
    form.max_price    = limit.max_price;
    showForm.value    = true;
}

function closeForm() {
    showForm.value  = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    if (editingId.value) {
        form.patch(route('admin.services.price-limits.update', editingId.value), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    } else {
        form.post(route('admin.services.price-limits.store'), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    }
}

function destroy(id) {
    if (!confirm('Удалить лимит цены?')) return;
    router.delete(route('admin.services.price-limits.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <div>
        <div class="page-header">
            <h1 class="page-title">Лимиты цен</h1>
            <button class="btn-primary" @click="openAdd">+ Добавить</button>
        </div>

        <p class="hint">Лимиты применяются к айдолам с рейтингом ниже порога (настройки платформы).</p>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Единица времени</th>
                        <th>Макс. цена (₽)</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="limit in limits" :key="limit.id">
                        <td>{{ limit.time_unit?.name ?? '—' }}</td>
                        <td>{{ limit.max_price.toLocaleString('ru') }} ₽</td>
                        <td>
                            <div class="actions">
                                <button class="btn-edit" @click="openEdit(limit)">Изменить</button>
                                <button class="btn-danger" @click="destroy(limit.id)">Удалить</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!limits.length">
                        <td colspan="3" class="empty-row">Лимитов нет</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Teleport to="body">
            <div v-if="showForm" class="overlay" @click.self="closeForm">
                <div class="modal">
                    <div class="modal__header">
                        <span>{{ editingId ? 'Редактировать лимит' : 'Новый лимит' }}</span>
                        <button class="modal__close" @click="closeForm">✕</button>
                    </div>
                    <form @submit.prevent="submit" class="modal__body">
                        <div class="field">
                            <label>Единица времени</label>
                            <AppSelect
                                v-model="form.time_unit_id"
                                :options="timeUnits.map(u => ({ value: u.id, label: u.name }))"
                                placeholder="Выберите..."
                                :error="!!form.errors.time_unit_id"
                            />
                            <p v-if="form.errors.time_unit_id" class="err">{{ form.errors.time_unit_id }}</p>
                        </div>
                        <div class="field">
                            <label>Максимальная цена (₽)</label>
                            <input v-model.number="form.max_price" type="number" min="1" class="input" :class="{ 'input--err': form.errors.max_price }" placeholder="300" />
                            <p v-if="form.errors.max_price" class="err">{{ form.errors.max_price }}</p>
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
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
.page-title  { font-size: 1.4rem; color: #fff; margin: 0; }
.hint { font-size: 0.82rem; color: rgba(255,255,255,0.35); margin: 0 0 1.25rem; }
.btn-primary { padding: 0.45rem 1rem; border: 1px solid rgba(254,40,162,0.45); border-radius: 3px; background: rgba(254,40,162,0.1); color: rgba(254,40,162,0.9); font-size: 0.82rem; cursor: pointer; font-family: inherit; transition: background 0.15s; }
.btn-primary:hover { background: rgba(254,40,162,0.2); }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 0.6rem 1rem; font-size: 0.72rem; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 0.07em; border-bottom: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.025); }
.data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.07); color: rgba(255,255,255,0.8); font-size: 0.88rem; }
.data-table tr:hover td { background: rgba(255,255,255,0.03); }
.empty-row { text-align: center; color: rgba(255,255,255,0.25); }
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
.input { padding: 0.45rem 0.7rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 3px; color: rgba(255,255,255,0.85); font-family: inherit; font-size: 0.88rem; outline: none; transition: border-color 0.15s; box-sizing: border-box; width: 100%; appearance: none; }
.input:focus { border-color: rgba(254,40,162,0.45); }
.input--err { border-color: rgba(239,68,68,0.5); }
.err { font-size: 0.75rem; color: rgba(239,68,68,0.8); margin: 0; }
.modal__actions { display: flex; justify-content: flex-end; gap: 0.5rem; padding-top: 0.25rem; }
.btn-cancel { padding: 0.45rem 0.9rem; border: 1px solid rgba(255,255,255,0.12); border-radius: 3px; background: transparent; color: rgba(255,255,255,0.4); font-family: inherit; font-size: 0.82rem; cursor: pointer; }
.btn-submit { padding: 0.45rem 1rem; border: 1px solid rgba(254,40,162,0.45); border-radius: 3px; background: rgba(254,40,162,0.1); color: rgba(255,255,255,0.9); font-family: inherit; font-size: 0.82rem; cursor: pointer; }
.btn-submit:disabled { opacity: 0.5; cursor: default; }
</style>
