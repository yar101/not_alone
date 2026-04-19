<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import IdolBadge from '@/Components/IdolBadge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { __, transChoice, locale } = useTranslations();

function localName(item) {
    return locale.value?.current === 'en' && item?.name_en ? item.name_en : (item?.name_ru ?? item?.name ?? '');
}


const props = defineProps({
    users: Object,
    traits: Array,
    interestCategories: Array,
    serviceCategories: Array,
    filters: Object,
});

const LANGUAGES = [
    { code: 'ru', label: 'Русский' },
    { code: 'en', label: 'English' },
    { code: 'de', label: 'Deutsch' },
    { code: 'fr', label: 'Français' },
    { code: 'es', label: 'Español' },
    { code: 'zh', label: '中文' },
    { code: 'ja', label: '日本語' },
    { code: 'ko', label: '한국어' },
    { code: 'uk', label: 'Українська' },
    { code: 'pl', label: 'Polski' },
    { code: 'tr', label: 'Türkçe' },
    { code: 'ar', label: 'العربية' },
];

const TIMEZONES = [
    'Europe/Moscow', 'Europe/Kiev', 'Europe/Minsk', 'Europe/London',
    'Europe/Berlin', 'Europe/Paris', 'Europe/Amsterdam', 'Europe/Warsaw',
    'Asia/Almaty', 'Asia/Tashkent', 'Asia/Yekaterinburg', 'Asia/Novosibirsk',
    'Asia/Krasnoyarsk', 'Asia/Irkutsk', 'Asia/Yakutsk', 'Asia/Vladivostok',
    'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
    'Asia/Tokyo', 'Asia/Seoul', 'Asia/Shanghai', 'Asia/Dubai', 'Asia/Kolkata',
];

// ── Reactive filters ────────────────────────────────────────
const f = ref({
    name: props.filters.name ?? '',
    gender: props.filters.gender ?? '',
    age_from: props.filters.age_from ?? '',
    age_to: props.filters.age_to ?? '',
    is_idol: props.filters.is_idol ?? '',
    rating_from: props.filters.rating_from ?? '',
    rating_to: props.filters.rating_to ?? '',
    traits: props.filters.traits ? [].concat(props.filters.traits).map(Number) : [],
    interests: props.filters.interests ? [].concat(props.filters.interests).map(Number) : [],
    languages: props.filters.languages ? [].concat(props.filters.languages) : [],
    timezone: props.filters.timezone ?? '',
    service_categories: props.filters.service_categories ? [].concat(props.filters.service_categories).map(Number) : [],
    sort_by: props.filters.sort_by ?? 'rating',
    sort_dir: props.filters.sort_dir ?? 'desc',
});

// Snapshot of last applied state (excluding sort_*)
const appliedFilters = ref({ ...f.value });

const DIRTY_KEYS = ['name', 'gender', 'age_from', 'age_to', 'is_idol',
    'rating_from', 'rating_to', 'traits', 'interests', 'languages',
    'timezone', 'service_categories'];

const isDirty = computed(() =>
    DIRTY_KEYS.some(k => JSON.stringify(f.value[k]) !== JSON.stringify(appliedFilters.value[k]))
);

function apply() {
    const params = {};
    Object.entries(f.value).forEach(([k, v]) => {
        if (v !== '' && v !== null && !(Array.isArray(v) && v.length === 0)) {
            params[k] = v;
        }
    });
    router.get(route('users.search'), params, { preserveState: true, replace: true });
}

function applyFilters() {
    appliedFilters.value = { ...f.value };
    apply();
}

// Sort watchers — apply immediately, don't affect isDirty
watch(() => f.value.sort_by, apply);
watch(() => f.value.sort_dir, apply);

function resetFilters() {
    f.value = {
        name: '', gender: '', age_from: '', age_to: '',
        is_idol: '', rating_from: '', rating_to: '',
        traits: [], interests: [], languages: [],
        timezone: '', service_categories: [],
        sort_by: 'rating', sort_dir: 'desc',
    };
    appliedFilters.value = { ...f.value };
    router.get(route('users.search'), {}, { preserveState: false, replace: true });
}

