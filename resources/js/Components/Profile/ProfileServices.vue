<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick, inject } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { Plus } from '@element-plus/icons-vue';
import AppSelect from '@/Components/AppSelect.vue';
import CreateButton from '@/Components/CreateButton.vue';
import SiteModal from '@/Components/Site/SiteModal.vue';

const page = usePage();
const showPendingModal = ref(false);

watch(() => page.props.flash?.service_pending, (val) => {
    if (val) showPendingModal.value = true;
}, { immediate: true });

const props = defineProps({
    services: { default: null },
    serviceCategories: { default: null },
    serviceTimeUnits: { default: null },
    isOwner: { type: Boolean, default: false },
    isIdol: { type: Boolean, default: false },
    profileUser: { type: Object, default: null },
    isBlockedByIdol: { type: Boolean, default: false },
});

// ── localServices — не сбрасывается в null при redirect-рефетче ─
const localServices = ref(null);

// ── Cart ─────────────────────────────────────────────────────────
const cart = inject('cart', null);
const openAuth = inject('openAuth', null);
const cartConflictModal = ref(false);
const pendingCartItem   = ref(null);
const blockError        = ref(false);
let blockErrorTimer = null;

function showBlockError() {
    blockError.value = true;
    clearTimeout(blockErrorTimer);
    blockErrorTimer = setTimeout(() => { blockError.value = false; }, 3500);
}

function isInCart(serviceId) {
    return !!cart?.value?.items.find(i => i.service_id === serviceId);
}

function addToCart(item) {
    if (!page.props.auth?.user) { openAuth?.('register'); return; }
    if (props.isBlockedByIdol) { showBlockError(); return; }
    if (!cart) return;
    const c = cart.value;
    const idolId = props.profileUser?.id;
    if (c.idol_id && c.idol_id !== idolId) {
        // Different idol — show conflict modal
        pendingCartItem.value = item;
        cartConflictModal.value = true;
        return;
    }
    doAddToCart(item);
}

function doAddToCart(item) {
    if (!cart) return;
    const c = cart.value;
    if (isInCart(item.id)) return;
    c.idol_id     = props.profileUser?.id;
    c.idol_name   = props.profileUser?.name ?? '';
    c.idol_avatar = props.profileUser?.avatar_url ?? null;
    c.items.push({
        service_id: item.id,
        name:       item.name,
        price:      item.price,
        time_unit:  item.time_unit?.name ?? null,
        quantity:   1,
    });
}

function confirmCartReplace() {
    if (!cart) return;
    cart.value.items = [];
    cart.value.idol_id = null;
    doAddToCart(pendingCartItem.value);
    pendingCartItem.value = null;
    cartConflictModal.value = false;
}

function cancelCartReplace() {
    pendingCartItem.value = null;
    cartConflictModal.value = false;
}

// ── Two-level navigation ────────────────────────────────────────
const serviceNav = inject('serviceNav', null);
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
    if (serviceNav) {
        serviceNav.inCategory = true;
        serviceNav.accent = group.category.accent_color || '#a0a0ff';
        serviceNav.onBack = backToList;
    }
}

function backToList() {
    catTransitionDir.value = 'back';
    selectedCategory.value = null;
    sessionStorage.removeItem(SESSION_KEY.value);
    if (serviceNav) serviceNav.inCategory = false;
    router.reload({ only: ['services', 'serviceCategories', 'serviceTimeUnits'] });
}

// Mark that a resync is needed after the next deferred prop update
function resyncSelectedCategory() {
    pendingResync.value = true;
}

// Restore on initial deferred prop load OR after a mutation (pendingResync)
watch(() => props.services, (services) => {
    if (!services) return;
    localServices.value = services;
    const catId = selectedCategory.value?.category?.id;
    if (!catId) return;
    if (!selectedCategory.value || pendingResync.value) {
        const group = services.find(g => g.category.id == catId);
        selectedCategory.value = group ?? null;
        if (!group) sessionStorage.removeItem(SESSION_KEY.value);
        pendingResync.value = false;
    }
}, { immediate: true });

function navigateToIdolInCategory(idol) {
    const categoryId = selectedCategory.value?.category?.id;
    if (categoryId) {
        sessionStorage.setItem(`services_cat_${idol.id}`, categoryId);
    }
    window.location.href = route('profile.show', idol.id) + '#services';
}

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
    loadDraft();
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
    clearDraft();
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

const showCancelConfirm = ref(false);

function tryCloseForm() {
    if (!editingId.value && (form.name || form.price || form.time_unit_id)) {
        showCancelConfirm.value = true;
    } else {
        closeForm();
    }
}

function confirmCancelForm() {
    showCancelConfirm.value = false;
    closeForm();
}

const deleteConfirmId = ref(null);

function askDeleteService(id) {
    deleteConfirmId.value = id;
}

function confirmDeleteService() {
    const id = deleteConfirmId.value;
    deleteConfirmId.value = null;
    router.delete(route('profile.services.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: resyncSelectedCategory,
    });
}

function cancelDeleteService() {
    deleteConfirmId.value = null;
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

const sortedServices = computed(() => {
    if (!Array.isArray(localServices.value)) return { withItems: [], empty: [] };
    return {
        withItems: localServices.value.filter(g => g.items.length > 0),
        empty: localServices.value.filter(g => g.items.length === 0),
    };
});

const formCategory = computed(() =>
    (props.serviceCategories ?? []).find(c => c.id === form.category_id) ?? null
);
const formSuggestions = computed(() =>
    Array.isArray(formCategory.value?.name_suggestions)
        ? formCategory.value.name_suggestions
        : []
);
const namePlaceholder = computed(() =>
    formSuggestions.value[0] ?? 'Название услуги'
);

// ── #1 Price preview ─────────────────────────────────────────
const pricePreview = computed(() => {
    if (!form.price || !form.time_unit_id) return null;
    const unit = (props.serviceTimeUnits ?? []).find(u => u.id === form.time_unit_id);
    if (!unit) return null;
    return `${Number(form.price).toLocaleString('ru')} ₽ / ${unit.name}`;
});

// ── #4 Draft ─────────────────────────────────────────────────
const DRAFT_KEY = computed(() => `svc_draft_${props.profileUser?.id}`);

function loadDraft() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY.value);
        if (!raw) return;
        const d = JSON.parse(raw);
        if (d.name) form.name = d.name;
        if (d.category_id) form.category_id = d.category_id;
        if (d.price) form.price = d.price;
        if (d.time_unit_id) form.time_unit_id = d.time_unit_id;
    } catch { }
}

