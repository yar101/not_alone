<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Plus } from '@element-plus/icons-vue';
import AppSelect from '@/Components/AppSelect.vue';
import CreateButton from '@/Components/CreateButton.vue';

const props = defineProps({
    services: { default: null },
    serviceCategories: { default: null },
    serviceTimeUnits: { default: null },
    isOwner: { type: Boolean, default: false },
    isIdol: { type: Boolean, default: false },
    profileUser: { type: Object, default: null },
});

// ── localServices — не сбрасывается в null при redirect-рефетче ─
const localServices = ref(null);

// ── Two-level navigation ────────────────────────────────────────
const selectedCategory = ref(null);
const pendingResync = ref(false);
const catTransitionDir = ref('forward'); // 'forward' | 'back'
const catTransitionName = computed(() =>
    catTransitionDir.value === 'forward' ? 'drill-in' : 'drill-out'
);

const SESSION_KEY = computed(() => `services_cat_${props.profileUser?.id}`);

function openCategory(group) {
    catTransitionDir.value = 'forward';
    selectedCategory.value = group;
    sessionStorage.setItem(SESSION_KEY.value, group.category.id);
}

function backToList() {
    catTransitionDir.value = 'back';
    selectedCategory.value = null;
    sessionStorage.removeItem(SESSION_KEY.value);
}

// Mark that a resync is needed after the next deferred prop update
function resyncSelectedCategory() {
    pendingResync.value = true;
}

// Restore on initial deferred prop load OR after a mutation (pendingResync)
watch(() => props.services, (services) => {
    if (!services) return;
    localServices.value = services;
    const catId = selectedCategory.value?.category?.id
        ?? sessionStorage.getItem(SESSION_KEY.value);
    if (!catId) return;
    if (!selectedCategory.value || pendingResync.value) {
        const group = services.find(g => g.category.id == catId);
        selectedCategory.value = group ?? null;
        if (!group) sessionStorage.removeItem(SESSION_KEY.value);
        pendingResync.value = false;
    }
}, { immediate: true });

// ── Inline description edit ─────────────────────────────────────
const editingDesc = ref(false);
const descDraft = ref('');

function startDescEdit() {
    descDraft.value = selectedCategory.value?.idol_description ?? '';
    editingDesc.value = true;
}

function cancelDescEdit() {
    editingDesc.value = false;
}

function saveDesc() {
    const catId = selectedCategory.value?.category?.id;
    if (!catId) return;
    router.patch(
        route('profile.services.category.description', catId),
        { description: descDraft.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess() {
                if (selectedCategory.value) {
                    selectedCategory.value.idol_description = descDraft.value;
                }
                editingDesc.value = false;
            },
        }
    );
}

// ── Add / Edit service form ─────────────────────────────────────
const showForm = ref(false);
const editingId = ref(null);

const form = useForm({
    name: '',
    category_id: null,
    time_unit_id: null,
    price: '',
});

