<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Plus } from '@element-plus/icons-vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import CreateButton from '@/Components/CreateButton.vue';
import ImageDropzone from '@/Components/ImageDropzone.vue';
import draggable from 'vuedraggable';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    categories:             Array,
    timeUnits:              Array,
    limits:                 Array,
    moderation_services:    Object,
    moderation_filters:     Object,
    moderation_counts:      Object,
    moderation_categories:  Array,
    active_tab:             String,
});

function switchTab(tab) {
    if (tab === 'categories')   router.visit(route('admin.services.categories.index'),   { preserveState: false });
    if (tab === 'time-units')   router.visit(route('admin.services.time-units.index'),   { preserveState: false });
    if (tab === 'price-limits') router.visit(route('admin.services.price-limits.index'), { preserveState: false });
    if (tab === 'moderation')   router.visit(route('admin.services.moderation.index'),   { preserveState: false });
    if (tab === 'change-requests') router.visit(route('admin.services.change-requests.index'), { preserveState: false });
}

// ─── Moderation ──────────────────────────────────────────────────────────────

// Filters
const filterStatus     = ref(props.moderation_filters?.status ?? 'pending');
const filterSearch     = ref(props.moderation_filters?.search ?? '');
const filterCategoryId = ref(props.moderation_filters?.category_id ?? null);
let searchDebounce = null;

function applyFilters() {
    router.get(route('admin.services.moderation.index'), {
        status:      filterStatus.value,
        search:      filterSearch.value || undefined,
        category_id: filterCategoryId.value || undefined,
    }, { preserveState: false });
}

function onSearchInput() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(applyFilters, 300);
}

function switchStatusTab(status) {
    filterStatus.value = status;
    applyFilters();
}

// Checkboxes / multi-select
const selectedIds = ref(new Set());

const allPageIds = computed(() =>
    (props.moderation_services?.data ?? []).map(s => s.id)
);

const allSelected = computed(() =>
    allPageIds.value.length > 0 && allPageIds.value.every(id => selectedIds.value.has(id))
);

function toggleSelectAll() {
    if (allSelected.value) {
        allPageIds.value.forEach(id => selectedIds.value.delete(id));
    } else {
        allPageIds.value.forEach(id => selectedIds.value.add(id));
    }
    // trigger reactivity
    selectedIds.value = new Set(selectedIds.value);
}

function toggleRow(id) {
    const s = new Set(selectedIds.value);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    selectedIds.value = s;
}

// Reject reason templates
const REJECT_TEMPLATES = [
    'Название не соответствует категории услуг',
    'Слишком низкая или нереалистичная цена',
    'Описание услуги отсутствует или слишком короткое',
    'Услуга нарушает правила платформы',
    'Дублирует уже существующую услугу',
    'Недопустимый контент',
    'Требуется уточнение деталей услуги',
];

// Single approve/reject
const showRejectModal  = ref(false);
const rejectTarget     = ref(null); // null = bulk
const rejectBulkAll    = ref(false);
const rejectReason     = ref('');
const rejectError      = ref('');

function openRejectModal(service) {
    rejectTarget.value  = service;
    rejectBulkAll.value = false;
    rejectReason.value  = '';
    rejectError.value   = '';
    showRejectModal.value = true;
}

function openBulkRejectModal(filterAll = false) {
    rejectTarget.value  = null;
    rejectBulkAll.value = filterAll;
    rejectReason.value  = '';
    rejectError.value   = '';
    showRejectModal.value = true;
}

function closeRejectModal() {
    showRejectModal.value = false;
    rejectTarget.value = null;
}

function approveService(id) {
    router.patch(route('admin.services.moderation.approve', id), {}, { preserveScroll: false });
}

function bulkApprove() {
    router.post(route('admin.services.moderation.bulk-approve'), {
        ids: [...selectedIds.value],
    }, {
        preserveScroll: false,
        onSuccess: () => { selectedIds.value = new Set(); },
    });
}

function bulkApproveAll() {
    router.post(route('admin.services.moderation.bulk-approve'), {
        filter_all:  true,
        status:      filterStatus.value,
        search:      filterSearch.value || undefined,
        category_id: filterCategoryId.value || undefined,
    }, { preserveScroll: false });
}

function submitReject() {
    if (!rejectReason.value.trim()) {
        rejectError.value = 'Укажите причину отклонения';
        return;
    }

    // Single service reject
    if (rejectTarget.value) {
        router.patch(route('admin.services.moderation.reject', rejectTarget.value.id), {
            rejection_reason: rejectReason.value,
        }, {
            preserveScroll: false,
            onSuccess: closeRejectModal,
        });
        return;
    }

    // Bulk reject
    const payload = rejectBulkAll.value
        ? {
            filter_all:       true,
            status:           filterStatus.value,
            search:           filterSearch.value || undefined,
            category_id:      filterCategoryId.value || undefined,
            rejection_reason: rejectReason.value,
          }
        : {
            ids:              [...selectedIds.value],
            rejection_reason: rejectReason.value,
          };

    router.post(route('admin.services.moderation.bulk-reject'), payload, {
        preserveScroll: false,
        onSuccess: () => {
            closeRejectModal();
            selectedIds.value = new Set();
        },
    });
}

const rejectModalTitle = computed(() => {
    if (rejectTarget.value) return `Отклонить: ${rejectTarget.value.name}`;
    if (rejectBulkAll.value) return `Отклонить все (${props.moderation_services?.total ?? 0})`;
    return `Отклонить выбранные (${selectedIds.value.size})`;
});

