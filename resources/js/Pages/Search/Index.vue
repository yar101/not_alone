<script setup>
import { ref, computed, watch } from "vue";
import { router, usePage, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";
import IdolBadge from "@/Components/IdolBadge.vue";
import SiteModal from "@/Components/Site/SiteModal.vue";
import SearchFilters from "@/Components/Search/SearchFilters.vue";
import AppSelect from "@/Components/AppSelect.vue";
import {
    ArrowLeft,
    ArrowRight,
    SortUp,
    SortDown,
} from "@element-plus/icons-vue";
import { useTranslations } from "@/composables/useTranslations";

const { __, transChoice, locale } = useTranslations();

function localName(item) {
    return locale.value?.current === "en" && item?.name_en
        ? item.name_en
        : (item?.name_ru ?? item?.name ?? "");
}

const props = defineProps({
    users: Object,
    traits: Array,
    interestCategories: Array,
    serviceCategories: Array,
    filters: Object,
});

const LANGUAGES = [
    { code: "ru", label: "Русский" },
    { code: "en", label: "English" },
    { code: "de", label: "Deutsch" },
    { code: "fr", label: "Français" },
    { code: "es", label: "Español" },
    { code: "zh", label: "中文" },
    { code: "ja", label: "日本語" },
    { code: "ko", label: "한국어" },
    { code: "uk", label: "Українська" },
    { code: "pl", label: "Polski" },
    { code: "tr", label: "Türkçe" },
    { code: "ar", label: "العربية" },
];

const TIMEZONES = [
    "Europe/Moscow",
    "Europe/Kiev",
    "Europe/Minsk",
    "Europe/London",
    "Europe/Berlin",
    "Europe/Paris",
    "Europe/Amsterdam",
    "Europe/Warsaw",
    "Asia/Almaty",
    "Asia/Tashkent",
    "Asia/Yekaterinburg",
    "Asia/Novosibirsk",
    "Asia/Krasnoyarsk",
    "Asia/Irkutsk",
    "Asia/Yakutsk",
    "Asia/Vladivostok",
    "America/New_York",
    "America/Chicago",
    "America/Denver",
    "America/Los_Angeles",
    "Asia/Tokyo",
    "Asia/Seoul",
    "Asia/Shanghai",
    "Asia/Dubai",
    "Asia/Kolkata",
];

// ── Reactive filters ────────────────────────────────────────
const f = ref({
    name: props.filters.name ?? "",
    gender: props.filters.gender ?? "",
    age_from: props.filters.age_from ?? "",
    age_to: props.filters.age_to ?? "",
    is_idol: props.filters.is_idol ?? "",
    rating_from: props.filters.rating_from ?? "",
    rating_to: props.filters.rating_to ?? "",
    traits: props.filters.traits
        ? [].concat(props.filters.traits).map(Number)
        : [],
    interests: props.filters.interests
        ? [].concat(props.filters.interests).map(Number)
        : [],
    languages: props.filters.languages
        ? [].concat(props.filters.languages)
        : [],
    timezone: props.filters.timezone ?? "",
    service_categories: props.filters.service_categories
        ? [].concat(props.filters.service_categories).map(Number)
        : [],
    sort_by: props.filters.sort_by ?? "rating",
    sort_dir: props.filters.sort_dir ?? "desc",
});

watch(
    () => props.filters,
    (val) => {
        f.value.name = val.name ?? "";
        f.value.gender = val.gender ?? "";
        f.value.age_from = val.age_from ?? "";
        f.value.age_to = val.age_to ?? "";
        f.value.is_idol = val.is_idol ?? "";
        f.value.rating_from = val.rating_from ?? "";
        f.value.rating_to = val.rating_to ?? "";
        f.value.traits = val.traits ? [].concat(val.traits).map(Number) : [];
        f.value.interests = val.interests
            ? [].concat(val.interests).map(Number)
            : [];
        f.value.languages = val.languages ? [].concat(val.languages) : [];
        f.value.timezone = val.timezone ?? "";
        f.value.service_categories = val.service_categories
            ? [].concat(val.service_categories).map(Number)
            : [];
        f.value.sort_by = val.sort_by ?? "rating";
        f.value.sort_dir = val.sort_dir ?? "desc";

        appliedFilters.value = JSON.parse(JSON.stringify(f.value));
    },
    { deep: true },
);

// Snapshot of last applied state (excluding sort_*)
const appliedFilters = ref(JSON.parse(JSON.stringify(f.value)));

const DIRTY_KEYS = [
    "name",
    "gender",
    "age_from",
    "age_to",
    "is_idol",
    "rating_from",
    "rating_to",
    "traits",
    "interests",
    "languages",
    "timezone",
    "service_categories",
];

const isDirty = computed(() =>
    DIRTY_KEYS.some(
        (k) =>
            JSON.stringify(f.value[k]) !==
            JSON.stringify(appliedFilters.value[k]),
    ),
);

function apply() {
    const params = {};
    Object.entries(f.value).forEach(([k, v]) => {
        if (v !== "" && v !== null && !(Array.isArray(v) && v.length === 0)) {
            params[k] = v;
        }
    });
    router.get(route("users.search"), params, {
        preserveState: true,
        replace: true,
    });
}

function applyFilters() {
    appliedFilters.value = JSON.parse(JSON.stringify(f.value));
    apply();
}

// Sort watchers — apply immediately, don't affect isDirty
watch(() => f.value.sort_by, apply);
watch(() => f.value.sort_dir, apply);

function resetFilters() {
    f.value = {
        name: "",
        gender: "",
        age_from: "",
        age_to: "",
        is_idol: "",
        rating_from: "",
        rating_to: "",
        traits: [],
        interests: [],
        languages: [],
        timezone: "",
        service_categories: [],
        sort_by: "rating",
        sort_dir: "desc",
    };
    appliedFilters.value = JSON.parse(JSON.stringify(f.value));
    router.get(
        route("users.search"),
        {},
        { preserveState: false, replace: true },
    );
}

const mobileFiltersOpen = ref(false);

function applyAndClose() {
    mobileFiltersOpen.value = false;
    setTimeout(() => {
        applyFilters();
    }, 50);
}

function resetAndClose() {
    mobileFiltersOpen.value = false;
    setTimeout(() => {
        resetFilters();
    }, 50);
}

function toggleSortDir() {
    f.value.sort_dir = f.value.sort_dir === "desc" ? "asc" : "desc";
}

const sortOptions = computed(() => [
    { value: "rating", label: __("search.sort.rating") },
    { value: "created_at", label: __("search.sort.date") },
]);

// ── Active chips ─────────────────────────────────────────────
const activeChips = computed(() => {
    const chips = [];
    if (f.value.name)
        chips.push({
            label: __("search.active.name", { value: f.value.name }),
            key: "name",
        });
    if (f.value.gender)
        chips.push({
            label: __("search.active.gender", {
                value:
                    f.value.gender === "male"
                        ? __("gender.male")
                        : __("gender.female"),
            }),
            key: "gender",
        });
    if (f.value.age_from || f.value.age_to)
        chips.push({
            label: __("search.active.age", {
                from: f.value.age_from || "…",
                to: f.value.age_to || "…",
            }),
            key: "age",
        });
    if (f.value.is_idol !== "")
        chips.push({
            label: __("search.active.idol", {
                value:
                    f.value.is_idol === "1"
                        ? __("common.yes")
                        : __("common.no"),
            }),
            key: "is_idol",
        });
    if (f.value.rating_from || f.value.rating_to)
        chips.push({
            label: __("search.active.rating", {
                from: f.value.rating_from || "…",
                to: f.value.rating_to || "…",
            }),
            key: "rating",
        });
    f.value.traits.forEach((id) => {
        const t = props.traits.find((x) => x.id === id);
        if (t) chips.push({ label: localName(t), key: "traits", value: id });
    });
    f.value.interests.forEach((id) => {
        for (const cat of props.interestCategories) {
            const i = cat.interests.find((x) => x.id === id);
            if (i) {
                chips.push({
                    label: localName(i),
                    key: "interests",
                    value: id,
                });
                break;
            }
        }
    });
    f.value.languages.forEach((code) => {
        const l = LANGUAGES.find((x) => x.code === code);
        if (l) chips.push({ label: l.label, key: "languages", value: code });
    });
    if (f.value.timezone)
        chips.push({ label: f.value.timezone, key: "timezone" });
    f.value.service_categories.forEach((id) => {
        const c = props.serviceCategories.find((x) => x.id === id);
        if (c)
            chips.push({
                label: localName(c),
                key: "service_categories",
                value: id,
            });
    });
    return chips;
});

function resetChip(chip) {
    if (chip.key === "name") f.value.name = "";
    else if (chip.key === "gender") f.value.gender = "";
    else if (chip.key === "age") {
        f.value.age_from = "";
        f.value.age_to = "";
    } else if (chip.key === "is_idol") f.value.is_idol = "";
    else if (chip.key === "rating") {
        f.value.rating_from = "";
        f.value.rating_to = "";
    } else if (chip.key === "timezone") f.value.timezone = "";
    else if (
        ["traits", "interests", "languages", "service_categories"].includes(
            chip.key,
        )
    )
        f.value[chip.key] = f.value[chip.key].filter((v) => v !== chip.value);
}

// ── Windowed pagination ──────────────────────────────────────
const visiblePageLinks = computed(() => {
    const pages = props.users.links.slice(1, -1);
    if (pages.length <= 7)
        return pages.map((p) => ({ ...p, isEllipsis: false }));

    const currentIdx = pages.findIndex((p) => p.active);
    const delta = 1;
    const result = [];

    pages.forEach((page, i) => {
        const keep =
            i === 0 ||
            i === pages.length - 1 ||
            Math.abs(i - currentIdx) <= delta;
        if (keep) {
            result.push({ ...page, isEllipsis: false });
        } else if (result.length && !result[result.length - 1].isEllipsis) {
            result.push({
                label: "…",
                url: null,
                active: false,
                isEllipsis: true,
            });
        }
    });

    return result;
});

// ── Helpers ─────────────────────────────────────────────────
function calcAge(birthDate) {
    if (!birthDate) return null;
    const diff = Date.now() - new Date(birthDate).getTime();
    return Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
}

function genderLabel(g) {
    if (g === "male") return __("gender.abbr.male");
    if (g === "female") return __("gender.abbr.female");
    return "";
}

function avatarUrl(user) {
    if (!user.avatar_path) return null;
    return "/storage/" + user.avatar_path;
}

function initial(name) {
    return name?.charAt(0).toUpperCase() ?? "?";
}
</script>

<template>
    <Head :title="__('search.title')" />
    <AppLayout>
        <div class="search-page">
            <!-- Results -->
            <div class="search-results">
                <!-- Sort bar -->
                <div class="sort-bar">
                    <div class="sort-controls">
                        <span class="sort-label">{{ __("search.sort_by") }}</span>
                        <AppSelect
                            v-model="f.sort_by"
                            :options="sortOptions"
                            class="sort-select"
                        />
                        <button
                            @click="toggleSortDir"
                            class="sort-dir-btn"
                            :title="
                                f.sort_dir === 'desc'
                                    ? __('search.sort.desc')
                                    : __('search.sort.asc')
                            "
                        >
                            <el-icon
                                ><SortDown
                                    v-if="f.sort_dir === 'desc'" /><SortUp
                                    v-else
                            /></el-icon>
                        </button>
                    </div>
                    <div class="sort-bar-right">
                        <div class="found-count">
                            {{ __("search.found", { count: users.total }) }}
                        </div>
                        <button
                            class="mobile-filters-toggle"
                            @click="mobileFiltersOpen = !mobileFiltersOpen"
                        >
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="4" y1="6" x2="20" y2="6" />
                                <line x1="8" y1="12" x2="16" y2="12" />
                                <line x1="10" y1="18" x2="14" y2="18" />
                            </svg>
                            {{ __("search.filters.title") }}
                            <span v-if="isDirty" class="mobile-filters-dot"></span>
                        </button>
                    </div>
                </div>

                <!-- Cards -->
                <div class="results-body">
                    <div v-if="users.data.length > 0" class="user-grid">
                        <Link
                            v-for="user in users.data"
                            :key="user.id"
                            :href="
                                route('profile.show', { user: user.id }) +
                                '#about'
                            "
                            class="user-card"
                        >
                            <div class="card-avatar-wrap">
                                <div class="card-avatar">
                                    <img
                                        v-if="avatarUrl(user)"
                                        :src="avatarUrl(user)"
                                        :alt="__('common.avatar')"
                                        class="card-avatar__img"
                                    />
                                    <span
                                        v-else
                                        class="card-avatar__initials"
                                        >{{ initial(user.name) }}</span
                                    >
                                </div>
                                <div class="card-avatar-badges">
                                    <IdolBadge
                                        v-if="user.is_idol"
                                        class="card-idol-badge"
                                    />
                                    <span v-if="user.rating" class="card-rating"
                                        >★ {{ user.rating }}</span
                                    >
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="card-name-row">
                                    <div class="card-name">{{ user.name }}</div>
                                </div>
                                <div class="card-badges">
                                    <span
                                        v-if="user.gender"
                                        class="card-badge"
                                        :class="
                                            user.gender === 'female'
                                                ? 'card-badge--female'
                                                : 'card-badge--male'
                                        "
                                        >{{
                                            user.gender === "female"
                                                ? "\u2640\uFE0F"
                                                : "\u2642\uFE0F"
                                        }}</span
                                    >
                                    <span
                                        v-if="calcAge(user.birth_date)"
                                        class="card-badge card-badge--age"
                                        >{{ calcAge(user.birth_date) }}
                                        {{
                                            transChoice(
                                                "search.age.years",
                                                calcAge(user.birth_date),
                                            )
                                        }}</span
                                    >
                                </div>
                            </div>
                        </Link>
                    </div>

                    <div v-else class="no-results">
                        <p>{{ __("search.empty") }}</p>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="users.last_page > 1" class="pagination">
                    <!-- Prev -->
                    <Link
                        v-if="users.links[0]?.url"
                        :href="users.links[0].url"
                        class="page-btn"
                    >
                        <el-icon><ArrowLeft /></el-icon>
                    </Link>
                    <span v-else class="page-btn page-btn--disabled">
                        <el-icon><ArrowLeft /></el-icon>
                    </span>

                    <!-- Page numbers -->
                    <template v-for="(link, i) in visiblePageLinks" :key="i">
                        <span v-if="link.isEllipsis" class="page-ellipsis"
                            >…</span
                        >
                        <Link
                            v-else-if="link.url && !link.active"
                            :href="link.url"
                            class="page-btn"
                            >{{ link.label }}</Link
                        >
                        <span
                            v-else
                            :class="[
                                'page-btn',
                                {
                                    'page-btn--active': link.active,
                                    'page-btn--disabled': !link.url,
                                },
                            ]"
                            >{{ link.label }}</span
                        >
                    </template>

                    <!-- Next -->
                    <Link
                        v-if="users.links[users.links.length - 1]?.url"
                        :href="users.links[users.links.length - 1].url"
                        class="page-btn"
                    >
                        <el-icon><ArrowRight /></el-icon>
                    </Link>
                    <span v-else class="page-btn page-btn--disabled">
                        <el-icon><ArrowRight /></el-icon>
                    </span>
                </div>
            </div>

            <!-- Sidebar (right) -->
            <aside
                class="search-sidebar"
                :class="{ 'search-sidebar--mobile-open': mobileFiltersOpen }"
            >
                <div class="sidebar-inner">
                    <h2 class="sidebar-title">
                        {{ __("search.filters.title") }}
                    </h2>

                    <SearchFilters
                        v-model="f"
                        :traits="traits"
                        :interest-categories="interestCategories"
                        :service-categories="serviceCategories"
                        :languages="LANGUAGES"
                        :timezones="TIMEZONES"
                        :active-chips="activeChips"
                        @reset-chip="resetChip"
                    />

                    <button @click="resetFilters" class="reset-btn">
                        {{ __("search.filters.reset") }}
                    </button>
                </div>

                <Transition name="slide-up">
                    <div v-if="isDirty" class="sidebar-footer">
                        <button class="apply-btn" @click="applyFilters">
                            {{ __("search.filters.apply") }}
                        </button>
                    </div>
                </Transition>
            </aside>
        </div>
    </AppLayout>

    <!-- Mobile filters modal -->
    <SiteModal
        :show="mobileFiltersOpen"
        variant="pink"
        @close="mobileFiltersOpen = false"
    >
        <div class="mf-wrap">
            <h2 class="mf-title">{{ __("search.filters.title") }}</h2>

            <SearchFilters
                v-model="f"
                :traits="traits"
                :interest-categories="interestCategories"
                :service-categories="serviceCategories"
                :languages="LANGUAGES"
                :timezones="TIMEZONES"
                :active-chips="activeChips"
                @reset-chip="resetChip"
            />

            <!-- Сбросить / Применить -->
            <div class="mf-actions">
                <button
                    type="button"
                    @click.stop="resetAndClose"
                    class="reset-btn"
                >
                    {{ __("search.filters.reset") }}
                </button>
                <button
                    type="button"
                    class="apply-btn"
                    @click.stop="applyAndClose"
                >
                    {{ __("search.filters.apply") }}
                </button>
            </div>
        </div>
    </SiteModal>