function openAdd() {
    editingId.value = null;
    form.reset();
    // Pre-fill category if we're inside a category detail
    if (selectedCategory.value) {
        form.category_id = selectedCategory.value.category.id;
    }
    showForm.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    form.name = item.name;
    form.category_id = item.category_id ?? null;
    form.time_unit_id = item.time_unit?.id ?? null;
    form.price = item.price;
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submitForm() {
    if (editingId.value) {
        form.patch(route('profile.services.update', editingId.value), {
            preserveScroll: true,
            preserveState: true,
            onSuccess() { closeForm(); resyncSelectedCategory(); },
        });
    } else {
        form.post(route('profile.services.store'), {
            preserveScroll: true,
            preserveState: true,
            onSuccess() { closeForm(); resyncSelectedCategory(); },
        });
    }
}

function deleteService(id) {
    if (!confirm('Удалить услугу?')) return;
    router.delete(route('profile.services.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: resyncSelectedCategory,
    });
}

function toggleActive(item) {
    router.patch(route('profile.services.update', item.id), {
        is_active: !item.is_active,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: resyncSelectedCategory,
    });
}

// ── Computed ────────────────────────────────────────────────────
const loaded = computed(() => Array.isArray(localServices.value));
const isEmpty = computed(() => loaded.value && localServices.value.length === 0);
</script>

<template>
    <div class="services-wrap">

        <!-- Loading skeleton -->
        <template v-if="!loaded">
            <div class="svc-skeleton" v-for="n in 3" :key="n">
                <div class="svc-skeleton__row" />
            </div>
        </template>

        <Transition v-else :name="catTransitionName" mode="out-in">

            <!-- ── CategoryList ── -->
            <div v-if="!selectedCategory" key="list">
                <div class="svc-list-header">
                    <h2 class="svc-list-header__title">Категории</h2>
                    <CreateButton v-if="isOwner && isIdol" @click="openAdd">
                        <template #icon><el-icon>
                                <Plus />
                            </el-icon></template>
                        Добавить услугу
                    </CreateButton>
                </div>

                <!-- Empty state -->
                <div v-if="isEmpty" class="svc-empty">
                    <p class="svc-empty__title">Айдол пока не добавил услуги</p>
                </div>

                <!-- Category cards -->
                <div v-else class="cat-list">
                    <button v-for="group in localServices" :key="group.category.id" class="cat-card"
                        @click="openCategory(group)">
                        <div class="cat-card__img-wrap">
                            <img v-if="group.category.image_url" :src="group.category.image_url"
                                :alt="group.category.name" class="cat-card__img" />
                            <div v-else class="cat-card__img-placeholder">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.5" opacity="0.3">
                                    <rect x="3" y="3" width="18" height="18" rx="2" />
                                    <circle cx="8.5" cy="8.5" r="1.5" />
                                    <path d="M21 15l-5-5L5 21" />
                                </svg>
                            </div>
                        </div>
                        <div class="cat-card__info">
                            <p class="cat-card__name">{{ group.category.name }}</p>
                            <p v-if="group.category.description" class="cat-card__desc cat-card__desc--global">{{
                                group.category.description }}</p>
                            <p class="cat-card__count">{{ group.items.length }} {{ group.items.length === 1 ? 'услуга' :
                                group.items.length < 5 ? 'услуги' : 'услуг' }}</p>
                        </div>
                        <div class="cat-card__arrow">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 18l6-6-6-6" />
                            </svg>
                        </div>
                    </button>
                </div>
            </div>

            <!-- ── CategoryDetail ── -->
            <div v-else key="detail">
                <!-- Back link -->
                <button class="cd-back" @click="backToList">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 18l-6-6 6-6" />
                    </svg>
                    К категориям
                </button>

                <!-- Category hero card -->
                <div class="cd-hero">
                    <div class="cd-hero__body">
                        <div class="cd-hero__top">
                            <h2 class="cd-hero__title">{{ selectedCategory.category.name }}</h2>
                            <button v-if="isOwner && !editingDesc" class="cd-hero__edit-btn" @click="startDescEdit"
                                title="Редактировать описание">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                </svg>
                            </button>
                        </div>

                        <Transition name="desc-swap" mode="out-in">
                            <div v-if="!editingDesc" key="view">
                                <p v-if="selectedCategory.idol_description" class="cd-hero__desc">{{
                                    selectedCategory.idol_description }}</p>
                                <p v-else-if="isOwner" class="cd-hero__desc cd-hero__desc--placeholder">
                                    Напишите описание своих услуг в этой категории…</p>
                            </div>
                            <div v-else key="edit">
                                <textarea v-model="descDraft" class="cd-hero__textarea" rows="3" maxlength="1000"
                                    placeholder="Расскажите об этой категории услуг…" />
                                <div class="cd-hero__actions">
                                    <button class="svc-btn-cancel" @click="cancelDescEdit">Отмена</button>
                                    <button class="svc-btn-submit" @click="saveDesc">Сохранить</button>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>

                <!-- Services section -->
                <div class="cd-section cd-section--services">
                    <div class="cd-section__header">
                        <span class="cd-section__label">Услуги</span>
                        <CreateButton v-if="isOwner && isIdol" @click="openAdd">
                            <template #icon><el-icon>
                                    <Plus />
                                </el-icon></template>
                            Добавить
                        </CreateButton>
                    </div>

                    <div v-if="selectedCategory.items.length === 0" class="svc-empty">
                        <p class="svc-empty__title">Услуг в этой категории нет</p>
                    </div>
                    <TransitionGroup v-else name="svc-item" tag="div" class="svc-list">
                        <div v-for="item in selectedCategory.items" :key="item.id" class="svc-card"
                            :class="{ 'svc-card--inactive': !item.is_active }">
                            <div class="svc-card__main">
                                <span class="svc-card__name">{{ item.name }}</span>
                                <span class="svc-card__unit">{{ item.time_unit.name }}</span>
                            </div>
                            <div class="svc-card__right">
                                <span class="svc-card__price">{{ item.price.toLocaleString('ru') }} ₽</span>
                                <button v-if="!isOwner" class="svc-buy-btn">
                                    Купить
                                </button>
                                <template v-if="isOwner">
                                    <button class="svc-icon-btn"
                                        :class="{ 'svc-icon-btn--on': item.is_active, 'svc-icon-btn--off': !item.is_active }"
                                        :title="item.is_active ? 'Отключить' : 'Включить'" @click="toggleActive(item)">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                            <circle cx="12" cy="12" r="10" />
                                            <line v-if="!item.is_active" x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                                        </svg>
                                    </button>
                                    <button class="svc-icon-btn" title="Редактировать" @click="openEdit(item)">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                        </svg>
                                    </button>
                                    <button class="svc-icon-btn svc-icon-btn--danger" title="Удалить"
                                        @click="deleteService(item.id)">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6" />
                                            <path d="M19 6l-1 14H6L5 6" />
                                            <path d="M10 11v6M14 11v6" />
                                            <path d="M9 6V4h6v2" />
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </TransitionGroup>
                </div>

                <!-- Reviews placeholder -->
                <div class="cd-section cd-section--reviews">
                    <p class="cd-section__label">Отзывы</p>
                    <div class="cd-placeholder">Скоро здесь появятся отзывы</div>
                </div>

                <!-- Other idols -->
                <div v-if="selectedCategory.other_idols && selectedCategory.other_idols.length > 0" class="cd-section">
                    <p class="cd-section__label">Другие айдолы в этой категории</p>
                    <div class="cd-other-idols">
                        <a v-for="idol in selectedCategory.other_idols" :key="idol.id"
                            :href="route('profile.show', idol.id)" class="cd-idol-chip">
                            <img v-if="idol.avatar_url" :src="idol.avatar_url" :alt="idol.name"
                                class="cd-idol-chip__avatar" />
                            <div v-else class="cd-idol-chip__avatar cd-idol-chip__avatar--placeholder">
                                {{ idol.name.charAt(0) }}
                            </div>
                            <span class="cd-idol-chip__name">{{ idol.name }}</span>
                        </a>
                    </div>
                </div>
            </div>

        </Transition>

        <!-- Add/Edit modal -->
        <Teleport to="body">
            <Transition name="fade-overlay">
                <div v-if="showForm" class="svc-overlay" @click.self="closeForm">
                    <div class="svc-modal">
                        <div class="svc-modal__header">
                            <span>{{ editingId ? 'Редактировать услугу' : 'Новая услуга' }}</span>
                            <button class="svc-modal__close" @click="closeForm">
                                <svg width="14" height="14" viewBox="0 0 14 14" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round">
                                    <line x1="1" y1="1" x2="13" y2="13" />
                                    <line x1="13" y1="1" x2="1" y2="13" />
                                </svg>
                            </button>
                        </div>

                        <form @submit.prevent="submitForm" class="svc-modal__body">
                            <div class="svc-field">
                                <label class="svc-label">Категория</label>
                                <AppSelect v-model="form.category_id"
                                    :options="(serviceCategories ?? []).map(c => ({ value: c.id, label: c.name }))"
                                    placeholder="Выберите категорию" :error="!!form.errors.category_id" />
                                <p v-if="form.errors.category_id" class="svc-err">{{ form.errors.category_id }}</p>
                            </div>

                            <div class="svc-field">
                                <label class="svc-label">Название</label>
                                <input v-model="form.name" class="svc-input"
                                    :class="{ 'svc-input--err': form.errors.name }" placeholder="Игра в CS2"
                                    maxlength="120" />
                                <p v-if="form.errors.name" class="svc-err">{{ form.errors.name }}</p>
                            </div>

                            <div class="svc-field-row">
                                <div class="svc-field">
                                    <label class="svc-label">Цена (₽)</label>
                                    <input v-model.number="form.price" type="number" min="1" class="svc-input"
                                        :class="{ 'svc-input--err': form.errors.price }" placeholder="500" />
                                    <p v-if="form.errors.price" class="svc-err">{{ form.errors.price }}</p>
                                </div>
                                <div class="svc-field">
                                    <label class="svc-label">Единица</label>
                                    <AppSelect v-model="form.time_unit_id"
                                        :options="(serviceTimeUnits ?? []).map(u => ({ value: u.id, label: u.name }))"
                                        placeholder="За..." :error="!!form.errors.time_unit_id" />
                                    <p v-if="form.errors.time_unit_id" class="svc-err">{{ form.errors.time_unit_id }}
                                    </p>
                                </div>
                            </div>

                            <div class="svc-modal__actions">
                                <button type="button" class="svc-btn-cancel" @click="closeForm">Отмена</button>
                                <button type="submit" class="svc-btn-submit" :disabled="form.processing">
                                    {{ editingId ? 'Сохранить' : 'Добавить' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
/* ── Skeleton ─────────────────────────────────────────────── */
@keyframes shimmer {
    0% {
        background-position: -400px 0;
    }

    100% {
        background-position: 400px 0;
    }
}

.svc-skeleton {
    margin-bottom: 0.5rem;
}

.svc-skeleton__row {
    height: 72px;
    border-radius: 3px;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.04) 25%, rgba(255, 255, 255, 0.08) 50%, rgba(255, 255, 255, 0.04) 75%);
    background-size: 800px 100%;
    animation: shimmer 1.4s infinite linear;
}

/* ── Wrap ─────────────────────────────────────────────────── */
.services-wrap {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* ── Owner bar ────────────────────────────────────────────── */
.svc-owner-bar {
    display: flex;
    justify-content: flex-end;
}


/* ── Empty ────────────────────────────────────────────────── */
.svc-empty {
    padding: 2.5rem 2rem;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px;
}

.svc-empty__title {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.25);
    margin: 0;
}

/* ── List header ──────────────────────────────────────────── */
.svc-list-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}