// ─── Categories ──────────────────────────────────────────────────────────────
const showCatForm     = ref(false);
const catEditingId    = ref(null);
const catImagePreview = ref(null);
const newSuggestionRu = ref('');
const newSuggestionEn = ref('');

const catForm = useForm({
    name_ru:             '',
    name_en:             '',
    description_ru:      '',
    description_en:      '',
    name_suggestions_ru: [],
    name_suggestions_en: [],
    accent_color:        '#ffb2ef',
    sort_order:          0,
    is_active:           true,
    image:               null,
    remove_image:        false,
});

function openCatAdd() {
    catEditingId.value    = null;
    catImagePreview.value = null;
    newSuggestionRu.value = '';
    newSuggestionEn.value = '';
    catForm.reset();
    catForm.is_active = true;
    catForm.name_suggestions_ru = [];
    catForm.name_suggestions_en = [];
    showCatForm.value = true;
}

function openCatEdit(cat) {
    catEditingId.value    = cat.id;
    catImagePreview.value = cat.image_path ? `/storage/${cat.image_path}` : null;
    newSuggestionRu.value = '';
    newSuggestionEn.value = '';
    catForm.name_ru             = cat.name_ru ?? '';
    catForm.name_en             = cat.name_en ?? '';
    catForm.description_ru      = cat.description_ru ?? '';
    catForm.description_en      = cat.description_en ?? '';
    catForm.name_suggestions_ru = cat.name_suggestions_ru ?? [];
    catForm.name_suggestions_en = cat.name_suggestions_en ?? [];
    catForm.accent_color     = cat.accent_color ?? '#ffb2ef';
    catForm.sort_order       = cat.sort_order;
    catForm.is_active        = cat.is_active;
    catForm.image            = null;
    showCatForm.value        = true;
}

function closeCatForm() {
    showCatForm.value     = false;
    catEditingId.value    = null;
    catImagePreview.value = null;
    newSuggestionRu.value = '';
    newSuggestionEn.value = '';
    catForm.reset();
    catForm.clearErrors();
}

function addSuggestionRu() {
    const s = newSuggestionRu.value.trim();
    if (!s || catForm.name_suggestions_ru.includes(s)) return;
    catForm.name_suggestions_ru.push(s);
    newSuggestionRu.value = '';
}

function removeSuggestionRu(index) {
    catForm.name_suggestions_ru.splice(index, 1);
}

function addSuggestionEn() {
    const s = newSuggestionEn.value.trim();
    if (!s || catForm.name_suggestions_en.includes(s)) return;
    catForm.name_suggestions_en.push(s);
    newSuggestionEn.value = '';
}

function removeSuggestionEn(index) {
    catForm.name_suggestions_en.splice(index, 1);
}

function onCatImageChange(file, url) {
    catForm.image = file;
    catForm.remove_image = false;
    catImagePreview.value = url;
}

function removeCatImage() {
    catForm.image = null;
    catForm.remove_image = true;
    catImagePreview.value = null;
}

function submitCat() {
    if (catEditingId.value) {
        catForm.patch(route('admin.services.categories.update', catEditingId.value), {
            preserveScroll: true,
            forceFormData:  true,
            onSuccess:      closeCatForm,
        });
    } else {
        catForm.post(route('admin.services.categories.store'), {
            preserveScroll: true,
            onSuccess:      closeCatForm,
        });
    }
}

function destroyCat(id) {
    if (!confirm('Удалить категорию?')) return;
    router.delete(route('admin.services.categories.destroy', id), { preserveScroll: true });
}

// Sort modal
const showSortModal = ref(false);
const sortList = ref([]);

function openSortModal() {
    sortList.value = props.categories.map(c => ({ ...c }));
    showSortModal.value = true;
}

function closeSortModal() {
    showSortModal.value = false;
}

function moveUp(index) {
    if (index === 0) return;
    const arr = sortList.value;
    [arr[index - 1], arr[index]] = [arr[index], arr[index - 1]];
}

function moveDown(index) {
    if (index === sortList.value.length - 1) return;
    const arr = sortList.value;
    [arr[index], arr[index + 1]] = [arr[index + 1], arr[index]];
}

function saveOrder() {
    router.post(route('admin.services.categories.reorder'), {
        ids: sortList.value.map(c => c.id),
    }, { onSuccess: closeSortModal });
}

// ─── Time Units ───────────────────────────────────────────────────────────────
const showUnitForm  = ref(false);
const unitEditingId = ref(null);

const unitForm = useForm({
    name_ru:    '',
    name_en:    '',
    sort_order: 0,
    is_active:  true,
});

function openUnitAdd() {
    unitEditingId.value = null;
    unitForm.reset();
    unitForm.is_active = true;
    showUnitForm.value = true;
}

function openUnitEdit(unit) {
    unitEditingId.value = unit.id;
    unitForm.name_ru    = unit.name_ru ?? '';
    unitForm.name_en    = unit.name_en ?? '';
    unitForm.sort_order = unit.sort_order;
    unitForm.is_active  = unit.is_active;
    showUnitForm.value  = true;
}

function closeUnitForm() {
    showUnitForm.value  = false;
    unitEditingId.value = null;
    unitForm.reset();
    unitForm.clearErrors();
}

function submitUnit() {
    if (unitEditingId.value) {
        unitForm.patch(route('admin.services.time-units.update', unitEditingId.value), {
            preserveScroll: true,
            onSuccess: closeUnitForm,
        });
    } else {
        unitForm.post(route('admin.services.time-units.store'), {
            preserveScroll: true,
            onSuccess: closeUnitForm,
        });
    }
}

