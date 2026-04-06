<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    news: Array,
});

// ─── Форматирование ───────────────────────────────────────────
function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('ru-RU', {
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function statusLabel(item) {
    if (!item.published_at) return 'Черновик';
    return new Date(item.published_at) <= new Date() ? 'Опубликовано' : 'Запланировано';
}

function statusClass(item) {
    if (!item.published_at) return 'status--draft';
    return new Date(item.published_at) <= new Date() ? 'status--published' : 'status--scheduled';
}


// ─── Редактирование ───────────────────────────────────────────
const editingId     = ref(null);
const editTitle     = ref('');
const editBody      = ref('');
const editExcerpt   = ref('');
const editDate      = ref('');
const editIsPinned  = ref(false);
const editImageFile = ref(null);

function startEdit(item) {
    editingId.value    = item.id;
    editTitle.value    = item.title;
    editBody.value     = item.body;
    editExcerpt.value  = item.excerpt || '';
    editDate.value     = item.published_at ? item.published_at.slice(0, 16) : '';
    editIsPinned.value = !!item.is_pinned;
    editImageFile.value = null;
}

function cancelEdit() {
    editingId.value = null;
    editImageFile.value = null;
}

function onEditFileChange(e) {
    editImageFile.value = e.target.files[0] || null;
}

function saveEdit(item) {
    const fd = new FormData();
    fd.append('_method',      'PATCH');
    fd.append('title',        editTitle.value);
    fd.append('body',         editBody.value);
    fd.append('excerpt',      editExcerpt.value);
    fd.append('published_at', editDate.value || '');
    fd.append('is_pinned',    editIsPinned.value ? '1' : '0');
    if (editImageFile.value) fd.append('image', editImageFile.value);

    router.post(route('admin.news.update', editingId.value), fd, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: cancelEdit,
    });
}

// ─── Удаление ─────────────────────────────────────────────────
function destroy(item) {
    if (!confirm(`Удалить «${item.title}»?`)) return;
    router.delete(route('admin.news.destroy', item.id), { preserveScroll: true });
}

// ─── Добавление ───────────────────────────────────────────────
const newTitle     = ref('');
const newBody      = ref('');
const newExcerpt   = ref('');
const newDate      = ref('');
const newIsPinned  = ref(false);
const newImageFile = ref(null);

function onNewFileChange(e) {
    newImageFile.value = e.target.files[0] || null;
}

function store() {
    if (!newTitle.value.trim() || !newBody.value.trim()) return;

    const fd = new FormData();
    fd.append('title',        newTitle.value.trim());
    fd.append('body',         newBody.value.trim());
    fd.append('excerpt',      newExcerpt.value.trim());
    fd.append('published_at', newDate.value || '');
    fd.append('is_pinned',    newIsPinned.value ? '1' : '0');
    if (newImageFile.value) fd.append('image', newImageFile.value);

    router.post(route('admin.news.store'), fd, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            newTitle.value     = '';
            newBody.value      = '';
            newExcerpt.value   = '';
            newDate.value      = '';
            newIsPinned.value  = false;
            newImageFile.value = null;
        },
    });
}
</script>