function saveDraft() {
    if (!editingId.value) {
        localStorage.setItem(DRAFT_KEY.value, JSON.stringify({
            name: form.name,
            category_id: form.category_id,
            price: form.price,
            time_unit_id: form.time_unit_id,
        }));
    }
}

function clearDraft() {
    localStorage.removeItem(DRAFT_KEY.value);
}

watch([() => form.name, () => form.category_id, () => form.price, () => form.time_unit_id], saveDraft);

// ── #6 Chip animation ────────────────────────────────────────
const animatingChip = ref(null);

function selectChip(s) {
    form.name = s;
    animatingChip.value = s;
    setTimeout(() => { animatingChip.value = null; }, 300);
}

// ── #9 Dropdown menu ─────────────────────────────────────────
const openMenuId = ref(null);

function toggleMenu(id) {
    openMenuId.value = openMenuId.value === id ? null : id;
}

function closeMenu() {
    openMenuId.value = null;
}

function onDocClick(e) {
    if (!e.target.closest('.svc-menu')) closeMenu();
}

onMounted(() => document.addEventListener('click', onDocClick, true));
onUnmounted(() => {
    document.removeEventListener('click', onDocClick, true);
    selectedCategory.value = null;
    sessionStorage.removeItem(SESSION_KEY.value);
    if (serviceNav) serviceNav.inCategory = false;
});

// ── #8 Form validation ───────────────────────────────────────
const formValid = computed(() =>
    form.name.trim().length > 0 &&
    form.category_id !== null &&
    Number(form.price) > 0 &&
    form.time_unit_id !== null
);

// ── Carousel ─────────────────────────────────────────────────
const carouselPage = ref(1);
const carouselIdols = ref([]);
const carouselTotal = ref(0);
const carouselHasMore = ref(false);
const carouselLoading = ref(false);
const carouselReady = ref(false);
const carouselDir = ref('next'); // 'next' | 'prev'

async function loadCarousel(page = 1) {
    carouselLoading.value = true;
    try {
        const res = await fetch(
            route('profile.category-idols', {
                user: props.profileUser?.id,
                category: selectedCategory.value.category.id,
            }) + `?page=${page}`
        );
        const data = await res.json();
        carouselPage.value = page;
        carouselIdols.value = data.idols;
        carouselTotal.value = data.total;
        carouselHasMore.value = data.hasMore;
        carouselReady.value = true;
    } finally {
        carouselLoading.value = false;
    }
}

function carouselPrev() {
    if (carouselPage.value > 1) {
        carouselDir.value = 'prev';
        loadCarousel(carouselPage.value - 1);
    }
}
function carouselNext() {
    if (carouselHasMore.value) {
        carouselDir.value = 'next';
        loadCarousel(carouselPage.value + 1);
    }
}

watch(selectedCategory, (cat) => {
    if (cat) {
        carouselReady.value = false;
        carouselPage.value = 1;
        carouselIdols.value = [];
        carouselTotal.value = 0;
        carouselHasMore.value = false;
        loadCarousel(1);
    }
});
</script>

