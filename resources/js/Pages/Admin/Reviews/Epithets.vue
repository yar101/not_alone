<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    epithets: Array,
});

const editingId    = ref(null);
const editingLabel = ref('');
const newLabel     = ref('');

function startEdit(item) {
    editingId.value    = item.id;
    editingLabel.value = item.label;
}
function cancelEdit() {
    editingId.value    = null;
    editingLabel.value = '';
}
function saveEdit(item) {
    if (!editingLabel.value.trim()) return;
    router.patch(route('admin.review-epithets.update', item.id), {
        label: editingLabel.value.trim(),
    }, { preserveScroll: true, onSuccess: cancelEdit });
}
function deleteEpithet(item) {
    if (!confirm(`Удалить «${item.label}»?`)) return;
    router.delete(route('admin.review-epithets.destroy', item.id), { preserveScroll: true });
}
function move(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= props.epithets.length) return;
    const items = props.epithets.map((e, i) => ({ id: e.id, sort_order: i }));
    [items[index].sort_order, items[target].sort_order] = [items[target].sort_order, items[index].sort_order];
    router.post(route('admin.review-epithets.reorder'), { items }, { preserveScroll: true });
}
function addEpithet() {
    if (!newLabel.value.trim()) return;
    router.post(route('admin.review-epithets.store'), {
        label: newLabel.value.trim(),
    }, { preserveScroll: true, onSuccess: () => { newLabel.value = ''; } });
}
</script>

<template>
    <div>
        <div class="page-header">
            <h1 class="page-title">Отзывы — Эпитеты</h1>
            <p class="page-subtitle">Облака эпитетов, которые заказчик выбирает при оставлении отзыва</p>
        </div>

        <div class="section">
            <div class="reason-list">
                <div v-for="(item, index) in epithets" :key="item.id" class="reason-row">
                    <div class="order-btns">
                        <button class="order-btn" :disabled="index === 0" @click="move(index, -1)">↑</button>
                        <button class="order-btn" :disabled="index === epithets.length - 1" @click="move(index, 1)">↓</button>
                    </div>
                    <template v-if="editingId === item.id">
                        <input v-model="editingLabel" class="edit-input" @keydown.enter="saveEdit(item)" @keydown.escape="cancelEdit" />
                        <button class="btn-save" @click="saveEdit(item)">Сохранить</button>
                        <button class="btn-cancel" @click="cancelEdit">Отмена</button>
                    </template>
                    <template v-else>
                        <span class="reason-label">{{ item.label }}</span>
                        <button class="btn-edit" @click="startEdit(item)">Изменить</button>
                        <button class="btn-delete" @click="deleteEpithet(item)">Удалить</button>
                    </template>
                </div>
                <div v-if="!epithets.length" class="empty-msg">Нет эпитетов</div>
            </div>

            <div class="add-row">
                <input
                    v-model="newLabel"
                    class="add-input"
                    placeholder="Новый эпитет..."
                    maxlength="255"
                    @keydown.enter.prevent="addEpithet"
                />
                <button type="button" class="btn-add" :disabled="!newLabel.trim()" @click="addEpithet">
                    Добавить
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.page-header { margin-bottom: 1.5rem; }
.page-title  { font-size: 1.4rem; color: #fff; margin: 0 0 0.3rem; }
.page-subtitle { font-size: 0.82rem; color: rgba(255,255,255,0.38); margin: 0; }

.section { background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.08); padding: 1.25rem; }

.reason-list { display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 0.85rem; }

.reason-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 0.6rem;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
}

.order-btns { display: flex; flex-direction: column; gap: 1px; }
.order-btn {
    padding: 0 0.35rem;
    border: 1px solid rgba(255,255,255,0.1);
    background: transparent;
    color: rgba(255,255,255,0.35);
    font-size: 0.68rem;
    cursor: pointer;
    line-height: 1.4;
    font-family: inherit;
}
.order-btn:hover:not(:disabled) { color: rgba(255,255,255,0.7); border-color: rgba(255,255,255,0.25); }
.order-btn:disabled { opacity: 0.25; cursor: default; }

.reason-label { flex: 1; font-size: 0.88rem; color: rgba(255,255,255,0.8); }

.edit-input {
    flex: 1;
    padding: 0.3rem 0.55rem;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.15);
    color: #fff;
    font-size: 0.88rem;
    font-family: inherit;
    outline: none;
}
.edit-input:focus { border-color: rgba(100,200,255,0.4); }

.empty-msg { font-size: 0.82rem; color: rgba(255,255,255,0.25); padding: 0.4rem 0; }

.btn-edit   { padding: 0.25rem 0.6rem; background: rgba(100,200,255,0.08); border: 1px solid rgba(100,200,255,0.2); color: rgba(100,200,255,0.7); font-size: 0.78rem; cursor: pointer; font-family: inherit; }
.btn-delete { padding: 0.25rem 0.6rem; background: rgba(255,80,80,0.08);   border: 1px solid rgba(255,80,80,0.2);   color: rgba(255,100,100,0.7); font-size: 0.78rem; cursor: pointer; font-family: inherit; }
.btn-save   { padding: 0.25rem 0.6rem; background: rgba(80,200,120,0.08);  border: 1px solid rgba(80,200,120,0.2);  color: rgba(80,220,130,0.8);  font-size: 0.78rem; cursor: pointer; font-family: inherit; }
.btn-cancel { padding: 0.25rem 0.6rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.12); color: rgba(255,255,255,0.4); font-size: 0.78rem; cursor: pointer; font-family: inherit; }
.btn-add    { padding: 0.35rem 0.85rem; background: rgba(100,200,255,0.1); border: 1px solid rgba(100,200,255,0.25); color: rgba(100,200,255,0.85); font-size: 0.85rem; cursor: pointer; font-family: inherit; }
.btn-add:disabled { opacity: 0.3; cursor: default; }
.btn-edit:hover, .btn-delete:hover, .btn-save:hover, .btn-cancel:hover, .btn-add:hover:not(:disabled) { filter: brightness(1.2); }

.add-row { display: flex; gap: 0.5rem; align-items: center; }
.add-input {
    flex: 1;
    padding: 0.4rem 0.65rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    color: #fff;
    font-size: 0.88rem;
    font-family: inherit;
    outline: none;
}
.add-input:focus { border-color: rgba(100,200,255,0.35); }
.add-input::placeholder { color: rgba(255,255,255,0.2); }
</style>