</template>

<style scoped>
/* ── Page layout ─────────────────────────────────────────── */
.search-page {
    display: flex;
    gap: 0;
    height: calc(100vh - 60px);
    overflow: hidden;
    align-items: flex-start;
}

/* ── Sidebar ─────────────────────────────────────────────── */
.search-sidebar {
    width: 25%;
    max-width: 300px;
    min-width: 220px;
    flex-shrink: 0;
    border-left: 1px solid rgba(255, 178, 239, 0.12);
    height: 100%;
    background: rgba(10, 10, 20, 0.6);
    display: flex;
    flex-direction: column;
}

.sidebar-inner {
    scrollbar-width: thin;
    scrollbar-color: rgba(224, 85, 143, 0.6) rgba(255, 255, 255, 0.04);
}

.sidebar-inner::-webkit-scrollbar {
    width: 6px;
}

.sidebar-inner::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.04);
    border-radius: 3px;
}

.sidebar-inner::-webkit-scrollbar-thumb {
    background: rgba(224, 85, 143, 0.5);
    border-radius: 3px;
}

.sidebar-inner::-webkit-scrollbar-thumb:hover {
    background: rgba(224, 85, 143, 0.8);
}

.sidebar-inner {
    flex: 1;
    overflow-y: auto;
    padding: 1.75rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
}

