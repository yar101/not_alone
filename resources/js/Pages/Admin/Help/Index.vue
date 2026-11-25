<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessageBox, ElMessage } from 'element-plus';
import draggable from 'vuedraggable';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import AdminInput from '@/Components/Admin/UI/AdminInput.vue';
import HtmlEditor from '@/Components/Admin/HtmlEditor.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    categories: { type: Array, default: () => [] },
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
        onSuccess: () => ElMessage.success('Порядок разделов обновлён'),
    });
}

function handleArticlesReorder() {
    const ids = articlesList.value.map(a => a.id);
    router.post(route('admin.help-articles.reorder'), { ids }, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Порядок статей обновлён'),
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
                ElMessage.success('Раздел обновлён');
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
                ElMessage.success('Раздел создан');
            }
        });
    }
}

function deleteCategory(cat) {
    ElMessageBox.confirm(`Удалить раздел "${cat.title_ru}" и все входящие в него статьи?`, 'Подтверждение удаления', {
        confirmButtonText: 'Удалить',
        cancelButtonText: 'Отмена',
        type: 'warning',
    }).then(() => {
        router.delete(route('admin.help-categories.destroy', cat.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedCategoryId.value === cat.id) {
                    selectedCategoryId.value = props.categories.find(c => c.id !== cat.id)?.id || null;
                }
                ElMessage.success('Раздел удалён');
            }
        });
    }).catch(() => {});
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
                ElMessage.success('Статья обновлена');
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
                ElMessage.success('Статья добавлена');
            }
        });
    }
}

function deleteArticle(art) {
    ElMessageBox.confirm(`Удалить статью "${art.title_ru}"?`, 'Подтверждение удаления', {
        confirmButtonText: 'Удалить',
        cancelButtonText: 'Отмена',
        type: 'warning',
    }).then(() => {
        router.delete(route('admin.help-articles.destroy', art.id), {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Статья удалена'),
        });
    }).catch(() => {});
}
</script>

