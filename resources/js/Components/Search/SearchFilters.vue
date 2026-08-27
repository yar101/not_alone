<script setup>
import { ref, computed } from "vue";
import IdolBadge from "@/Components/IdolBadge.vue";
import { useTranslations } from "@/composables/useTranslations";

const { __, transChoice, locale } = useTranslations();

function localName(item) {
    return locale.value?.current === "en" && item?.name_en
        ? item.name_en
        : (item?.name_ru ?? item?.name ?? "");
}

const props = defineProps({
    modelValue: Object, // The filter object (f)
    traits: Array,
    interestCategories: Array,
    serviceCategories: Array,
    languages: Array,
    timezones: Array,
    activeChips: Array,
});

const emit = defineEmits(["update:modelValue", "reset-chip"]);

// ── Collapsible sections ─────────────────────────────────────
const openSection = ref(null);
function toggleSection(key) {
    openSection.value = openSection.value === key ? null : key;
}

function toggleFilterId(key, id) {
    const filters = { ...props.modelValue };
    const idx = filters[key].indexOf(id);
    if (idx === -1) filters[key].push(id);
    else filters[key].splice(idx, 1);
    emit("update:modelValue", filters);
}

function interestCountForCat(cat) {
    return cat.interests.filter((i) => props.modelValue.interests.includes(i.id)).length;
}

// Per-section search strings
const sectionSearch = ref({
    traits: "",
    languages: "",
    service_categories: "",
});

const filteredTraits = computed(() =>
    props.traits.filter((t) =>
        localName(t)
            .toLowerCase()
            .includes(sectionSearch.value.traits.toLowerCase()),
    ),
);

const filteredLanguages = computed(() =>
    props.languages.filter((l) =>
        l.label
            .toLowerCase()
            .includes(sectionSearch.value.languages.toLowerCase()),
    ),
);

const filteredServiceCategories = computed(() =>
    props.serviceCategories.filter((c) =>
        localName(c)
            .toLowerCase()
            .includes(
                (sectionSearch.value.service_categories || "").toLowerCase(),
            ),
    ),
);

function filteredInterests(cat) {
    const q = (
        sectionSearch.value[`interest_cat_${cat.id}`] || ""
    ).toLowerCase();
    return cat.interests.filter((i) => localName(i).toLowerCase().includes(q));
}
</script>