.sidebar-footer {
    flex-shrink: 0;
    padding: 0.75rem 1.5rem;
    border-top: 1px solid rgba(255, 178, 239, 0.15);
    background: rgba(10, 10, 20, 0.95);
}

.apply-btn {
    width: 100%;
    padding: 0.7rem;
    border-radius: 4px;
    border: 1px solid rgba(255, 178, 239, 0.5);
    background: rgba(255, 178, 239, 0.15);
    color: var(--color-base-1);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    font-family: inherit;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.apply-btn:hover {
    background: rgba(255, 178, 239, 0.28);
    border-color: rgba(255, 178, 239, 0.8);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.18),
        0 4px 12px rgba(0, 0, 0, 0.2);
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition:
        opacity 0.2s,
        transform 0.2s;
}

.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translateY(8px);
}

.sidebar-title {
    font-size: 1.05rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.7);
    margin: 0;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

/* ── Reset button ────────────────────────────────────────── */
.reset-btn {
    padding: 0.55rem 1.2rem;
    border-radius: 4px;
    border: 1px solid rgba(255, 178, 239, 0.3);
    background: transparent;
    color: rgba(255, 178, 239, 0.7);
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.15s;
    font-family: inherit;
    align-self: flex-start;
}

.reset-btn:hover {
    border-color: rgba(224, 85, 143, 0.5);
    color: #e0558f;
}