<template>
    <div class="services-wrap">

        <!-- Block error toast -->
        <Transition name="block-err">
            <div v-if="blockError" class="svc-block-error">
                Вы заблокированы этим пользователем и не можете делать заказы
            </div>
        </Transition>

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

                <!-- Category cards -->
                <div class="cat-grid">
                    <button v-for="group in sortedServices.withItems" :key="group.category.id" class="cat-tile"
                        @click="openCategory(group)"
                        :style="{ '--cat-accent': group.category.accent_color || '#a0a0ff' }">
                        <div class="cat-tile__img-wrap">
                            <img v-if="group.category.image_url" :src="group.category.image_url"
                                :alt="group.category.name" class="cat-tile__img" />
                            <div v-else class="cat-tile__img-placeholder">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.5" opacity="0.25">
                                    <rect x="3" y="3" width="18" height="18" rx="2" />
                                    <circle cx="8.5" cy="8.5" r="1.5" />
                                    <path d="M21 15l-5-5L5 21" />
                                </svg>
                            </div>
                        </div>
                        <div class="cat-tile__body">
                            <span class="cat-tile__name">{{ group.category.name }}</span>
                            <p v-if="group.category.description" class="cat-tile__desc">
                                {{ group.category.description }}
                            </p>
                            <div class="cat-tile__footer">
                                <span class="cat-tile__count">
                                    {{ group.items.length + '\u00a0' + (group.items.length === 1 ? 'услуга' :
                                        group.items.length
                                            < 5 ? 'услуги' : 'услуг') }} </span>
                            </div>
                        </div>
                    </button>

                    <div v-if="sortedServices.empty.length > 0" class="cat-grid__divider"></div>

                    <button v-for="group in sortedServices.empty" :key="group.category.id"
                        class="cat-tile cat-tile--empty" @click="openCategory(group)"
                        :style="{ '--cat-accent': group.category.accent_color || '#a0a0ff' }">
                        <div class="cat-tile__img-wrap">
                            <img v-if="group.category.image_url" :src="group.category.image_url"
                                :alt="group.category.name" class="cat-tile__img" />
                            <div v-else class="cat-tile__img-placeholder">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.5" opacity="0.25">
                                    <rect x="3" y="3" width="18" height="18" rx="2" />
                                    <circle cx="8.5" cy="8.5" r="1.5" />
                                    <path d="M21 15l-5-5L5 21" />
                                </svg>
                            </div>
                        </div>
                        <div class="cat-tile__body">
                            <span class="cat-tile__name">{{ group.category.name }}</span>
                            <p v-if="group.category.description" class="cat-tile__desc">
                                {{ group.category.description }}
                            </p>
                            <div class="cat-tile__footer">
                                <span class="cat-tile__count cat-tile__count--empty">0 услуг</span>
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- ── CategoryDetail ── -->
            <div v-else key="detail" class="cd-detail"
                :style="{ '--cat-accent': selectedCategory.category.accent_color || '#a0a0ff' }">

                <!-- Category hero card -->
                <div class="cd-hero">
                    <div class="cd-hero__body">
                        <div class="cd-hero__top">
                            <h2 class="cd-hero__title">{{ selectedCategory.category.name }}</h2>
                            <div class="cd-hero__top-actions">
                                <button v-if="isOwner && !editingDesc" class="cd-hero__edit-btn" @click="startDescEdit">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                    </svg>
                                    Изменить описание
                                </button>
                                <CreateButton v-if="isOwner && isIdol" @click="openAdd">
                                    <template #icon><el-icon>
                                            <Plus />
                                        </el-icon></template>
                                    Добавить услугу
                                </CreateButton>
                            </div>
                        </div>

                        <Transition name="desc-swap" mode="out-in">
                            <div v-if="!editingDesc" key="view">
                                <p v-if="selectedCategory.idol_description" class="cd-hero__desc">{{
                                    selectedCategory.idol_description }}</p>
                                <p v-else-if="isOwner" class="cd-hero__desc cd-hero__desc--placeholder">
                                    Напишите описание своих услуг в этой категории…</p>
                                <p v-else class="cd-hero__desc cd-hero__desc--placeholder">
                                    Айдол пока не добавил описание</p>
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
                    <span class="cd-section__label">Варианты</span>

                    <div v-if="selectedCategory.items.length === 0" class="svc-empty">
                        <p class="svc-empty__title">У этого айдола пока нет услуг в данной категории.</p>
                    </div>
                    <TransitionGroup v-else name="svc-item" tag="div" class="svc-list">
                        <div v-for="item in selectedCategory.items" :key="item.id" class="svc-card" :class="{
                            'svc-card--inactive': !item.is_active,
                            'svc-card--pending': isOwner && item.status === 'pending',
                            'svc-card--rejected': isOwner && item.status === 'rejected',
                        }">

                            <!-- Badges: top-right corner -->
                            <div v-if="isOwner" class="svc-card__badges">
                                <span v-if="!item.is_active" class="svc-pill svc-pill--hidden">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path
                                            d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94" />
                                        <path
                                            d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19" />
                                        <line x1="1" y1="1" x2="23" y2="23" />
                                    </svg>
                                    скрыто
                                </span>
                                <span v-if="item.status === 'pending'" class="svc-pill svc-pill--pending">
                                    <i class="svc-pill__dot"></i>модерация
                                </span>
                                <span v-else-if="item.status === 'rejected'" class="svc-pill svc-pill--rejected">
                                    отклонено
                                </span>
                            </div>

                            <!-- Info column -->
                            <div class="svc-card__info">
                                <div class="svc-card__name-row">
                                    <span class="svc-card__name">{{ item.name }}</span>
                                </div>
                                <span v-if="isOwner && item.status === 'rejected' && item.rejection_reason"
                                    class="svc-card__reason">{{ item.rejection_reason }}</span>
                            </div>

                            <!-- Footer: price + actions -->
                            <div class="svc-card__footer">
                                <div class="svc-card__price-block">
                                    <span class="svc-card__amount">{{ item.price.toLocaleString('ru') }}</span><span
                                        class="svc-card__rub">₽</span><span class="svc-card__sep">/</span><span
                                        class="svc-card__unit">{{ item.time_unit.name }}</span>
                                </div>

                                <!-- Actions column -->
                                <div class="svc-card__actions">
                                    <button
                                        v-if="!isOwner && cart"
                                        class="svc-buy-btn"
                                        :class="{ 'svc-buy-btn--in-cart': isInCart(item.id) }"
                                        @click="addToCart(item)"
                                    >
                                        <template v-if="isInCart(item.id)">
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            <span>В корзине</span>
                                        </template>
                                        <template v-else>
                                            <span>В корзину</span>
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                        </template>
                                    </button>
                                    <div v-if="isOwner" class="svc-menu">
                                        <button class="svc-menu__trigger"
                                            :class="{ 'svc-menu__trigger--open': openMenuId === item.id }"
                                            @click.stop="toggleMenu(item.id)" title="Действия">
                                            <span></span><span></span><span></span>
                                        </button>
                                        <Transition name="svc-menu-pop">
                                            <div v-if="openMenuId === item.id" class="svc-menu__dropdown">
                                                <template v-if="item.status === 'approved'">
                                                    <button class="svc-menu__item"
                                                        @click="toggleActive(item); closeMenu()">
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                                            stroke="currentColor" stroke-width="2"
                                                            stroke-linecap="round">
                                                            <circle cx="12" cy="12" r="10" />
                                                            <line v-if="item.is_active" x1="4.93" y1="4.93" x2="19.07"
                                                                y2="19.07" />
                                                        </svg>
                                                        {{ item.is_active ? 'Отключить' : 'Включить' }}
                                                    </button>
                                                    <button class="svc-menu__item" @click="openEdit(item); closeMenu()">
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                                            stroke="currentColor" stroke-width="2"
                                                            stroke-linecap="round" stroke-linejoin="round">
                                                            <path
                                                                d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                            <path
                                                                d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                        </svg>
                                                        Редактировать
                                                    </button>
                                                    <div class="svc-menu__divider"></div>
                                                </template>
                                                <button class="svc-menu__item svc-menu__item--danger"
                                                    @click="askDeleteService(item.id); closeMenu()">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <polyline points="3 6 5 6 21 6" />
                                                        <path d="M19 6l-1 14H6L5 6" />
                                                        <path d="M10 11v6M14 11v6" />
                                                        <path d="M9 6V4h6v2" />
                                                    </svg>
                                                    Удалить
                                                </button>
                                            </div>
                                        </Transition>
                                    </div>
                                </div>
                            </div><!-- /.svc-card__footer -->

                        </div>
                    </TransitionGroup>
                </div>

                <!-- Carousel of other idols -->
                <div v-if="!carouselReady || carouselTotal > 0" class="cd-section cd-carousel">
                    <p class="cd-section__label">Другие айдолы в этой категории</p>
                    <div class="cd-carousel__row">
                        <button class="cd-carousel__nav cd-carousel__nav--prev"
                            :disabled="carouselPage === 1 || carouselLoading" @click="carouselPrev">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 18l-6-6 6-6" />
                            </svg>
                        </button>
                        <div class="cd-carousel__track">
                            <!-- Layer 1: skeletons — always in DOM, provide stable height -->
                            <div class="cd-carousel__skeletons"
                                :class="{ 'cd-carousel__skeletons--hidden': !carouselLoading && carouselReady }">
                                <div v-for="n in 4" :key="n" class="cd-carousel__idol cd-carousel__idol--skel"></div>
                            </div>
                            <!-- Layer 2: real cards — absolute on top, only when loaded -->
                            <Transition :name="carouselDir === 'next' ? 'carousel-next' : 'carousel-prev'"
                                mode="out-in">
                                <div v-if="!carouselLoading" class="cd-carousel__idols" :key="carouselPage">
                                    <a v-for="idol in carouselIdols" :key="idol.id" href="#" class="cd-carousel__idol"
                                        @click.prevent="navigateToIdolInCategory(idol)">
                                        <img v-if="idol.avatar_url" :src="idol.avatar_url" :alt="idol.name"
                                            class="cd-carousel__avatar" />
                                        <div v-else class="cd-carousel__avatar cd-carousel__avatar--placeholder">{{
                                            idol.name.charAt(0) }}</div>
                                        <span v-if="idol.rating" class="cd-carousel__rating">★ {{ idol.rating }}</span>
                                        <span class="cd-carousel__name">{{ idol.name }}</span>
                                    </a>
                                </div>
                            </Transition>
                        </div>
                        <button class="cd-carousel__nav cd-carousel__nav--next"
                            :disabled="!carouselHasMore || carouselLoading" @click="carouselNext">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 18l6-6-6-6" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Reviews placeholder -->
                <div v-if="selectedCategory.items.length > 0" class="cd-section cd-section--reviews">
                    <p class="cd-section__label">Отзывы</p>
                    <div class="cd-placeholder">Скоро здесь появятся отзывы</div>
                </div>
            </div>

        </Transition>

        <!-- Cart conflict modal -->
        <SiteModal :show="cartConflictModal" variant="pink" :compact="true" @close="cancelCartReplace">
            <div class="sf-wrap">
                <div class="sf-title">Очистить корзину?</div>
                <p class="svc-pending-text">В корзине уже есть услуги другого айдола. Очистить корзину и добавить эту услугу?</p>
                <div class="sf-actions">
                    <button class="sf-btn-cancel" @click="cancelCartReplace">Отмена</button>
                    <button class="sf-btn-submit" @click="confirmCartReplace">Очистить и добавить</button>
                </div>
            </div>
        </SiteModal>

        <!-- Service pending modal -->
        <SiteModal :show="showPendingModal" variant="pink" :compact="true" @close="showPendingModal = false">
            <div class="sf-wrap">
                <div class="sf-title">Услуга отправлена на модерацию</div>
                <p class="svc-pending-text">Она появится в вашем профиле после проверки администратором.</p>
                <div class="sf-actions">
                    <button class="sf-btn-submit" @click="showPendingModal = false">Понятно</button>
                </div>
            </div>
        </SiteModal>

        <!-- Delete confirm modal -->
        <SiteModal :show="deleteConfirmId !== null" variant="pink" :compact="true" @close="cancelDeleteService">
            <div class="sf-wrap">
                <div class="sf-title">Удалить услугу?</div>
                <p class="svc-pending-text">Это действие нельзя отменить.</p>
                <div class="sf-actions">
                    <button type="button" class="svc-btn-cancel" @click="cancelDeleteService">Отмена</button>
                    <button type="button" class="sf-btn-danger" @click="confirmDeleteService">Удалить</button>
                </div>
            </div>
        </SiteModal>

        <!-- Add/Edit modal -->
        <SiteModal :show="showForm" variant="pink" :compact="true" @close="tryCloseForm">
            <div class="sf-wrap">
                <Transition name="sf-screen" mode="out-in">
                    <div v-if="showCancelConfirm" key="confirm" class="sf-screen">
                        <div class="sf-title">Выйти без сохранения?</div>
                        <p class="svc-pending-text">Введённые данные будут потеряны.</p>
                        <div class="sf-actions">
                            <button type="button" class="svc-btn-cancel"
                                @click="showCancelConfirm = false">Остаться</button>
                            <button type="button" class="sf-btn-danger" @click="confirmCancelForm">Выйти</button>
                        </div>
                    </div>
                    <div v-else key="form" class="sf-screen">
                        <div class="sf-title">{{ editingId ? 'Редактировать услугу' : 'Новая услуга' }}</div>
                        <form @submit.prevent="submitForm" class="sf-form">

                            <div class="sf-field">
                                <label class="sf-label">Категория</label>
                                <AppSelect v-model="form.category_id"
                                    :options="(serviceCategories ?? []).map(c => ({ value: c.id, label: c.name }))"
                                    placeholder="Выберите категорию" :error="!!form.errors.category_id"
                                    :disabled="!editingId && !!selectedCategory" />
                                <p v-if="form.errors.category_id" class="sf-err">{{ form.errors.category_id }}</p>
                            </div>

                            <div class="sf-field">
                                <label class="sf-label">Название</label>
                                <div class="sf-input-wrap">
                                    <input v-model="form.name" class="sf-input"
                                        :class="{ 'sf-input--err': form.errors.name }" :placeholder="namePlaceholder"
                                        maxlength="45" />
                                    <span class="sf-char-count"
                                        :class="{ 'sf-char-count--warn': form.name.length >= 38 }">
                                        {{ form.name.length }}/45
                                    </span>
                                </div>
                                <div v-if="formSuggestions.length" class="svc-suggestions">
                                    <button v-for="s in formSuggestions" :key="s" type="button" class="svc-chip"
                                        :class="{ 'svc-chip--active': form.name === s, 'svc-chip--pop': animatingChip === s }"
                                        @click="selectChip(s)">{{ s }}</button>
                                </div>
                                <p v-if="form.errors.name" class="sf-err">{{ form.errors.name }}</p>
                            </div>

                            <div class="sf-row">
                                <div class="sf-field">
                                    <label class="sf-label">Цена</label>
                                    <div class="sf-input-wrap">
                                        <input v-model.number="form.price" type="number" min="1" class="sf-input"
                                            :class="{ 'sf-input--err': form.errors.price }" placeholder="500" />
                                        <Transition name="sf-preview-fade">
                                            <span v-if="pricePreview" class="sf-preview">{{ pricePreview }}</span>
                                        </Transition>
                                    </div>
                                    <p v-if="form.errors.price" class="sf-err">{{ form.errors.price }}</p>
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label">Единица</label>
                                    <AppSelect v-model="form.time_unit_id"
                                        :options="(serviceTimeUnits ?? []).map(u => ({ value: u.id, label: u.name }))"
                                        placeholder="За..." :error="!!form.errors.time_unit_id" />
                                    <p v-if="form.errors.time_unit_id" class="sf-err">{{ form.errors.time_unit_id }}</p>
                                </div>
                            </div>

                            <div class="sf-actions">
                                <button type="button" class="svc-btn-cancel" @click="tryCloseForm">Отмена</button>
                                <button type="submit" class="sf-btn-submit" :disabled="!formValid || form.processing">
                                    {{ editingId ? 'Сохранить' : 'Добавить' }}
                                </button>
                            </div>

                        </form>
                    </div>
                </Transition>
            </div>
        </SiteModal>
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
    margin: 1.25rem 0;
    padding: 0 1.25rem;
}

