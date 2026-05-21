<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminModal from '@/Components/Admin/AdminModal.vue';
import HtmlEditor from '@/Components/Admin/HtmlEditor.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    categories: Array,
});

// ─── Reactive Lists and Selection ───────────────────────────
const categoriesList = ref([...props.categories]);
const selectedCategoryId = ref(props.categories[0]?.id || null);

const selectedCategory = computed(() => {
    return props.categories.find(c => c.id === selectedCategoryId.value) || null;
});

const articlesList = ref([]);

watch(() => props.categories, (newVal) => {
    categoriesList.value = [...newVal];
}, { deep: true });

watch(selectedCategory, (newCat) => {
    articlesList.value = newCat ? [...newCat.articles] : [];
}, { immediate: true, deep: true });

function selectCategory(id) {
    selectedCategoryId.value = id;
}

// ─── Reordering via Drag and Drop ───────────────────────────
function handleCategoriesReorder() {
    const ids = categoriesList.value.map(c => c.id);
    router.post(route('admin.help-categories.reorder'), { ids }, {
        preserveScroll: true,
    });
}

function handleArticlesReorder() {
    const ids = articlesList.value.map(a => a.id);
    router.post(route('admin.help-articles.reorder'), { ids }, {
        preserveScroll: true,
    });
}

// ─── Category Modals ─────────────────────────────────────────
const categoryModalOpen = ref(false);
const editingCategory = ref(null);
const categoryForm = ref({
    title_ru: '',
    title_en: '',
});

function openCreateCategory() {
    editingCategory.value = null;
    categoryForm.value = { title_ru: '', title_en: '' };
    categoryModalOpen.value = true;
}

function openEditCategory(cat) {
    editingCategory.value = cat;
    categoryForm.value = {
        title_ru: cat.title_ru || '',
        title_en: cat.title_en || '',
    };
    categoryModalOpen.value = true;
}

function saveCategory() {
    if (!categoryForm.value.title_ru.trim()) return;

    if (editingCategory.value) {
        router.patch(route('admin.help-categories.update', editingCategory.value.id), categoryForm.value, {
            preserveScroll: true,
            onSuccess: () => {
                categoryModalOpen.value = false;
            }
        });
    } else {
        router.post(route('admin.help-categories.store'), categoryForm.value, {
            preserveScroll: true,
            onSuccess: (page) => {
                categoryModalOpen.value = false;
                // If it's a new category and it's the first one, select it
                if (props.categories.length > 0 && !selectedCategoryId.value) {
                    selectedCategoryId.value = props.categories[0].id;
                }
            }
        });
    }
}

function deleteCategory(cat) {
    if (!confirm(`Удалить раздел «${cat.title_ru}» и все его статьи?`)) return;
    router.delete(route('admin.help-categories.destroy', cat.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedCategoryId.value === cat.id) {
                selectedCategoryId.value = props.categories.find(c => c.id !== cat.id)?.id || null;
            }
        }
    });
}

// ─── Article Modals ──────────────────────────────────────────
const articleModalOpen = ref(false);
const editingArticle = ref(null);
const articleForm = ref({
    title_ru: '',
    title_en: '',
    content_ru: '',
    content_en: '',
});
const activeLangTab = ref('ru');

function openCreateArticle() {
    if (!selectedCategoryId.value) return;
    editingArticle.value = null;
    articleForm.value = {
        title_ru: '',
        title_en: '',
        content_ru: '',
        content_en: '',
    };
    activeLangTab.value = 'ru';
    articleModalOpen.value = true;
}

function openEditArticle(art) {
    editingArticle.value = art;
    articleForm.value = {
        title_ru: art.title_ru || '',
        title_en: art.title_en || '',
        content_ru: art.content_ru || '',
        content_en: art.content_en || '',
    };
    activeLangTab.value = 'ru';
    articleModalOpen.value = true;
}

function saveArticle() {
    if (!articleForm.value.title_ru.trim() || !articleForm.value.content_ru.trim()) return;

    if (editingArticle.value) {
        router.patch(route('admin.help-articles.update', editingArticle.value.id), articleForm.value, {
            preserveScroll: true,
            onSuccess: () => {
                articleModalOpen.value = false;
            }
        });
    } else {
        const payload = {
            ...articleForm.value,
            category_id: selectedCategoryId.value,
        };
        router.post(route('admin.help-articles.store'), payload, {
            preserveScroll: true,
            onSuccess: () => {
                articleModalOpen.value = false;
            }
        });
    }
}

