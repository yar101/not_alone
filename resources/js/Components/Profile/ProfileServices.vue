<script setup>
import {
    ref,
    computed,
    watch,
    onMounted,
    onUnmounted,
    nextTick,
    inject,
} from "vue";
import { useForm, router, usePage } from "@inertiajs/vue3";
import { ElTooltip, ElMessage, ElCheckbox, ElIcon, ElSwitch } from "element-plus";
import { InfoFilled, Present } from "@element-plus/icons-vue";
import axios from "axios";
import AppSelect from "@/Components/AppSelect.vue";
import CreateButton from "@/Components/CreateButton.vue";
import SiteModal from "@/Components/Site/SiteModal.vue";
import ServiceRemarksModal from "@/Components/Profile/ServiceRemarksModal.vue";
import ServiceStatusBadge from "@/Components/Profile/ServiceStatusBadge.vue";
import { useTranslations, localeLoading } from "@/composables/useTranslations";

const { __, transChoice, locale } = useTranslations();

function catName(cat) {
    return locale.value?.current === "en" && cat?.name_en
        ? cat.name_en
        : (cat?.name_ru ?? cat?.name ?? "");
}

function catDesc(cat) {
    return locale.value?.current === "en" && cat?.description_en
        ? cat.description_en
        : (cat?.description_ru ?? null);
}

function localUnitName(unit) {
    return locale.value?.current === "en" && unit?.name_en
        ? unit.name_en
        : (unit?.name_ru ?? "");
}

function localServiceName(item) {
    return locale.value?.current === "en" && item?.name_en
        ? item.name_en
        : (item?.name_ru ?? item?.name ?? "");
}

function isFlagged(item, field) {
    if (item.status === 'has_remarks' && item.latest_review) {
        return (item.latest_review.flagged_fields || []).includes(field);
    }
    if (item.pending_change?.status === 'has_remarks') {
        return (item.pending_change.flagged_fields || []).includes(field);
    }
    return false;
}

function getFieldComment(item, field) {
    if (item.status === 'has_remarks' && item.latest_review) {
        return (item.latest_review.field_comments || {})[field];
    }
    if (item.pending_change?.status === 'has_remarks') {
        return (item.pending_change.field_comments || {})[field];
    }
    return null;
}

const page = usePage();
const showPendingModal = ref(false);

watch(
    () => page.props.flash?.service_pending,
    (val) => {
        if (val) showPendingModal.value = true;
    },
    { immediate: true },
);

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
const cart = inject("cart", null);
const openAuth = inject("openAuth", null);
const cartConflictModal = ref(false);
const pendingCartItem = ref(null);
const blockError = ref(false);
let blockErrorTimer = null;

function showBlockError() {
    blockError.value = true;
    clearTimeout(blockErrorTimer);
    blockErrorTimer = setTimeout(() => {
        blockError.value = false;
    }, 3500);
}

// ── Remarks Modal ────────────────────────────────────────────────
const fixingService = ref(null);
const remarksMode = ref("review");
function openFix(service) {
    fixingService.value = service;
    remarksMode.value = service.status === "has_remarks" ? "review" : "change-request";
}

function isInCart(serviceId) {
    return !!cart?.value?.services?.items.find(
        (i) => i.service_id === serviceId,
    );
}

function addToCart(item) {
    if (!page.props.auth?.user) {
        openAuth?.("register");
        return;
    }
    if (props.isBlockedByIdol) {
        showBlockError();
        return;
    }
    if (!cart) return;
    const c = cart.value.services;
    const idolId = props.profileUser?.id;

    if (item.is_trial && c.items.some((i) => i.is_trial)) {
        ElMessage.warning('В корзине уже есть услуга "1-й заказ 0 ₽". Вы можете оформить только одну такую услугу за раз.');
        return;
    }

    if (c.idol_id && c.idol_id !== idolId && c.items.length > 0) {
        // Different idol — show conflict modal
        pendingCartItem.value = item;
        cartConflictModal.value = true;
        return;
    }
    doAddToCart(item);
}

function doAddToCart(item) {
    if (!cart) return;
    const c = cart.value.services;
    if (isInCart(item.id)) return;
    c.idol_id = props.profileUser?.id;
    c.idol_name = props.profileUser?.name ?? "";
    c.idol_avatar = props.profileUser?.avatar_url ?? null;
    c.items.push({
        service_id: item.id,
        name: localServiceName(item),
        price: item.is_trial ? 0 : item.price,
        time_unit: localUnitName(item.time_unit) || null,
        quantity: 1,
        is_trial: item.is_trial,
    });
}

function confirmCartReplace() {
    if (!cart) return;
    cart.value.services.items = [];
    cart.value.services.idol_id = null;
    doAddToCart(pendingCartItem.value);
    pendingCartItem.value = null;
    cartConflictModal.value = false;
}

function cancelCartReplace() {
    pendingCartItem.value = null;
    cartConflictModal.value = false;
}

// ── Two-level navigation ────────────────────────────────────────
const serviceNav = inject("serviceNav", null);
const selectedCategory = ref(null);
const pendingResync = ref(false);
const catTransitionDir = ref("forward"); // 'forward' | 'back'
const catTransitionName = computed(() =>
    catTransitionDir.value === "forward" ? "drill-in" : "drill-out",
);

const SESSION_KEY = computed(() => `services_cat_${props.profileUser?.id}`);

function openCategory(group) {
    catTransitionDir.value = "forward";
    selectedCategory.value = group;
    if (serviceNav) {
        serviceNav.inCategory = true;
        serviceNav.accent = group.category.accent_color || "#ffb2ef";
        serviceNav.onBack = backToList;
    }
}

function backToList() {
    catTransitionDir.value = "back";
    selectedCategory.value = null;
    sessionStorage.removeItem(SESSION_KEY.value);
    if (serviceNav) serviceNav.inCategory = false;
    router.reload({
        only: ["services", "serviceCategories", "serviceTimeUnits"],
    });
}

// Mark that a resync is needed after the next deferred prop update
function resyncSelectedCategory() {
    pendingResync.value = true;
}

// Restore on initial deferred prop load OR after a mutation (pendingResync)
watch(
    () => props.services,
    (services) => {
        if (!services) return;
        localServices.value = services;
        const catId = selectedCategory.value?.category?.id;
        if (!catId) return;
        if (!selectedCategory.value || pendingResync.value) {
            const group = services.find((g) => g.category.id == catId);
            selectedCategory.value = group ?? null;
            if (!group) sessionStorage.removeItem(SESSION_KEY.value);
            pendingResync.value = false;
        }
    },
    { immediate: true },
);

function navigateToIdolInCategory(idol) {
    const categoryId = selectedCategory.value?.category?.id;
    if (categoryId) {
        sessionStorage.setItem(`services_cat_${idol.id}`, categoryId);
    }
    window.location.href = route("profile.show", idol.id) + "#services";
}

// ── Inline description edit ─────────────────────────────────────
const editingDesc = ref(false);
const descDraft = ref("");
const descSaving = ref(false);

function startDescEdit() {
    descDraft.value = selectedCategory.value?.idol_description ?? "";
    editingDesc.value = true;
}

function cancelDescEdit() {
    editingDesc.value = false;
}

function saveDesc() {
    const catId = selectedCategory.value?.category?.id;
    if (!catId || descSaving.value) return;
    descSaving.value = true;
    router.patch(
        route("profile.services.category.description", catId),
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
            onFinish() {
                descSaving.value = false;
            },
        },
    );
}

// ── Add / Edit service form ─────────────────────────────────────
const showForm = ref(false);
const editingId = ref(null);
const showNameRu = ref(true);
const showNameEn = ref(false);

const form = useForm({
    name_ru: "",
    name_en: "",
    category_id: null,
    time_unit_id: null,
    price: "",
    is_trial: false,
});

function openAdd() {
    editingId.value = null;
    form.reset();
    if (locale.value?.current === "en") {
        showNameEn.value = true;
        showNameRu.value = false;
    } else {
        showNameRu.value = true;
        showNameEn.value = false;
    }
    // Pre-fill category if we're inside a category detail
    if (selectedCategory.value) {
        form.category_id = selectedCategory.value.category.id;
    }
    loadDraft();
    showForm.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    
    // If there is a pending change request with remarks, use its values
    const source = (item.pending_change?.status === 'has_remarks') ? item.pending_change : null;
    
    if (source) {
        form.name_ru = source.pending_name?.ru ?? "";
        form.name_en = source.pending_name?.en ?? "";
        form.category_id = source.pending_category?.id ?? item.category_id;
        form.time_unit_id = source.pending_time_unit?.id ?? item.time_unit?.id;
        form.price = source.pending_price ?? item.price;
        form.is_trial = source.pending_is_trial ?? item.is_trial ?? false;
    } else {
        form.name_ru = item.name_ru ?? "";
        form.name_en = item.name_en ?? "";
        form.category_id = item.category_id ?? null;
        form.time_unit_id = item.time_unit?.id ?? null;
        form.price = item.price;
        form.is_trial = item.is_trial ?? false;
    }
    
    showNameRu.value = !!form.name_ru;
    showNameEn.value = !!form.name_en;
    if (!showNameRu.value && !showNameEn.value) {
        if (locale.value?.current === "en") showNameEn.value = true;
        else showNameRu.value = true;
    }
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingId.value = null;
    showNameRu.value = true;
    showNameEn.value = false;
    form.reset();
    form.clearErrors();
    clearDraft();
}

