<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    chat_block_reasons: Array,
    user_ban_reasons:   Array,
});

// ─── Editing ──────────────────────────────────────────────────────────────────
const editingId    = ref(null);
const editingLabel = ref('');

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
    router.patch(route('admin.ban-reasons.update', item.id), {
        label: editingLabel.value.trim(),
    }, { preserveScroll: true, onSuccess: cancelEdit });
}

// ─── Delete ───────────────────────────────────────────────────────────────────
function deleteReason(item) {
    if (!confirm(`Удалить «${item.label}»?`)) return;
    router.delete(route('admin.ban-reasons.destroy', item.id), { preserveScroll: true });
}

// ─── Reorder ──────────────────────────────────────────────────────────────────
function move(list, index, direction) {
    const target = index + direction;
    if (target < 0 || target >= list.length) return;
    const items = list.map((r, i) => ({ id: r.id, sort_order: i }));
    [items[index].sort_order, items[target].sort_order] = [items[target].sort_order, items[index].sort_order];
    router.post(route('admin.ban-reasons.reorder'), { items }, { preserveScroll: true });
}

// ─── Add ──────────────────────────────────────────────────────────────────────
const newChatLabel = ref('');
const newBanLabel  = ref('');

function addChatReason() {
    if (!newChatLabel.value.trim()) return;
    router.post(route('admin.ban-reasons.store'), {
        label: newChatLabel.value.trim(),
        type: 'chat_block',
    }, { preserveScroll: true, onSuccess: () => { newChatLabel.value = ''; } });
}

function addBanReason() {
    if (!newBanLabel.value.trim()) return;
    router.post(route('admin.ban-reasons.store'), {
        label: newBanLabel.value.trim(),
        type: 'user_ban',
    }, { preserveScroll: true, onSuccess: () => { newBanLabel.value = ''; } });
}
</script>

<template>
    <div>
        <div class="page-header">
            <h1 class="page-title">Причины блокировок</h1>
        </div>

        <div class="sections">
            <!-- Chat block reasons -->
            <div class="section">
                <h2 class="section-title">Блокировки в чате</h2>

                <div class="reason-list">
                    <div v-for="(item, index) in chat_block_reasons" :key="item.id" class="reason-row">
                        <div class="order-btns">
                            <button class="order-btn" :disabled="index === 0" @click="move(chat_block_reasons, index, -1)">↑</button>
                            <button class="order-btn" :disabled="index === chat_block_reasons.length - 1" @click="move(chat_block_reasons, index, 1)">↓</button>
                        </div>
                        <template v-if="editingId === item.id">
                            <input v-model="editingLabel" class="edit-input" @keydown.enter="saveEdit(item)" @keydown.escape="cancelEdit" />
                            <button class="btn-save" @click="saveEdit(item)">Сохранить</button>
                            <button class="btn-cancel" @click="cancelEdit">Отмена</button>
                        </template>
                        <template v-else>
                            <span class="reason-label">{{ item.label }}</span>
                            <button class="btn-edit" @click="startEdit(item)">Изменить</button>
                            <button class="btn-delete" @click="deleteReason(item)">Удалить</button>
                        </template>
                    </div>
                    <div v-if="!chat_block_reasons.length" class="empty-msg">Нет причин</div>
                </div>

                <div class="add-row">
                    <input
                        v-model="newChatLabel"
                        class="add-input"
                        placeholder="Новая причина..."
                        maxlength="255"
                        @keydown.enter.prevent="addChatReason"
                    />
                    <button
                        type="button"
                        class="btn-add"
                        :disabled="!newChatLabel.trim()"
                        @click="addChatReason"
                    >Добавить</button>
                </div>
            </div>

            <!-- User ban reasons -->
            <div class="section">
                <h2 class="section-title">Блокировки пользователей</h2>

                <div class="reason-list">
                    <div v-for="(item, index) in user_ban_reasons" :key="item.id" class="reason-row">
                        <div class="order-btns">
                            <button class="order-btn" :disabled="index === 0" @click="move(user_ban_reasons, index, -1)">↑</button>
                            <button class="order-btn" :disabled="index === user_ban_reasons.length - 1" @click="move(user_ban_reasons, index, 1)">↓</button>
                        </div>
                        <template v-if="editingId === item.id">
                            <input v-model="editingLabel" class="edit-input" @keydown.enter="saveEdit(item)" @keydown.escape="cancelEdit" />
                            <button class="btn-save" @click="saveEdit(item)">Сохранить</button>
                            <button class="btn-cancel" @click="cancelEdit">Отмена</button>
                        </template>
                        <template v-else>
                            <span class="reason-label">{{ item.label }}</span>
                            <button class="btn-edit" @click="startEdit(item)">Изменить</button>
                            <button class="btn-delete" @click="deleteReason(item)">Удалить</button>
                        </template>
                    </div>
                    <div v-if="!user_ban_reasons.length" class="empty-msg">Нет причин</div>
                </div>

                <div class="add-row">
                    <input
                        v-model="newBanLabel"
                        class="add-input"
                        placeholder="Новая причина..."
                        maxlength="255"
                        @keydown.enter.prevent="addBanReason"
                    />
                    <button
                        type="button"
                        class="btn-add"
                        :disabled="!newBanLabel.trim()"
                        @click="addBanReason"
                    >Добавить</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.page-header { margin-bottom: 1.5rem; }