function deleteArticle(art) {
    if (!confirm(`Удалить подраздел «${art.title_ru}»?`)) return;
    router.delete(route('admin.help-articles.destroy', art.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="help-manager">
        <div class="page-header">
            <h1 class="page-title">Управление Справкой (Help Center)</h1>
        </div>

        <div class="help-grid">
            <!-- Left Column: Categories -->
            <div class="grid-card">
                <div class="card-header">
                    <h2 class="card-title">Разделы (Категории)</h2>
                    <button class="btn-primary-mini" @click="openCreateCategory">+ Раздел</button>
                </div>

                <div class="card-body">
                    <draggable
                        v-model="categoriesList"
                        item-key="id"
                        handle=".drag-handle"
                        @end="handleCategoriesReorder"
                        class="drag-list"
                        ghost-class="drag-ghost"
                    >
                        <template #item="{ element }">
                            <div
                                class="category-item"
                                :class="{ 'category-item--active': element.id === selectedCategoryId }"
                                @click="selectCategory(element.id)"
                            >
                                <span class="drag-handle">☰</span>
                                <span class="item-text" :title="element.title_ru">
                                    {{ element.title_ru }}
                                </span>
                                <div class="item-actions">
                                    <button class="action-edit" @click.stop="openEditCategory(element)">✎</button>
                                    <button class="action-delete" @click.stop="deleteCategory(element)">✕</button>
                                </div>
                            </div>
                        </template>
                    </draggable>
                    <div v-if="!categoriesList.length" class="empty-text">
                        Разделы не созданы
                    </div>
                </div>
            </div>

            <!-- Right Column: Articles -->
            <div class="grid-card">
                <div class="card-header">
                    <h2 class="card-title">
                        Подразделы (Статьи):
                        <span v-if="selectedCategory" class="text-cyan-400">
                            {{ selectedCategory.title_ru }}
                        </span>
                        <span v-else>выберите раздел</span>
                    </h2>
                    <button
                        v-if="selectedCategoryId"
                        class="btn-primary-mini"
                        @click="openCreateArticle"
                    >
                        + Добавить статью
                    </button>
                </div>

                <div class="card-body">
                    <div v-if="!selectedCategoryId" class="no-selection">
                        Выберите раздел в левой колонке для управления статьями.
                    </div>
                    <div v-else>
                        <draggable
                            v-model="articlesList"
                            item-key="id"
                            handle=".drag-handle"
                            @end="handleArticlesReorder"
                            class="drag-list"
                            ghost-class="drag-ghost"
                        >
                            <template #item="{ element }">
                                <div class="article-item">
                                    <span class="drag-handle">☰</span>
                                    <div class="article-info">
                                        <span class="article-title">{{ element.title_ru }}</span>
                                        <span class="article-preview" v-html="element.content_ru"></span>
                                    </div>
                                    <div class="item-actions">
                                        <button class="action-edit" @click="openEditArticle(element)">Изменить</button>
                                        <button class="action-delete" @click="deleteArticle(element)">Удалить</button>
                                    </div>
                                </div>
                            </template>
                        </draggable>
                        <div v-if="!articlesList.length" class="empty-text">
                            В этом разделе пока нет статей. Нажмите кнопку выше для добавления.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Category Modal -->
        <AdminModal :show="categoryModalOpen" @close="categoryModalOpen = false" :title="editingCategory ? 'Редактировать раздел' : 'Создать раздел'">
            <div class="modal-form">
                <div class="form-group">
                    <label class="form-label">Название (RU)</label>
                    <input
                        v-model="categoryForm.title_ru"
                        class="form-input"
                        placeholder="Что такое no alone?"
                        required
                    />
                </div>
                <div class="form-group">
                    <label class="form-label">Название (EN)</label>
                    <input
                        v-model="categoryForm.title_en"
                        class="form-input"
                        placeholder="What is no alone?"
                    />
                </div>
                <div class="modal-actions">
                    <button class="btn-cancel" @click="categoryModalOpen = false">Отмена</button>
                    <button
                        class="btn-save"
                        :disabled="!categoryForm.title_ru.trim()"
                        @click="saveCategory"
                    >
                        Сохранить
                    </button>
                </div>
            </div>
        </AdminModal>

        <!-- Create/Edit Article Modal -->
        <AdminModal
            :show="articleModalOpen"
            @close="articleModalOpen = false"
            :title="editingArticle ? 'Редактировать статью' : 'Создать статью'"
            maxWidth="3xl"
        >
            <div class="modal-form">
                <!-- Lang Tabs -->
                <div class="lang-tabs">
                    <button
                        type="button"
                        class="tab-btn"
                        :class="{ 'tab-btn--active': activeLangTab === 'ru' }"
                        @click="activeLangTab = 'ru'"
                    >
                        Русский
                    </button>
                    <button
                        type="button"
                        class="tab-btn"
                        :class="{ 'tab-btn--active': activeLangTab === 'en' }"
                        @click="activeLangTab = 'en'"
                    >
                        English
                    </button>
                </div>

                <!-- Tab: RU -->
                <div v-show="activeLangTab === 'ru'" class="tab-content">
                    <div class="form-group">
                        <label class="form-label">Заголовок статьи (RU)</label>
                        <input
                            v-model="articleForm.title_ru"
                            class="form-input"
                            placeholder="О сервисе"
                        />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Текст статьи (RU)</label>
                        <HtmlEditor v-model="articleForm.content_ru" />
                    </div>
                </div>

                <!-- Tab: EN -->
                <div v-show="activeLangTab === 'en'" class="tab-content">
                    <div class="form-group">
                        <label class="form-label">Заголовок статьи (EN)</label>
                        <input
                            v-model="articleForm.title_en"
                            class="form-input"
                            placeholder="About service"
                        />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Текст статьи (EN)</label>
                        <HtmlEditor v-model="articleForm.content_en" />
                    </div>
                </div>

                <div class="modal-actions" style="margin-top: 1.5rem">
                    <button class="btn-cancel" @click="articleModalOpen = false">Отмена</button>
                    <button
                        class="btn-save"
                        :disabled="!articleForm.title_ru.trim() || !articleForm.content_ru.trim()"
                        @click="saveArticle"
                    >
                        Сохранить
                    </button>
                </div>
            </div>
        </AdminModal>
    </div>
</template>

<style scoped>
.help-manager {
    padding: 0.5rem;
}

.help-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 1.5rem;
    margin-top: 1.5rem;
}

@media (max-width: 900px) {
    .help-grid {
        grid-template-columns: 1fr;
    }
}

.grid-card {
    background-color: #12121d;
    border: 1px solid #1f1f2e;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.card-header {
    background-color: #161625;
    border-bottom: 1px solid #1f1f2e;
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #ffffff;
    margin: 0;
}

.card-body {
    padding: 1rem;
    min-height: 350px;
}

.btn-primary-mini {
    background: linear-gradient(135deg, #ffb2ef 0%, #a765ff 100%);
    color: #ffffff;
    border: none;
    padding: 5px 12px;
    font-size: 0.85rem;
    font-weight: 700;
    border-radius: 5px;
    cursor: pointer;
    transition: opacity 0.2s;
}
.btn-primary-mini:hover {
    opacity: 0.9;
}

/* Category Items */
.category-item {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    background-color: #161625;
    border: 1px solid #1f1f2e;
    border-radius: 6px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.category-item:hover {
    border-color: rgba(255, 178, 239, 0.4);
    background-color: #1b1b2d;
}

.category-item--active {
    border-color: #64d2ff !important;
    background-color: #162030 !important;
}

.drag-handle {
    color: #52527a;
    cursor: grab;
    margin-right: 10px;
    font-size: 1.1rem;
    user-select: none;
}

.item-text {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #e2e2e9;
    font-size: 0.9rem;
}

.item-actions {
    display: flex;
    gap: 6px;
    margin-left: 10px;
}

.action-edit, .action-delete {
    background: none;
    border: none;
    color: #8383a3;
    cursor: pointer;
    font-size: 0.9rem;
    padding: 2px 4px;
    border-radius: 4px;
    transition: all 0.15s ease;
}

.action-edit:hover {
    color: #64d2ff;
    background-color: rgba(100, 210, 255, 0.1);
}

.action-delete:hover {
    color: #ff5e84;
    background-color: rgba(255, 94, 132, 0.1);
}

/* Article Items */
.article-item {
    display: flex;
    align-items: center;
    padding: 12px 14px;
    background-color: #161625;
    border: 1px solid #1f1f2e;
    border-radius: 8px;
    margin-bottom: 10px;
}

.article-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.article-title {
    font-weight: 600;
    color: #ffffff;
    font-size: 0.95rem;
}

.article-preview {
    font-size: 0.8rem;
    color: #8c8ca5;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
}

:deep(.article-preview *) {
    display: inline;
    margin: 0;
    padding: 0;
    font-weight: normal;
    font-style: normal;
}

/* Modals & Forms */
.modal-form {
    padding: 1rem 0;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: #a0a0b8;
    margin-bottom: 0.5rem;
}

.form-input {
    width: 100%;
    background-color: #0e0e15;
    border: 1px solid #1f1f2e;
    border-radius: 6px;
    padding: 8px 12px;
    color: #ffffff;
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.15s ease;
}

.form-input:focus {
    border-color: #64d2ff;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 1rem;
}

.btn-cancel, .btn-save, .btn-delete {
    font-family: inherit;
    font-size: 0.88rem;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    border: none;
    transition: opacity 0.2s;
}

.btn-cancel {
    background-color: #1c1c2e;
    color: #e2e2e9;
}
.btn-cancel:hover {
    background-color: #26263e;
}

.btn-save {
    background: linear-gradient(135deg, #ffb2ef 0%, #a765ff 100%);
    color: #ffffff;
}
.btn-save:hover {
    opacity: 0.9;
}
.btn-save:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* Language Tabs */
.lang-tabs {
    display: flex;
    border-bottom: 1px solid #1f1f2e;
    margin-bottom: 1.25rem;
}

.tab-btn {
    padding: 8px 16px;
    color: #8c8ca5;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.15s ease;
}

.tab-btn:hover {
    color: #ffffff;
}

.tab-btn--active {
    color: #ffb2ef !important;
    border-bottom-color: #ffb2ef !important;
}

.tab-content {
    animation: fadeIn 0.2s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.empty-text {
    color: #646485;
    text-align: center;
    padding: 2rem 0;
    font-size: 0.9rem;
}

.no-selection {
    color: #8c8ca5;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 250px;
    border: 2px dashed #1f1f2e;
    border-radius: 8px;
    font-size: 0.95rem;
}

.drag-ghost {
    opacity: 0.4;
    background-color: #24243b !important;
}
</style>