function submitForm() {
    if (editingId.value) {
        const item = localServices.value.flatMap(g => g.items).find(i => i.id === editingId.value);
        const isFixingCR = item?.pending_change?.status === 'has_remarks';

        localeLoading.value = true;
        const method = isFixingCR ? 'post' : 'patch';
        const url = isFixingCR
            ? route("profile.services.fix-change-request", editingId.value)
            : route("profile.services.update", editingId.value);

        form.submit(method, url, {
            preserveScroll: true,
            preserveState: true,
            onSuccess() {
                closeForm();
                resyncSelectedCategory();
            },
            onFinish: () => {
                localeLoading.value = false;
            },
        });
    } else {
        form.post(route("profile.services.store"), {
            preserveScroll: true,
            preserveState: true,
            onSuccess() {
                closeForm();
                resyncSelectedCategory();
            },
        });
    }
}
const showCancelConfirm = ref(false);

function tryCloseForm() {
    if (!editingId.value && (form.name_ru || form.price || form.time_unit_id)) {
        showCancelConfirm.value = true;
    } else {
        closeForm();
    }
}

function confirmCancelForm() {
    showCancelConfirm.value = false;
    closeForm();
}

function removeNameRu() {
    showNameRu.value = false;
    form.name_ru = "";
}

const dismissChangeRequest = (item) => {
    router.delete(route("profile.services.dismiss-change-request", item.id), {
        onSuccess: () => {
            resyncSelectedCategory();
            toast.success(__("profile.services.dismiss_success", "Отклоненные изменения скрыты"));
        },
    });
};

function removeNameEn() {
    showNameEn.value = false;
    form.name_en = "";
}
function addSecondary() {
    if (!showNameRu.value) {
        showNameRu.value = true;
    } else if (!showNameEn.value) {
        showNameEn.value = true;
    }
}

const deleteConfirmId = ref(null);

function askDeleteService(id) {
    deleteConfirmId.value = id;
}

function confirmDeleteService() {
    const id = deleteConfirmId.value;
    deleteConfirmId.value = null;
    localeLoading.value = true;
    router.delete(route("profile.services.destroy", id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: resyncSelectedCategory,
        onFinish: () => {
            localeLoading.value = false;
        },
    });
}

function cancelDeleteService() {
    deleteConfirmId.value = null;
}

function toggleActive(item) {
    localeLoading.value = true;
    router.patch(
        route("profile.services.update", item.id),
        {
            is_active: !item.is_active,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: resyncSelectedCategory,
            onFinish: () => {
                localeLoading.value = false;
            },
        },
    );
}

function toggleTrialStatus(item, val = null) {
    localeLoading.value = true;
    router.post(
        route("profile.services.toggle-trial", item.id),
        {
            is_trial: val !== null ? val : !item.is_trial,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                resyncSelectedCategory();
                toast.success('Статус услуги изменен');
            },
            onFinish: () => {
                localeLoading.value = false;
            },
        },
    );
}

// ── Computed ────────────────────────────────────────────────────
const loaded = computed(() => Array.isArray(localServices.value));
const isEmpty = computed(
    () => loaded.value && localServices.value.length === 0,
);

const sortedServices = computed(() => {
    if (!Array.isArray(localServices.value))
        return { withItems: [], empty: [] };
    return {
        withItems: localServices.value.filter((g) => g.items.length > 0),
        empty: localServices.value.filter((g) => g.items.length === 0),
    };
});

const formCategory = computed(
    () =>
        (props.serviceCategories ?? []).find(
            (c) => c.id === form.category_id,
        ) ?? null,
);
const formSuggestionsRu = computed(() =>
    Array.isArray(formCategory.value?.name_suggestions?.ru)
        ? formCategory.value.name_suggestions.ru
        : [],
);
const formSuggestionsEn = computed(() =>
    Array.isArray(formCategory.value?.name_suggestions?.en)
        ? formCategory.value.name_suggestions.en
        : [],
);
const namePlaceholderRu = computed(
    () => formSuggestionsRu.value[0] ?? __("profile.services.search_ph"),
);
const namePlaceholderEn = computed(
    () => formSuggestionsEn.value[0] ?? __("profile.services.search_ph_en"),
);

// ── #1 Price preview ─────────────────────────────────────────
const pricePreview = computed(() => {
    if (!form.price || !form.time_unit_id) return null;
    const unit = (props.serviceTimeUnits ?? []).find(
        (u) => u.id === form.time_unit_id,
    );
    if (!unit) return null;
    return `${Number(form.price).toLocaleString("ru")} ₽ / ${localUnitName(unit)}`;
});

// ── #4 Draft ─────────────────────────────────────────────────
const DRAFT_KEY = computed(() => `svc_draft_${props.profileUser?.id}`);

function loadDraft() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY.value);
        if (!raw) return;
        const d = JSON.parse(raw);
        if (d.name_ru) {
            form.name_ru = d.name_ru;
            showNameRu.value = true;
        }
        if (d.name_en) {
            form.name_en = d.name_en;
            showNameEn.value = true;
        }
        if (d.category_id) form.category_id = d.category_id;
        if (d.price) form.price = d.price;
        if (d.time_unit_id) form.time_unit_id = d.time_unit_id;
        if (d.is_trial !== undefined) form.is_trial = d.is_trial;
    } catch {}
}

function saveDraft() {
    if (!editingId.value) {
        localStorage.setItem(
            DRAFT_KEY.value,
            JSON.stringify({
                name_ru: form.name_ru,
                name_en: form.name_en,
                category_id: form.category_id,
                price: form.price,
                time_unit_id: form.time_unit_id,
                is_trial: form.is_trial,
            }),
        );
    }
}

function clearDraft() {
    localStorage.removeItem(DRAFT_KEY.value);
}

watch(
    [
        () => form.name_ru,
        () => form.name_en,
        () => form.category_id,
        () => form.price,
        () => form.time_unit_id,
        () => form.is_trial,
    ],
    saveDraft,
);

// ── #6 Chip animation ────────────────────────────────────────
const animatingChip = ref(null);

function selectChip(s, lang) {
    if (lang === "en") form.name_en = s;
    else form.name_ru = s;
    animatingChip.value = s;
    setTimeout(() => {
        animatingChip.value = null;
    }, 300);
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
    if (!e.target.closest(".svc-menu")) closeMenu();
}

let svcNotifyChannel = null;

async function fetchServicesFromApi() {
    if (!props.profileUser?.id) return;
    try {
        const res = await axios.get(route('profile.services.index-for-profile', props.profileUser.id));
        localServices.value = res.data.groups;
        // Resync selected category if open
        if (selectedCategory.value) {
            const catId = selectedCategory.value.category.id;
            const updated = res.data.groups.find(g => g.category.id === catId);
            if (updated) selectedCategory.value = updated;
        }
    } catch (e) {
        // silent fail — stale data is better than a crash
    }
}

onMounted(() => {
    document.addEventListener("click", onDocClick, true);
    if (props.isOwner && window.Echo && props.profileUser?.id) {
        svcNotifyChannel = window.Echo.private(`App.Models.User.${props.profileUser.id}`)
            .listen('.new-notification', () => {
                fetchServicesFromApi();
            });
    }
});

onUnmounted(() => {
    document.removeEventListener("click", onDocClick, true);
    if (svcNotifyChannel && props.profileUser?.id) {
        svcNotifyChannel.stopListening('.new-notification');
    }
    selectedCategory.value = null;
    sessionStorage.removeItem(SESSION_KEY.value);
    if (serviceNav) serviceNav.inCategory = false;
});

// ── #8 Form validation ───────────────────────────────────────
const formAccentColor = computed(() => {
    if (selectedCategory.value)
        return selectedCategory.value.category.accent_color || "#ffb2ef";
    const cat = (props.serviceCategories ?? []).find(
        (c) => c.id === form.category_id,
    );
    return cat?.accent_color || "#ffb2ef";
});

