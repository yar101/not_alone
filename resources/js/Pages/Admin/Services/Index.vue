<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Plus } from '@element-plus/icons-vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import CreateButton from '@/Components/CreateButton.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    categories:          Array,
    timeUnits:           Array,
    limits:              Array,
    moderation_services: Object,
    active_tab:          String,
});

function switchTab(tab) {
    if (tab === 'categories')   router.visit(route('admin.services.categories.index'),   { preserveState: false });
    if (tab === 'time-units')   router.visit(route('admin.services.time-units.index'),   { preserveState: false });
    if (tab === 'price-limits') router.visit(route('admin.services.price-limits.index'), { preserveState: false });
    if (tab === 'moderation')   router.visit(route('admin.services.moderation.index'),   { preserveState: false });
}

// ─── Moderation ──────────────────────────────────────────────────────────────
const showRejectModal  = ref(false);
const rejectTarget     = ref(null);
const rejectReason     = ref('');
const rejectError      = ref('');

function openRejectModal(service) {
    rejectTarget.value = service;
    rejectReason.value = '';
    rejectError.value  = '';
    showRejectModal.value = true;
}

function closeRejectModal() {
    showRejectModal.value = false;
    rejectTarget.value = null;
}

function approveService(id) {
    router.patch(route('admin.services.moderation.approve', id), {}, { preserveScroll: false });
}

function submitReject() {
    if (!rejectReason.value.trim()) {
        rejectError.value = 'Укажите причину отклонения';
        return;
    }
    router.patch(route('admin.services.moderation.reject', rejectTarget.value.id), {
        rejection_reason: rejectReason.value,
    }, {
        preserveScroll: false,
        onSuccess: closeRejectModal,
    });
}

// ─── Categories ──────────────────────────────────────────────────────────────
const showCatForm     = ref(false);
const catEditingId    = ref(null);
const catImagePreview = ref(null);
const newSuggestion   = ref('');

const catForm = useForm({
    name:             '',
    description:      '',
    name_suggestions: [],
    sort_order:       0,
    is_active:        true,
    image:            null,
});

function openCatAdd() {
    catEditingId.value    = null;
    catImagePreview.value = null;
    newSuggestion.value   = '';
    catForm.reset();
    catForm.is_active = true;
    catForm.name_suggestions = [];
    showCatForm.value = true;
}

function openCatEdit(cat) {
    catEditingId.value    = cat.id;
    catImagePreview.value = cat.image_path ? `/storage/${cat.image_path}` : null;
    newSuggestion.value   = '';
    catForm.name             = cat.name;
    catForm.description      = cat.description ?? '';
    catForm.name_suggestions = cat.name_suggestions ?? [];
    catForm.sort_order       = cat.sort_order;
    catForm.is_active        = cat.is_active;
    catForm.image            = null;
    showCatForm.value        = true;
}

function closeCatForm() {
    showCatForm.value     = false;
    catEditingId.value    = null;
    catImagePreview.value = null;
    newSuggestion.value   = '';
    catForm.reset();
    catForm.clearErrors();
}

function addSuggestion() {
    const s = newSuggestion.value.trim();
    if (!s || catForm.name_suggestions.includes(s)) return;
    catForm.name_suggestions.push(s);
    newSuggestion.value = '';
}

function removeSuggestion(index) {
    catForm.name_suggestions.splice(index, 1);
}

function onCatImageChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    catForm.image = file;
    catImagePreview.value = URL.createObjectURL(file);
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

// ─── Time Units ───────────────────────────────────────────────────────────────
const showUnitForm  = ref(false);
const unitEditingId = ref(null);