/* ── Results panel ───────────────────────────────────────── */
.results-body {
    scrollbar-width: thin;
    scrollbar-color: rgba(224, 85, 143, 0.6) rgba(255, 255, 255, 0.04);
}

.results-body::-webkit-scrollbar {
    width: 6px;
}

.results-body::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.04);
    border-radius: 3px;
}

.results-body::-webkit-scrollbar-thumb {
    background: rgba(224, 85, 143, 0.5);
    border-radius: 3px;
}

.results-body::-webkit-scrollbar-thumb:hover {
    background: rgba(224, 85, 143, 0.8);
}

.search-results {
    flex: 1;
    min-width: 0;
    height: 100%;
    overflow: hidden;
    padding: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.results-body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    justify-content: start;
}

/* ── Sort bar ────────────────────────────────────────────── */
.sort-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem 0;
}

.sort-controls {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.sort-label {
    font-size: 0.88rem;
    color: rgba(255, 255, 255, 0.4);
}

.sort-select {
    width: 180px;
}

.sort-dir-btn {
    padding: 0.58rem 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
}

.sort-dir-btn:hover {
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.08);
}

.sort-bar-right {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.found-count {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.35);
}

/* ── User grid ───────────────────────────────────────────── */
/* Сайдбар ~540px, учитываем оставшееся пространство */
.user-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1.25rem;
    padding: 1rem;
}