const formValid = computed(
    () =>
        ((showNameRu.value && form.name_ru.trim().length > 0) ||
            (showNameEn.value && form.name_en.trim().length > 0)) &&
        form.category_id !== null &&
        Number(form.price) > 0 &&
        form.time_unit_id !== null,
);

// ── Carousel ─────────────────────────────────────────────────
const isMobile = ref(window.innerWidth <= 600);
function onResizeCarousel() {
    isMobile.value = window.innerWidth <= 600;
}
onMounted(() => window.addEventListener("resize", onResizeCarousel));
onUnmounted(() => window.removeEventListener("resize", onResizeCarousel));

const carouselPage = ref(1);
const carouselIdols = ref([]);
const carouselTotal = ref(0);
const carouselHasMore = ref(false);
const carouselLoading = ref(false);
const carouselReady = ref(false);
const carouselDir = ref("next"); // 'next' | 'prev'

async function loadCarousel(page = 1) {
    carouselLoading.value = true;
    const perPage = isMobile.value ? 2 : 4;
    try {
        const res = await fetch(
            route("profile.category-idols", {
                user: props.profileUser?.id,
                category: selectedCategory.value.category.id,
            }) + `?page=${page}&per_page=${perPage}`,
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
        carouselDir.value = "prev";
        loadCarousel(carouselPage.value - 1);
    }
}
function carouselNext() {
    if (carouselHasMore.value) {
        carouselDir.value = "next";
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
                {{ __("profile.services.blocked_error") }}
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
                    <h2 class="svc-list-header__title">
                        {{ __("profile.services.categories_label") }}
                    </h2>
                    <CreateButton v-if="isOwner && isIdol" @click="openAdd"
                        >{{ __("profile.services.new_btn") }}
                    </CreateButton>
                </div>

                <!-- Category cards -->
                <div class="cat-grid">
                    <button
                        v-for="group in sortedServices.withItems"
                        :key="group.category.id"
                        class="cat-tile"
                        @click="openCategory(group)"
                        :style="{
                            '--cat-accent':
                                group.category.accent_color || '#ffb2ef',
                        }"
                    >
                        <div class="cat-tile__img-wrap">
                            <img
                                v-if="group.category.image_url"
                                :src="group.category.image_url"
                                :alt="catName(group.category)"
                                class="cat-tile__img"
                            />
                            <div v-else class="cat-tile__img-placeholder">
                                <svg
                                    width="22"
                                    height="22"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    opacity="0.25"
                                >
                                    <rect
                                        x="3"
                                        y="3"
                                        width="18"
                                        height="18"
                                        rx="2"
                                    />
                                    <circle cx="8.5" cy="8.5" r="1.5" />
                                    <path d="M21 15l-5-5L5 21" />
                                </svg>
                            </div>
                        </div>
                        <div class="cat-tile__body">
                            <span class="cat-tile__name">{{
                                catName(group.category)
                            }}</span>
                            <p
                                v-if="catDesc(group.category)"
                                class="cat-tile__desc"
                            >
                                {{ catDesc(group.category) }}
                            </p>
                            <div class="cat-tile__footer">
                                <span class="cat-tile__count">
                                    {{
                                        transChoice(
                                            "order.service_count",
                                            group.items.length,
                                            {
                                                count: group.items.length,
                                            },
                                        )
                                    }}</span
                                >
                            </div>
                        </div>
                    </button>

                    <div
                        v-if="sortedServices.empty.length > 0"
                        class="cat-grid__divider"
                    ></div>

                    <button
                        v-for="group in sortedServices.empty"
                        :key="group.category.id"
                        class="cat-tile cat-tile--empty"
                        @click="openCategory(group)"
                        :style="{
                            '--cat-accent':
                                group.category.accent_color || '#ffb2ef',
                        }"
                    >
                        <div class="cat-tile__img-wrap">
                            <img
                                v-if="group.category.image_url"
                                :src="group.category.image_url"
                                :alt="catName(group.category)"
                                class="cat-tile__img"
                            />
                            <div v-else class="cat-tile__img-placeholder">
                                <svg
                                    width="22"
                                    height="22"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    opacity="0.25"
                                >
                                    <rect
                                        x="3"
                                        y="3"
                                        width="18"
                                        height="18"
                                        rx="2"
                                    />
                                    <circle cx="8.5" cy="8.5" r="1.5" />
                                    <path d="M21 15l-5-5L5 21" />
                                </svg>
                            </div>
                        </div>
                        <div class="cat-tile__body">
                            <span class="cat-tile__name">{{
                                catName(group.category)
                            }}</span>
                            <p
                                v-if="catDesc(group.category)"
                                class="cat-tile__desc"
                            >
                                {{ catDesc(group.category) }}
                            </p>
                            <div class="cat-tile__footer">
                                <span
                                    class="cat-tile__count cat-tile__count--empty"
                                    >{{
                                        transChoice("order.service_count", 0, {
                                            count: 0,
                                        })
                                    }}</span
                                >
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- ── CategoryDetail ── -->
            <div
                v-else
                key="detail"
                class="cd-detail"
                :style="{
                    '--cat-accent':
                        selectedCategory.category.accent_color || '#ffb2ef',
                }"
            >
                <!-- Category hero card -->
                <div class="cd-hero">
                    <div class="cd-hero__body">
                        <div class="cd-hero__top">
                            <h2 class="cd-hero__title">
                                {{ catName(selectedCategory.category) }}
                            </h2>
                            <div class="cd-hero__top-actions">
                                <button
                                    v-if="isOwner && !editingDesc"
                                    class="cd-hero__edit-btn"
                                    @click="startDescEdit"
                                >
                                    <svg
                                        width="13"
                                        height="13"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path
                                            d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
                                        />
                                        <path
                                            d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
                                        />
                                    </svg>
                                    {{ __("profile.services.desc_edit") }}
                                </button>
                                <CreateButton
                                    v-if="isOwner && isIdol"
                                    @click="openAdd"
                                    >{{ __("profile.services.new_btn") }}
                                </CreateButton>
                            </div>
                        </div>

                        <Transition name="desc-swap" mode="out-in">
                            <div v-if="!editingDesc" key="view">
                                <p
                                    v-if="selectedCategory.idol_description"
                                    class="cd-hero__desc"
                                >
                                    {{ selectedCategory.idol_description }}
                                </p>
                                <p
                                    v-else-if="isOwner"
                                    class="cd-hero__desc cd-hero__desc--placeholder"
                                >
                                    {{ __("profile.services.desc_ph") }}
                                </p>
                                <p
                                    v-else
                                    class="cd-hero__desc cd-hero__desc--placeholder"
                                >
                                    {{ __("profile.services.desc_empty") }}
                                </p>
                            </div>
                            <div v-else key="edit">
                                <textarea
                                    v-model="descDraft"
                                    class="cd-hero__textarea"
                                    rows="3"
                                    maxlength="1000"
                                    :placeholder="
                                        __('profile.services.desc_edit_ph')
                                    "
                                />
                                <div class="cd-hero__actions">
                                    <button
                                        class="svc-btn-cancel"
                                        @click="cancelDescEdit"
                                    >
                                        {{ __("common.cancel") }}
                                    </button>
                                    <button
                                        class="svc-btn-submit"
                                        :class="{
                                            'svc-btn-submit--saving':
                                                descSaving,
                                        }"
                                        :disabled="descSaving"
                                        @click="saveDesc"
                                    >
                                        <svg
                                            v-if="descSaving"
                                            class="svc-btn-spinner"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            width="13"
                                            height="13"
                                        >
                                            <circle
                                                cx="12"
                                                cy="12"
                                                r="9"
                                                stroke-width="2.5"
                                                stroke-opacity="0.25"
                                            />
                                            <path
                                                d="M12 3a9 9 0 0 1 9 9"
                                                stroke-width="2.5"
                                                stroke-linecap="round"
                                            />
                                        </svg>
                                        {{ __("common.save") }}
                                    </button>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>

                <!-- Owner action buttons (mobile only — outside hero) -->
                <div v-if="isOwner" class="cd-owner-actions">
                    <button
                        v-if="!editingDesc"
                        class="cd-hero__edit-btn"
                        @click="startDescEdit"
                    >
                        <svg
                            width="13"
                            height="13"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
                            />
                            <path
                                d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
                            />
                        </svg>
                        {{ __("profile.services.desc_edit") }}
                    </button>
                    <CreateButton v-if="isIdol" @click="openAdd"
                        >{{ __("profile.services.new_btn") }}
                    </CreateButton>
                </div>

                <!-- Services section -->
                <div class="cd-section cd-section--services">
                    <span class="cd-section__label">{{
                        __("profile.services.variants")
                    }}</span>

                    <div
                        v-if="selectedCategory.items.length === 0"
                        class="svc-empty"
                    >
                        <p class="svc-empty__title">
                            {{ __("profile.services.empty") }}
                        </p>
                    </div>
                    <TransitionGroup
                        v-else
                        name="svc-item"
                        tag="div"
                        class="svc-list"
                    >
                        <div
                            v-for="item in selectedCategory.items"
                            :key="item.id"
                            class="svc-card"
                            :class="{
                                'svc-card--inactive': !item.is_active,
                                'svc-card--pending': isOwner && (item.status === 'pending' || item.pending_change?.status === 'pending'),
                                'svc-card--remarks': isOwner && (item.status === 'has_remarks' || item.pending_change?.status === 'has_remarks'),
                                'svc-card--rejected': isOwner && item.status === 'rejected',
                            }"
                        >
                            <!-- Info column -->
                            <div class="svc-card__info">
                                <div class="svc-card__header">
                                    <div class="svc-card__name-row">
                                        <span class="svc-card__name" :class="{ 'svc-card__name--flagged': isFlagged(item, 'name_ru') || isFlagged(item, 'name_en') }">
                                            {{ localServiceName(item) }}
                                            <span v-if="isFlagged(item, 'name_ru') || isFlagged(item, 'name_en')" class="svc-card__flag-icon" :title="getFieldComment(item, 'name_ru') || getFieldComment(item, 'name_en') || 'Замечание модератора'">⚠️</span>
                                        </span>
                                    </div>
                                    
                                    <!-- Badges -->
                                    <div class="svc-card__badges">
                                        <ServiceStatusBadge
                                            v-if="item.is_trial"
                                            status="trial"
                                            title="Бесплатно 1 раз для новых клиентов"
                                        />
                                        <template v-if="isOwner">
                                            <ServiceStatusBadge
                                                v-if="!item.is_active"
                                                status="hidden"
                                            />
                                            <ServiceStatusBadge
                                                v-else-if="item.status === 'rejected'"
                                                status="rejected"
                                            />
                                            <ServiceStatusBadge
                                                v-else-if="item.status === 'has_remarks' || item.pending_change?.status === 'has_remarks'"
                                                status="has_remarks"
                                                :is-change-request="item.pending_change?.status === 'has_remarks'"
                                            />
                                            <ServiceStatusBadge
                                                v-else-if="item.pending_change?.status === 'pending'"
                                                status="pending"
                                                :is-change-request="true"
                                            />
                                            <ServiceStatusBadge
                                                v-else-if="item.pending_change?.status === 'rejected'"
                                                status="rejected"
                                                :is-change-request="true"
                                            />
                                            <ServiceStatusBadge
                                                v-else-if="item.status === 'pending'"
                                                status="pending"
                                            />
                                        </template>
                                    </div>
                                </div>
                                <span
                                    v-if="
                                        isOwner &&
                                        ((item.status === 'rejected' && item.rejection_reason) ||
                                        (item.pending_change?.status === 'rejected' && item.pending_change?.admin_comment))
                                    "
                                    class="svc-card__reason"
                                    >{{ item.status === 'rejected' ? item.rejection_reason : item.pending_change.admin_comment }}</span
                                >
                            </div>

                            <!-- Footer: price + actions -->
                            <div class="svc-card__footer">
                                <div class="svc-card__price-block" :class="{'svc-card__price-block--trial': !isOwner && item.is_trial}">
                                    <template v-if="!isOwner && item.is_trial">
                                        <div class="svc-card__price-main">
                                            <span class="svc-card__amount">0</span><span class="svc-card__rub">₽</span><span class="svc-card__sep">/</span><span class="svc-card__unit">{{ localUnitName(item.time_unit) }}</span>
                                        </div>
                                        <div class="svc-card__old-price">{{ item.price.toLocaleString("ru") }} ₽</div>
                                    </template>
                                    <template v-else>
                                        <span class="svc-card__amount">{{ item.price.toLocaleString("ru") }}</span
                                        ><span class="svc-card__rub">₽</span
                                        ><span class="svc-card__sep">/</span
                                        ><span class="svc-card__unit">{{ localUnitName(item.time_unit) }}</span>
                                    </template>
                                </div>

                                <!-- Actions column -->
                                <div class="svc-card__actions">
                                    <button
                                        v-if="!isOwner && cart"
                                        class="svc-buy-btn"
                                        :class="{
                                            'svc-buy-btn--in-cart': isInCart(
                                                item.id,
                                            ),
                                        }"
                                        @click="addToCart(item)"
                                    >
                                        <template v-if="isInCart(item.id)">
                                            <svg
                                                width="11"
                                                height="11"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2.5"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <polyline
                                                    points="20 6 9 17 4 12"
                                                />
                                            </svg>
                                            <span>{{
                                                __("profile.services.in_cart")
                                            }}</span>
                                        </template>
                                        <template v-else>
                                            <svg
                                                width="11"
                                                height="11"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <path
                                                    d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"
                                                />
                                                <line
                                                    x1="3"
                                                    y1="6"
                                                    x2="21"
                                                    y2="6"
                                                />
                                                <path d="M16 10a4 4 0 01-8 0" />
                                            </svg>
                                            <span>{{
                                                __("profile.services.to_cart")
                                            }}</span>
                                        </template>
                                    </button>
                                    <div v-if="isOwner" class="svc-menu">
                                        <button
                                            class="svc-menu__trigger"
                                            :class="{
                                                'svc-menu__trigger--open':
                                                    openMenuId === item.id,
                                            }"
                                            @click.stop="toggleMenu(item.id)"
                                            :title="
                                                __('profile.services.actions')
                                            "
                                        >
                                            <span></span><span></span
                                            ><span></span>
                                        </button>
                                        <Transition name="svc-menu-pop">
                                            <div
                                                v-if="openMenuId === item.id"
                                                class="svc-menu__dropdown"
                                            >
                                                <template
                                                    v-if="
                                                        item.status === 'approved' &&
                                                        item.pending_change?.status !== 'has_remarks' &&
                                                        item.pending_change?.status !== 'pending'
                                                    "
                                                >
                                                    <button
                                                        class="svc-menu__item"
                                                        @click="
                                                            toggleActive(item);
                                                            closeMenu();
                                                        "
                                                    >
                                                        <svg
                                                            width="13"
                                                            height="13"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            stroke-width="2"
                                                            stroke-linecap="round"
                                                        >
                                                            <circle
                                                                cx="12"
                                                                cy="12"
                                                                r="10"
                                                            />
                                                            <line
                                                                v-if="
                                                                    item.is_active
                                                                "
                                                                x1="4.93"
                                                                y1="4.93"
                                                                x2="19.07"
                                                                y2="19.07"
                                                            />
                                                        </svg>
                                                        {{
                                                            item.is_active
                                                                ? __(
                                                                      "common.disable",
                                                                  )
                                                                : __(
                                                                      "common.enable",
                                                                  )
                                                        }}
                                                    </button>
                                                    <button
                                                        class="svc-menu__item"
                                                        @click="
                                                            openEdit(item);
                                                            closeMenu();
                                                        "
                                                    >
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                        </svg>
                                                        {{ __("common.edit") }}
                                                    </button>
                                                    <button
                                                        class="svc-menu__item"
                                                        :class="{ 'svc-menu__item--active': item.is_trial }"
                                                        @click="
                                                            toggleTrialStatus(item);
                                                            closeMenu();
                                                        "
                                                    >
                                                        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                                            <span style="display: flex; align-items: center; gap: 0.6rem; white-space: nowrap;">
                                                                <el-icon><Present /></el-icon>
                                                                1-й заказ 0 ₽
                                                            </span>
                                                            <el-tooltip placement="top" effect="dark" popper-class="newbie-dark-tooltip" :hide-after="0" trigger="click">
                                                                <template #content>
                                                                    Новый клиент сможет заказать эту услугу за 0 ₽.<br>
                                                                    Один клиент может взять только одну бесплатную услугу.
                                                                </template>
                                                                <el-icon :size="16" style="color: rgba(255, 255, 255, 0.4); cursor: help; outline: none;" @click.prevent.stop><InfoFilled /></el-icon>
                                                            </el-tooltip>
                                                        </div>
                                                    </button>
                                                    <div
                                                        class="svc-menu__divider"
                                                    ></div>
                                                </template>
                                                <template
                                                    v-if="
                                                        item.status === 'has_remarks' ||
                                                        item.pending_change?.status === 'has_remarks'
                                                    "
                                                >
                                                    <button
                                                        class="svc-menu__item"
                                                        @click="
                                                            openFix(item);
                                                            closeMenu();
                                                        "
                                                    >
                                                        <svg
                                                            width="13"
                                                            height="13"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            stroke-width="2"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                        >
                                                            <path d="M12 20h9"/>
                                                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                                                        </svg>
                                                        {{ __("profile.services.fix_btn") }}
                                                    </button>
                                                    <div class="svc-menu__divider"></div>
                                                </template>
                                                <button
                                                    class="svc-menu__item svc-menu__item--danger"
                                                    @click="
                                                        askDeleteService(
                                                            item.id,
                                                        );
                                                        closeMenu();
                                                    "
                                                >
                                                    <svg
                                                        width="13"
                                                        height="13"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    >
                                                        <polyline
                                                            points="3 6 5 6 21 6"
                                                        />
                                                        <path
                                                            d="M19 6l-1 14H6L5 6"
                                                        />
                                                        <path
                                                            d="M10 11v6M14 11v6"
                                                        />
                                                        <path d="M9 6V4h6v2" />
                                                    </svg>
                                                    {{ __("common.delete") }}
                                                </button>

                                                <button
                                                    v-if="item.pending_change?.status === 'rejected'"
                                                    class="svc-menu__item svc-menu__item--danger"
                                                    @click="
                                                        dismissChangeRequest(item);
                                                        closeMenu();
                                                    "
                                                >
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                                    </svg>
                                                    {{ __("profile.services.dismiss_cr", "Скрыть отклонение") }}
                                                </button>
                                            </div>
                                        </Transition>
                                    </div>
                                </div>
                            </div>
                            <!-- /.svc-card__footer -->
                        </div>
                    </TransitionGroup>
                </div>

                <!-- Carousel of other idols -->
                <div
                    v-if="!carouselReady || carouselTotal > 0"
                    class="cd-section cd-carousel"
                >
                    <p class="cd-section__label">
                        {{ __("profile.services.other_idols") }}
                    </p>
                    <div class="cd-carousel__row">
                        <button
                            class="cd-carousel__nav cd-carousel__nav--prev"
                            :disabled="carouselPage === 1 || carouselLoading"
                            @click="carouselPrev"
                        >
                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M15 18l-6-6 6-6" />
                            </svg>
                        </button>
                        <div class="cd-carousel__track">
                            <!-- Layer 1: skeletons — always in DOM, provide stable height -->
                            <div
                                class="cd-carousel__skeletons"
                                :class="{
                                    'cd-carousel__skeletons--hidden':
                                        !carouselLoading && carouselReady,
                                }"
                            >
                                <div
                                    v-for="n in isMobile ? 2 : 4"
                                    :key="n"
                                    class="cd-carousel__idol cd-carousel__idol--skel"
                                ></div>
                            </div>
                            <!-- Layer 2: real cards — absolute on top, only when loaded -->
                            <Transition
                                :name="
                                    carouselDir === 'next'
                                        ? 'carousel-next'
                                        : 'carousel-prev'
                                "
                                mode="out-in"
                            >
                                <div
                                    v-if="!carouselLoading"
                                    class="cd-carousel__idols"
                                    :key="carouselPage"
                                >
                                    <a
                                        v-for="idol in carouselIdols"
                                        :key="idol.id"
                                        href="#"
                                        class="cd-carousel__idol"
                                        @click.prevent="
                                            navigateToIdolInCategory(idol)
                                        "
                                    >
                                        <img
                                            v-if="idol.avatar_url"
                                            :src="idol.avatar_url"
                                            :alt="idol.name"
                                            class="cd-carousel__avatar"
                                        />
                                        <div
                                            v-else
                                            class="cd-carousel__avatar cd-carousel__avatar--placeholder"
                                        >
                                            {{ idol.name.charAt(0) }}
                                        </div>
                                        <span
                                            v-if="idol.rating"
                                            class="cd-carousel__rating"
                                            >★ {{ idol.rating }}</span
                                        >
                                        <span class="cd-carousel__name">{{
                                            idol.name
                                        }}</span>
                                    </a>
                                </div>
                            </Transition>
                        </div>
                        <button
                            class="cd-carousel__nav cd-carousel__nav--next"
                            :disabled="!carouselHasMore || carouselLoading"
                            @click="carouselNext"
                        >
                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M9 18l6-6-6-6" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Cart conflict modal -->
        <SiteModal
            :show="cartConflictModal"
            variant="pink"
            :compact="true"
            @close="cancelCartReplace"
        >
            <div class="sf-wrap">
                <div class="sf-title">
                    {{ __("profile.services.cart_conflict.title") }}
                </div>
                <p class="svc-pending-text">
                    {{ __("profile.services.cart_conflict.body") }}
                </p>
                <div class="sf-actions">
                    <button class="sf-btn-cancel" @click="cancelCartReplace">
                        {{ __("common.cancel") }}
                    </button>
                    <button class="sf-btn-submit" @click="confirmCartReplace">
                        {{ __("profile.services.cart_conflict.confirm") }}
                    </button>
                </div>
            </div>
        </SiteModal>

        <!-- Service pending modal -->
        <SiteModal
            :show="showPendingModal"
            variant="pink"
            :compact="true"
            @close="showPendingModal = false"
        >
            <div class="sf-wrap">
                <div class="sf-title">
                    {{ __("profile.services.moderation.title") }}
                </div>
                <p class="svc-pending-text">
                    {{ __("profile.services.moderation.body") }}
                </p>
                <div class="sf-actions">
                    <button
                        class="sf-btn-submit"
                        @click="showPendingModal = false"
                    >
                        {{ __("common.got_it") }}
                    </button>
                </div>
            </div>
        </SiteModal>

        <!-- Delete confirm modal -->
        <SiteModal
            :show="deleteConfirmId !== null"
            variant="pink"
            :compact="true"
            @close="cancelDeleteService"
        >
            <div class="sf-wrap">
                <div class="sf-title">
                    {{ __("profile.services.delete_confirm.title") }}
                </div>
                <p class="svc-pending-text">
                    {{ __("profile.services.delete_confirm.body") }}
                </p>
                <div class="sf-actions">
                    <button
                        type="button"
                        class="svc-btn-cancel"
                        @click="cancelDeleteService"
                    >
                        {{ __("common.cancel") }}
                    </button>
                    <button
                        type="button"
                        class="sf-btn-danger"
                        @click="confirmDeleteService"
                    >
                        {{ __("common.delete") }}
                    </button>
                </div>
            </div>
        </SiteModal>

        <!-- Add/Edit modal -->
        <SiteModal
            :show="showForm"
            variant="pink"
            :compact="true"
            @close="tryCloseForm"
        >
            <div class="sf-wrap" :style="{ '--cat-accent': formAccentColor }">
                <Transition name="sf-screen" mode="out-in">
                    <div
                        v-if="showCancelConfirm"
                        key="confirm"
                        class="sf-screen"
                    >
                        <div class="sf-title">
                            {{ __("common.leave_confirm") }}
                        </div>
                        <p class="svc-pending-text">
                            {{ __("common.leave_body") }}
                        </p>
                        <div class="sf-actions">
                            <button
                                type="button"
                                class="svc-btn-cancel"
                                @click="showCancelConfirm = false"
                            >
                                {{ __("common.stay") }}
                            </button>
                            <button
                                type="button"
                                class="sf-btn-danger"
                                @click="confirmCancelForm"
                            >
                                {{ __("common.leave") }}
                            </button>
                        </div>
                    </div>
                    <div v-else key="form" class="sf-screen">
                        <div class="sf-title">
                            {{
                                editingId
                                    ? __("profile.services.form.edit_title")
                                    : __("profile.services.form.new_title")
                            }}
                        </div>
                        <form @submit.prevent="submitForm" class="sf-form">
                            <div class="sf-field">
                                <label class="sf-label">{{
                                    __("profile.services.form.category")
                                }}</label>
                                <AppSelect
                                    v-model="form.category_id"
                                    :options="
                                        (serviceCategories ?? []).map((c) => ({
                                            value: c.id,
                                            label: catName(c),
                                        }))
                                    "
                                    :placeholder="
                                        __('profile.services.form.category_ph')
                                    "
                                    :error="!!form.errors.category_id"
                                    :disabled="!editingId && !!selectedCategory"
                                />
                                <p
                                    v-if="form.errors.category_id"
                                    class="sf-err"
                                >
                                    {{ form.errors.category_id }}
                                </p>
                            </div>

                            <Transition name="sf-name-fade">
                                <div v-if="showNameRu" class="sf-field">
                                    <div class="sf-label-row">
                                        <label class="sf-label">{{
                                            __("profile.services.form.name_ru")
                                        }}</label>
                                        <button
                                            v-if="showNameRu && showNameEn"
                                            type="button"
                                            class="sf-name-remove"
                                            @click="removeNameRu"
                                            aria-label="Remove RU"
                                        >
                                            ×
                                        </button>
                                    </div>
                                    <div class="sf-input-wrap">
                                        <input
                                            v-model="form.name_ru"
                                            class="sf-input"
                                            :class="{
                                                'sf-input--err':
                                                    form.errors.name_ru,
                                            }"
                                            :placeholder="namePlaceholderRu"
                                            maxlength="45"
                                        />
                                        <span
                                            class="sf-char-count"
                                            :class="{
                                                'sf-char-count--warn':
                                                    form.name_ru.length >= 38,
                                            }"
                                        >
                                            {{ form.name_ru.length }}/45
                                        </span>
                                    </div>
                                    <div
                                        v-if="formSuggestionsRu.length"
                                        class="svc-suggestions"
                                    >
                                        <button
                                            v-for="s in formSuggestionsRu"
                                            :key="s"
                                            type="button"
                                            class="svc-chip"
                                            :class="{
                                                'svc-chip--active':
                                                    form.name_ru === s,
                                                'svc-chip--pop':
                                                    animatingChip === s,
                                            }"
                                            @click="selectChip(s, 'ru')"
                                        >
                                            {{ s }}
                                        </button>
                                    </div>
                                    <p
                                        v-if="form.errors.name_ru"
                                        class="sf-err"
                                    >
                                        {{ form.errors.name_ru }}
                                    </p>
                                </div>
                            </Transition>

                            <button
                                v-if="showNameRu && !showNameEn"
                                type="button"
                                class="sf-add-lang"
                                @click="addSecondary"
                            >
                                + {{ __("profile.services.form.add_en") }}
                            </button>
                            <button
                                v-if="showNameEn && !showNameRu"
                                type="button"
                                class="sf-add-lang"
                                @click="addSecondary"
                            >
                                + {{ __("profile.services.form.add_ru") }}
                            </button>

                            <Transition name="sf-name-fade">
                                <div v-if="showNameEn" class="sf-field">
                                    <div class="sf-label-row">
                                        <label class="sf-label">{{
                                            __("profile.services.form.name_en")
                                        }}</label>
                                        <button
                                            v-if="showNameRu && showNameEn"
                                            type="button"
                                            class="sf-name-remove"
                                            @click="removeNameEn"
                                            aria-label="Remove EN"
                                        >
                                            ×
                                        </button>
                                    </div>
                                    <div class="sf-input-wrap">
                                        <input
                                            v-model="form.name_en"
                                            class="sf-input"
                                            :class="{
                                                'sf-input--err':
                                                    form.errors.name_en,
                                            }"
                                            :placeholder="namePlaceholderEn"
                                            maxlength="45"
                                            @input="
                                                form.name_en =
                                                    form.name_en.replace(
                                                        /[\u0400-\u04FF\u0500-\u052F]/g,
                                                        '',
                                                    )
                                            "
                                        />
                                        <span
                                            class="sf-char-count"
                                            :class="{
                                                'sf-char-count--warn':
                                                    form.name_en.length >= 38,
                                            }"
                                        >
                                            {{ form.name_en.length }}/45
                                        </span>
                                    </div>
                                    <div
                                        v-if="formSuggestionsEn.length"
                                        class="svc-suggestions"
                                    >
                                        <button
                                            v-for="s in formSuggestionsEn"
                                            :key="s"
                                            type="button"
                                            class="svc-chip"
                                            :class="{
                                                'svc-chip--active':
                                                    form.name_en === s,
                                                'svc-chip--pop':
                                                    animatingChip === s,
                                            }"
                                            @click="selectChip(s, 'en')"
                                        >
                                            {{ s }}
                                        </button>
                                    </div>
                                    <p
                                        v-if="form.errors.name_en"
                                        class="sf-err"
                                    >
                                        {{ form.errors.name_en }}
                                    </p>
                                </div>
                            </Transition>

                            <div class="sf-row">
                                <div class="sf-field">
                                    <label class="sf-label">{{
                                        __("profile.services.form.price")
                                    }}</label>
                                    <div class="sf-input-wrap">
                                        <input
                                            v-model.number="form.price"
                                            type="number"
                                            min="1"
                                            class="sf-input"
                                            :class="{
                                                'sf-input--err':
                                                    form.errors.price,
                                            }"
                                            placeholder="500"
                                        />
                                        <Transition name="sf-preview-fade">
                                            <span
                                                v-if="pricePreview"
                                                class="sf-preview"
                                                >{{ pricePreview }}</span
                                            >
                                        </Transition>
                                    </div>
                                    <p v-if="form.errors.price" class="sf-err">
                                        {{ form.errors.price }}
                                    </p>
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label">{{
                                        __("profile.services.form.unit")
                                    }}</label>
                                    <AppSelect
                                        v-model="form.time_unit_id"
                                        :options="
                                            (serviceTimeUnits ?? []).map(
                                                (u) => ({
                                                    value: u.id,
                                                    label: localUnitName(u),
                                                }),
                                            )
                                        "
                                        :placeholder="
                                            __('profile.services.form.unit_ph')
                                        "
                                        :error="!!form.errors.time_unit_id"
                                    />
                                    <p
                                        v-if="form.errors.time_unit_id"
                                        class="sf-err"
                                    >
                                        {{ form.errors.time_unit_id }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="!editingId" class="sf-field" style="flex-direction: row; align-items: center; justify-content: space-between; margin-top: 1.5rem; margin-bottom: 0.5rem; background: rgba(255, 255, 255, 0.03); padding: 1rem 1.25rem; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.08);">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <input type="checkbox" id="is_trial" v-model="form.is_trial" class="svc-custom-checkbox" />
                                    <label for="is_trial" style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem; cursor: pointer; user-select: none; margin: 0;">
                                        Сделать <span style="color: var(--color-base-1); font-weight: 600;">1-й заказ 0 ₽</span>
                                    </label>
                                </div>
                                
                                <el-tooltip placement="top" effect="dark" popper-class="newbie-dark-tooltip" :hide-after="0" trigger="click">
                                    <template #content>
                                        Новый клиент сможет заказать эту услугу за 0 ₽.<br>
                                        Один клиент может взять только одну бесплатную услугу.
                                    </template>
                                    <el-icon :size="20" style="color: rgba(255, 255, 255, 0.4); cursor: help; outline: none;" @click.prevent.stop><InfoFilled /></el-icon>
                                </el-tooltip>
                            </div>

                            <div class="sf-actions">
                                <button
                                    type="button"
                                    class="svc-btn-cancel"
                                    @click="tryCloseForm"
                                >
                                    {{ __("common.cancel") }}
                                </button>
                                <button
                                    type="submit"
                                    class="sf-btn-submit"
                                    :disabled="!formValid || form.processing"
                                >
                                    {{
                                        editingId
                                            ? __("common.save")
                                            : __("common.add")
                                    }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </SiteModal>

        <ServiceRemarksModal
            v-if="fixingService"
            :show="!!fixingService"
            :service="fixingService"
            :mode="remarksMode"
            :service-categories="serviceCategories"
            :service-time-units="serviceTimeUnits"
            @close="fixingService = null"
            @submitted="resyncSelectedCategory"
            @fixed="resyncSelectedCategory"
        />
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
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0.04) 25%,
        rgba(255, 255, 255, 0.08) 50%,
        rgba(255, 255, 255, 0.04) 75%
    );
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
    background: linear-gradient(
        to right,
        transparent,
        rgba(255, 178, 239, 0.4),
        transparent
    );
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
        linear-gradient(
                to right,
                transparent,
                color-mix(in srgb, var(--cat-accent) 80%, white),
                rgba(255, 255, 255, 0.2),
                transparent
            )
            0 0 / 100% 1px no-repeat,
        rgba(30, 28, 45, 0.55);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    cursor: pointer;
    text-align: left;
    overflow: hidden;
    transition:
        border-color 0.2s,
        background 0.2s,
        box-shadow 0.2s;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.28);
}