const unitForm = useForm({
    name:       '',
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
    unitForm.name       = unit.name;
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
                <span v-if="moderation_services && moderation_services.total > 0" class="tab-count">{{ moderation_services.total }}</span>
            </button>
        </div>

        <!-- ═══ Categories tab ═══ -->
        <template v-if="active_tab === 'categories'">
            <div class="page-header">
                <h1 class="page-title">Категории услуг</h1>
                <CreateButton @click="openCatAdd">
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
                            <td>{{ cat.name }}</td>
                            <td class="td-desc">{{ cat.description ? cat.description.slice(0, 60) + (cat.description.length > 60 ? '…' : '') : '—' }}</td>
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
                                <label>Название</label>
                                <input v-model="catForm.name" class="input" :class="{ 'input--err': catForm.errors.name }" />
                                <p v-if="catForm.errors.name" class="err">{{ catForm.errors.name }}</p>
                            </div>
                            <div class="field">
                                <label>Описание (глобальное)</label>
                                <textarea v-model="catForm.description" class="input input--textarea" rows="3" maxlength="1000" placeholder="Описание категории для профиля айдола" />
                            </div>
                            <div class="field">
                                <label>Варианты названий</label>
                                <div class="sug-input-row">
                                    <input
                                        v-model="newSuggestion"
                                        class="input"
                                        placeholder="Введите вариант…"
                                        maxlength="120"
                                        @keydown.enter.prevent="addSuggestion"
                                    />
                                    <button type="button" class="sug-add-btn" @click="addSuggestion">+</button>
                                </div>
                                <div v-if="catForm.name_suggestions.length" class="sug-chips">
                                    <span v-for="(s, i) in catForm.name_suggestions" :key="i" class="sug-chip">
                                        {{ s }}
                                        <button type="button" class="sug-chip__remove" @click="removeSuggestion(i)">×</button>
                                    </span>
                                </div>
                            </div>
                            <div class="field">
                                <label>Изображение категории</label>
                                <div v-if="catImagePreview" class="img-preview">
                                    <img :src="catImagePreview" alt="preview" class="img-preview__img" />
                                </div>
                                <input type="file" accept="image/jpeg,image/png,image/webp" class="input-file" @change="onCatImageChange" />
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
                            <td>{{ unit.name }}</td>
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
                                <label>Название</label>
                                <input v-model="unitForm.name" class="input" :class="{ 'input--err': unitForm.errors.name }" placeholder="15 минут" />
                                <p v-if="unitForm.errors.name" class="err">{{ unitForm.errors.name }}</p>
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
                            <td>{{ limit.time_unit?.name ?? '—' }}</td>
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
                                    :options="timeUnits.map(u => ({ value: u.id, label: u.name }))"
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
            <div class="page-header">
                <h1 class="page-title">Модерация услуг</h1>
                <span v-if="moderation_services" class="hint" style="margin: 0;">{{ moderation_services.total }} на рассмотрении</span>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Айдол</th>
                            <th>Услуга</th>
                            <th>Категория</th>
                            <th>Ед. времени</th>
                            <th>Цена</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in moderation_services?.data" :key="s.id">
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
                            <td class="no-val">{{ s.created_at ? new Date(s.created_at).toLocaleDateString('ru') : '—' }}</td>
                            <td>
                                <div class="actions">
                                    <button class="btn-approve" @click="approveService(s.id)">Одобрить</button>
                                    <button class="btn-danger" @click="openRejectModal(s)">Отклонить</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!moderation_services?.data?.length">
                            <td colspan="7" class="empty-row">Услуг на модерации нет</td>
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
                            <span>Отклонить услугу: {{ rejectTarget?.name }}</span>
                            <button class="modal__close" @click="closeRejectModal">✕</button>
                        </div>
                        <form @submit.prevent="submitReject" class="modal__body">
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
.input-file { font-size: 0.82rem; color: rgba(255,255,255,0.5); cursor: pointer; }
.img-preview { margin-bottom: 0.4rem; }
.img-preview__img { width: 100%; max-height: 140px; object-fit: cover; border-radius: 3px; border: 1px solid rgba(255,255,255,0.1); }
.err { font-size: 0.75rem; color: rgba(239,68,68,0.8); margin: 0; }
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
.mod-user-cell { display: flex; align-items: center; gap: 0.6rem; }
.mod-avatar { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; background: rgba(155,110,232,0.15); flex-shrink: 0; }
.mod-avatar--initials { display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 600; color: #9B6EE8; border: 1px solid rgba(155,110,232,0.3); }
.mod-name  { font-size: 0.85rem; color: rgba(255,255,255,0.85); font-weight: 500; }
.mod-email { font-size: 0.75rem; color: rgba(255,255,255,0.3); }
.btn-approve { padding: 0.3rem 0.7rem; border: 1px solid rgba(76,222,143,0.4); background: rgba(76,222,143,0.07); color: #4cde8f; font-family: inherit; font-size: 0.78rem; cursor: pointer; }
.btn-approve:hover { background: rgba(76,222,143,0.18); }

/* Pagination */
.pagination { display: flex; gap: 0.25rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link { padding: 0.28rem 0.6rem; border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); font-size: 0.8rem; text-decoration: none; cursor: pointer; }
.page-link--active   { border-color: rgba(155,110,232,0.6); color: #9B6EE8; background: rgba(155,110,232,0.1); }
.page-link--disabled { opacity: 0.3; pointer-events: none; }
</style>