.svc-list-header__title {
    font-size: 1.4rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.92);
    margin: 0;
    letter-spacing: -0.01em;
}

/* ── Category list ────────────────────────────────────────── */
.cat-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.cat-card {
    display: flex;
    align-items: stretch;
    gap: 0;
    background: #06060e;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    text-align: left;
    width: 100%;
    overflow: hidden;
}

.cat-card:hover {
    border-color: rgba(190, 145, 255, 0.35);
    background: rgba(255, 255, 255, 0.02);
}

.cat-card__img-wrap {
    width: 20%;
    aspect-ratio: 2 / 3;
    flex-shrink: 0;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.03);
    display: flex;
    align-items: center;
    justify-content: center;
}

.cat-card__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cat-card__img-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.cat-card__info {
    flex: 1;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 0;
}

.cat-card__name {
    font-size: 1rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
}

.cat-card__desc {
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.cat-card__desc--global {
    color: rgba(255, 255, 255, 0.35);
}

.cat-card__count {
    font-size: 0.72rem;
    color: rgba(190, 145, 255, 0.5);
    margin: 0;
    margin-top: auto;
}

.cat-card__arrow {
    display: flex;
    align-items: center;
    padding: 0 0.85rem;
    color: rgba(255, 255, 255, 0.2);
    flex-shrink: 0;
}

.cat-card:hover .cat-card__arrow {
    color: rgba(190, 145, 255, 0.5);
}

/* ── Category detail ──────────────────────────────────────── */
.cd-back {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.4);
    font-family: inherit;
    font-size: 0.82rem;
    cursor: pointer;
    padding: 0;
    transition: color 0.15s;
    margin-bottom: 0.75rem;
}