.cat-tile::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(
        to right,
        transparent 5%,
        color-mix(in srgb, var(--cat-accent) 4%, transparent)
    );
    pointer-events: none;
    z-index: 1;
}

.cat-tile:hover {
    background:
        linear-gradient(
                to right,
                transparent,
                color-mix(in srgb, var(--cat-accent) 90%, white),
                rgba(255, 255, 255, 0.28),
                transparent
            )
            0 0 / 100% 1px no-repeat,
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

@media (max-width: 600px) {
    .cat-tile {
        flex-direction: column;
    }

    .cat-tile__img-wrap {
        width: 100%;
        height: 110px;
        order: 0;
        overflow: hidden;
        background: linear-gradient(
            135deg,
            color-mix(in srgb, var(--cat-accent) 12%, transparent),
            transparent
        );
    }

    .cat-tile__img {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        transform: none;
        object-fit: cover;
        object-position: center 30%;
    }

    .cat-tile__img-placeholder {
        height: 100%;
    }

    .cat-tile__body {
        order: 1;
    }
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
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.cat-tile__desc {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.5);
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
    transition:
        color 0.15s,
        transform 0.15s;
}

.cat-tile:hover .cat-tile__arrow {
    color: color-mix(in srgb, var(--cat-accent) 60%, transparent);
    transform: translateX(2px);
}

/* ── Category detail ──────────────────────────────────────── */
.cd-detail {
    padding: 0 1rem;
}

.cd-owner-actions {
    display: none;
}

@media (max-width: 600px) {
    .cd-owner-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    .cd-owner-actions .cd-hero__edit-btn {
        flex: 1;
        justify-content: center;
    }

    .cd-detail {
        padding: 0 0.75rem;
    }

    .cd-hero__title {
        font-size: 1.25rem;
    }

    .cd-section {
        gap: 0.75rem;
    }
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
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 1rem;
    border: 1px solid color-mix(in srgb, var(--cat-accent) 35%, transparent);
    border-top: none;
    border-radius: 6px;
    background: color-mix(in srgb, var(--cat-accent) 8%, transparent);
    color: color-mix(in srgb, var(--cat-accent) 80%, white);
    font-size: 0.92rem;
    font-family: inherit;
    font-weight: 500;
    cursor: pointer;
    flex-shrink: 0;
    white-space: nowrap;
    transition:
        background 0.15s,
        border-color 0.15s,
        color 0.15s;
    box-shadow:
        inset 0 1px 0 color-mix(in srgb, var(--cat-accent) 25%, transparent),
        inset 0 -1px 0 rgba(0, 0, 0, 0.18);
}

.cd-hero__edit-btn:hover {
    background: color-mix(in srgb, var(--cat-accent) 16%, transparent);
    border-color: color-mix(in srgb, var(--cat-accent) 55%, transparent);
    color: var(--cat-accent);
}

.cd-hero__top-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

@media (max-width: 600px) {
    .cd-hero__top-actions {
        display: none;
    }
}

.cd-hero__desc {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.55);
    margin: 0;
    line-height: 1.65;
    white-space: pre-wrap;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--color-base-1) transparent;
}