.svc-list-header__title {
    font-size: 1.4rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.92);
    margin: 0;
    letter-spacing: -0.01em;
}

/* ── Category list ────────────────────────────────────────── */
.cat-grid {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    padding: 0 1.25rem;
}

.cat-grid__divider {
    width: 100%;
    height: 1px;
    background: linear-gradient(to right, transparent, rgba(180, 160, 255, 0.4), transparent);
    margin: 0.25rem 0;
}

.cat-tile--empty .cat-tile__img-wrap,
.cat-tile--empty .cat-tile__name,
.cat-tile--empty .cat-tile__desc,
.cat-tile--empty .cat-tile__arrow {
    opacity: 0.45;
    transition: opacity 0.4s;
}

.cat-tile--empty:hover .cat-tile__img-wrap,
.cat-tile--empty:hover .cat-tile__name,
.cat-tile--empty:hover .cat-tile__desc,
.cat-tile--empty:hover .cat-tile__arrow {
    opacity: 1;
}

.cat-tile {
    display: flex;
    flex-direction: row;
    align-items: stretch;
    overflow: visible;
    background:
        linear-gradient(to right, transparent, color-mix(in srgb, var(--cat-accent) 80%, white), rgba(255, 255, 255, 0.2), transparent) 0 0 / 100% 1px no-repeat,
        rgba(30, 28, 45, 0.55);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    cursor: pointer;
    text-align: left;
    overflow: hidden;
    transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.28);
}