<template>
    <div class="filters-container">
        <!-- Active chips -->
        <Transition name="chips-fade">
            <div v-if="activeChips.length" class="active-chips">
                <span
                    v-for="chip in activeChips"
                    :key="chip.key + (chip.value ?? '')"
                    class="active-chip"
                >
                    {{ chip.label }}
                    <button class="active-chip__remove" @click="$emit('reset-chip', chip)">
                        ×
                    </button>
                </span>
            </div>
        </Transition>

        <!-- Имя -->
        <div class="filter-group">
            <label class="filter-label">{{ __("auth.name") }}</label>
            <input
                :value="modelValue.name"
                @input="$emit('update:modelValue', { ...modelValue, name: $event.target.value })"
                type="text"
                class="filter-input"
                :placeholder="__('search.name')"
            />
        </div>

        <!-- Пол -->
        <div class="filter-group">
            <label class="filter-label">{{ __("auth.gender") }}</label>
            <div class="btn-group">
                <button
                    :class="['btn-toggle', { active: modelValue.gender === '' }]"
                    @click="$emit('update:modelValue', { ...modelValue, gender: '' })"
                >
                    {{ __("gender.any") }}
                </button>
                <button
                    :class="['btn-toggle', { active: modelValue.gender === 'male' }]"
                    @click="$emit('update:modelValue', { ...modelValue, gender: 'male' })"
                    :title="__('gender.male')"
                >
                    <i class="fa-solid fa-mars"></i>
                </button>
                <button
                    :class="['btn-toggle', { active: modelValue.gender === 'female' }]"
                    @click="$emit('update:modelValue', { ...modelValue, gender: 'female' })"
                    :title="__('gender.female')"
                >
                    <i class="fa-solid fa-venus"></i>
                </button>
            </div>
        </div>

        <!-- Возраст -->
        <div class="filter-group">
            <label class="filter-label">{{ __("search.filters.age") }}</label>
            <div class="range-row">
                <input
                    :value="modelValue.age_from"
                    @input="$emit('update:modelValue', { ...modelValue, age_from: $event.target.value })"
                    type="number"
                    min="18"
                    max="120"
                    class="filter-input filter-input--sm"
                    :placeholder="__('search.price.from')"
                />
                <span class="range-sep">—</span>
                <input
                    :value="modelValue.age_to"
                    @input="$emit('update:modelValue', { ...modelValue, age_to: $event.target.value })"
                    type="number"
                    min="18"
                    max="120"
                    class="filter-input filter-input--sm"
                    :placeholder="__('search.price.to')"
                />
            </div>
        </div>

        <!-- Айдол -->
        <div class="filter-group">
            <label class="filter-label">{{ __("search.filters.idol") }}</label>
            <div class="btn-group">
                <button
                    :class="['btn-toggle', { active: modelValue.is_idol === '' }]"
                    @click="$emit('update:modelValue', { ...modelValue, is_idol: '' })"
                >
                    {{ __("common.any") }}
                </button>
                <button
                    :class="['btn-toggle', { active: modelValue.is_idol === '1' }]"
                    @click="$emit('update:modelValue', { ...modelValue, is_idol: '1' })"
                >
                    {{ __("common.yes") }}
                </button>
                <button
                    :class="['btn-toggle', { active: modelValue.is_idol === '0' }]"
                    @click="$emit('update:modelValue', { ...modelValue, is_idol: '0' })"
                >
                    {{ __("common.no") }}
                </button>
            </div>
        </div>

        <!-- Рейтинг -->
        <div class="filter-group">
            <label class="filter-label">{{ __("search.filters.rating") }}</label>
            <div class="range-row">
                <input
                    :value="modelValue.rating_from"
                    @input="$emit('update:modelValue', { ...modelValue, rating_from: $event.target.value })"
                    type="number"
                    min="0"
                    max="100"
                    class="filter-input filter-input--sm"
                    :placeholder="__('search.price.from')"
                />
                <span class="range-sep">—</span>
                <input
                    :value="modelValue.rating_to"
                    @input="$emit('update:modelValue', { ...modelValue, rating_to: $event.target.value })"
                    type="number"
                    min="0"
                    max="100"
                    class="filter-input filter-input--sm"
                    :placeholder="__('search.price.to')"
                />
            </div>
        </div>

        <!-- Divider -->
        <div class="filter-divider">
            <span>{{ __("search.filters.advanced") }}</span>
        </div>

        <!-- Черты характера -->
        <div class="filter-group">
            <div class="filter-section-header" @click="toggleSection('traits')">
                <label class="filter-label">{{ __("search.filters.traits") }}</label>
                <div class="header-indicators">
                    <span v-if="modelValue.traits.length" class="active-dot"></span>
                    <span v-if="openSection !== 'traits' && modelValue.traits.length" class="section-badge">{{ modelValue.traits.length }}</span>
                    <svg class="section-chevron" :class="{ open: openSection === 'traits' }" viewBox="0 0 14 14" fill="none">
                        <path d="M2.5 5L7 9.5L11.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </div>
            <Transition name="filter-section">
                <div v-if="openSection === 'traits'" class="section-content">
                    <input class="section-search" v-model="sectionSearch.traits" :placeholder="__('search.filter')" />
                    <div class="checkbox-list">
                        <button
                            v-for="trait in filteredTraits"
                            :key="trait.id"
                            class="filter-toggle-btn"
                            :class="{ active: modelValue.traits.includes(trait.id) }"
                            @click="toggleFilterId('traits', trait.id)"
                        >
                            {{ localName(trait) }}
                        </button>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Интересы -->
        <div class="filter-group">
            <label class="filter-label">{{ __("search.filters.interests") }}</label>
            <div v-for="cat in interestCategories" :key="cat.id" class="interest-cat">
                <div class="filter-section-header" @click="toggleSection(`interest_cat_${cat.id}`)">
                    <div class="interest-cat__name">{{ localName(cat) }}</div>
                    <div class="header-indicators">
                        <span v-if="interestCountForCat(cat)" class="active-dot"></span>
                        <span v-if="openSection !== `interest_cat_${cat.id}` && interestCountForCat(cat)" class="section-badge">{{ interestCountForCat(cat) }}</span>
                        <svg class="section-chevron" :class="{ open: openSection === `interest_cat_${cat.id}` }" viewBox="0 0 14 14" fill="none">
                            <path d="M2.5 5L7 9.5L11.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                </div>
                <Transition name="filter-section">
                    <div v-if="openSection === `interest_cat_${cat.id}`" class="section-content">
                        <input class="section-search" v-model="sectionSearch[`interest_cat_${cat.id}`]" :placeholder="__('search.filter')" />
                        <div class="checkbox-list">
                            <button
                                v-for="interest in filteredInterests(cat)"
                                :key="interest.id"
                                class="filter-toggle-btn"
                                :class="{ active: modelValue.interests.includes(interest.id) }"
                                @click="toggleFilterId('interests', interest.id)"
                            >
                                {{ localName(interest) }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>

        <!-- Языки -->
        <div class="filter-group">
            <div class="filter-section-header" @click="toggleSection('languages')">
                <label class="filter-label">{{ __("search.filters.languages") }}</label>
                <div class="header-indicators">
                    <span v-if="modelValue.languages.length" class="active-dot"></span>
                    <span v-if="openSection !== 'languages' && modelValue.languages.length" class="section-badge">{{ modelValue.languages.length }}</span>
                    <svg class="section-chevron" :class="{ open: openSection === 'languages' }" viewBox="0 0 14 14" fill="none">
                        <path d="M2.5 5L7 9.5L11.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </div>
            <Transition name="filter-section">
                <div v-if="openSection === 'languages'" class="section-content">
                    <input class="section-search" v-model="sectionSearch.languages" :placeholder="__('search.filter')" />
                    <div class="checkbox-list">
                        <button
                            v-for="lang in filteredLanguages"
                            :key="lang.code"
                            class="filter-toggle-btn"
                            :class="{ active: modelValue.languages.includes(lang.code) }"
                            @click="toggleFilterId('languages', lang.code)"
                        >
                            {{ lang.label }}
                        </button>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Часовой пояс -->
        <div class="filter-group">
            <label class="filter-label">{{ __("search.filters.timezone") }}</label>
            <select
                :value="modelValue.timezone"
                @change="$emit('update:modelValue', { ...modelValue, timezone: $event.target.value })"
                class="filter-input filter-select"
            >
                <option value="">{{ __("search.filters.any_tz") }}</option>
                <option v-for="tz in timezones" :key="tz.value" :value="tz.value">
                    {{ tz.label }}
                </option>
            </select>
        </div>

        <!-- Категории услуг -->
        <div class="filter-group">
            <div class="filter-section-header" @click="toggleSection('service_categories')">
                <label class="filter-label">{{ __("search.filters.services") }}</label>
                <div class="header-indicators">
                    <span v-if="modelValue.service_categories.length" class="active-dot"></span>
                    <span v-if="openSection !== 'service_categories' && modelValue.service_categories.length" class="section-badge">{{ modelValue.service_categories.length }}</span>
                    <svg class="section-chevron" :class="{ open: openSection === 'service_categories' }" viewBox="0 0 14 14" fill="none">
                        <path d="M2.5 5L7 9.5L11.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </div>
            <Transition name="filter-section">
                <div v-if="openSection === 'service_categories'" class="section-content">
                    <input class="section-search" v-model="sectionSearch.service_categories" :placeholder="__('search.filter')" />
                    <div class="checkbox-list">
                        <button
                            v-for="cat in filteredServiceCategories"
                            :key="cat.id"
                            class="filter-toggle-btn"
                            :class="{ active: modelValue.service_categories.includes(cat.id) }"
                            @click="toggleFilterId('service_categories', cat.id)"
                        >
                            {{ localName(cat) }}
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </div>
</template>

<style scoped>
.filters-container {
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
}

/* ── Filter groups ───────────────────────────────────────── */
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.filter-label {
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.45);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    cursor: pointer;
}