function destroyUnit(id) {
    if (!confirm('Удалить единицу времени?')) return;
    router.delete(route('admin.services.time-units.destroy', id), { preserveScroll: true });
}

// ─── Price Limits ─────────────────────────────────────────────────────────────
const showLimitForm  = ref(false);
const limitEditingId = ref(null);

const limitForm = useForm({
    time_unit_id: null,
    max_price:    '',
});

function openLimitAdd() {
    limitEditingId.value    = null;
    limitForm.time_unit_id  = null;
    limitForm.max_price     = '';
    showLimitForm.value     = true;
}

function openLimitEdit(limit) {
    limitEditingId.value   = limit.id;
    limitForm.time_unit_id = limit.time_unit_id;
    limitForm.max_price    = limit.max_price;
    showLimitForm.value    = true;
}

function closeLimitForm() {
    showLimitForm.value  = false;
    limitEditingId.value = null;
    limitForm.reset();
    limitForm.clearErrors();
}

function submitLimit() {
    if (limitEditingId.value) {
        limitForm.patch(route('admin.services.price-limits.update', limitEditingId.value), {
            preserveScroll: true,
            onSuccess: closeLimitForm,
        });
    } else {
        limitForm.post(route('admin.services.price-limits.store'), {
            preserveScroll: true,
            onSuccess: closeLimitForm,
        });
    }
}