.cat-tile:hover {
    background:
        linear-gradient(to right, transparent, color-mix(in srgb, var(--cat-accent) 90%, white), rgba(255, 255, 255, 0.28), transparent) 0 0 / 100% 1px no-repeat,
        rgba(38, 35, 55, 0.6);
    border-color: rgba(255, 255, 255, 0.15);
    box-shadow:
        0 6px 32px rgba(0, 0, 0, 0.32),
        0 0 22px color-mix(in srgb, var(--cat-accent) 20%, transparent);
}

.cat-tile__img-wrap {
    width: 38%;
    flex-shrink: 0;
    background: transparent;
    position: relative;
    overflow: visible;
    order: 1;
}

.cat-tile__img {
    position: absolute;
    width: 100%;
    height: 160%;
    top: 50%;
    transform: translateY(-50%);
    object-fit: contain;
}

.cat-tile__img-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.cat-tile__body {
    padding: 0.6rem 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1;
    min-width: 0;
    overflow: hidden;
    order: 0;
}

.cat-tile__name {
    font-size: 1.15rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.88);
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.cat-tile__desc {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.50);
    line-height: 1.45;
    margin: auto 0;
}

.cat-tile__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.25rem;
}

.cat-tile__count {
    font-size: 0.82rem;
    color: var(--cat-accent);
}

.cat-tile__arrow {
    color: rgba(255, 255, 255, 0.18);
    flex-shrink: 0;
    transition: color 0.15s, transform 0.15s;
}

.cat-tile:hover .cat-tile__arrow {
    color: color-mix(in srgb, var(--cat-accent) 60%, transparent);
    transform: translateX(2px);
}

/* ── Category detail ──────────────────────────────────────── */
.cd-detail {
    padding: 0 1rem;
}



/* ── Hero card ────────────────────────────────────────────── */
.cd-hero {
    display: flex;
    align-items: stretch;
    background: #06060e;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
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
    gap: 0.45rem;
    padding: 0.45rem 1rem;
    border: 1px solid rgba(155, 110, 232, 0.35);
    border-radius: 6px;
    background: rgba(155, 110, 232, 0.08);
    color: rgba(155, 110, 232, 0.75);
    font-size: 0.92rem;
    font-family: inherit;
    font-weight: 500;
    cursor: pointer;
    flex-shrink: 0;
    white-space: nowrap;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}

.cd-hero__edit-btn:hover {
    border-color: rgba(155, 110, 232, 0.65);
    background: rgba(155, 110, 232, 0.15);
    color: rgba(155, 110, 232, 1);
}

.cd-hero__top-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.cd-hero__desc {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.55);
    margin: 0;
    line-height: 1.65;
    white-space: pre-wrap;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(160, 160, 255, 0.25) transparent;
}

.cd-hero__desc::-webkit-scrollbar {
    width: 3px;
}

.cd-hero__desc::-webkit-scrollbar-thumb {
    background: rgba(160, 160, 255, 0.28);
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
    border-color: rgba(160, 160, 255, 0.45);
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
    gap: 1rem;
}

.cd-section--services {
    margin-top: 1rem;
}

.cd-section--reviews {
    margin-top: 1rem;
}

.cd-section__label {
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(155, 110, 232, 0.8);
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

/* ── Empty tile variant ───────────────────────────────────── */
.cat-tile__count--empty {
    color: rgba(180, 160, 255, 1);
}

/* ── Carousel ─────────────────────────────────────────────── */
.cd-carousel {
    margin-top: 1rem;
}

.cd-carousel__row {
    display: flex;
    align-items: stretch;
    gap: 0.5rem;
}

.cd-carousel__track {
    flex: 1;
    overflow: hidden;
}

.cd-carousel__nav {
    flex-shrink: 0;
    width: 30px;
    border-radius: 3px;
    border: 1px solid rgba(155, 110, 232, 0.18);
    background: linear-gradient(135deg, rgba(155, 110, 232, 0.06) 0%, rgba(255, 255, 255, 0.02) 100%);
    color: rgba(190, 145, 255, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: border-color 0.18s, background 0.18s, color 0.18s, box-shadow 0.18s;
}


.cd-carousel__nav svg {
    transition: transform 0.18s;
}

.cd-carousel__nav:hover:not(:disabled) {
    border-color: rgba(155, 110, 232, 0.45);
    background: linear-gradient(135deg, rgba(155, 110, 232, 0.13) 0%, rgba(255, 255, 255, 0.03) 100%);
    color: rgba(190, 145, 255, 0.9);
    box-shadow: 0 0 10px rgba(155, 110, 232, 0.18);
}

.cd-carousel__nav--prev:hover:not(:disabled) svg {
    transform: translateX(-2px);
}

.cd-carousel__nav--next:hover:not(:disabled) svg {
    transform: translateX(2px);
}

.cd-carousel__nav:active:not(:disabled) {
    background: linear-gradient(135deg, rgba(155, 110, 232, 0.2) 0%, rgba(255, 255, 255, 0.04) 100%);
    transition-duration: 0.06s;
}

.cd-carousel__nav:disabled {
    opacity: 0.2;
    cursor: default;
}

.cd-carousel__skeletons {
    display: flex;
    gap: 0.5rem;
}

.cd-carousel__skeletons--hidden {
    visibility: hidden;
}

.cd-carousel__idol--skel {
    background: linear-gradient(90deg,
            rgba(255, 255, 255, 0.05) 25%,
            rgba(255, 255, 255, 0.10) 50%,
            rgba(255, 255, 255, 0.05) 75%);
    background-size: 400px 100%;
    animation: shimmer 1.4s infinite linear;
    pointer-events: none;
}

.cd-carousel__idols {
    position: absolute;
    inset: 0;
    display: flex;
    gap: 0.5rem;
}

.cd-carousel__idol {
    position: relative;
    overflow: hidden;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    text-decoration: none;
    flex: 1;
    min-width: 0;
    aspect-ratio: 3 / 4;
    transition: border-color 0.15s;
}

.cd-carousel__idol:hover {
    border-color: color-mix(in srgb, var(--cat-accent) 50%, transparent);
}

.cd-carousel__avatar {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cd-carousel__avatar--placeholder {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    background: rgba(160, 160, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 600;
    color: rgba(160, 160, 255, 0.7);
}

.cd-carousel__name {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 1.5rem 0.4rem 0.4rem;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.92);
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.35) 0%, transparent 100%);
}

.cd-carousel__rating {
    position: absolute;
    top: 0.35rem;
    right: 0.35rem;
    font-size: 0.85rem;
    color: rgba(180, 130, 255, 0.95);
    background: rgba(0, 0, 0, 0.55);
    padding: 0.1rem 0.3rem;
    border-radius: 4px;
    line-height: 1.4;
}


.cd-carousel__pager {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.25);
    margin: 0.4rem 0 0;
    text-align: center;
}