.page-title  { font-size: 1.4rem; color: #fff; margin: 0; }

.sections { display: flex; flex-direction: column; gap: 1.5rem; }
.section  { background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.08); padding: 1.25rem; }
.section-title { font-size: 0.9rem; color: rgba(255,255,255,0.6); font-weight: 600; text-transform: uppercase; letter-spacing: 0.07em; margin: 0 0 1rem; }

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
    border: 1px solid rgba(190,145,255,0.4);
    color: rgba(255,255,255,0.9);
    font-family: inherit;
    font-size: 0.88rem;
    outline: none;
}

.btn-edit   { padding: 0.22rem 0.6rem; border: 1px solid rgba(155,110,232,0.3); background: transparent; color: rgba(190,145,255,0.7); font-family: inherit; font-size: 0.78rem; cursor: pointer; }
.btn-edit:hover { background: rgba(155,110,232,0.1); }
.btn-save   { padding: 0.22rem 0.6rem; border: 1px solid rgba(76,222,143,0.35); background: rgba(76,222,143,0.07); color: #4cde8f; font-family: inherit; font-size: 0.78rem; cursor: pointer; }
.btn-save:hover { background: rgba(76,222,143,0.18); }
.btn-cancel { padding: 0.22rem 0.6rem; border: 1px solid rgba(255,255,255,0.12); background: transparent; color: rgba(255,255,255,0.35); font-family: inherit; font-size: 0.78rem; cursor: pointer; }
.btn-delete { padding: 0.22rem 0.6rem; border: 1px solid rgba(239,68,68,0.3); background: transparent; color: rgba(239,68,68,0.65); font-family: inherit; font-size: 0.78rem; cursor: pointer; }
.btn-delete:hover { background: rgba(239,68,68,0.1); }

.empty-msg { font-size: 0.83rem; color: rgba(255,255,255,0.25); padding: 0.5rem 0; }

.add-row { display: flex; gap: 0.5rem; }
.add-input {
    flex: 1;
    padding: 0.38rem 0.65rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.85);
    font-family: inherit;
    font-size: 0.88rem;
    outline: none;
}
.add-input:focus { border-color: rgba(190,145,255,0.45); }
.add-input::placeholder { color: rgba(255,255,255,0.2); }
.btn-add {
    padding: 0.38rem 0.85rem;
    border: 1px solid rgba(155,110,232,0.4);
    background: rgba(155,110,232,0.1);
    color: rgba(190,145,255,0.85);
    font-family: inherit;
    font-size: 0.82rem;
    cursor: pointer;
    white-space: nowrap;
}
.btn-add:hover:not(:disabled) { background: rgba(155,110,232,0.2); }
.btn-add:disabled { opacity: 0.35; cursor: default; }
</style>