.cd-hero__desc::-webkit-scrollbar {
    width: 3px;
}

.cd-hero__desc::-webkit-scrollbar-thumb {
    background: var(--color-base-1);
    opacity: 0.28;
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
    border-color: var(--color-base-1);
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
    background: linear-gradient(
        135deg,
        rgba(155, 110, 232, 0.06) 0%,
        rgba(255, 255, 255, 0.02) 100%
    );
    color: rgba(190, 145, 255, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition:
        border-color 0.18s,
        background 0.18s,
        color 0.18s,
        box-shadow 0.18s;
}

.cd-carousel__nav svg {
    transition: transform 0.18s;
}

.cd-carousel__nav:hover:not(:disabled) {
    border-color: rgba(155, 110, 232, 0.45);
    background: linear-gradient(
        135deg,
        rgba(155, 110, 232, 0.13) 0%,
        rgba(255, 255, 255, 0.03) 100%
    );
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
    background: linear-gradient(
        135deg,
        rgba(155, 110, 232, 0.2) 0%,
        rgba(255, 255, 255, 0.04) 100%
    );
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
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0.05) 25%,
        rgba(255, 255, 255, 0.1) 50%,
        rgba(255, 255, 255, 0.05) 75%
    );
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
    background: rgba(255, 178, 239, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 600;
    color: rgba(255, 178, 239, 0.7);
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
    background: linear-gradient(
        to top,
        rgba(0, 0, 0, 0.35) 0%,
        transparent 100%
    );
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
    transition:
        transform 0.22s ease,
        opacity 0.22s ease;
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
    transition:
        border-color 0.15s,
        background 0.15s;
}