/* ── Carousel slide transitions ───────────────────────────── */
.carousel-next-enter-active,
.carousel-next-leave-active,
.carousel-prev-enter-active,
.carousel-prev-leave-active {
    transition: transform 0.22s ease, opacity 0.22s ease;
    position: absolute;
    width: 100%;
}

.carousel-next-enter-from {
    transform: translateX(100%);
    opacity: 0;
}

.carousel-next-leave-to {
    transform: translateX(-100%);
    opacity: 0;
}

.carousel-prev-enter-from {
    transform: translateX(-100%);
    opacity: 0;
}

.carousel-prev-leave-to {
    transform: translateX(100%);
    opacity: 0;
}

.carousel-next-enter-to,
.carousel-next-leave-from,
.carousel-prev-enter-to,
.carousel-prev-leave-from {
    transform: translateX(0);
    opacity: 1;
}

/* position:relative on track so absolute children are contained */
.cd-carousel__track {
    position: relative;
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
    border-color: rgba(160, 160, 255, 0.35);
    background: rgba(160, 160, 255, 0.06);
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
    background: rgba(160, 160, 255, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(160, 160, 255, 0.8);
    flex-shrink: 0;
}

.cd-idol-chip__name {
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.7);
    white-space: nowrap;
}

/* ── Service list & cards ─────────────────────────────────── */
.svc-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.85rem;
}

.svc-card {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding: 1rem 1.1rem;
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid color-mix(in srgb, var(--cat-accent, white) 28%, transparent);
    border-radius: 6px;
    transition: background 0.18s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    position: relative;
}

.svc-card:hover {
    background: rgba(255, 255, 255, 0.045);
    border-color: color-mix(in srgb, var(--cat-accent, white) 50%, transparent);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.svc-card:not(.svc-card--pending):not(.svc-card--rejected) .svc-card__amount {
    color: var(--cat-accent);
}

.svc-card:not(.svc-card--pending):not(.svc-card--rejected) .svc-card__rub {
    color: color-mix(in srgb, var(--cat-accent) 70%, transparent);
}

.svc-card:not(.svc-card--pending):not(.svc-card--rejected) .svc-card__unit {
    color: color-mix(in srgb, var(--cat-accent) 55%, transparent);
}

.svc-card--inactive .svc-card__badges,
.svc-card--inactive .svc-card__info,
.svc-card--inactive .svc-card__price-block {
    opacity: 0.6;
}

.svc-card--pending {
    border-color: rgba(251, 146, 60, 0.3);
    background: rgba(251, 146, 60, 0.03);
}

.svc-card--rejected {
    border-color: rgba(239, 68, 68, 0.25);
    background: rgba(239, 68, 68, 0.025);
}

/* Info column */
.svc-card__info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.svc-card__name-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.svc-card__name {
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.35;
    color: rgba(255, 255, 255, 0.92);
    white-space: normal;
    overflow: hidden;
    letter-spacing: 0;
}


.svc-card__reason {
    font-size: 0.8rem;
    color: rgba(239, 68, 68, 0.85);
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Badges wrapper — pinned to top-right of card */
.svc-card__badges {
    position: absolute;
    top: 0;
    right: 0;
    display: flex;
    flex-direction: row;
    gap: 0;
}

.svc-card__badges .svc-pill {
    border-top: none;
    border-right: none;
}

.svc-card__badges .svc-pill:first-child:last-child {
    border-radius: 0 6px 0 4px;
}

.svc-card__badges .svc-pill:first-child:not(:last-child) {
    border-radius: 0 0 0 4px;
    border-right: none;
}

.svc-card__badges .svc-pill:last-child:not(:first-child) {
    border-radius: 0 6px 0 4px;
}

/* Status pills */
.svc-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    padding: 0.15rem 0.5rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    white-space: nowrap;
    flex-shrink: 0;
}

.svc-pill--hidden {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.svc-pill--pending {
    background: rgba(251, 146, 60, 0.12);
    color: rgba(251, 146, 60, 0.9);
    border: 1px solid rgba(251, 146, 60, 0.2);
}

.svc-pill--rejected {
    background: rgba(239, 68, 68, 0.1);
    color: rgba(239, 68, 68, 0.85);
    border: 1px solid rgba(239, 68, 68, 0.18);
}

.svc-pill__dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: currentColor;
    flex-shrink: 0;
    animation: svc-pulse 1.8s ease-in-out infinite;
}

@keyframes svc-pulse {

    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }

    50% {
        opacity: 0.45;
        transform: scale(0.65);
    }
}

/* Footer: price + action on one row */
.svc-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
}

/* Price column */
.svc-card__price-block {
    flex-shrink: 0;
    text-align: left;
    white-space: nowrap;
}

.svc-card__amount {
    font-size: 1.2rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.88);
    letter-spacing: -0.015em;
}

.svc-card__rub {
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.55);
    margin-left: 0.05em;
}