function toggleSortDir() {
    f.value.sort_dir = f.value.sort_dir === 'desc' ? 'asc' : 'desc';
}

// ── Collapsible sections ─────────────────────────────────────
const openSections = ref(new Set());
function toggleSection(key) {
    if (openSections.value.has(key)) openSections.value.delete(key);
    else openSections.value.add(key);
    openSections.value = new Set(openSections.value);
}

// Per-section search strings
const sectionSearch = ref({
    traits: '',
    languages: '',
    service_categories: '',
});

const filteredTraits = computed(() =>
    props.traits.filter(t => localName(t).toLowerCase().includes(sectionSearch.value.traits.toLowerCase()))
);

const filteredLanguages = computed(() =>
    LANGUAGES.filter(l => l.label.toLowerCase().includes(sectionSearch.value.languages.toLowerCase()))
);

const filteredServiceCategories = computed(() =>
    props.serviceCategories.filter(c => localName(c).toLowerCase().includes((sectionSearch.value.service_categories || '').toLowerCase()))
);

function filteredInterests(cat) {
    const q = (sectionSearch.value[`interest_cat_${cat.id}`] || '').toLowerCase();
    return cat.interests.filter(i => localName(i).toLowerCase().includes(q));
}

// ── Active chips ─────────────────────────────────────────────
const activeChips = computed(() => {
    const chips = [];
    if (f.value.name)
        chips.push({ label: __('search.active.name', { value: f.value.name }), key: 'name' });
    if (f.value.gender)
        chips.push({ label: __('search.active.gender', { value: f.value.gender === 'male' ? __('gender.male') : __('gender.female') }), key: 'gender' });
    if (f.value.age_from || f.value.age_to)
        chips.push({ label: __('search.active.age', { from: f.value.age_from || '…', to: f.value.age_to || '…' }), key: 'age' });
    if (f.value.is_idol !== '')
        chips.push({ label: __('search.active.idol', { value: f.value.is_idol === '1' ? __('common.yes') : __('common.no') }), key: 'is_idol' });
    if (f.value.rating_from || f.value.rating_to)
        chips.push({ label: __('search.active.rating', { from: f.value.rating_from || '…', to: f.value.rating_to || '…' }), key: 'rating' });
    f.value.traits.forEach(id => {
        const t = props.traits.find(x => x.id === id);
        if (t) chips.push({ label: localName(t), key: 'traits', value: id });
    });
    f.value.interests.forEach(id => {
        for (const cat of props.interestCategories) {
            const i = cat.interests.find(x => x.id === id);
            if (i) { chips.push({ label: localName(i), key: 'interests', value: id }); break; }
        }
    });
    f.value.languages.forEach(code => {
        const l = LANGUAGES.find(x => x.code === code);
        if (l) chips.push({ label: l.label, key: 'languages', value: code });
    });
    if (f.value.timezone)
        chips.push({ label: f.value.timezone, key: 'timezone' });
    f.value.service_categories.forEach(id => {
        const c = props.serviceCategories.find(x => x.id === id);
        if (c) chips.push({ label: localName(c), key: 'service_categories', value: id });
    });
    return chips;
});

function resetChip(chip) {
    if (chip.key === 'name') f.value.name = '';
    else if (chip.key === 'gender') f.value.gender = '';
    else if (chip.key === 'age') { f.value.age_from = ''; f.value.age_to = ''; }
    else if (chip.key === 'is_idol') f.value.is_idol = '';
    else if (chip.key === 'rating') { f.value.rating_from = ''; f.value.rating_to = ''; }
    else if (chip.key === 'timezone') f.value.timezone = '';
    else if (['traits', 'interests', 'languages', 'service_categories'].includes(chip.key))
        f.value[chip.key] = f.value[chip.key].filter(v => v !== chip.value);
}

function interestCountForCat(cat) {
    return cat.interests.filter(i => f.value.interests.includes(i.id)).length;
}

// ── Helpers ─────────────────────────────────────────────────
function calcAge(birthDate) {
    if (!birthDate) return null;
    const diff = Date.now() - new Date(birthDate).getTime();
    return Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
}