.cd-back:hover {
    color: rgba(255, 255, 255, 0.75);
}

/* ── Hero card ────────────────────────────────────────────── */
.cd-hero {
    display: flex;
    align-items: stretch;
    background: #06060e;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
    height: 300px;
}


.cd-hero__body {
    flex: 1;
    padding: 1.1rem 1.25rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    min-width: 0;
    overflow: auto;
}

.cd-hero__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.cd-hero__title {
    font-size: 1.5rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.92);
    margin: 0;
    letter-spacing: -0.02em;
    line-height: 1.15;
}

.cd-hero__edit-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    background: transparent;
    color: rgba(255, 255, 255, 0.3);
    cursor: pointer;
    flex-shrink: 0;
    transition: border-color 0.15s, color 0.15s;
}

.cd-hero__edit-btn:hover {
    border-color: rgba(190, 145, 255, 0.4);
    color: rgba(190, 145, 255, 0.7);
}

.cd-hero__desc {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.55);
    margin: 0;
    line-height: 1.65;
    white-space: pre-wrap;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(190, 145, 255, 0.25) transparent;
}

.cd-hero__desc::-webkit-scrollbar {
    width: 3px;
}

.cd-hero__desc::-webkit-scrollbar-thumb {
    background: rgba(190, 145, 255, 0.28);
    border-radius: 999px;
}