function destroyLimit(id) {
    if (!confirm('Удалить лимит цены?')) return;
    router.delete(route('admin.services.price-limits.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <div>
        <!-- Tabs -->
        <div class="tabs-nav">
            <button
                class="tab-btn"
                :class="{ 'tab-btn--active': active_tab === 'categories' }"
                @click="switchTab('categories')"
            >Категории</button>
            <button
                class="tab-btn"
                :class="{ 'tab-btn--active': active_tab === 'time-units' }"
                @click="switchTab('time-units')"
            >Ед. времени</button>
            <button
                class="tab-btn"
                :class="{ 'tab-btn--active': active_tab === 'price-limits' }"
                @click="switchTab('price-limits')"
            >Лимиты цен</button>
            <button
                class="tab-btn"
                :class="{ 'tab-btn--active': active_tab === 'moderation' }"
                @click="switchTab('moderation')"
            >
                Модерация
                <span v-if="moderation_counts?.pending > 0" class="tab-count">{{ moderation_counts?.pending }}</span>
            </button>
            <button
                class="tab-btn"
                :class="{ 'tab-btn--active': active_tab === 'change-requests' }"
                @click="switchTab('change-requests')"
            >Изменения</button>
        </div>

        <!-- ═══ Categories tab ═══ -->
        <template v-if="active_tab === 'categories'">
            <div class="page-header">
                <h1 class="page-title">Категории услуг</h1>
                <div class="header-actions">
                    <button class="btn-order" @click="openSortModal" title="Порядок категорий">↕ Порядок</button>
                    <CreateButton @click="openCatAdd">
                        <template #icon><el-icon><Plus /></el-icon></template>
                        Добавить
                    </CreateButton>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Порядок</th>
                            <th>Название</th>
                            <th>Описание</th>
                            <th>Подсказки</th>
                            <th>Фото</th>
                            <th>Активна</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="cat in categories" :key="cat.id">
                            <td>{{ cat.sort_order }}</td>
                            <td>{{ cat.name_ru }}</td>
                            <td class="td-desc">{{ cat.description_ru ? cat.description_ru.slice(0, 60) + (cat.description_ru.length > 60 ? '…' : '') : '—' }}</td>
                            <td class="td-suggestions">
                                <span v-if="cat.name_suggestions && cat.name_suggestions.length">
                                    {{ cat.name_suggestions.slice(0, 2).join(', ') }}{{ cat.name_suggestions.length > 2 ? ` +${cat.name_suggestions.length - 2}` : '' }}
                                </span>
                                <span v-else class="no-val">—</span>
                            </td>
                            <td>
                                <img v-if="cat.image_path" :src="`/storage/${cat.image_path}`" class="thumb" alt="" />
                                <span v-else class="no-val">—</span>
                            </td>
                            <td><span :class="['badge', cat.is_active ? 'badge--on' : 'badge--off']">{{ cat.is_active ? 'Да' : 'Нет' }}</span></td>
                            <td>
                                <div class="actions">
                                    <button class="btn-edit" @click="openCatEdit(cat)">Изменить</button>
                                    <button class="btn-danger" @click="destroyCat(cat.id)">Удалить</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!categories.length">
                            <td colspan="7" class="empty-row">Категорий нет</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Teleport to="body">
                <div v-if="showCatForm" class="overlay" @click.self="closeCatForm">
                    <div class="modal">
                        <div class="modal__header">
                            <span>{{ catEditingId ? 'Редактировать' : 'Новая категория' }}</span>
                            <button class="modal__close" @click="closeCatForm">✕</button>
                        </div>
                        <form @submit.prevent="submitCat" class="modal__body">
                            <div class="field">
                                <label>Название (RU)</label>
                                <input v-model="catForm.name_ru" class="input" :class="{ 'input--err': catForm.errors.name_ru }" />
                                <p v-if="catForm.errors.name_ru" class="err">{{ catForm.errors.name_ru }}</p>
                            </div>
                            <div class="field">
                                <label>Название (EN)</label>
                                <input v-model="catForm.name_en" class="input" :class="{ 'input--err': catForm.errors.name_en }" placeholder="Casual Chat" />
                                <p v-if="catForm.errors.name_en" class="err">{{ catForm.errors.name_en }}</p>
                            </div>
                            <div class="field">
                                <label>Описание (RU)</label>
                                <textarea v-model="catForm.description_ru" class="input input--textarea" rows="3" maxlength="1000" placeholder="Описание категории для профиля айдола" />
                            </div>
                            <div class="field">
                                <label>Описание (EN)</label>
                                <textarea v-model="catForm.description_en" class="input input--textarea" rows="3" maxlength="1000" placeholder="Description for idol profile" />
                            </div>
                            <div class="field">
                                <label>Акцентный цвет</label>
                                <input v-model="catForm.accent_color" type="color" class="input input--color" />
                            </div>
                            <div class="field">
                                <label>Варианты названий (RU)</label>
                                <div class="sug-input-row">
                                    <input v-model="newSuggestionRu" class="input" placeholder="Введите вариант…"
                                        maxlength="120" @keydown.enter.prevent="addSuggestionRu" />
                                    <button type="button" class="sug-add-btn" @click="addSuggestionRu">+</button>
                                </div>
                                <div v-if="catForm.name_suggestions_ru.length" class="sug-chips">
                                    <span v-for="(s, i) in catForm.name_suggestions_ru" :key="i" class="sug-chip">
                                        {{ s }}
                                        <button type="button" class="sug-chip__remove" @click="removeSuggestionRu(i)">×</button>
                                    </span>
                                </div>
                            </div>
                            <div class="field">
                                <label>Варианты названий (EN)</label>
                                <div class="sug-input-row">
                                    <input v-model="newSuggestionEn" class="input" placeholder="Enter suggestion…"
                                        maxlength="120" @keydown.enter.prevent="addSuggestionEn" />
                                    <button type="button" class="sug-add-btn" @click="addSuggestionEn">+</button>
                                </div>
                                <div v-if="catForm.name_suggestions_en.length" class="sug-chips">
                                    <span v-for="(s, i) in catForm.name_suggestions_en" :key="i" class="sug-chip">
                                        {{ s }}
                                        <button type="button" class="sug-chip__remove" @click="removeSuggestionEn(i)">×</button>
                                    </span>
                                </div>
                            </div>
                            <div class="field">
                                <label>Изображение категории</label>
                                <ImageDropzone
                                    :preview="catImagePreview"
                                    @change="onCatImageChange"
                                    @remove="removeCatImage"
                                />
                            </div>
                            <div class="field">
                                <label>Порядок сортировки</label>
                                <input v-model.number="catForm.sort_order" type="number" min="0" class="input" />
                            </div>
                            <div class="field field--row">
                                <label>Активна</label>
                                <input v-model="catForm.is_active" type="checkbox" />
                            </div>
                            <div class="modal__actions">
                                <button type="button" class="btn-cancel" @click="closeCatForm">Отмена</button>
                                <button type="submit" class="btn-submit" :disabled="catForm.processing">Сохранить</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>

            <!-- Sort Modal -->
            <Teleport to="body">
                <div v-if="showSortModal" class="overlay" @click.self="closeSortModal">
                    <div class="modal modal--sort">
                        <div class="modal__header">
                            <span>Порядок категорий</span>
                            <button class="modal__close" @click="closeSortModal">✕</button>
                        </div>
                        <div class="modal__body">
                            <draggable v-model="sortList" item-key="id" tag="div" class="sort-grid">
                                <template #item="{ element: cat, index }">
                                    <div class="sort-card">
                                        <div class="sort-card__arrows">
                                            <button class="arrow-btn" :disabled="index === 0" @click="moveUp(index)">↑</button>
                                            <button class="arrow-btn" :disabled="index === sortList.length - 1" @click="moveDown(index)">↓</button>
                                        </div>
                                        <div class="sort-card__img-wrap">
                                            <img v-if="cat.image_path" :src="`/storage/${cat.image_path}`" class="sort-card__img" alt="" />
                                            <div v-else class="sort-card__no-img">{{ (cat.name_ru ?? '').slice(0, 2) }}</div>
                                        </div>
                                        <div class="sort-card__name">{{ cat.name_ru }}</div>
                                    </div>
                                </template>
                            </draggable>
                            <div class="modal__actions">
                                <button type="button" class="btn-cancel" @click="closeSortModal">Отмена</button>
                                <button type="button" class="btn-submit" @click="saveOrder">Сохранить порядок</button>
                            </div>
                        </div>
                    </div>
                </div>
            </Teleport>
        </template>

        <!-- ═══ Time Units tab ═══ -->
        <template v-if="active_tab === 'time-units'">
            <div class="page-header">
                <h1 class="page-title">Единицы времени</h1>
                <CreateButton @click="openUnitAdd">
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
                            <td>{{ unit.name_ru }}<span v-if="unit.name_en" style="color:#888;font-size:0.82em"> / {{ unit.name_en }}</span></td>
                            <td><span :class="['badge', unit.is_active ? 'badge--on' : 'badge--off']">{{ unit.is_active ? 'Да' : 'Нет' }}</span></td>
                            <td>
                                <div class="actions">
                                    <button class="btn-edit" @click="openUnitEdit(unit)">Изменить</button>
                                    <button class="btn-danger" @click="destroyUnit(unit.id)">Удалить</button>
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
                <div v-if="showUnitForm" class="overlay" @click.self="closeUnitForm">
                    <div class="modal">
                        <div class="modal__header">
                            <span>{{ unitEditingId ? 'Редактировать' : 'Новая единица' }}</span>
                            <button class="modal__close" @click="closeUnitForm">✕</button>
                        </div>
                        <form @submit.prevent="submitUnit" class="modal__body">
                            <div class="field">
                                <label>Название (RU)</label>
                                <input v-model="unitForm.name_ru" class="input" :class="{ 'input--err': unitForm.errors.name_ru }" placeholder="15 минут" />
                                <p v-if="unitForm.errors.name_ru" class="err">{{ unitForm.errors.name_ru }}</p>
                            </div>
                            <div class="field">
                                <label>Название (EN)</label>
                                <input v-model="unitForm.name_en" class="input" :class="{ 'input--err': unitForm.errors.name_en }" placeholder="15 minutes" />
                                <p v-if="unitForm.errors.name_en" class="err">{{ unitForm.errors.name_en }}</p>
                            </div>
                            <div class="field">
                                <label>Порядок сортировки</label>
                                <input v-model.number="unitForm.sort_order" type="number" min="0" class="input" />
                            </div>
                            <div class="field field--row">
                                <label>Активна</label>
                                <input v-model="unitForm.is_active" type="checkbox" />
                            </div>
                            <div class="modal__actions">
                                <button type="button" class="btn-cancel" @click="closeUnitForm">Отмена</button>
                                <button type="submit" class="btn-submit" :disabled="unitForm.processing">Сохранить</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>
        </template>

        <!-- ═══ Price Limits tab ═══ -->
        <template v-if="active_tab === 'price-limits'">
            <div class="page-header">
                <h1 class="page-title">Лимиты цен</h1>
                <CreateButton @click="openLimitAdd">
                    <template #icon><el-icon><Plus /></el-icon></template>
                    Добавить
                </CreateButton>
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
                            <td>{{ limit.time_unit?.name_ru ?? '—' }}</td>
                            <td>{{ limit.max_price.toLocaleString('ru') }} ₽</td>
                            <td>
                                <div class="actions">
                                    <button class="btn-edit" @click="openLimitEdit(limit)">Изменить</button>
                                    <button class="btn-danger" @click="destroyLimit(limit.id)">Удалить</button>
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
                <div v-if="showLimitForm" class="overlay" @click.self="closeLimitForm">
                    <div class="modal">
                        <div class="modal__header">
                            <span>{{ limitEditingId ? 'Редактировать лимит' : 'Новый лимит' }}</span>
                            <button class="modal__close" @click="closeLimitForm">✕</button>
                        </div>
                        <form @submit.prevent="submitLimit" class="modal__body">
                            <div class="field">
                                <label>Единица времени</label>
                                <AppSelect
                                    v-model="limitForm.time_unit_id"
                                    :options="timeUnits.map(u => ({ value: u.id, label: u.name_ru }))"
                                    placeholder="Выберите..."
                                    :error="!!limitForm.errors.time_unit_id"
                                />
                                <p v-if="limitForm.errors.time_unit_id" class="err">{{ limitForm.errors.time_unit_id }}</p>
                            </div>
                            <div class="field">
                                <label>Максимальная цена (₽)</label>
                                <input v-model.number="limitForm.max_price" type="number" min="1" class="input" :class="{ 'input--err': limitForm.errors.max_price }" placeholder="300" />
                                <p v-if="limitForm.errors.max_price" class="err">{{ limitForm.errors.max_price }}</p>
                            </div>
                            <div class="modal__actions">
                                <button type="button" class="btn-cancel" @click="closeLimitForm">Отмена</button>
                                <button type="submit" class="btn-submit" :disabled="limitForm.processing">Сохранить</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>
        </template>

        <!-- ═══ Moderation tab ═══ -->
        <template v-if="active_tab === 'moderation'">

            <!-- Status tabs -->
            <div class="mod-status-tabs">
                <button
                    v-for="tab in [
                        { key: 'pending',     label: 'Ожидают',      count: moderation_counts?.pending },
                        { key: 'resubmitted', label: 'Переподано',   count: moderation_counts?.resubmitted },
                        { key: 'has_remarks', label: 'Замечания',    count: moderation_counts?.has_remarks },
                        { key: 'approved',    label: 'Одобрены',     count: moderation_counts?.approved },
                        { key: 'rejected',    label: 'Отклонены',    count: moderation_counts?.rejected },
                    ]"
                    :key="tab.key"
                    class="mod-status-tab"
                    :class="{ 'mod-status-tab--active': filterStatus === tab.key }"
                    @click="switchStatusTab(tab.key)"
                >
                    {{ tab.label }}
                    <span v-if="tab.count > 0" class="tab-count">{{ tab.count }}</span>
                </button>
            </div>

            <!-- Search + category filter -->
            <div class="mod-filters">
                <input
                    v-model="filterSearch"
                    class="input mod-search"
                    placeholder="Поиск по имени, email, услуге…"
                    @input="onSearchInput"
                />
                <select
                    v-model="filterCategoryId"
                    class="input mod-cat-select"
                    @change="applyFilters"
                >
                    <option :value="null">Все категории</option>
                    <option v-for="cat in moderation_categories" :key="cat.id" :value="cat.id">
                        {{ cat.name_ru ?? cat.name }}
                    </option>
                </select>
            </div>

            <!-- Bulk action bar (when items selected) / page header -->
            <div class="page-header">
                <template v-if="selectedIds.size > 0">
                    <span class="mod-selected-label">✓ {{ selectedIds.size }} выбрано</span>
                    <div class="actions">
                        <button class="btn-approve" @click="bulkApprove">Одобрить выбранные</button>
                        <button class="btn-danger" @click="openBulkRejectModal(false)">Отклонить выбранные</button>
                    </div>
                </template>
                <template v-else>
                    <h1 class="page-title">Модерация услуг</h1>
                    <div v-if="filterStatus === 'pending'" class="actions">
                        <button class="btn-approve" @click="bulkApproveAll">
                            Одобрить все ({{ moderation_services?.total ?? 0 }})
                        </button>
                        <button class="btn-danger" @click="openBulkRejectModal(true)">
                            Отклонить все ({{ moderation_services?.total ?? 0 }})
                        </button>
                    </div>
                </template>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:36px;">
                                <label class="mod-cb">
                                    <input
                                        type="checkbox"
                                        class="mod-cb__input"
                                        :checked="allSelected"
                                        :indeterminate="selectedIds.size > 0 && !allSelected"
                                        @change="toggleSelectAll"
                                    />
                                    <span class="mod-cb__box">
                                        <svg v-if="allSelected" class="mod-cb__check" viewBox="0 0 10 8" fill="none">
                                            <path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <svg v-else-if="selectedIds.size > 0" class="mod-cb__minus" viewBox="0 0 10 2" fill="none">
                                            <path d="M1 1h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                </label>
                            </th>
                            <th>Айдол</th>
                            <th>Услуга</th>
                            <th>Категория</th>
                            <th>Ед. времени</th>
                            <th>Цена</th>
                            <th>Статус</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in moderation_services?.data" :key="s.id"
                            :class="{ 'tr--selected': selectedIds.has(s.id) }">
                            <td>
                                <label class="mod-cb">
                                    <input type="checkbox" class="mod-cb__input" :checked="selectedIds.has(s.id)" @change="toggleRow(s.id)" />
                                    <span class="mod-cb__box">
                                        <svg v-if="selectedIds.has(s.id)" class="mod-cb__check" viewBox="0 0 10 8" fill="none">
                                            <path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                </label>
                            </td>
                            <td>
                                <div class="mod-user-cell">
                                    <img v-if="s.user?.avatar_url" :src="s.user.avatar_url" class="mod-avatar" alt="" />
                                    <div v-else class="mod-avatar mod-avatar--initials">{{ s.user?.name?.[0] ?? '?' }}</div>
                                    <div>
                                        <div class="mod-name">{{ s.user?.name ?? '—' }}</div>
                                        <div class="mod-email">{{ s.user?.email ?? '' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ s.name }}</td>
                            <td class="no-val">{{ s.category ?? '—' }}</td>
                            <td class="no-val">{{ s.time_unit ?? '—' }}</td>
                            <td>{{ s.price?.toLocaleString('ru') }} ₽</td>
                            <td>
                                <div>
                                    <span :class="['badge', `badge--${s.status}`]">
                                        {{ s.status === 'pending' ? 'Ожидает' : s.status === 'approved' ? 'Одобрено' : 'Отклонено' }}
                                    </span>
                                    <div v-if="s.status === 'rejected' && s.rejection_reason"
                                         class="mod-reject-reason"
                                         :title="s.rejection_reason">
                                        {{ s.rejection_reason.length > 40 ? s.rejection_reason.slice(0, 40) + '…' : s.rejection_reason }}
                                    </div>
                                </div>
                            </td>
                            <td class="no-val">{{ s.created_at ? new Date(s.created_at).toLocaleDateString('ru') : '—' }}</td>
                            <td>
                                <div v-if="s.status === 'pending'" class="actions">
                                    <button class="btn-approve" @click="approveService(s.id)">Одобрить</button>
                                    <button class="btn-danger" @click="openRejectModal(s)">Отклонить</button>
                                </div>
                                <span v-else class="no-val">—</span>
                            </td>
                        </tr>
                        <tr v-if="!moderation_services?.data?.length">
                            <td colspan="9" class="empty-row">Услуг нет</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="pagination" v-if="moderation_services?.last_page > 1">
                <a
                    v-for="link in moderation_services.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="page-link"
                    :class="{ 'page-link--active': link.active, 'page-link--disabled': !link.url }"
                    v-html="link.label"
                    @click.prevent="link.url && router.get(link.url, {}, { preserveState: true })"
                />
            </div>

            <Teleport to="body">
                <div v-if="showRejectModal" class="overlay" @click.self="closeRejectModal">
                    <div class="modal">
                        <div class="modal__header">
                            <span>{{ rejectModalTitle }}</span>
                            <button class="modal__close" @click="closeRejectModal">✕</button>
                        </div>
                        <form @submit.prevent="submitReject" class="modal__body">
                            <div class="field">
                                <label>Шаблоны</label>
                                <div class="reject-templates">
                                    <button
                                        v-for="t in REJECT_TEMPLATES"
                                        :key="t"
                                        type="button"
                                        class="reject-tpl"
                                        :class="{ 'reject-tpl--active': rejectReason === t }"
                                        @click="rejectReason = t; rejectError = ''"
                                    >{{ t }}</button>
                                </div>
                            </div>
                            <div class="field">
                                <label>Причина *</label>
                                <textarea
                                    v-model="rejectReason"
                                    class="input input--textarea"
                                    rows="3"
                                    maxlength="500"
                                    :class="{ 'input--err': rejectError }"
                                />
                                <p v-if="rejectError" class="err">{{ rejectError }}</p>
                            </div>
                            <div class="modal__actions">
                                <button type="button" class="btn-cancel" @click="closeRejectModal">Отмена</button>
                                <button type="submit" class="btn-submit">Отклонить</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>
        </template>
    </div>
</template>

<style scoped>
/* Tabs */
.tabs-nav {
    display: flex;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    margin-bottom: 1.5rem;
}
.tab-btn {
    padding: 0.6rem 1.25rem;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    color: rgba(255,255,255,0.4);
    font-size: 0.88rem;
    cursor: pointer;
    font-family: inherit;
    transition: color 0.15s;
    margin-bottom: -1px;
}
.tab-btn:hover { color: rgba(255,255,255,0.75); }
.tab-btn--active { color: #9B6EE8; border-bottom-color: #9B6EE8; }

/* Header */
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.page-title  { font-size: 1.4rem; color: #fff; margin: 0; }
.hint { font-size: 0.82rem; color: rgba(255,255,255,0.35); margin: 0 0 1.25rem; }

/* Table */
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
    text-align: left;
    padding: 0.75rem 1.25rem;
    font-size: 0.82rem;
    color: rgba(255,255,255,0.35);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    border-bottom: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.025);
}
.data-table td {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    color: rgba(255,255,255,0.8);
    font-size: 0.93rem;
}
.data-table tr:hover td { background: rgba(255,255,255,0.03); }
.td-desc        { max-width: 200px; color: rgba(255,255,255,0.45); font-size: 0.8rem; }
.td-suggestions { max-width: 180px; color: rgba(255,255,255,0.45); font-size: 0.8rem; }
.empty-row { text-align: center; color: rgba(255,255,255,0.25); padding: 2rem; }
.thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 3px; }
.no-val { color: rgba(255,255,255,0.2); }

.badge { padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.72rem; font-weight: 600; }
.badge--on  { background: rgba(74,222,128,0.1); color: rgba(74,222,128,0.8); }
.badge--off { background: rgba(239,68,68,0.1);  color: rgba(239,68,68,0.7); }

.actions { display: flex; gap: 0.5rem; }
.btn-edit, .btn-danger { padding: 0.3rem 0.7rem; border-radius: 3px; font-size: 0.78rem; cursor: pointer; font-family: inherit; transition: background 0.15s; }
.btn-edit   { border: 1px solid rgba(255,255,255,0.15); background: transparent; color: rgba(255,255,255,0.6); }
.btn-edit:hover   { background: rgba(255,255,255,0.08); }
.btn-danger { border: 1px solid rgba(239,68,68,0.3); background: transparent; color: rgba(239,68,68,0.7); }
.btn-danger:hover { background: rgba(239,68,68,0.1); }

/* Modal */
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #0a0a0f; border: 1px solid rgba(255,255,255,0.12); border-radius: 3px; width: 100%; max-width: 420px; margin: 1rem; max-height: 90vh; overflow-y: auto; }
.modal__header { display: flex; align-items: center; justify-content: space-between; padding: 0.8rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.07); font-size: 0.88rem; color: rgba(255,255,255,0.85); font-weight: 600; }
.modal__close { background: none; border: none; color: rgba(255,255,255,0.4); cursor: pointer; font-size: 1rem; }
.modal__body { padding: 1rem; display: flex; flex-direction: column; gap: 0.8rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field--row { flex-direction: row; align-items: center; gap: 0.5rem; }
.field label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.35); }
.input { padding: 0.45rem 0.7rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 3px; color: rgba(255,255,255,0.85); font-family: inherit; font-size: 0.88rem; outline: none; transition: border-color 0.15s; box-sizing: border-box; width: 100%; }
.input:focus { border-color: rgba(190,145,255,0.45); }
.input--err { border-color: rgba(239,68,68,0.5); }
.input--textarea { resize: vertical; min-height: 72px; }
.input--color { width: 48px; height: 36px; padding: 2px 4px; cursor: pointer; }
.input-file { font-size: 0.82rem; color: rgba(255,255,255,0.5); cursor: pointer; }
.err { font-size: 0.75rem; color: rgba(239,68,68,0.8); margin: 0; }
.reject-templates { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.reject-tpl { padding: 0.3rem 0.65rem; background: transparent; border: 1px dashed rgba(255,255,255,0.18); border-radius: 3px; color: rgba(255,255,255,0.45); font-size: 0.8rem; font-family: inherit; cursor: pointer; transition: border-color 0.15s, color 0.15s, background 0.15s; }
.reject-tpl:hover { border-color: rgba(255,255,255,0.4); color: rgba(255,255,255,0.8); }
.reject-tpl--active { border-style: solid; border-color: rgba(255,100,100,0.55); color: rgba(255,130,130,0.9); background: rgba(255,80,80,0.08); }
.modal__actions { display: flex; justify-content: flex-end; gap: 0.5rem; padding-top: 0.25rem; }
.btn-cancel { padding: 0.45rem 0.9rem; border: 1px solid rgba(255,255,255,0.12); border-radius: 3px; background: transparent; color: rgba(255,255,255,0.4); font-family: inherit; font-size: 0.82rem; cursor: pointer; }
.btn-submit { padding: 0.45rem 1rem; border: 1px solid rgba(190,145,255,0.45); border-radius: 3px; background: rgba(190,145,255,0.1); color: rgba(255,255,255,0.9); font-family: inherit; font-size: 0.82rem; cursor: pointer; }
.btn-submit:disabled { opacity: 0.5; cursor: default; }

.sug-input-row { display: flex; gap: 0.4rem; }
.sug-add-btn { flex-shrink: 0; width: 34px; height: 34px; border: 1px solid rgba(190,145,255,0.35); border-radius: 3px; background: rgba(190,145,255,0.08); color: rgba(190,145,255,0.8); font-size: 1.1rem; line-height: 1; cursor: pointer; }
.sug-add-btn:hover { background: rgba(190,145,255,0.18); }
.sug-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.4rem; }
.sug-chip { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.22rem 0.55rem; border: 1px solid rgba(190,145,255,0.25); border-radius: 99px; background: rgba(190,145,255,0.07); color: rgba(255,255,255,0.75); font-size: 0.75rem; }
.sug-chip__remove { background: none; border: none; color: rgba(255,255,255,0.3); cursor: pointer; font-size: 0.95rem; line-height: 1; padding: 0; }
.sug-chip__remove:hover { color: rgba(239,68,68,0.7); }