/* ~1600px и меньше → 4 колонки */
@media (max-width: 1600px) {
    .user-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

/* ~1200px и меньше → 3 колонки */
@media (max-width: 1200px) {
    .user-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* ~900px и меньше → 2 колонки */
@media (max-width: 900px) {
    .user-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* ~500px и меньше → 2 колонки (оставляем компактными) */
@media (max-width: 500px) {
    .user-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }
}

.user-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    background: linear-gradient(
        180deg,
        rgba(255, 255, 255, 0.03) 0%,
        rgba(255, 255, 255, 0.01) 100%
    );
    border: 1px solid rgba(255, 178, 239, 0.08);
    border-radius: 12px;
    padding: 1.25rem 1rem;
    text-decoration: none;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    min-width: 0;
    overflow: hidden;
}

.user-card:hover {
    border-color: rgba(255, 178, 239, 0.25);
    box-shadow:
        0 6px 16px -4px rgba(0, 0, 0, 0.4),
        0 0 10px rgba(255, 178, 239, 0.05);
}

.card-avatar-wrap {
    position: relative;
    align-self: center;
    margin-bottom: 0.25rem;
}

.card-avatar {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    overflow: hidden;
    background: rgba(255, 178, 239, 0.12);
    border: 2px solid rgba(255, 178, 239, 0.3);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.4s ease;
}

.user-card:hover .card-avatar {
    border-color: rgba(255, 178, 239, 0.5);
}

.card-avatar__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.card-avatar__initials {
    font-size: 2.5rem;
    font-weight: 600;
    color: var(--color-base-1);
    text-shadow: 0 0 20px rgba(255, 178, 239, 0.4);
}

.card-avatar-badges {
    position: absolute;
    bottom: -4px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
}

.card-rating {
    background: rgba(20, 15, 30, 0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 178, 239, 0.4);
    border-radius: 4px;
    padding: 0.2rem 0.6rem;
    font-size: 0.75rem;
    color: var(--color-base-1);
    font-weight: 700;
    white-space: nowrap;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    gap: 0.2rem;
    line-height: 1;
}

:deep(.card-idol-badge) {
    background: rgba(20, 15, 30, 0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 178, 239, 0.4);
    border-radius: 4px;
    padding: 0.2rem 0.6rem;
    font-size: 0.75rem;
    color: var(--color-base-1);
    font-weight: 700;
    white-space: nowrap;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    line-height: 1;
}

.card-body {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    min-width: 0;
    align-items: center;
    text-align: center;
}

.card-name-row {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 0;
    width: 100%;
}

.card-name {
    font-size: 1.15rem;
    font-weight: 600;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
    letter-spacing: 0.01em;
}

.card-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    justify-content: center;
    margin-top: 0.25rem;
}