.cd-hero__desc--placeholder {
    color: rgba(255, 255, 255, 0.2);
    font-style: italic;
}

.cd-hero__textarea {
    width: 100%;
    padding: 0.5rem 0.7rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 3px;
    color: rgba(255, 255, 255, 0.85);
    font-family: inherit;
    font-size: 0.88rem;
    outline: none;
    resize: none;
    height: 140px;
    box-sizing: border-box;
    transition: border-color 0.15s;
    overflow-y: auto;
}

.cd-hero__textarea:focus {
    border-color: rgba(190, 145, 255, 0.45);
}

.cd-hero__actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 0.5rem;
    gap: 0.5rem;
}

/* ── Sections ─────────────────────────────────────────────── */
.cd-section {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.cd-section--services {
    margin-top: 0.5rem;
}

.cd-section--reviews {
    margin-top: 1.5rem;
}

.cd-section__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 0.4rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.cd-section__label {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: rgba(190, 145, 255, 0.5);
    margin: 0;
    padding: 0 0.1rem;
}

.cd-placeholder {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.2);
    text-align: center;
    padding: 2rem;
    border: 1px dashed rgba(255, 255, 255, 0.08);
    border-radius: 3px;
}

/* ── Other idols ──────────────────────────────────────────── */
.cd-other-idols {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.cd-idol-chip {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.75rem 0.4rem 0.4rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 99px;
    text-decoration: none;
    transition: border-color 0.15s, background 0.15s;
}

.cd-idol-chip:hover {
    border-color: rgba(190, 145, 255, 0.35);
    background: rgba(190, 145, 255, 0.06);
}

.cd-idol-chip__avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.cd-idol-chip__avatar--placeholder {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(190, 145, 255, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(190, 145, 255, 0.8);
    flex-shrink: 0;
}

.cd-idol-chip__name {
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.7);
    white-space: nowrap;
}

/* ── Service list & cards ─────────────────────────────────── */
.svc-list {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.svc-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.7rem 0.85rem;
    background: #06060e;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-top: none;
    transition: background 0.15s, opacity 0.28s ease;
}

.svc-card:first-child {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px 3px 0 0;
}

.svc-card:last-child {
    border-radius: 0 0 3px 3px;
}

.svc-card:only-child {
    border-radius: 3px;
}

.svc-card:hover {
    background: rgba(255, 255, 255, 0.02);
}

.svc-card--inactive {
    opacity: 0.45;
}

.svc-card__main {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.svc-card__name {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.85);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.svc-card__unit {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.3);
}

.svc-card__right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.svc-card__price {
    font-size: 0.95rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.7);
    white-space: nowrap;
}

/* ── Buy button ───────────────────────────────────────────── */
.svc-buy-btn {
    padding: 0.35rem 0.9rem;
    border: 1px solid rgba(200, 70, 126, 0.45);
    border-radius: 3px;
    background: linear-gradient(135deg, rgba(200, 70, 126, 0.18) 0%, rgba(140, 30, 80, 0.12) 100%);
    color: rgba(200, 70, 126, 0.9);
    font-family: inherit;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    cursor: pointer;
    white-space: nowrap;
    transition: border-color 0.15s, background 0.15s, box-shadow 0.15s, color 0.15s;
}

.svc-buy-btn:hover {
    border-color: rgba(200, 70, 126, 0.75);
    background: linear-gradient(135deg, rgba(200, 70, 126, 0.28) 0%, rgba(140, 30, 80, 0.2) 100%);
    color: #fff;
    box-shadow: 0 0 14px rgba(200, 70, 126, 0.22);
}

/* ── Icon buttons ─────────────────────────────────────────── */
.svc-icon-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    background: transparent;
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
}

.svc-icon-btn:hover {
    border-color: rgba(255, 255, 255, 0.3);
    color: rgba(255, 255, 255, 0.8);
}

.svc-icon-btn--on {
    color: rgba(74, 222, 128, 0.7);
    border-color: rgba(74, 222, 128, 0.25);
}

.svc-icon-btn--off {
    color: rgba(255, 255, 255, 0.2);
}