/* Moderation tab */
.mod-status-tabs { display: flex; gap: 0; margin-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.08); }
.mod-status-tab { padding: 0.45rem 1rem; background: none; border: none; border-bottom: 2px solid transparent; color: rgba(255,255,255,0.4); font-size: 0.84rem; cursor: pointer; font-family: inherit; margin-bottom: -1px; transition: color 0.15s; display: flex; align-items: center; gap: 0.4rem; }
.mod-status-tab:hover { color: rgba(255,255,255,0.7); }
.mod-status-tab--active { color: #9B6EE8; border-bottom-color: #9B6EE8; }

.mod-filters { display: flex; gap: 0.6rem; margin-bottom: 1rem; }
.mod-search { flex: 1; }
.mod-cat-select { width: 180px; appearance: none; }

.mod-selected-label { font-size: 0.88rem; color: rgba(255,255,255,0.7); font-weight: 600; }

.tr--selected td { background: rgba(155,110,232,0.06); }

.badge--pending  { background: rgba(251,146,60,0.12); color: rgba(251,146,60,0.9); }
.badge--approved { background: rgba(74,222,128,0.1);  color: rgba(74,222,128,0.8); }
.badge--rejected { background: rgba(239,68,68,0.1);   color: rgba(239,68,68,0.7); }

.mod-reject-reason { font-size: 0.72rem; color: rgba(239,68,68,0.55); margin-top: 0.2rem; cursor: help; }

.mod-resub-hint {
    font-size: 0.75rem;
    color: #ff9800;
    font-weight: 600;
    text-transform: uppercase;
}

.badge--has_remarks {
    background: #fff3e0;
    color: #ef6c00;
    border: 1px solid #ffe0b2;
}

.mod-user-cell { display: flex; align-items: center; gap: 0.6rem; }
.mod-avatar { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; background: rgba(155,110,232,0.15); flex-shrink: 0; }
.mod-avatar--initials { display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 600; color: #9B6EE8; border: 1px solid rgba(155,110,232,0.3); }
.mod-name  { font-size: 0.85rem; color: rgba(255,255,255,0.85); font-weight: 500; }
.mod-email { font-size: 0.75rem; color: rgba(255,255,255,0.3); }
.btn-approve { padding: 0.3rem 0.7rem; border: 1px solid rgba(76,222,143,0.4); background: rgba(76,222,143,0.07); color: #4cde8f; font-family: inherit; font-size: 0.78rem; cursor: pointer; border-radius: 3px; }
.btn-approve:hover { background: rgba(76,222,143,0.18); }

/* Pagination */
.pagination { display: flex; gap: 0.25rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link { padding: 0.28rem 0.6rem; border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); font-size: 0.8rem; text-decoration: none; cursor: pointer; }
.page-link--active   { border-color: rgba(155,110,232,0.6); color: #9B6EE8; background: rgba(155,110,232,0.1); }
.page-link--disabled { opacity: 0.3; pointer-events: none; }

/* Styled checkbox */
.mod-cb {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    user-select: none;
}
.mod-cb__input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}
.mod-cb__box {
    width: 15px;
    height: 15px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.04);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: border-color 0.15s, background 0.15s;
    color: #9B6EE8;
}
.mod-cb:hover .mod-cb__box {
    border-color: rgba(155, 110, 232, 0.5);
    background: rgba(155, 110, 232, 0.06);
}
.mod-cb__input:checked ~ .mod-cb__box,
.mod-cb__input:indeterminate ~ .mod-cb__box {
    border-color: rgba(155, 110, 232, 0.7);
    background: rgba(155, 110, 232, 0.15);
}
.mod-cb__check,
.mod-cb__minus {
    width: 10px;
    height: 10px;
}

/* Header actions */
.header-actions { display: flex; align-items: center; gap: 0.6rem; }
.btn-order {
    padding: 0.4rem 0.8rem;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 3px;
    background: transparent;
    color: rgba(255, 255, 255, 0.6);
    font-family: inherit;
    font-size: 0.82rem;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}
.btn-order:hover { background: rgba(255, 255, 255, 0.08); border-color: rgba(255, 255, 255, 0.25); }

/* Sort modal */
.modal--sort { max-width: 680px; }
.sort-grid { display: flex; flex-wrap: wrap; gap: 0.75rem; }
.sort-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
    width: 100px;
    cursor: grab;
    user-select: none;
}
.sort-card:active { cursor: grabbing; }
.sort-card__arrows { display: flex; gap: 0.25rem; }
.arrow-btn {
    width: 26px;
    height: 26px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 3px;
    background: transparent;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.arrow-btn:hover:not(:disabled) { background: rgba(255, 255, 255, 0.08); color: rgba(255, 255, 255, 0.9); }
.arrow-btn:disabled { opacity: 0.25; cursor: default; }
.sort-card__img-wrap { width: 90px; height: 90px; border-radius: 4px; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.1); }
.sort-card__img { width: 100%; height: 100%; object-fit: cover; }
.sort-card__no-img {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.3);
    font-size: 1.4rem;
    font-weight: 600;
    text-transform: uppercase;
}
.sort-card__name { font-size: 0.72rem; color: rgba(255, 255, 255, 0.6); text-align: center; word-break: break-word; line-height: 1.2; }
</style>
