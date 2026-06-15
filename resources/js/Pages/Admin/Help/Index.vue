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
                if (props.categories.length > 0 && !selectedCategoryId.value) {
                    selectedCategoryId.value = props.categories[0].id;
                }
            }
        });
    }
}

// Fixed delete category confirm dialog message interpolation
function deleteCategory(cat) {
    if (!confirm(`Удалить раздел "${cat.title_ru}" и все его статьи?`)) return;
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

// Fixed delete article confirm dialog message interpolation
function deleteArticle(art) {
    if (!confirm(`Удалить подраздел "${art.title_ru}"?`)) return;
    router.delete(route('admin.help-articles.destroy', art.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="help-manager">
        <div class="page-header-block">
            <div>
                <h1 class="page-title">Справочный центр</h1>
                <p class="page-desc">
                    Управление разделами и статьями помощи. Вы можете менять порядок элементов перетаскиванием.
                </p>
            </div>
        </div>

        <div class="help-grid">
            <!-- Left Column: Categories -->
            <div class="grid-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Разделы справки</h2>
                        <span class="card-subtitle">Перетащите для изменения порядка</span>
                    </div>
                    <button class="btn-create-item" @click="openCreateCategory">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Раздел
                    </button>
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
                                <span class="drag-handle" title="Перетащить">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <circle cx="9" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/>
                                        <circle cx="15" cy="5" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
                                    </svg>
                                </span>
                                <div class="category-info">
                                    <span class="item-text" :title="element.title_ru">
                                        {{ element.title_ru }}
                                    </span>
                                    <div class="lang-indicators">
                                        <span class="lang-badge lang-badge--active">RU</span>
                                        <span class="lang-badge" :class="{ 'lang-badge--active': element.title_en }">EN</span>
                                    </div>
                                </div>
                                <div class="item-actions">
                                    <button class="action-btn action-btn--edit" @click.stop="openEditCategory(element)" title="Редактировать">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>
                                    <button class="action-btn action-btn--delete" @click.stop="deleteCategory(element)" title="Удалить">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                                            <path d="M10 11v6M14 11v6"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </draggable>
                    <div v-if="!categoriesList.length" class="empty-state-placeholder">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span>Разделы не созданы</span>
                    </div>
                </div>
            </div>

            <!-- Right Column: Articles -->
            <div class="grid-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">
                            <span v-if="selectedCategory">Статьи в «{{ selectedCategory.title_ru }}»</span>
                            <span v-else>Статьи раздела</span>
                        </h2>
                        <span class="card-subtitle">Перетащите для изменения порядка</span>
                    </div>
                    <button
                        v-if="selectedCategoryId"
                        class="btn-create-item btn-create-item--pink"
                        @click="openCreateArticle"
                    >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Добавить статью
                    </button>
                </div>

                <div class="card-body">
                    <div v-if="!selectedCategoryId" class="no-selection-placeholder">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="9" y1="9" x2="15" y2="9"></line>
                            <line x1="9" y1="13" x2="15" y2="13"></line>
                            <line x1="9" y1="17" x2="13" y2="17"></line>
                        </svg>
                        <p>Выберите раздел в левой колонке для управления его статьями.</p>
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
                                    <span class="drag-handle" title="Перетащить">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <circle cx="9" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/>
                                            <circle cx="15" cy="5" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
                                        </svg>
                                    </span>
                                    <div class="article-info">
                                        <div class="article-title-row">
                                            <span class="article-title">{{ element.title_ru }}</span>
                                            <div class="lang-indicators">
                                                <span class="lang-badge lang-badge--active">RU</span>
                                                <span class="lang-badge" :class="{ 'lang-badge--active': element.title_en && element.content_en }">EN</span>
                                            </div>
                                        </div>
                                        <span class="article-preview" v-html="element.content_ru"></span>
                                    </div>
                                    <div class="item-actions">
                                        <button class="action-btn action-btn--edit" @click="openEditArticle(element)" title="Изменить">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>
                                        <button class="action-btn action-btn--delete" @click="deleteArticle(element)" title="Удалить">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                                                <path d="M10 11v6M14 11v6"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </draggable>
                        <div v-if="!articlesList.length" class="empty-state-placeholder">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span>В этом разделе пока нет статей.</span>
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
                        placeholder="Например: Общие вопросы"
                        required
                    />
                </div>
                <div class="form-group">
                    <label class="form-label">Название (EN)</label>
                    <input
                        v-model="categoryForm.title_en"
                        class="form-input"
                        placeholder="Например: General Questions"
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
                            placeholder="Например: Как начать общение?"
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
                            placeholder="Например: How to start chatting?"
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
    padding: 0.25rem;
    font-family: 'Rubik', sans-serif;
}

.page-header-block {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
}

.page-title {
    font-size: 1.4rem;
    color: #fff;
    margin: 0 0 0.3rem;
    font-weight: 600;
}

.page-desc {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.45);
    margin: 0;
}

.help-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 1.5rem;
}

@media (max-width: 900px) {
    .help-grid {
        grid-template-columns: 1fr;
    }
}

.grid-card {
    background-color: #0b0b12;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}

.card-header {
    background-color: rgba(255, 255, 255, 0.02);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.card-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #ffffff;
    margin: 0;
}

.card-subtitle {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.35);
    display: block;
    margin-top: 0.15rem;
}

.card-body {
    padding: 1rem;
    min-height: 400px;
    display: flex;
    flex-direction: column;
}

.btn-create-item {
    background: rgba(155, 110, 232, 0.15);
    border: 1px solid rgba(155, 110, 232, 0.35);
    color: #ffb2ef;
    padding: 5px 12px;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-family: inherit;
}

.btn-create-item:hover {
    background: rgba(155, 110, 232, 0.28);
    border-color: rgba(155, 110, 232, 0.6);
    box-shadow: 0 0 12px rgba(155, 110, 232, 0.2);
}

.btn-create-item--pink {
    background: rgba(255, 178, 239, 0.15);
    border-color: rgba(255, 178, 239, 0.35);
    color: #ffb2ef;
}

.btn-create-item--pink:hover {
    background: rgba(255, 178, 239, 0.28);
    border-color: rgba(255, 178, 239, 0.6);
    box-shadow: 0 0 12px rgba(255, 178, 239, 0.2);
}

/* Category Items */
.drag-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.category-item {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    background-color: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.category-item:hover {
    border-color: rgba(255, 178, 239, 0.3);
    background-color: rgba(255, 255, 255, 0.04);
}

.category-item--active {
    border-color: rgba(155, 110, 232, 0.6) !important;
    background-color: rgba(155, 110, 232, 0.08) !important;
    box-shadow: inset 0 0 8px rgba(155, 110, 232, 0.05);
}

.category-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.drag-handle {
    color: rgba(255, 255, 255, 0.2);
    cursor: grab;
    margin-right: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    user-select: none;
    transition: color 0.15s;
}

.drag-handle:hover {
    color: rgba(255, 255, 255, 0.55);
}

.drag-handle:active {
    cursor: grabbing;
}

.item-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: rgba(255, 255, 255, 0.88);
    font-size: 0.88rem;
    font-weight: 500;
}

.lang-indicators {
    display: flex;
    gap: 0.25rem;
}

.lang-badge {
    font-size: 0.65rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.06);
    padding: 0.05rem 0.25rem;
    border-radius: 2px;
}