.card-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 500;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.03);
    color: rgba(255, 255, 255, 0.5);
    white-space: nowrap;
    transition: all 0.2s;
}

.user-card:hover .card-badge {
    border-color: rgba(255, 255, 255, 0.15);
    color: rgba(255, 255, 255, 0.7);
}

.card-badge--female {
    border-color: rgba(255, 178, 239, 0.25);
    background: rgba(255, 178, 239, 0.05);
    color: rgba(255, 178, 239, 0.8);
}

.card-badge--male {
    border-color: rgba(100, 210, 255, 0.25);
    background: rgba(100, 210, 255, 0.05);
    color: rgba(100, 210, 255, 0.8);
}

.card-badge--age {
    background: rgba(255, 255, 255, 0.02);
}

/* ── No results ──────────────────────────────────────────── */
.no-results {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4rem 0;
    color: rgba(255, 255, 255, 0.3);
    font-size: 1rem;
}

/* ── Pagination ──────────────────────────────────────────── */
.pagination {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    justify-content: center;
    flex-wrap: nowrap;
    padding-top: 0.5rem;
    flex-shrink: 0;
}

.pagination::before,
.pagination::after {
    content: "";
    flex: 1;
    height: 1.5px;
    background: linear-gradient(
        to var(--dir),
        rgba(140, 100, 230, 0.7),
        transparent
    );
    min-width: 0;
}