.svc-card__sep {
    font-size: 0.85rem;
    font-weight: 400;
    color: rgba(255, 255, 255, 0.2);
    margin: 0 0.1em;
}

.svc-card__unit {
    font-size: 0.8rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.4);
    letter-spacing: 0;
}

/* Actions column */
.svc-card__actions {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* ── Buy button ───────────────────────────────────────────── */
.svc-buy-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.38rem 0.85rem;
    border: 1px solid color-mix(in srgb, var(--cat-accent, #a0a0ff) 40%, transparent);
    border-radius: 6px;
    background: color-mix(in srgb, var(--cat-accent, #a0a0ff) 10%, transparent);
    color: color-mix(in srgb, var(--cat-accent, #a0a0ff) 85%, white);
    font-family: inherit;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    position: relative;
    transition: border-color 0.2s, color 0.2s, box-shadow 0.2s, background 0.2s;
}

.svc-buy-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(105deg,
            transparent 30%,
            rgba(255, 255, 255, 0.08) 50%,
            transparent 70%);
    transform: translateX(-100%);
    transition: transform 0.5s ease;
}

.svc-buy-btn:hover::before {
    transform: translateX(100%);
}

.svc-buy-btn:hover {
    border-color: color-mix(in srgb, var(--cat-accent, #a0a0ff) 65%, transparent);
    background: color-mix(in srgb, var(--cat-accent, #a0a0ff) 18%, transparent);
    color: var(--cat-accent, #a0a0ff);
    box-shadow: 0 0 20px color-mix(in srgb, var(--cat-accent, #a0a0ff) 20%, transparent),
        inset 0 0 12px color-mix(in srgb, var(--cat-accent, #a0a0ff) 8%, transparent);
}

.svc-buy-btn svg {
    transition: transform 0.2s ease;
    flex-shrink: 0;
}

.svc-buy-btn:hover svg {
    transform: translateX(2px);
}
.svc-buy-btn--in-cart {
    background: rgba(100,200,130,0.08);
    border-color: rgba(100,200,130,0.3);
    color: rgba(140,255,180,0.85);
}
.svc-buy-btn--in-cart:hover {
    background: rgba(100,200,130,0.12);
    box-shadow: none;
}
.svc-buy-btn--in-cart svg { stroke: rgba(140,255,180,0.85); }
.svc-buy-btn--in-cart:hover svg { transform: none; }

/* ── 3-dot menu ───────────────────────────────────────────── */
.svc-menu {
    position: relative;
    flex-shrink: 0;
}

.svc-menu__trigger {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 3px;
    width: 28px;
    height: 28px;
    border: 1px solid transparent;
    border-radius: 4px;
    background: transparent;
    cursor: pointer;
    padding: 0;
    transition: border-color 0.15s, background 0.15s;
}

.svc-menu__trigger span {
    display: block;
    width: 3px;
    height: 3px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transition: background 0.15s;
}

.svc-menu__trigger:hover,
.svc-menu__trigger--open {
    border-color: rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.06);
}

.svc-menu__trigger:hover span,
.svc-menu__trigger--open span {
    background: rgba(255, 255, 255, 0.75);
}

.svc-menu__dropdown {
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    z-index: 50;
    min-width: 160px;
    background: #0f0f18;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    padding: 0.3rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5), 0 2px 8px rgba(0, 0, 0, 0.3);
    transform-origin: top right;
}

.svc-menu__item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    width: 100%;
    padding: 0.48rem 0.65rem;
    border: none;
    border-radius: 4px;
    background: transparent;
    color: rgba(255, 255, 255, 0.65);
    font-family: inherit;
    font-size: 0.82rem;
    text-align: left;
    cursor: pointer;
    transition: background 0.12s, color 0.12s;
}

.svc-menu__item svg {
    flex-shrink: 0;
    color: rgba(255, 255, 255, 0.35);
    transition: color 0.12s;
}

.svc-menu__item:hover {
    background: rgba(255, 255, 255, 0.07);
    color: rgba(255, 255, 255, 0.92);
}

.svc-menu__item:hover svg {
    color: rgba(255, 255, 255, 0.65);
}

.svc-menu__item--danger {
    color: rgba(239, 68, 68, 0.75);
}

.svc-menu__item--danger svg {
    color: rgba(239, 68, 68, 0.5);
}

.svc-menu__item--danger:hover {
    background: rgba(239, 68, 68, 0.08);
    color: rgba(239, 68, 68, 1);
}

.svc-menu__item--danger:hover svg {
    color: rgba(239, 68, 68, 0.8);
}

.svc-menu__divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.07);
    margin: 0.3rem 0;
}

/* Dropdown pop animation */
.svc-menu-pop-enter-active {
    transition: opacity 0.12s ease, transform 0.14s cubic-bezier(0.2, 0, 0.2, 1.4);
}

.svc-menu-pop-leave-active {
    transition: opacity 0.1s ease, transform 0.1s ease;
}

.svc-menu-pop-enter-from {
    opacity: 0;
    transform: scale(0.92) translateY(-4px);
}

.svc-menu-pop-leave-to {
    opacity: 0;
    transform: scale(0.96) translateY(-2px);
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
    border: 1px solid rgba(160, 160, 255, 0.45);
    border-radius: 3px;
    background: linear-gradient(135deg, rgba(110, 110, 210, 0.3) 0%, rgba(124, 45, 126, 0.2) 100%);
    color: rgba(255, 255, 255, 0.9);
    font-family: inherit;
    font-size: 0.82rem;
    cursor: pointer;
    transition: box-shadow 0.15s, border-color 0.15s;
}

.svc-btn-submit:hover {
    border-color: rgba(160, 160, 255, 0.7);
    box-shadow: 0 0 12px rgba(110, 110, 210, 0.25);
}

.svc-btn-danger {
    padding: 0.5rem 1.2rem;
    border: 1px solid rgba(239, 68, 68, 0.45);
    border-radius: 3px;
    background: rgba(239, 68, 68, 0.1);
    color: rgba(239, 68, 68, 0.9);
    font-family: inherit;
    font-size: 0.82rem;
    cursor: pointer;
    transition: box-shadow 0.15s, border-color 0.15s;
}

.svc-btn-danger:hover {
    border-color: rgba(239, 68, 68, 0.7);
    box-shadow: 0 0 10px rgba(239, 68, 68, 0.2);
}

/* ── Modal overlay ────────────────────────────────────────── */
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
    border-color: rgba(160, 160, 255, 0.45);
}

.svc-input--err {
    border-color: rgba(239, 68, 68, 0.5);
}

.svc-err {
    font-size: 0.75rem;
    color: rgba(239, 68, 68, 0.8);
    margin: 0;
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

/* ── Name suggestions chips ──────────────────────────────── */
.svc-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.svc-chip {
    padding: 0.45rem 1rem;
    border: 1px solid rgba(200, 70, 126, 0.3);
    border-radius: 99px;
    background: transparent;
    color: rgba(200, 70, 126, 0.75);
    font-family: inherit;
    font-size: 0.92rem;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}

.svc-chip:hover {
    border-color: rgba(200, 70, 126, 0.6);
    color: rgba(200, 70, 126, 1);
    background: rgba(200, 70, 126, 0.07);
}

.svc-chip--active {
    border-color: rgba(200, 70, 126, 0.65);
    background: rgba(200, 70, 126, 0.12);
    color: #fff;
}

/* ── Service form (inside SiteModal) ─────────────────────── */
.sf-wrap {
    display: flex;
    flex-direction: column;
}

.sf-screen {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

.sf-screen-enter-active {
    transition: opacity 0.2s ease, transform 0.22s ease;
}

.sf-screen-leave-active {
    transition: opacity 0.15s ease, transform 0.18s ease;
}

.sf-screen-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

.sf-screen-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}


.sf-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.92);
    letter-spacing: -0.015em;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.sf-form {
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
}

.sf-field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.sf-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 0.75rem;
}

.sf-field :deep(.app-select),
.sf-field :deep(.app-select__trigger) {
    font-size: 0.95rem;
}

.sf-label {
    font-size: 0.78rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(200, 70, 126, 0.85);
}

.sf-input {
    width: 100%;
    box-sizing: border-box;
    padding: 0.58rem 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 3px;
    color: rgba(255, 255, 255, 0.88);
    font-family: inherit;
    font-size: 0.95rem;
    line-height: 1.4;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    appearance: none;
    -moz-appearance: textfield;
}

.sf-input::-webkit-outer-spin-button,
.sf-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.sf-input:focus {
    border-color: rgba(200, 70, 126, 0.45);
    box-shadow: 0 0 0 3px rgba(200, 70, 126, 0.08);
}

.sf-input--err {
    border-color: rgba(239, 68, 68, 0.5);
}

.sf-err {
    font-size: 0.75rem;
    color: rgba(220, 100, 140, 0.9);
    margin: 0;
}

.sf-actions {
    display: flex;
    justify-content: space-between;
    gap: 0.6rem;
    padding-top: 0.35rem;
}

.sf-btn-submit {
    padding: 0.65rem 1.4rem;
    border: 1px solid rgba(200, 70, 126, 0.45);
    border-radius: 3px;
    background: linear-gradient(135deg, rgba(200, 70, 126, 0.25), rgba(200, 70, 126, 0.1));
    color: #fff;
    font-family: inherit;
    font-size: 0.88rem;
    cursor: pointer;
    transition: background 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
}

.sf-btn-submit:hover:not(:disabled) {
    background: linear-gradient(135deg, rgba(200, 70, 126, 0.4), rgba(200, 70, 126, 0.2));
    box-shadow: 0 0 18px rgba(200, 70, 126, 0.22);
}

.sf-btn-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.sf-btn-danger {
    padding: 0.65rem 1.4rem;
    border: 1px solid rgba(239, 68, 68, 0.45);
    border-radius: 3px;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.22), rgba(239, 68, 68, 0.08));
    color: rgba(239, 68, 68, 0.95);
    font-family: inherit;
    font-size: 0.88rem;
    cursor: pointer;
    transition: background 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.sf-btn-danger:hover {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.35), rgba(239, 68, 68, 0.15));
    border-color: rgba(239, 68, 68, 0.7);
    box-shadow: 0 0 18px rgba(239, 68, 68, 0.2);
}

/* ── Price preview (#1) ───────────────────────────────────── */
.sf-input-wrap {
    position: relative;
}

.sf-input-wrap .sf-input {
    padding-right: 4rem;
}

.sf-preview {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.82rem;
    color: rgba(200, 70, 126, 0.7);
    letter-spacing: 0.02em;
    pointer-events: none;
    white-space: nowrap;
}

.sf-preview-fade-enter-active,
.sf-preview-fade-leave-active {
    transition: opacity 0.2s ease;
}

.sf-preview-fade-enter-from,
.sf-preview-fade-leave-to {
    opacity: 0;
}

/* ── Char counter (#5) ────────────────────────────────────── */
.sf-char-count {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.2);
    pointer-events: none;
    transition: color 0.2s;
}

.sf-char-count--warn {
    color: rgba(200, 70, 126, 0.7);
}

.svc-pending-text {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.55);
    margin: 0;
    line-height: 1.6;
}

/* ── Chip pop animation (#6) ──────────────────────────────── */
@keyframes chip-pop {
    0% {
        transform: scale(1);
    }

    40% {
        transform: scale(0.88);
    }

    100% {
        transform: scale(1);
    }
}

.svc-chip--pop {
    animation: chip-pop 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.sf-btn-cancel {
    padding: 0.65rem 1.4rem;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    background: transparent;
    color: rgba(255,255,255,0.5);
    font-family: inherit;
    font-size: 0.88rem;
    cursor: pointer;
    transition: color 0.18s, border-color 0.18s;
}
.sf-btn-cancel:hover { color: rgba(255,255,255,0.8); border-color: rgba(255,255,255,0.2); }

.svc-block-error {
    position: sticky;
    top: 1rem;
    z-index: 10;
    margin: 0 1rem 1rem;
    padding: 0.65rem 1rem;
    background: rgba(255, 80, 80, 0.12);
    border: 1px solid rgba(255, 80, 80, 0.35);
    border-radius: 6px;
    color: rgba(255, 140, 140, 0.95);
    font-size: 0.9rem;
    text-align: center;
}
.block-err-enter-active, .block-err-leave-active { transition: opacity 0.25s, transform 0.25s; }
.block-err-enter-from, .block-err-leave-to { opacity: 0; transform: translateY(-6px); }
</style>

<style>
.cd-back {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(160,160,255,0.08);
    border: 1px solid rgba(160,160,255,0.28);
    border-radius: 5px;
    color: rgba(180,180,255,0.85);
    font-family: inherit;
    font-size: 0.88rem;
    cursor: pointer;
    padding: 0.35rem 0.75rem;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}

.cd-back:hover {
    background: rgba(160,160,255,0.16);
    border-color: rgba(160,160,255,0.5);
    color: var(--color-base-1);
}
</style>