.lang-badge--active {
    color: #ffb2ef;
    background: rgba(255, 178, 239, 0.12);
    border-color: rgba(255, 178, 239, 0.3);
}

.item-actions {
    display: flex;
    gap: 0.35rem;
    margin-left: 10px;
}

.action-btn {
    background: transparent;
    border: 1px solid transparent;
    color: rgba(255, 255, 255, 0.3);
    cursor: pointer;
    padding: 0.3rem;
    border-radius: 4px;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.action-btn--edit:hover {
    color: #ffb2ef;
    border-color: rgba(255, 178, 239, 0.3);
    background-color: rgba(255, 178, 239, 0.08);
}

.action-btn--delete:hover {
    color: #ff6b6b;
    border-color: rgba(255, 80, 80, 0.3);
    background-color: rgba(255, 80, 80, 0.08);
}

/* Article Items */
.article-item {
    display: flex;
    align-items: flex-start;
    padding: 12px;
    background-color: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 4px;
    margin-bottom: 0.5rem;
}

.article-item:hover {
    border-color: rgba(255, 178, 239, 0.25);
    background-color: rgba(255, 255, 255, 0.03);
}

.article-item .drag-handle {
    margin-top: 0.25rem;
}

.article-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.article-title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.article-title {
    font-weight: 600;
    color: #ffffff;
    font-size: 0.92rem;
}

.article-preview {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.4);
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    line-height: 1.4;
}

:deep(.article-preview *) {
    display: inline;
    margin: 0;
    padding: 0;
    font-weight: normal;
    font-style: normal;
}

.article-item .item-actions {
    margin-top: 0.15rem;
}

/* Modals & Forms */
.modal-form {
    padding: 0.5rem 0;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 0.45rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.form-input {
    width: 100%;
    background-color: #0e0e15;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 4px;
    padding: 8px 12px;
    color: #ffffff;
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.15s ease;
    font-family: inherit;
}

.form-input:focus {
    border-color: rgba(155, 110, 232, 0.5);
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 1rem;
}

.btn-cancel, .btn-save {
    font-family: inherit;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s;
}

.btn-cancel {
    background-color: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.6);
}

.btn-cancel:hover {
    background-color: rgba(255, 255, 255, 0.08);
    color: #fff;
}

.btn-save {
    background: linear-gradient(135deg, #9B6EE8, #a03466);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(155, 110, 232, 0.15);
}

.btn-save:hover:not(:disabled) {
    opacity: 0.9;
}

.btn-save:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* Language Tabs */
.lang-tabs {
    display: flex;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    margin-bottom: 1.25rem;
}

.tab-btn {
    padding: 8px 16px;
    color: rgba(255, 255, 255, 0.4);
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-size: 0.88rem;
    font-weight: 600;
    transition: all 0.15s ease;
    font-family: inherit;
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

.empty-state-placeholder {
    color: rgba(255, 255, 255, 0.25);
    text-align: center;
    padding: 3rem 1rem;
    font-size: 0.85rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    border: 1px dashed rgba(255, 255, 255, 0.08);
    border-radius: 4px;
}

.empty-state-placeholder svg {
    color: rgba(255, 255, 255, 0.15);
}

.no-selection-placeholder {
    color: rgba(255, 255, 255, 0.3);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 300px;
    border: 2px dashed rgba(255, 255, 255, 0.05);
    border-radius: 6px;
    font-size: 0.88rem;
    padding: 2rem;
    text-align: center;
    gap: 0.75rem;
}

.no-selection-placeholder svg {
    color: rgba(255, 255, 255, 0.15);
}

.no-selection-placeholder p {
    margin: 0;
    max-width: 250px;
    line-height: 1.5;
}

.drag-ghost {
    opacity: 0.3;
    border-color: rgba(155, 110, 232, 0.4) !important;
    background-color: rgba(155, 110, 232, 0.05) !important;
}
</style>