<template>
    <div>
        <div class="page-header">
            <h1 class="page-title">Новости</h1>
        </div>

        <!-- Список новостей -->
        <div class="section">
            <div class="news-list">
                <div v-for="item in news" :key="item.id" class="news-row">

                    <!-- Просмотр -->
                    <template v-if="editingId !== item.id">
                        <div class="news-meta">
                            <img v-if="item.image" :src="item.image" class="news-thumb" alt="" />
                            <span class="news-title">{{ item.title }}</span>
                            <span v-if="item.is_pinned" class="pin-badge">📌</span>
                            <span :class="['status-badge', statusClass(item)]">{{ statusLabel(item) }}</span>
                            <span class="news-date">{{ formatDate(item.published_at) }}</span>
                            <span class="views-count">◎ {{ item.views_count }}</span>
                        </div>
                        <p class="news-body-preview">{{ (item.excerpt || item.body).slice(0, 120) }}{{ (item.excerpt || item.body).length > 120 ? '…' : '' }}</p>
                        <div class="row-actions">
                            <button class="btn-edit" @click="startEdit(item)">Изменить</button>
                            <button class="btn-delete" @click="destroy(item)">Удалить</button>
                        </div>
                    </template>

                    <!-- Редактирование -->
                    <template v-else>
                        <div class="edit-form">
                            <input v-model="editTitle" class="edit-input" placeholder="Заголовок" maxlength="255" />
                            <textarea v-model="editExcerpt" class="edit-textarea edit-textarea--short" placeholder="Краткое описание (для карточки, необязательно)" rows="2" maxlength="500" />
                            <textarea v-model="editBody" class="edit-textarea" placeholder="Полный текст новости" rows="5" />

                            <div class="fields-row">
                                <div class="field-group field-group--pin">
                                    <label class="pin-label">
                                        <input type="checkbox" v-model="editIsPinned" class="pin-checkbox" />
                                        <span>Закрепить</span>
                                    </label>
                                </div>
                            </div>

                            <div class="file-row">
                                <label class="file-label">
                                    <span class="file-label__text">Изображение</span>
                                    <input type="file" accept="image/*" class="file-input" @change="onEditFileChange" />
                                    <span class="file-btn">{{ editImageFile ? editImageFile.name : 'Выбрать файл' }}</span>
                                </label>
                                <img v-if="item.image && !editImageFile" :src="item.image" class="file-preview" alt="" />
                                <img v-if="editImageFile" :src="URL.createObjectURL(editImageFile)" class="file-preview" alt="" />
                            </div>

                            <div class="edit-bottom">
                                <div class="date-wrap">
                                    <label class="date-label">Дата публикации</label>
                                    <input v-model="editDate" type="datetime-local" class="edit-input edit-input--date" />
                                    <span class="date-hint">Пусто = черновик</span>
                                </div>
                                <div class="edit-actions">
                                    <button class="btn-save" @click="saveEdit(item)">Сохранить</button>
                                    <button class="btn-cancel" @click="cancelEdit">Отмена</button>
                                </div>
                            </div>
                        </div>
                    </template>

                </div>
                <div v-if="!news.length" class="empty-msg">Новостей пока нет</div>
            </div>
        </div>

        <!-- Добавить -->
        <div class="section">
            <h2 class="section-title">Добавить новость</h2>
            <div class="add-form">
                <input v-model="newTitle" class="add-input" placeholder="Заголовок" maxlength="255" />
                <textarea v-model="newExcerpt" class="add-textarea add-textarea--short" placeholder="Краткое описание (для карточки, необязательно)" rows="2" maxlength="500" />
                <textarea v-model="newBody" class="add-textarea" placeholder="Полный текст новости" rows="5" />

                <div class="fields-row">
                    <div class="field-group field-group--pin">
                        <label class="pin-label">
                            <input type="checkbox" v-model="newIsPinned" class="pin-checkbox" />
                            <span>Закрепить</span>
                        </label>
                    </div>
                </div>

                <div class="file-row">
                    <label class="file-label">
                        <span class="file-label__text">Изображение</span>
                        <input type="file" accept="image/*" class="file-input" @change="onNewFileChange" />
                        <span class="file-btn">{{ newImageFile ? newImageFile.name : 'Выбрать файл' }}</span>
                    </label>
                    <img v-if="newImageFile" :src="URL.createObjectURL(newImageFile)" class="file-preview" alt="" />
                </div>

                <div class="add-bottom">
                    <div class="date-wrap">
                        <label class="date-label">Дата публикации</label>
                        <input v-model="newDate" type="datetime-local" class="add-input add-input--date" />
                        <span class="date-hint">Пусто = черновик</span>
                    </div>
                    <button class="btn-add" :disabled="!newTitle.trim() || !newBody.trim()" @click="store">Добавить</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.page-header { margin-bottom: 1.5rem; }
.page-title  { font-size: 1.4rem; color: #fff; margin: 0; }

.section {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.08);
    padding: 1.25rem;
    margin-bottom: 1.25rem;
}

.section-title {
    font-size: 0.9rem; color: rgba(255,255,255,0.6); font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.07em; margin: 0 0 1rem;
}

.news-list { display: flex; flex-direction: column; gap: 0.75rem; }

.news-row {
    padding: 0.85rem 1rem;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    display: flex; flex-direction: column; gap: 0.4rem;
}