.cd-idol-chip:hover {
    border-color: rgba(255, 178, 239, 0.35);
    background: rgba(255, 178, 239, 0.06);
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
    background: rgba(255, 178, 239, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(255, 178, 239, 0.8);
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

@media (max-width: 600px) {
    .svc-list {
        grid-template-columns: 1fr;
    }
}

.svc-card {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding: 1.5rem 1.1rem;
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid
        color-mix(in srgb, var(--cat-accent, white) 28%, transparent);
    border-radius: 6px;
    transition:
        background 0.18s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
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

.svc-card--remarks {
    border-color: rgba(255, 230, 0, 0.25);
    background: rgba(255, 230, 0, 0.02);
}

.svc-card__info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.svc-card__flag-icon {
    display: inline-flex;
    margin-left: 0.25rem;
    cursor: help;
    font-size: 0.85rem;
    vertical-align: middle;
}

.svc-card__name--flagged {
    color: #ffd900 !important;
}



.svc-card__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
}

.svc-card__name-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    flex: 1;
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

/* Badges wrapper */
.svc-card__badges {
    display: flex;
    flex-direction: row;
    gap: 0.25rem;
    flex-wrap: wrap;
    justify-content: flex-end;
    flex-shrink: 0;
    margin: -0.4rem;
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

.svc-pill--remarks {
    background: rgba(255, 204, 0, 0.12);
    color: #ffcc00;
    border: 1px solid rgba(255, 204, 0, 0.25);
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
    border: 1px solid
        color-mix(in srgb, var(--cat-accent, #ffb2ef) 40%, transparent);
    border-radius: 6px;
    background: color-mix(in srgb, var(--cat-accent, #ffb2ef) 10%, transparent);
    color: color-mix(in srgb, var(--cat-accent, #ffb2ef) 85%, white);
    font-family: inherit;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    position: relative;
    transition:
        border-color 0.2s,
        color 0.2s,
        box-shadow 0.2s,
        background 0.2s;
}

.svc-buy-btn::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(
        105deg,
        transparent 30%,
        rgba(255, 255, 255, 0.08) 50%,
        transparent 70%
    );
    transform: translateX(-100%);
    transition: transform 0.5s ease;
}

.svc-buy-btn:hover::before {
    transform: translateX(100%);
}

.svc-buy-btn:hover {
    border-color: color-mix(
        in srgb,
        var(--cat-accent, #ffb2ef) 65%,
        transparent
    );
    background: color-mix(in srgb, var(--cat-accent, #ffb2ef) 18%, transparent);
    color: var(--cat-accent, #ffb2ef);
    box-shadow:
        0 0 20px color-mix(in srgb, var(--cat-accent, #ffb2ef) 20%, transparent),
        inset 0 0 12px
            color-mix(in srgb, var(--cat-accent, #ffb2ef) 8%, transparent);
}

.svc-buy-btn svg {
    transition: transform 0.2s ease;
    flex-shrink: 0;
}

.svc-buy-btn:hover svg {
    transform: translateX(2px);
}

.svc-buy-btn--in-cart {
    background: rgba(100, 200, 130, 0.08);
    border-color: rgba(100, 200, 130, 0.3);
    color: rgba(140, 255, 180, 0.85);
}

.svc-buy-btn--in-cart:hover {
    background: rgba(100, 200, 130, 0.12);
    box-shadow: none;
}

.svc-buy-btn--in-cart svg {
    stroke: rgba(140, 255, 180, 0.85);
}

.svc-buy-btn--in-cart:hover svg {
    transform: none;
}

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
    transition:
        border-color 0.15s,
        background 0.15s;
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
    min-width: 190px;
    background: #0f0f18;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    padding: 0.3rem;
    box-shadow:
        0 8px 24px rgba(0, 0, 0, 0.5),
        0 2px 8px rgba(0, 0, 0, 0.3);
    transform-origin: top right;
}

.svc-menu__item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    padding: 0.6rem 0.75rem;
    border: none;
    border-radius: 4px;
    background: transparent;
    color: rgba(255, 255, 255, 0.75);
    font-family: inherit;
    font-size: 0.95rem;
    text-align: left;
    cursor: pointer;
    transition:
        background 0.12s,
        color 0.12s;
    white-space: nowrap;
}

.svc-menu__item--active {
    color: var(--color-base-1);
    background: rgba(var(--color-base-1-rgb, 255, 178, 239), 0.1);
}

.svc-menu__item--active svg,
.svc-menu__item--active .el-icon {
    color: var(--color-base-1) !important;
}

.svc-menu__item--active:hover {
    background: rgba(var(--color-base-1-rgb, 255, 178, 239), 0.2);
    color: var(--color-base-1);
}

.svc-menu__item svg,
.svc-menu__item .el-icon {
    flex-shrink: 0;
    color: rgba(255, 255, 255, 0.35);
    transition: color 0.12s;
    width: 16px !important;
    height: 16px !important;
    font-size: 16px !important;
}

.svc-menu__item:hover {
    background: rgba(255, 255, 255, 0.07);
    color: rgba(255, 255, 255, 0.92);
}

.svc-menu__item:hover svg,
.svc-menu__item:hover .el-icon {
    color: rgba(255, 255, 255, 0.92);
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
    transition:
        opacity 0.12s ease,
        transform 0.14s cubic-bezier(0.2, 0, 0.2, 1.4);
}

.svc-menu-pop-leave-active {
    transition:
        opacity 0.1s ease,
        transform 0.1s ease;
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
    transition:
        border-color 0.15s,
        color 0.15s;
}

.svc-btn-cancel:hover {
    border-color: rgba(255, 255, 255, 0.25);
    color: rgba(255, 255, 255, 0.7);
}

.svc-btn-submit {
    padding: 0.5rem 1.2rem;
    border: 1px solid rgba(255, 178, 239, 0.45);
    border-radius: 3px;
    background: linear-gradient(
        135deg,
        rgba(255, 178, 239, 0.3) 0%,
        rgba(124, 45, 126, 0.2) 100%
    );
    color: rgba(255, 255, 255, 0.9);
    font-family: inherit;
    font-size: 0.82rem;
    cursor: pointer;
    transition:
        box-shadow 0.15s,
        border-color 0.15s;
}

.svc-btn-submit:hover:not(:disabled) {
    border-color: rgba(255, 178, 239, 0.7);
    box-shadow: 0 0 12px rgba(255, 178, 239, 0.25);
}

.svc-btn-submit--saving {
    opacity: 0.7;
    cursor: default;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.svc-btn-spinner {
    animation: spin 0.75s linear infinite;
    flex-shrink: 0;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
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
    transition:
        box-shadow 0.15s,
        border-color 0.15s;
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
    border-color: rgba(255, 178, 239, 0.45);
}

.svc-input--err {
    border-color: rgba(239, 68, 68, 0.5);
}

.svc-err {
    font-size: 0.85rem;
    color: var(--color-danger);
    margin-top: 0.5rem;
}

.svc-trial-badge {
    display: inline-block;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 2px 6px;
    border-radius: 4px;
    margin-right: 6px;
    vertical-align: middle;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
}

.svc-card__price-block--trial {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
}
.svc-card__price-main {
    display: flex;
    align-items: baseline;
    color: #10b981;
}
.svc-card__old-price {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.4);
    text-decoration: line-through;
}

/* ── Category drill-in (list → detail) ───────────────────── */
.drill-in-enter-active,
.drill-in-leave-active {
    transition:
        transform 0.24s cubic-bezier(0.25, 0.46, 0.45, 0.94),
        opacity 0.2s ease;
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
    transition:
        transform 0.24s cubic-bezier(0.25, 0.46, 0.45, 0.94),
        opacity 0.2s ease;
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
    transition:
        opacity 0.22s ease,
        transform 0.22s ease;
}

.svc-item-leave-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
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
    border-radius: 6px;
    background: transparent;
    color: rgba(200, 70, 126, 0.75);
    font-family: inherit;
    font-size: 0.92rem;
    cursor: pointer;
    transition:
        border-color 0.15s,
        color 0.15s,
        background 0.15s;
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
    transition:
        opacity 0.2s ease,
        transform 0.22s ease;
}

.sf-screen-leave-active {
    transition:
        opacity 0.15s ease,
        transform 0.18s ease;
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

.sf-name-fade-enter-active,
.sf-name-fade-leave-active {
    transition: opacity 0.18s ease;
}

.sf-name-fade-enter-from,
.sf-name-fade-leave-to {
    opacity: 0;
}

.sf-label-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.3rem;
}

.sf-label-row .sf-label {
    margin-bottom: 0;
}

.sf-name-remove {
    background: none;
    border: none;
    padding: 0 0.1rem;
    color: rgba(255, 255, 255, 0.2);
    cursor: pointer;
    font-size: 1.35rem;
    line-height: 1;
    transition: color 0.15s;
    flex-shrink: 0;
}

.sf-name-remove:hover {
    color: rgba(239, 68, 68, 0.7);
}

.sf-add-lang {
    display: block;
    width: 100%;
    padding: 0.55rem 1rem;
    margin-top: 0.1rem;
    background: color-mix(in srgb, var(--cat-accent, #ffb2ef) 8%, transparent);
    border: 1px dashed
        color-mix(in srgb, var(--cat-accent, #ffb2ef) 35%, transparent);
    border-radius: 8px;
    font-size: 0.9rem;
    font-family: inherit;
    color: color-mix(in srgb, var(--cat-accent, #ffb2ef) 65%, white);
    cursor: pointer;
    transition:
        background 0.15s,
        border-color 0.15s,
        color 0.15s;
    text-align: center;
}

.sf-add-lang:hover {
    background: color-mix(in srgb, var(--cat-accent, #ffb2ef) 15%, transparent);
    border-color: color-mix(
        in srgb,
        var(--cat-accent, #ffb2ef) 60%,
        transparent
    );
    color: color-mix(in srgb, var(--cat-accent, #ffb2ef) 90%, white);
}

.sf-label {
    font-size: 0.78rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--cat-accent, #ffb2ef) 80%, white);
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
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
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
    background: linear-gradient(
        135deg,
        rgba(200, 70, 126, 0.25),
        rgba(200, 70, 126, 0.1)
    );
    color: #fff;
    font-family: inherit;
    font-size: 0.88rem;
    cursor: pointer;
    transition:
        background 0.2s ease,
        box-shadow 0.2s ease,
        opacity 0.2s ease;
}

.sf-btn-submit:hover:not(:disabled) {
    background: linear-gradient(
        135deg,
        rgba(200, 70, 126, 0.4),
        rgba(200, 70, 126, 0.2)
    );
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
    background: linear-gradient(
        135deg,
        rgba(239, 68, 68, 0.22),
        rgba(239, 68, 68, 0.08)
    );
    color: rgba(239, 68, 68, 0.95);
    font-family: inherit;
    font-size: 0.88rem;
    cursor: pointer;
    transition:
        background 0.2s ease,
        box-shadow 0.2s ease,
        border-color 0.2s ease;
}

.sf-btn-danger:hover {
    background: linear-gradient(
        135deg,
        rgba(239, 68, 68, 0.35),
        rgba(239, 68, 68, 0.15)
    );
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
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    background: transparent;
    color: rgba(255, 255, 255, 0.5);
    font-family: inherit;
    font-size: 0.88rem;
    cursor: pointer;
    transition:
        color 0.18s,
        border-color 0.18s;
}

.sf-btn-cancel:hover {
    color: rgba(255, 255, 255, 0.8);
    border-color: rgba(255, 255, 255, 0.2);
}

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

.block-err-enter-active,
.block-err-leave-active {
    transition:
        opacity 0.25s,
        transform 0.25s;
}

.block-err-enter-from,
.block-err-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>

<style>
.cd-back {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(255, 178, 239, 0.08);
    border: 1px solid rgba(255, 178, 239, 0.28);
    border-radius: 5px;
    color: rgba(180, 180, 255, 0.85);
    font-family: inherit;
    font-size: 0.88rem;
    cursor: pointer;
    padding: 0.35rem 0.75rem;
    transition:
        background 0.15s,
        border-color 0.15s,
        color 0.15s;
}

.cd-back:hover {
    background: rgba(255, 178, 239, 0.16);
    border-color: rgba(255, 178, 239, 0.5);
    color: var(--color-base-1);
}

.svc-custom-checkbox {
    appearance: none;
    -webkit-appearance: none;
    width: 22px;
    height: 22px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.05);
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
    flex-shrink: 0;
    margin: 0;
    outline: none;
}

.svc-custom-checkbox:hover {
    border-color: var(--color-base-1);
    background: rgba(255, 255, 255, 0.1);
}

.svc-custom-checkbox:checked {
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-color: var(--color-base-1);
    box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.5);
}

.svc-custom-checkbox:checked::after {
    content: '';
    position: absolute;
    left: 6px;
    top: 2px;
    width: 6px;
    height: 11px;
    border: solid var(--color-base-1);
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}
</style>