<template>
    <div class="space-y-6">
        <AdminPageHeader
            title="Справочный центр (FAQ)"
            subtitle="Управление разделами и статьями базы знаний с поддержкой двуязычности (RU / EN) и сортировкой перетаскиванием"
        />

        <!-- 2-Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- Left: Categories (4 cols) -->
            <div class="lg:col-span-5 bg-zinc-900/60 border border-zinc-800 rounded-xl overflow-hidden shadow-sm">
                <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800 bg-zinc-950/40">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-zinc-300">Разделы справки</div>
                        <div class="text-[11px] text-zinc-500">Перетащите для сортировки</div>
                    </div>
                    <AdminButton variant="outline" size="sm" @click="openCreateCategory">
                        <svg class="w-3.5 h-3.5 mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Раздел
                    </AdminButton>
                </div>

                <div class="p-3">
                    <draggable
                        v-model="categoriesList"
                        item-key="id"
                        handle=".drag-handle"
                        @end="handleCategoriesReorder"
                        class="space-y-2"
                        ghost-class="opacity-40"
                    >
                        <template #item="{ element }">
                            <div
                                class="group flex items-center justify-between p-3 rounded-lg border transition-all cursor-pointer"
                                :class="element.id === selectedCategoryId
                                    ? 'bg-violet-950/20 border-violet-500/40 text-violet-100 shadow-sm'
                                    : 'bg-zinc-950/40 border-zinc-800/80 hover:border-zinc-700 text-zinc-300'"
                                @click="selectCategory(element.id)"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="drag-handle p-1 cursor-grab active:cursor-grabbing text-zinc-500 hover:text-zinc-300 transition-colors" title="Перетащить">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="9" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/>
                                            <circle cx="15" cy="5" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
                                        </svg>
                                    </span>

                                    <div class="min-w-0">
                                        <div class="text-sm font-medium truncate">{{ element.title_ru }}</div>
                                        <div class="flex items-center gap-1.5 mt-1">
                                            <span class="text-[10px] font-mono px-1 py-0.2 rounded bg-zinc-800 text-zinc-400">RU</span>
                                            <span
                                                class="text-[10px] font-mono px-1 py-0.2 rounded transition-colors"
                                                :class="element.title_en ? 'bg-violet-500/20 text-violet-300' : 'bg-zinc-800/50 text-zinc-600'"
                                            >EN</span>
                                            <span class="text-[11px] text-zinc-500 ml-1">({{ element.articles?.length || 0 }} ст.)</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1 opacity-80 group-hover:opacity-100 transition-opacity">
                                    <button
                                        @click.stop="openEditCategory(element)"
                                        class="p-1.5 rounded hover:bg-zinc-800 text-zinc-400 hover:text-zinc-200 transition-colors"
                                        title="Редактировать"
                                    >
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </button>
                                    <button
                                        @click.stop="deleteCategory(element)"
                                        class="p-1.5 rounded hover:bg-rose-500/20 text-zinc-400 hover:text-rose-400 transition-colors"
                                        title="Удалить"
                                    >
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </draggable>

                    <div v-if="!categoriesList.length" class="text-center py-8 text-xs text-zinc-500 italic">
                        Разделы ещё не созданы. Нажмите «+ Раздел» выше.
                    </div>
                </div>
            </div>

            <!-- Right: Articles in Category (7 cols) -->
            <div class="lg:col-span-7 bg-zinc-900/60 border border-zinc-800 rounded-xl overflow-hidden shadow-sm">
                <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800 bg-zinc-950/40">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-zinc-300">
                            <span v-if="selectedCategory">Статьи: {{ selectedCategory.title_ru }}</span>
                            <span v-else>Статьи раздела</span>
                        </div>
                        <div class="text-[11px] text-zinc-500">Перетащите для сортировки внутри раздела</div>
                    </div>
                    <AdminButton
                        v-if="selectedCategoryId"
                        variant="primary"
                        size="sm"
                        @click="openCreateArticle"
                    >
                        <svg class="w-3.5 h-3.5 mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Добавить статью
                    </AdminButton>
                </div>

                <div class="p-3">
                    <div v-if="!selectedCategoryId" class="text-center py-12 text-sm text-zinc-500">
                        Выберите раздел в левой колонке для просмотра и добавления статей.
                    </div>
                    <div v-else>
                        <draggable
                            v-model="articlesList"
                            item-key="id"
                            handle=".drag-handle"
                            @end="handleArticlesReorder"
                            class="space-y-2"
                            ghost-class="opacity-40"
                        >
                            <template #item="{ element }">
                                <div class="group flex items-start justify-between p-3.5 rounded-lg border border-zinc-800/80 bg-zinc-950/40 hover:border-zinc-700 transition-colors">
                                    <div class="flex items-start gap-2.5 min-w-0 flex-1">
                                        <span class="drag-handle p-1 cursor-grab active:cursor-grabbing text-zinc-500 hover:text-zinc-300 transition-colors mt-0.5" title="Перетащить">
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="9" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/>
                                                <circle cx="15" cy="5" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
                                            </svg>
                                        </span>

                                        <div class="min-w-0 flex-1 pr-4">
                                            <div class="flex items-center gap-2">
                                                <h4 class="text-sm font-medium text-zinc-200">{{ element.title_ru }}</h4>
                                                <span class="text-[10px] font-mono px-1 py-0.2 rounded bg-zinc-800 text-zinc-400">RU</span>
                                                <span
                                                    class="text-[10px] font-mono px-1 py-0.2 rounded"
                                                    :class="element.title_en && element.content_en ? 'bg-violet-500/20 text-violet-300' : 'bg-zinc-800/50 text-zinc-600'"
                                                >EN</span>
                                            </div>
                                            <div
                                                class="text-xs text-zinc-500 line-clamp-2 mt-1.5 prose prose-invert max-w-none"
                                                v-html="element.content_ru"
                                            ></div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1 opacity-80 group-hover:opacity-100 transition-opacity">
                                        <button
                                            @click="openEditArticle(element)"
                                            class="p-1.5 rounded hover:bg-zinc-800 text-zinc-400 hover:text-zinc-200 transition-colors"
                                            title="Редактировать статью"
                                        >
                                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </button>
                                        <button
                                            @click="deleteArticle(element)"
                                            class="p-1.5 rounded hover:bg-rose-500/20 text-zinc-400 hover:text-rose-400 transition-colors"
                                            title="Удалить статью"
                                        >
                                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </draggable>

                        <div v-if="!articlesList.length" class="text-center py-12 text-xs text-zinc-500 italic">
                            В этом разделе пока нет статей. Нажмите «+ Добавить статью».
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Category Modal -->
        <AdminModal
            :open="categoryModalOpen"
            :title="editingCategory ? 'Редактировать раздел' : 'Создать раздел'"
            description="Разделы группируют статьи базы знаний в справочном центре"
            @close="categoryModalOpen = false"
        >
            <div class="space-y-4 py-2">
                <AdminInput
                    v-model="categoryForm.title_ru"
                    label="Название (RU)"
                    placeholder="Например: Общие вопросы и безопасность"
                    required
                />
                <AdminInput
                    v-model="categoryForm.title_en"
                    label="Название (EN)"
                    placeholder="Например: General Questions & Security"
                />
            </div>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <AdminButton variant="outline" size="sm" @click="categoryModalOpen = false">Отмена</AdminButton>
                    <AdminButton variant="primary" size="sm" :disabled="!categoryForm.title_ru.trim()" @click="saveCategory">
                        Сохранить
                    </AdminButton>
                </div>
            </template>
        </AdminModal>

        <!-- Create/Edit Article Modal -->
        <AdminModal
            :open="articleModalOpen"
            :title="editingArticle ? 'Редактировать статью' : 'Создать статью'"
            :description="`Статья в разделе «${selectedCategory?.title_ru || ''}»`"
            size="2xl"
            @close="articleModalOpen = false"
        >
            <div class="space-y-4 py-2">
                <!-- Language Selector -->
                <div class="flex items-center gap-1 border-b border-zinc-800 pb-2">
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                        :class="activeLangTab === 'ru' ? 'bg-violet-500/20 text-violet-300 border border-violet-500/30' : 'text-zinc-400 hover:text-zinc-200'"
                        @click="activeLangTab = 'ru'"
                    >
                        Русская версия (RU)
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                        :class="activeLangTab === 'en' ? 'bg-violet-500/20 text-violet-300 border border-violet-500/30' : 'text-zinc-400 hover:text-zinc-200'"
                        @click="activeLangTab = 'en'"
                    >
                        English version (EN)
                    </button>
                </div>

                <!-- Tab: RU -->
                <div v-show="activeLangTab === 'ru'" class="space-y-4">
                    <AdminInput
                        v-model="articleForm.title_ru"
                        label="Заголовок статьи (RU)"
                        placeholder="Например: Как начать диалог с айдолом?"
                        required
                    />
                    <div>
                        <label class="block text-xs font-semibold uppercase text-zinc-400 mb-1.5">Текст статьи (RU)</label>
                        <HtmlEditor v-model="articleForm.content_ru" />
                    </div>
                </div>

                <!-- Tab: EN -->
                <div v-show="activeLangTab === 'en'" class="space-y-4">
                    <AdminInput
                        v-model="articleForm.title_en"
                        label="Заголовок статьи (EN)"
                        placeholder="Например: How to start chatting with an idol?"
                    />
                    <div>
                        <label class="block text-xs font-semibold uppercase text-zinc-400 mb-1.5">Текст статьи (EN)</label>
                        <HtmlEditor v-model="articleForm.content_en" />
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <AdminButton variant="outline" size="sm" @click="articleModalOpen = false">Отмена</AdminButton>
                    <AdminButton
                        variant="primary"
                        size="sm"
                        :disabled="!articleForm.title_ru.trim() || !articleForm.content_ru.trim()"
                        @click="saveArticle"
                    >
                        Сохранить
                    </AdminButton>
                </div>
            </template>
        </AdminModal>
    </div>
</template>