.news-meta { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
.news-thumb { width: 48px; height: 32px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
.news-title { font-size: 0.92rem; font-weight: 600; color: rgba(255,255,255,0.85); flex: 1; min-width: 0; }
.news-date  { font-size: 0.78rem; color: rgba(255,255,255,0.3); }
.views-count { font-size: 0.75rem; color: rgba(255,255,255,0.25); }

.pin-badge { font-size: 0.8rem; }

.status-badge { font-size: 0.72rem; padding: 0.15rem 0.5rem; border-radius: 3px; font-weight: 500; white-space: nowrap; }
.status--published { background: rgba(76,222,143,0.12); color: #4cde8f; border: 1px solid rgba(76,222,143,0.25); }
.status--draft     { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.35); border: 1px solid rgba(255,255,255,0.1); }
.status--scheduled { background: rgba(112,112,216,0.12); color: #9090e0; border: 1px solid rgba(112,112,216,0.25); }


.news-body-preview { font-size: 0.82rem; color: rgba(255,255,255,0.35); margin: 0; line-height: 1.45; }
.row-actions { display: flex; gap: 0.5rem; }

/* Поля */
.fields-row {
    display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;
}
.field-group { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; min-width: 140px; }
.field-group--pin { flex: 0; min-width: auto; justify-content: flex-end; padding-bottom: 0.2rem; }
.field-label { font-size: 0.75rem; color: rgba(255,255,255,0.35); display: flex; align-items: center; gap: 0.35rem; }
.field-hint  { color: rgba(255,255,255,0.2); font-weight: 400; }

.pin-label {
    display: flex; align-items: center; gap: 0.45rem; cursor: pointer;
    font-size: 0.82rem; color: rgba(255,255,255,0.45);
    white-space: nowrap;
}
.pin-checkbox { accent-color: rgba(190,145,255,0.8); width: 14px; height: 14px; cursor: pointer; }

/* Форма */
.edit-form, .add-form { display: flex; flex-direction: column; gap: 0.6rem; }

.edit-input, .add-input {
    width: 100%; padding: 0.38rem 0.65rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(190,145,255,0.35);
    color: rgba(255,255,255,0.9);
    font-family: inherit; font-size: 0.88rem; outline: none; box-sizing: border-box;
}
.add-input { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.1); color: rgba(255,255,255,0.85); }
.add-input:focus { border-color: rgba(190,145,255,0.45); }
.add-input::placeholder { color: rgba(255,255,255,0.2); }
.edit-input:focus { border-color: rgba(190,145,255,0.6); }

.edit-select, .add-select {
    width: 100%; padding: 0.38rem 0.65rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.8);
    font-family: inherit; font-size: 0.85rem; outline: none;
    color-scheme: dark;
}
.edit-select { border-color: rgba(190,145,255,0.35); background: rgba(255,255,255,0.06); }
.edit-select:focus, .add-select:focus { border-color: rgba(190,145,255,0.5); }

.edit-textarea, .add-textarea {
    width: 100%; padding: 0.5rem 0.65rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.85);
    font-family: inherit; font-size: 0.88rem; outline: none;
    resize: vertical; box-sizing: border-box; line-height: 1.55;
}
.edit-textarea { background: rgba(255,255,255,0.05); border-color: rgba(190,145,255,0.35); color: rgba(255,255,255,0.9); }
.edit-textarea--short, .add-textarea--short { font-size: 0.83rem; color: rgba(255,255,255,0.65); }
.edit-textarea:focus, .add-textarea:focus { border-color: rgba(190,145,255,0.5); }
.add-textarea::placeholder, .edit-textarea::placeholder { color: rgba(255,255,255,0.2); }

/* Файл */
.file-row { display: flex; align-items: center; gap: 0.75rem; }
.file-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
.file-label__text { font-size: 0.75rem; color: rgba(255,255,255,0.35); }
.file-input { display: none; }
.file-btn {
    padding: 0.3rem 0.7rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.55);
    font-family: inherit; font-size: 0.78rem; cursor: pointer; border-radius: 4px;
    max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.file-preview { width: 80px; height: 50px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }

/* Низ формы */
.edit-bottom, .add-bottom {
    display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
}
.edit-actions { display: flex; gap: 0.5rem; align-items: center; }
.date-wrap { display: flex; flex-direction: column; gap: 0.25rem; }
.date-label { font-size: 0.75rem; color: rgba(255,255,255,0.35); letter-spacing: 0.03em; }
.date-hint  { font-size: 0.72rem; color: rgba(255,255,255,0.2); }
.edit-input--date, .add-input--date { width: auto; color-scheme: dark; }

/* Кнопки */
.btn-edit   { padding: 0.22rem 0.6rem; border: 1px solid rgba(155,110,232,0.3); background: transparent; color: rgba(190,145,255,0.7); font-family: inherit; font-size: 0.78rem; cursor: pointer; }
.btn-edit:hover { background: rgba(155,110,232,0.1); }
.btn-save   { padding: 0.22rem 0.6rem; border: 1px solid rgba(76,222,143,0.35); background: rgba(76,222,143,0.07); color: #4cde8f; font-family: inherit; font-size: 0.78rem; cursor: pointer; }
.btn-save:hover { background: rgba(76,222,143,0.18); }
.btn-cancel { padding: 0.22rem 0.6rem; border: 1px solid rgba(255,255,255,0.12); background: transparent; color: rgba(255,255,255,0.35); font-family: inherit; font-size: 0.78rem; cursor: pointer; }
.btn-delete { padding: 0.22rem 0.6rem; border: 1px solid rgba(239,68,68,0.3); background: transparent; color: rgba(239,68,68,0.65); font-family: inherit; font-size: 0.78rem; cursor: pointer; }
.btn-delete:hover { background: rgba(239,68,68,0.1); }
.btn-add {
    padding: 0.38rem 0.85rem;
    border: 1px solid rgba(155,110,232,0.4); background: rgba(155,110,232,0.1);
    color: rgba(190,145,255,0.85); font-family: inherit; font-size: 0.82rem; cursor: pointer; white-space: nowrap;
}
.btn-add:hover:not(:disabled) { background: rgba(155,110,232,0.2); }
.btn-add:disabled { opacity: 0.35; cursor: default; }

.empty-msg { font-size: 0.83rem; color: rgba(255,255,255,0.25); padding: 0.5rem 0; }

</style>