function genderLabel(g) {
    if (g === 'male') return __('gender.abbr.male');
    if (g === 'female') return __('gender.abbr.female');
    return '';
}

function avatarUrl(user) {
    if (!user.avatar_path) return null;
    return '/storage/' + user.avatar_path;
}

function initial(name) {
    return name?.charAt(0).toUpperCase() ?? '?';
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
                        <span class="sort-label">{{ __('search.sort_by') }}</span>
                        <button :class="['sort-btn', { active: f.sort_by === 'rating' }]"
                            @click="f.sort_by = 'rating'">{{ __('search.sort.rating') }}</button>
                        <button :class="['sort-btn', { active: f.sort_by === 'created_at' }]"
                            @click="f.sort_by = 'created_at'">{{ __('search.sort.date') }}</button>
                        <button @click="toggleSortDir" class="sort-dir-btn"
                            :title="f.sort_dir === 'desc' ? __('search.sort.desc') : __('search.sort.asc')">
                            {{ f.sort_dir === 'desc' ? '↓' : '↑' }}
                        </button>
                    </div>
                    <div class="found-count">{{ __('search.found', { count: users.total }) }}</div>
                </div>

                <!-- Cards -->
                <div class="results-body">
                    <div v-if="users.data.length > 0" class="user-grid">
                        <Link v-for="user in users.data" :key="user.id"
                            :href="route('profile.show', { user: user.id }) + '#about'" class="user-card">
                            <div class="card-avatar">
                                <img v-if="avatarUrl(user)" :src="avatarUrl(user)" :alt="__('common.avatar')"
                                    class="card-avatar__img" />
                                <span v-else class="card-avatar__initials">{{ initial(user.name) }}</span>
                            </div>
                            <span v-if="user.rating" class="card-rating">★ {{ user.rating }}</span>
                            <div class="card-body">
                                <div class="card-name-row">
                                    <div class="card-name">{{ user.name }}</div>
                                </div>
                                <div class="card-badges">
                                    <IdolBadge v-if="user.is_idol" />
                                    <span v-if="user.gender" class="card-badge"
                                        :class="user.gender === 'female' ? 'card-badge--female' : 'card-badge--male'">{{
                                            user.gender === 'female' ? '\u2640\uFE0F' : '\u2642\uFE0F' }}</span>
                                    <span v-if="calcAge(user.birth_date)" class="card-badge card-badge--age">{{
                                        calcAge(user.birth_date) }} {{ transChoice('search.age.years', calcAge(user.birth_date)) }}</span>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <div v-else class="no-results">
                        <p>{{ __('search.empty') }}</p>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="users.last_page > 1" class="pagination">
                    <Link v-if="users.prev_page_url" :href="users.prev_page_url" class="page-btn">←</Link>
                    <span v-else class="page-btn page-btn--disabled">←</span>

                    <template v-for="link in users.links" :key="link.label">
                        <template v-if="!link.label.includes('Previous') && !link.label.includes('Next')">
                            <Link v-if="link.url && !link.active" :href="link.url" class="page-btn">{{ link.label }}
                            </Link>
                            <span v-else
                                :class="['page-btn', { 'page-btn--active': link.active, 'page-btn--disabled': !link.url }]">{{
                                link.label }}</span>
                        </template>
                    </template>

                    <Link v-if="users.next_page_url" :href="users.next_page_url" class="page-btn">→</Link>
                    <span v-else class="page-btn page-btn--disabled">→</span>
                </div>
            </div>

            <!-- Sidebar (right) -->
            <aside class="search-sidebar">
                <div class="sidebar-inner">
                    <h2 class="sidebar-title">{{ __('search.filters.title') }}</h2>

                    <!-- Active chips -->
                    <Transition name="chips-fade">
                        <div v-if="activeChips.length" class="active-chips">
                            <span v-for="chip in activeChips" :key="chip.key + (chip.value ?? '')" class="active-chip">
                                {{ chip.label }}
                                <button class="active-chip__remove" @click="resetChip(chip)">×</button>
                            </span>
                        </div>
                    </Transition>

                    <!-- Имя -->
                    <div class="filter-group">
                        <label class="filter-label">{{ __('auth.name') }}</label>
                        <input v-model="f.name" type="text" class="filter-input" :placeholder="__('search.name')" />
                    </div>

                    <!-- Пол -->
                    <div class="filter-group">
                        <label class="filter-label">{{ __('auth.gender') }}</label>
                        <div class="btn-group">
                            <button :class="['btn-toggle', { active: f.gender === '' }]"
                                @click="f.gender = ''">{{ __('gender.any') }}</button>
                            <button :class="['btn-toggle', { active: f.gender === 'male' }]"
                                @click="f.gender = 'male'">{{ __('gender.male') }}</button>
                            <button :class="['btn-toggle', { active: f.gender === 'female' }]"
                                @click="f.gender = 'female'">{{ __('gender.female') }}</button>
                        </div>
                    </div>

                    <!-- Возраст -->
                    <div class="filter-group">
                        <label class="filter-label">{{ __('search.filters.age') }}</label>
                        <div class="range-row">
                            <input v-model="f.age_from" type="number" min="18" max="120"
                                class="filter-input filter-input--sm" :placeholder="__('search.price.from')" />
                            <span class="range-sep">—</span>
                            <input v-model="f.age_to" type="number" min="18" max="120"
                                class="filter-input filter-input--sm" :placeholder="__('search.price.to')" />
                        </div>
                    </div>

                    <!-- Айдол -->
                    <div class="filter-group">
                        <label class="filter-label">{{ __('search.filters.idol') }}</label>
                        <div class="btn-group">
                            <button :class="['btn-toggle', { active: f.is_idol === '' }]"
                                @click="f.is_idol = ''">{{ __('common.any') }}</button>
                            <button :class="['btn-toggle', { active: f.is_idol === '1' }]"
                                @click="f.is_idol = '1'">{{ __('common.yes') }}</button>
                            <button :class="['btn-toggle', { active: f.is_idol === '0' }]"
                                @click="f.is_idol = '0'">{{ __('common.no') }}</button>
                        </div>
                    </div>

                    <!-- Рейтинг -->
                    <div class="filter-group">
                        <label class="filter-label">{{ __('search.filters.rating') }}</label>
                        <div class="range-row">
                            <input v-model="f.rating_from" type="number" min="0" max="100"
                                class="filter-input filter-input--sm" :placeholder="__('search.price.from')" />
                            <span class="range-sep">—</span>
                            <input v-model="f.rating_to" type="number" min="0" max="100"
                                class="filter-input filter-input--sm" :placeholder="__('search.price.to')" />
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="filter-divider"><span>{{ __('search.filters.advanced') }}</span></div>

                    <!-- Черты характера -->
                    <div class="filter-group">
                        <div class="filter-section-header" @click="toggleSection('traits')">
                            <label class="filter-label">{{ __('search.filters.traits') }}</label>
                            <span v-if="!openSections.has('traits') && f.traits.length" class="section-badge">{{
                                f.traits.length
                                }}</span>
                            <svg class="section-chevron" :class="{ open: openSections.has('traits') }"
                                viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2.5 5L7 9.5L11.5 5" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <input v-if="openSections.has('traits')" class="section-search" v-model="sectionSearch.traits"
                            :placeholder="__('search.filter')" />
                        <div v-if="openSections.has('traits')" class="checkbox-list">
                            <label v-for="trait in filteredTraits" :key="trait.id" class="checkbox-item">
                                <input type="checkbox" :value="trait.id" v-model="f.traits" class="checkbox-input" />
                                <span class="checkbox-label">{{ localName(trait) }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Интересы -->
                    <div class="filter-group">
                        <label class="filter-label">{{ __('search.filters.interests') }}</label>
                        <div v-for="cat in interestCategories" :key="cat.id" class="interest-cat">
                            <div class="filter-section-header" @click="toggleSection(`interest_cat_${cat.id}`)">
                                <div class="interest-cat__name">{{ localName(cat) }}</div>
                                <span v-if="!openSections.has(`interest_cat_${cat.id}`) && interestCountForCat(cat)"
                                    class="section-badge">{{ interestCountForCat(cat) }}</span>
                                <svg class="section-chevron"
                                    :class="{ open: openSections.has(`interest_cat_${cat.id}`) }" viewBox="0 0 14 14"
                                    fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2.5 5L7 9.5L11.5 5" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <input v-if="openSections.has(`interest_cat_${cat.id}`)" class="section-search"
                                v-model="sectionSearch[`interest_cat_${cat.id}`]" :placeholder="__('search.filter')" />
                            <div v-if="openSections.has(`interest_cat_${cat.id}`)" class="checkbox-list">
                                <label v-for="interest in filteredInterests(cat)" :key="interest.id"
                                    class="checkbox-item">
                                    <input type="checkbox" :value="interest.id" v-model="f.interests"
                                        class="checkbox-input" />
                                    <span class="checkbox-label">{{ localName(interest) }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Языки -->
                    <div class="filter-group">
                        <div class="filter-section-header" @click="toggleSection('languages')">
                            <label class="filter-label">{{ __('search.filters.languages') }}</label>
                            <span v-if="!openSections.has('languages') && f.languages.length" class="section-badge">{{
                                f.languages.length }}</span>
                            <svg class="section-chevron" :class="{ open: openSections.has('languages') }"
                                viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2.5 5L7 9.5L11.5 5" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <input v-if="openSections.has('languages')" class="section-search"
                            v-model="sectionSearch.languages" :placeholder="__('search.filter')" />
                        <div v-if="openSections.has('languages')" class="checkbox-list">
                            <label v-for="lang in filteredLanguages" :key="lang.code" class="checkbox-item">
                                <input type="checkbox" :value="lang.code" v-model="f.languages"
                                    class="checkbox-input" />
                                <span class="checkbox-label">{{ lang.label }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Часовой пояс -->
                    <div class="filter-group">
                        <label class="filter-label">{{ __('search.filters.timezone') }}</label>
                        <select v-model="f.timezone" class="filter-input filter-select">
                            <option value="">{{ __('search.filters.any_tz') }}</option>
                            <option v-for="tz in TIMEZONES" :key="tz" :value="tz">{{ tz }}</option>
                        </select>
                    </div>

                    <!-- Категории услуг -->
                    <div class="filter-group">
                        <div class="filter-section-header" @click="toggleSection('service_categories')">
                            <label class="filter-label">{{ __('search.filters.services') }}</label>
                            <span v-if="!openSections.has('service_categories') && f.service_categories.length"
                                class="section-badge">{{ f.service_categories.length }}</span>
                            <svg class="section-chevron" :class="{ open: openSections.has('service_categories') }"
                                viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2.5 5L7 9.5L11.5 5" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <input v-if="openSections.has('service_categories')" class="section-search"
                            v-model="sectionSearch.service_categories" :placeholder="__('search.filter')" />
                        <div v-if="openSections.has('service_categories')" class="checkbox-list">
                            <label v-for="cat in filteredServiceCategories" :key="cat.id" class="checkbox-item">
                                <input type="checkbox" :value="cat.id" v-model="f.service_categories"
                                    class="checkbox-input" />
                                <span class="checkbox-label">{{ localName(cat) }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Сбросить -->
                    <button @click="resetFilters" class="reset-btn">{{ __('search.filters.reset') }}</button>
                </div>

                <Transition name="slide-up">
                    <div v-if="isDirty" class="sidebar-footer">
                        <button class="apply-btn" @click="applyFilters">{{ __('search.filters.apply') }}</button>
                    </div>
                </Transition>
            </aside>
        </div>
    </AppLayout>
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
    flex-shrink: 0;
    border-left: 1px solid rgba(110, 110, 210, 0.12);
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
    padding: 1.75rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
}

.sidebar-footer {
    flex-shrink: 0;
    padding: 0.75rem 1.5rem;
    border-top: 1px solid rgba(110, 110, 210, 0.15);
    background: rgba(10, 10, 20, 0.95);
}

.apply-btn {
    width: 100%;
    padding: 0.7rem;
    border-radius: 4px;
    border: 1px solid rgba(160, 160, 255, 0.5);
    background: rgba(110, 110, 210, 0.15);
    color: #be91ff;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    font-family: inherit;
}

.apply-btn:hover {
    background: rgba(110, 110, 210, 0.28);
    border-color: rgba(160, 160, 255, 0.8);
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition: opacity 0.2s, transform 0.2s;
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

/* ── Active chips ────────────────────────────────────────── */
.active-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: -0.6rem;
}

.active-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.55rem;
    border-radius: 20px;
    background: rgba(160, 160, 255, 0.1);
    border: 1px solid rgba(160, 160, 255, 0.25);
    color: var(--color-base-1);
    font-size: 0.78rem;
    line-height: 1;
}

.active-chip__remove {
    background: none;
    border: none;
    color: rgba(160, 160, 255, 0.6);
    cursor: pointer;
    padding: 0;
    font-size: 0.9rem;
    line-height: 1;
    transition: color 0.15s;
    font-family: inherit;
}

.active-chip__remove:hover {
    color: rgba(160, 160, 255, 0.9);
}

.chips-fade-enter-active,
.chips-fade-leave-active {
    transition: opacity 0.2s;
}

.chips-fade-enter-from,
.chips-fade-leave-to {
    opacity: 0;
}

/* ── Section badge ───────────────────────────────────────── */
.section-badge {
    display: inline-block;
    padding: 0.1rem 0.38rem;
    border-radius: 20px;
    background: rgba(160, 160, 255, 0.15);
    color: var(--color-base-1);
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1.4;
    flex-shrink: 0;
}

/* ── Filter divider ──────────────────────────────────────── */
.filter-divider {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-divider::before,
.filter-divider::after {
    content: '';
    flex: 1;
    border-top: 1px solid rgba(110, 110, 210, 0.15);
}

.filter-divider span {
    font-size: 0.7rem;
    color: rgba(160, 160, 255, 0.35);
    white-space: nowrap;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

/* ── Filter group ────────────────────────────────────────── */
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.filter-label {
    font-size: 0.82rem;
    font-weight: 600;
    color: rgba(160, 160, 255, 0.7);
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.filter-input {
    width: 100%;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(110, 110, 210, 0.2);
    border-radius: 4px;
    color: rgba(255, 255, 255, 0.85);
    padding: 0.5rem 0.75rem;
    font-size: 0.92rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.18s;
    box-sizing: border-box;
}

.filter-input:focus {
    border-color: rgba(110, 110, 210, 0.5);
}

.filter-input--sm {
    width: calc(50% - 0.5rem);
}

.filter-select {
    cursor: pointer;
}

.filter-select option {
    background: #0e0e1e;
}

.range-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.range-sep {
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.8rem;
    flex-shrink: 0;
}

/* ── Button group ────────────────────────────────────────── */
.btn-group {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.btn-toggle {
    padding: 0.35rem 0.8rem;
    border-radius: 20px;
    border: 1px solid rgba(110, 110, 210, 0.25);
    background: transparent;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.88rem;
    cursor: pointer;
    transition: all 0.15s;
    font-family: inherit;
}

.btn-toggle:hover {
    background: rgba(110, 110, 210, 0.06);
    border-color: rgba(110, 110, 210, 0.5);
    color: rgba(255, 255, 255, 0.8);
}

.btn-toggle.active {
    border-color: rgba(160, 160, 255, 0.6);
    background: rgba(110, 110, 210, 0.15);
    color: #be91ff;
    box-shadow: 0 0 10px rgba(160, 160, 255, 0.2);
}

/* ── Collapsible section headers ─────────────────────────── */
.filter-section-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    user-select: none;
    padding: 0.3rem 0.4rem;
    border-radius: 4px;
    margin: 0 -0.4rem;
    transition: background 0.15s;
}

.filter-section-header:hover {
    background: rgba(110, 110, 210, 0.08);
}

.filter-section-header .filter-label {
    flex: 1;
    cursor: pointer;
    margin: 0;
}

.filter-section-header .interest-cat__name {
    flex: 1;
}

.section-search {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(110, 110, 210, 0.2);
    border-radius: 3px;
    color: rgba(255, 255, 255, 0.8);
    padding: 0.3rem 0.6rem;
    font-size: 0.82rem;
    font-family: inherit;
    outline: none;
    width: 100%;
    box-sizing: border-box;
    margin-top: 0.4rem;
    margin-bottom: 0.4rem;
}

.section-search:focus {
    border-color: rgba(110, 110, 210, 0.5);
}

.section-chevron {
    width: 14px;
    height: 14px;
    color: rgba(255, 255, 255, 0.3);
    transition: transform 0.2s;
    flex-shrink: 0;
}

.section-chevron.open {
    transform: rotate(180deg);
}

/* ── Checkboxes ──────────────────────────────────────────── */
.checkbox-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.4rem;
    max-height: 220px;
    overflow-y: auto;
    padding: 0.5rem 0.25rem 0.25rem 0.5rem;
}

.checkbox-list {
    scrollbar-width: thin;
    scrollbar-color: rgba(224, 85, 143, 0.6) rgba(255, 255, 255, 0.04);
}

.checkbox-list::-webkit-scrollbar {
    width: 6px;
}

.checkbox-list::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.04);
    border-radius: 3px;
}

.checkbox-list::-webkit-scrollbar-thumb {
    background: rgba(224, 85, 143, 0.5);
    border-radius: 3px;
}

.checkbox-list::-webkit-scrollbar-thumb:hover {
    background: rgba(224, 85, 143, 0.8);
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.checkbox-input {
    appearance: none;
    -webkit-appearance: none;
    width: 15px;
    height: 15px;
    border: 1.5px solid rgba(110, 110, 210, 0.35);
    border-radius: 3px;
    background: transparent;
    cursor: pointer;
    position: relative;
    flex-shrink: 0;
    transition: border-color 0.15s, background 0.15s;
}

.checkbox-input:checked {
    background: rgba(110, 110, 210, 0.5);
    border-color: rgba(160, 160, 255, 0.7);
}

.checkbox-input:checked::after {
    content: '';
    position: absolute;
    left: 3px;
    top: 0px;
    width: 5px;
    height: 9px;
    border: 2px solid #fff;
    border-top: none;
    border-left: none;
    transform: rotate(45deg);
}

.checkbox-label {
    font-size: 0.92rem;
    color: rgba(255, 255, 255, 0.6);
    transition: color 0.15s;
}

.checkbox-item:hover .checkbox-label {
    color: rgba(255, 255, 255, 0.9);
}

.interest-cat {
    margin-bottom: 0.5rem;
}

.interest-cat__name {
    font-size: 0.8rem;
    color: rgba(160, 160, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0;
    line-height: 1;
}

/* ── Reset button ────────────────────────────────────────── */
.reset-btn {
    padding: 0.55rem 1.2rem;
    border-radius: 4px;
    border: 1px solid rgba(110, 110, 210, 0.3);
    background: transparent;
    color: rgba(160, 160, 255, 0.7);
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
    padding: 1.5rem;
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
    flex-wrap: wrap;
    gap: 0.75rem;
}

.sort-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sort-label {
    font-size: 0.88rem;
    color: rgba(255, 255, 255, 0.4);
}

.sort-btn {
    padding: 0.35rem 0.75rem;
    border-radius: 3px;
    border: 1px solid rgba(110, 110, 210, 0.2);
    background: transparent;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.88rem;
    cursor: pointer;
    transition: all 0.15s;
    font-family: inherit;
}

.sort-btn:hover,
.sort-btn.active {
    border-color: rgba(110, 110, 210, 0.5);
    color: #be91ff;
    background: rgba(110, 110, 210, 0.1);
}

.sort-dir-btn {
    padding: 0.28rem 0.55rem;
    border-radius: 3px;
    border: 1px solid rgba(110, 110, 210, 0.2);
    background: transparent;
    color: rgba(255, 255, 255, 0.5);
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.15s;
    font-family: inherit;
    line-height: 1;
}

.sort-dir-btn:hover {
    border-color: rgba(110, 110, 210, 0.5);
    color: rgba(255, 255, 255, 0.9);
}

.found-count {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.35);
}


/* ── User grid ───────────────────────────────────────────── */
/* Сайдбар ~540px, учитываем оставшееся пространство */
.user-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}

/* ~1600px и меньше → 3 колонки */
@media (max-width: 1600px) {
    .user-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* ~1200px и меньше → 2 колонки */
@media (max-width: 1200px) {
    .user-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* ~900px и меньше → 1 колонка */
@media (max-width: 900px) {
    .user-grid {
        grid-template-columns: repeat(1, 1fr);
    }
}


.user-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(110, 110, 210, 0.12);
    border-radius: 8px;
    padding: 1rem;
    text-decoration: none;
    transition: border-color 0.18s, background 0.18s, transform 0.15s;
    min-width: 0;
    overflow: hidden;
}

.user-card:hover {
    border-color: rgba(110, 110, 210, 0.35);
    background: rgba(110, 110, 210, 0.06);
    transform: translateY(-2px);
}

.card-avatar {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    overflow: hidden;
    background: rgba(110, 110, 210, 0.15);
    border: 1.5px solid rgba(110, 110, 210, 0.4);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    align-self: center;
}

.card-avatar__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.card-avatar__initials {
    font-size: 2rem;
    font-weight: 600;
    color: #7070d8;
}

.card-body {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
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
    font-size: 1.2rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}

.card-rating {
    position: absolute;
    top: 0.6rem;
    right: 0.75rem;
    font-size: 0.85rem;
    color: var(--color-base-1);
    font-weight: 600;
    white-space: nowrap;
}

.card-badges {
    display: flex;
    flex-wrap: nowrap;
    gap: 0.35rem;
    justify-content: center;
    overflow: hidden;
    margin-top: 0.15rem;
}

.card-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.85rem;
    border-radius: 3px;
    font-size: 0.92rem;
    letter-spacing: 0.04em;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.55);
    white-space: nowrap;
}


.card-badge--female {
    border-color: rgba(160, 160, 255, 0.3);
    background: rgba(160, 160, 255, 0.06);
    color: rgba(160, 160, 255, 0.85);
}

.card-badge--male {
    border-color: rgba(167, 139, 250, 0.3);
    background: rgba(167, 139, 250, 0.06);
    color: rgba(167, 139, 250, 0.85);
}

.card-badge--age {
    color: rgba(255, 255, 255, 0.45);
}

.card-about {
    font-size: 0.92rem;
    color: rgba(255, 255, 255, 0.4);
    line-height: 1.4;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    margin: 0;
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
    flex-wrap: wrap;
    padding-top: 0.5rem;
    flex-shrink: 0;
}

.pagination::before,
.pagination::after {
    content: '';
    flex: 1;
    height: 1.5px;
    background: linear-gradient(to var(--dir), rgba(140, 100, 230, 0.7), transparent);
    min-width: 2rem;
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
    border: 1px solid rgba(110, 110, 210, 0.2);
    background: transparent;
    color: rgba(255, 255, 255, 0.5);
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.15s;
}

.page-btn:not(.page-btn--disabled):not(.page-btn--active):hover {
    border-color: rgba(110, 110, 210, 0.5);
    color: rgba(255, 255, 255, 0.9);
    background: rgba(110, 110, 210, 0.08);
}

.page-btn--active {
    border-color: rgba(160, 160, 255, 0.6);
    background: rgba(110, 110, 210, 0.2);
    color: #be91ff;
}

.page-btn--disabled {
    opacity: 0.3;
    cursor: default;
}

/* ── Mobile ──────────────────────────────────────────────── */
@media (max-width: 768px) {
    .search-page {
        flex-direction: column;
        height: auto;
        overflow: visible;
    }

    .search-sidebar {
        width: 100%;
        height: auto;
        border-left: none;
        border-top: 1px solid rgba(110, 110, 210, 0.12);
        order: 2;
    }

    .sidebar-inner {
        overflow-y: visible;
        padding: 1rem;
    }

    .search-results {
        height: auto;
        overflow: visible;
        order: 1;
    }

    .results-body {
        overflow-y: visible;
        justify-content: flex-start;
    }

    .user-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