@media (max-width: 600px) {
    .pagination {
        gap: 0.2rem;
    }
    .page-btn {
        min-width: 34px;
        height: 34px;
        font-size: 0.82rem;
        padding: 0 0.35rem;
    }
    .page-ellipsis {
        min-width: 20px;
    }
}

.pagination::before {
    --dir: left;
}

.pagination::after {
    --dir: right;
}

.page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 42px;
    height: 42px;
    padding: 0 0.8rem;
    border-radius: 5px;
    border: 1px solid rgba(255, 178, 239, 0.2);
    background: transparent;
    color: rgba(255, 255, 255, 0.5);
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.15s;
}

.page-btn:not(.page-btn--disabled):not(.page-btn--active):hover {
    border-color: rgba(255, 178, 239, 0.5);
    color: rgba(255, 255, 255, 0.9);
    background: rgba(255, 178, 239, 0.08);
}

.page-btn--active {
    border-color: rgba(255, 178, 239, 0.6);
    background: rgba(255, 178, 239, 0.2);
    color: #ffb2ef;
}

.page-btn--disabled {
    opacity: 0.3;
    cursor: default;
}

.page-ellipsis {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 42px;
    height: 42px;
    color: rgba(255, 255, 255, 0.3);
    font-size: 1rem;
    letter-spacing: 0.05em;
}

/* ── Mobile ──────────────────────────────────────────────── */
.mobile-filters-toggle {
    display: none;
    align-items: center;
    gap: 0.4rem;
    position: relative;
    padding: 0.35rem 0.7rem;
    border: 1px solid rgba(255, 178, 239, 0.3);
    border-radius: 6px;
    background: rgba(255, 178, 239, 0.08);
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.82rem;
    font-family: inherit;
    cursor: pointer;
    transition:
        border-color 0.15s,
        background 0.15s;
}
.mobile-filters-toggle:hover {
    border-color: rgba(255, 178, 239, 0.55);
    background: rgba(255, 178, 239, 0.14);
}
.mobile-filters-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #ffb2ef;
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .mobile-filters-toggle {
        display: flex;
    }

    .search-page {
        flex-direction: column;
        height: auto;
        overflow: visible;
    }

    .search-sidebar {
        display: none;
    }

    .search-results {
        height: auto;
        overflow: visible;
    }

    .results-body {
        overflow-y: visible;
        justify-content: flex-start;
    }

    .user-grid {
        grid-template-columns: 1fr;
    }

    /* ── Mobile Sort Bar Redesign ── */
    .sort-bar {
        flex-direction: column-reverse;
        align-items: stretch;
        gap: 1rem;
        padding: 1rem 1rem 0;
    }

    .sort-bar-right {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
    }

    .sort-controls {
        width: 100%;
        flex-direction: row;
        gap: 0.35rem;
    }

    .sort-label {
        display: none;
    }

    .sort-select {
        flex: 1;
        width: auto;
        padding: 0.5rem 0.4rem !important;
        font-size: 0.82rem !important;
    }

    .sort-dir-btn {
        flex: 0 0 44px;
        padding: 0.5rem 0;
        height: 38px;
    }

    .pagination {
        gap: 0.25rem;
        justify-content: center;
        flex-wrap: wrap;
    }
    .page-btn {
        min-width: 32px;
        height: 32px;
        font-size: 0.82rem;
        padding: 0 0.2rem;
    }
    .page-btn .el-icon {
        font-size: 1.1rem;
    }
    .page-btn:first-child,
    .page-btn:last-child {
        min-width: 44px;
    }
    .page-ellipsis {
        min-width: 24px;
        height: 32px;
    }
}

/* ── Mobile filters modal content ────────────────────────── */
.mf-wrap {
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
}

.mf-title {
    font-size: 1.05rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.7);
    margin: 0;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.mf-actions {
    display: flex;
    gap: 0.75rem;
    padding-top: 0.5rem;
}

.mf-actions .apply-btn {
    flex: 1;
}

.mf-actions .reset-btn {
    align-self: auto;
}
</style>