.svc-icon-btn--danger:hover {
    border-color: rgba(239, 68, 68, 0.5);
    color: rgba(239, 68, 68, 0.8);
}

/* ── Buttons ──────────────────────────────────────────────── */
.svc-btn-cancel {
    padding: 0.5rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    background: transparent;
    color: rgba(255, 255, 255, 0.4);
    font-family: inherit;
    font-size: 0.82rem;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
}

.svc-btn-cancel:hover {
    border-color: rgba(255, 255, 255, 0.25);
    color: rgba(255, 255, 255, 0.7);
}

.svc-btn-submit {
    padding: 0.5rem 1.2rem;
    border: 1px solid rgba(190, 145, 255, 0.45);
    border-radius: 3px;
    background: linear-gradient(135deg, rgba(155, 110, 232, 0.3) 0%, rgba(124, 45, 126, 0.2) 100%);
    color: rgba(255, 255, 255, 0.9);
    font-family: inherit;
    font-size: 0.82rem;
    cursor: pointer;
    transition: box-shadow 0.15s, border-color 0.15s;
}

.svc-btn-submit:hover {
    border-color: rgba(190, 145, 255, 0.7);
    box-shadow: 0 0 12px rgba(155, 110, 232, 0.25);
}

/* ── Modal overlay ────────────────────────────────────────── */
.svc-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(3px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.svc-modal {
    background: #0a0a0f;
    border: 1px solid rgba(190, 145, 255, 0.25);
    border-radius: 3px;
    width: 100%;
    max-width: 420px;
    margin: 1rem;
}

.svc-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    font-size: 0.88rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.85);
}

.svc-modal__close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: none;
    background: transparent;
    color: rgba(255, 255, 255, 0.35);
    cursor: pointer;
    border-radius: 3px;
    transition: color 0.15s;
}

.svc-modal__close:hover {
    color: rgba(255, 255, 255, 0.7);
}

.svc-modal__body {
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

/* ── Form fields ──────────────────────────────────────────── */
.svc-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.svc-field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.svc-label {
    font-size: 0.72rem;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.35);
}

.svc-input {
    padding: 0.5rem 0.7rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    color: rgba(255, 255, 255, 0.85);
    font-family: inherit;
    font-size: 0.88rem;
    outline: none;
    transition: border-color 0.15s;
    appearance: none;
    width: 100%;
    box-sizing: border-box;
}

.svc-input:focus {
    border-color: rgba(190, 145, 255, 0.45);
}

.svc-input--err {
    border-color: rgba(239, 68, 68, 0.5);
}

.svc-err {
    font-size: 0.75rem;
    color: rgba(239, 68, 68, 0.8);
    margin: 0;
}

.svc-modal__actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    padding-top: 0.25rem;
}

/* ── Transition ───────────────────────────────────────────── */
.fade-overlay-enter-active,
.fade-overlay-leave-active {
    transition: opacity 0.18s ease;
}

.fade-overlay-enter-from,
.fade-overlay-leave-to {
    opacity: 0;
}

/* ── Category drill-in (list → detail) ───────────────────── */
.drill-in-enter-active,
.drill-in-leave-active {
    transition: transform 0.24s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.2s ease;
}

.drill-in-enter-from {
    transform: translateX(32px);
    opacity: 0;
}

.drill-in-leave-to {
    transform: translateX(-24px);
    opacity: 0;
}

/* ── Category drill-out (detail → list) ──────────────────── */
.drill-out-enter-active,
.drill-out-leave-active {
    transition: transform 0.24s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.2s ease;
}

.drill-out-enter-from {
    transform: translateX(-32px);
    opacity: 0;
}

.drill-out-leave-to {
    transform: translateX(24px);
    opacity: 0;
}

/* ── Service item add / delete / reorder ─────────────────── */
.svc-item-enter-active {
    transition: opacity 0.22s ease, transform 0.22s ease;
}

.svc-item-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}

.svc-item-enter-from {
    opacity: 0;
    transform: translateX(-10px);
}

.svc-item-leave-to {
    opacity: 0;
    transform: translateX(16px);
}

/* FLIP: remaining items slide smoothly when one is removed */
.svc-item-move {
    transition: transform 0.22s ease;
}

/* ── Description view ↔ edit swap ───────────────────────── */
.desc-swap-enter-active,
.desc-swap-leave-active {
    transition: opacity 0.15s ease;
}

.desc-swap-enter-from,
.desc-swap-leave-to {
    opacity: 0;
}
</style>