/* ── Inputs & Toggles ────────────────────────────────────── */
.filter-input {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 178, 239, 0.2);
    border-radius: 4px;
    color: rgba(255, 255, 255, 0.8);
    padding: 0.6rem 0.8rem;
    font-size: 0.95rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s;
    width: 100%;
    box-sizing: border-box;
}

.filter-input:focus {
    border-color: rgba(255, 178, 239, 0.5);
}

.filter-input--sm {
    width: 80px;
    text-align: center;
    padding: 0.4rem;
}

.filter-select {
    cursor: pointer;
}

.btn-group {
    display: flex;
    gap: 1px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 178, 239, 0.2);
    border-radius: 4px;
    overflow: hidden;
}

.btn-toggle {
    flex: 1;
    background: transparent;
    border: none;
    padding: 0.5rem;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.88rem;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
}

.btn-toggle:hover {
    background: rgba(255, 255, 255, 0.04);
    color: #fff;
}

.btn-toggle.active {
    background: rgba(255, 178, 239, 0.15);
    color: var(--color-base-1);
}

.range-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.range-sep {
    color: rgba(255, 255, 255, 0.2);
}

.filter-divider {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 0.5rem 0;
}

.filter-divider span {
    font-size: 0.72rem;
    color: rgba(255, 178, 239, 0.4);
    text-transform: uppercase;
    letter-spacing: 0.15em;
    white-space: nowrap;
}

.filter-divider::before,
.filter-divider::after {
    content: "";
    flex: 1;
    border-top: 1px solid rgba(255, 178, 239, 0.15);
}

/* ── Collapsible section headers ─────────────────────────── */
.filter-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    cursor: pointer;
    user-select: none;
    padding: 0.3rem 0.4rem;
    border-radius: 4px;
    margin: 0 -0.4rem;
    transition: background 0.15s;
}

.filter-section-header:hover {
    background: rgba(255, 178, 239, 0.08);
}

.filter-section-header .filter-label {
    flex: 1;
    cursor: pointer;
    margin: 0;
}

.filter-section-header .interest-cat__name {
    flex: 1;
    font-size: 0.8rem;
    color: rgba(255, 178, 239, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0;
    line-height: 1.2;
}

.header-indicators {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.active-dot {
    width: 6px;
    height: 6px;
    background: var(--color-base-1);
    border-radius: 50%;
    box-shadow: 0 0 8px var(--color-base-1);
    flex-shrink: 0;
}

.section-badge {
    background: rgba(255, 178, 239, 0.15);
    color: var(--color-base-1);
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.1rem 0.4rem;
    border-radius: 100px;
    min-width: 1.2rem;
    text-align: center;
}

.section-chevron {
    color: rgba(255, 255, 255, 0.3);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    width: 14px;
    height: 14px;
}

.section-chevron.open {
    transform: rotate(180deg);
    color: var(--color-base-1);
}

/* ── Transitions ─────────────────────────────────────────── */
.filter-section-enter-active,
.filter-section-leave-active {
    transition: all 0.3s ease-in-out;
    max-height: 500px;
    overflow: hidden;
}

.filter-section-enter-from,
.filter-section-leave-to {
    max-height: 0 !important;
    opacity: 0;
}

.section-content {
    display: flex;
    flex-direction: column;
}

.section-search {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 178, 239, 0.2);
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
    border-color: rgba(255, 178, 239, 0.4);
}

/* ── Checkboxes ──────────────────────────────────────────── */
.checkbox-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    max-height: 250px;
    overflow-y: auto;
    padding: 0.5rem 0.4rem 0.5rem 0;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 178, 239, 0.3) transparent;
}

.checkbox-list::-webkit-scrollbar {
    width: 4px;
}

.checkbox-list::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.04);
    border-radius: 3px;
}

.checkbox-list::-webkit-scrollbar-thumb {
    background: rgba(255, 178, 239, 0.2);
    border-radius: 3px;
}

.filter-toggle-btn {
    width: 100%;
    text-align: left;
    padding: 0.6rem 0.8rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.55);
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-family: inherit;
}

.filter-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.15);
    color: rgba(255, 255, 255, 0.8);
}

.filter-toggle-btn.active {
    background: rgba(255, 178, 239, 0.12);
    border-color: rgba(255, 178, 239, 0.45);
    color: var(--color-base-1);
    font-weight: 600;
}

.filter-toggle-btn.active::after {
    content: '';
    width: 6px;
    height: 6px;
    background: var(--color-base-1);
    border-radius: 50%;
    box-shadow: 0 0 8px var(--color-base-1);
    flex-shrink: 0;
}

.interest-cat {
    margin-bottom: 0.5rem;
}

/* ── Active chips ────────────────────────────────────────── */
.active-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.active-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.7rem;
    background: rgba(255, 178, 239, 0.1);
    border: 1px solid rgba(255, 178, 239, 0.3);
    border-radius: 100px;
    color: var(--color-base-1);
    font-size: 0.8rem;
    font-weight: 500;
}

.active-chip__remove {
    background: none;
    border: none;
    color: inherit;
    font-size: 1.1rem;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.active-chip__remove:hover {
    opacity: 1;
}

.chips-fade-enter-active,
.chips-fade-leave-active {
    transition: all 0.3s ease;
}
.chips-fade-enter-from,
.chips-fade-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
