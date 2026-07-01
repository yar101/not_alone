<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { EditPen } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import { useTranslations } from '@/composables/useTranslations';

const { __, locale } = useTranslations();

const props = defineProps({
    traits:    { default: null },
    allTraits: { default: null },
    isOwner:   { type: Boolean, default: false },
    gender:    { type: String, default: 'male' },
});

const editModal = ref(false);
const traitSearch = ref('');
const selected = ref(new Set(Array.isArray(props.traits) ? props.traits.map(t => t.id) : []));
const form = useForm({ trait_ids: [] });

const view = ref('list');
const suggestionText = ref('');
const suggSuccess = ref(false);
const suggForm = useForm({ name: '' });

function applyGender(name, gender) {
    if (gender !== 'female') return name;
    if (name.endsWith('ый')) return name.slice(0, -2) + 'ая';
    if (name.endsWith('ий')) return name.slice(0, -2) + 'ая';
    return name;
}

function displayTrait(t) {
    if (locale.value?.current === 'en' && t.name_en) return t.name_en;
    return applyGender(t.name_ru, props.gender);
}

const filteredTraits = computed(() => {
    if (!Array.isArray(props.allTraits)) return [];
    if (!traitSearch.value.trim()) return props.allTraits;
    const q = traitSearch.value.toLowerCase();
    return props.allTraits.filter(t =>
        t.name_ru.toLowerCase().includes(q) ||
        (t.name_en && t.name_en.toLowerCase().includes(q))
    ); // search both langs so user can type in either
});

function toggleTrait(id) {
    if (selected.value.has(id)) selected.value.delete(id);
    else selected.value.add(id);
}

function submit() {
    form.trait_ids = [...selected.value];
    form.patch(route('profile.update.traits'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => editModal.value = false,
    });
}

function openEdit() {
    selected.value = new Set(Array.isArray(props.traits) ? props.traits.map(t => t.id) : []);
    traitSearch.value = '';
    view.value = 'list';
    suggestionText.value = '';
    suggSuccess.value = false;
    editModal.value = true;
}

function submitSuggestion() {
    suggForm.name = suggestionText.value.trim();
    suggForm.post(route('profile.trait-suggestions.store'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            suggestionText.value = '';
            suggSuccess.value = true;
            setTimeout(() => { suggSuccess.value = false; view.value = 'list'; }, 2000);
        },
    });
}
</script>

<template>
    <div id="tour-traits" class="block-section">
        <div class="section-header">
            <span class="section-title">{{ __('profile.traits.title') }}</span>
            <button v-if="isOwner" class="edit-btn" @click="openEdit" :title="__('common.edit')">
                <el-icon><EditPen /></el-icon>
            </button>
        </div>

        <div v-if="traits?.length" class="tags-row">
            <span v-for="t in traits" :key="t.id" class="tag">
                {{ displayTrait(t) }}
            </span>
        </div>
        <p v-else-if="isOwner" class="empty">{{ __('profile.traits.empty') }}</p>
        <p v-else class="empty">{{ __('profile.traits.not_specified') }}</p>

        <SiteModal :show="editModal" variant="pink" :compact="true" @close="editModal = false">
            <div class="edit-form">
                <h3 class="edit-title">{{ __('profile.traits.title') }}</h3>
                <p class="edit-hint">{{ __('profile.traits.subtitle') }}</p>

                <Transition name="view-slide" mode="out-in">
                <div v-if="view === 'list'" key="list">
                    <div class="search-row">
                        <input
                            v-model="traitSearch"
                            type="text"
                            class="search-input"
                            :placeholder="__('profile.traits.search')"
                        />
                        <button class="suggest-btn" @click="view = 'suggest'">{{ __('profile.traits.suggest_btn') }}</button>
                    </div>
                    <div class="trait-grid">
                        <button
                            v-for="t in filteredTraits"
                            :key="t.id"
                            type="button"
                            class="trait-btn"
                            :class="{ active: selected.has(t.id) }"
                            @click="toggleTrait(t.id)"
                            :disabled="!selected.has(t.id) && selected.size >= 10"
                        >{{ displayTrait(t) }}</button>
                    </div>
                    <p v-if="filteredTraits.length === 0" class="no-results">{{ __('profile.traits.not_found') }}</p>
                    <button class="save-btn" :disabled="form.processing" @click="submit">
                        {{ __('common.save') }} ({{ selected.size }})
                    </button>
                </div>

                <div v-else key="suggest" class="suggest-form">
                    <button class="back-btn" @click="view = 'list'">{{ __('profile.traits.back') }}</button>
                    <h4 class="suggest-title">{{ __('profile.traits.suggest.title') }}</h4>
                    <p class="suggest-hint">{{ __('profile.traits.suggest.hint') }}</p>
                    <textarea
                        v-model="suggestionText"
                        class="suggestion-textarea"
                        maxlength="100"
                        rows="3"
                        :placeholder="__('profile.traits.suggest.ph')"
                    />
                    <div class="suggest-footer">
                        <span class="char-count">{{ suggestionText.length }}/100</span>
                        <button class="save-btn suggest-submit-btn" :disabled="!suggestionText.trim() || suggForm.processing" @click="submitSuggestion">
                            {{ __('common.send') }}
                        </button>
                    </div>
                    <p v-if="suggSuccess" class="sugg-success">{{ __('profile.traits.sent') }}</p>
                </div>
                </Transition>
            </div>
        </SiteModal>
    </div>
</template>

<style scoped>
.block-section {
    padding: 1.5rem 2rem;
    position: relative;
    cursor: default;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.9rem;
}

.section-title {
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--color-base-1);
}

.edit-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 26px; height: 26px;
    padding: 0;
    border-radius: var(--profile-border-radius, 8px);
    border: 1px solid transparent;
    background: transparent;
    cursor: pointer;
    color: rgba(255,255,255,0.25);
    font-size: 0.95rem;
    opacity: 0.75;
    transition: opacity 0.18s, color 0.18s, border-color 0.18s, background 0.18s, box-shadow 0.18s;
}
.block-section:hover .edit-btn {
    opacity: 1;
    color: color-mix(in srgb, var(--color-base-1), transparent 20%);
    border-color: color-mix(in srgb, var(--color-base-1), transparent 65%);
    background: color-mix(in srgb, var(--color-base-1), transparent 92%);
}
.edit-btn:hover {
    color: var(--color-base-1);
    border-color: color-mix(in srgb, var(--color-base-1), transparent 30%);
    background: color-mix(in srgb, var(--color-base-1), transparent 84%);
    box-shadow: 0 0 8px color-mix(in srgb, var(--color-base-1), transparent 65%);
}

.tags-row { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.tag {
    padding: 0.28rem 0.65rem;
    border-radius: var(--profile-border-radius, 8px);
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.8);
    font-size: 1rem;
}
.empty { color: rgba(255,255,255,0.25); font-size: 1rem; font-style: italic; margin: 0; }

/* ── Форма редактирования ─────────────────────────────────── */
.edit-form { padding: 0.5rem 0.25rem; }
.edit-title { font-size: 1.1rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0 0 0.25rem; }
.edit-hint { font-size: 0.82rem; color: rgba(255,255,255,0.35); margin: 0 0 0.75rem; }

.search-row {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 0.75rem;
}
.search-input {
    flex: 1;
    padding: 0.55rem 0.9rem;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    border-radius: var(--profile-border-radius, 8px); color: rgba(255,255,255,0.88); font-size: 0.9rem; font-family: inherit;
    box-sizing: border-box; outline: none; transition: border-color 0.15s;
}
.search-input::placeholder { color: rgba(255,255,255,0.25); }
.search-input:focus { border-color: color-mix(in srgb, var(--color-base-1), transparent 50%); }

.suggest-btn {
    flex-shrink: 0;
    padding: 0.5rem 0.85rem;
    border-radius: var(--profile-border-radius, 8px);
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 70%);
    background: color-mix(in srgb, var(--color-base-1), transparent 93%);
    color: color-mix(in srgb, var(--color-base-1), transparent 15%);
    font-size: 0.85rem;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.15s, border-color 0.15s;
    white-space: nowrap;
}
.suggest-btn:hover { background: color-mix(in srgb, var(--color-base-1), transparent 85%); border-color: color-mix(in srgb, var(--color-base-1), transparent 50%); }

.trait-grid { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1rem; }
.trait-btn {
    padding: 0.3rem 0.75rem;
    border-radius: var(--profile-border-radius, 8px);
    border: 1px solid rgba(255,255,255,0.1);
    background: transparent;
    color: rgba(255,255,255,0.5);
    font-size: 0.9rem;
    cursor: pointer;
    font-family: inherit;
    transition: border-color 0.15s, background 0.15s, color 0.15s;
}
.trait-btn.active { border-color: color-mix(in srgb, var(--color-base-1), transparent 45%); background: color-mix(in srgb, var(--color-base-1), transparent 90%); color: #fff; }
.trait-btn:disabled:not(.active) { opacity: 0.3; cursor: not-allowed; }
.no-results { color: rgba(255,255,255,0.3); font-size: 0.9rem; text-align: center; padding: 0.5rem 0 1rem; margin: 0; }
.save-btn {
    width: 100%; padding: 0.75rem;
    border-radius: var(--profile-border-radius, 8px); border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 60%);
    background: color-mix(in srgb, var(--color-base-1), transparent 90%);
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.save-btn:hover:not(:disabled) { background: color-mix(in srgb, var(--color-base-1), transparent 80%); }
.save-btn:disabled { opacity: 0.45; cursor: not-allowed; }

/* ── Форма предложения черты ─────────────────────────────── */
.suggest-form { display: flex; flex-direction: column; gap: 0.6rem; }

.back-btn {
    align-self: flex-start;
    background: none;
    border: none;
    padding: 0;
    color: rgba(255,255,255,0.4);
    font-size: 0.88rem;
    cursor: pointer;
    font-family: inherit;
    transition: color 0.15s;
}
.back-btn:hover { color: rgba(255,255,255,0.7); }

.suggest-title { font-size: 1rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0; }
.suggest-hint { font-size: 0.82rem; color: rgba(255,255,255,0.35); margin: 0; }

.suggestion-textarea {
    width: 100%;
    padding: 0.6rem 0.9rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: var(--profile-border-radius, 8px);
    color: rgba(255,255,255,0.88);
    font-size: 0.9rem;
    font-family: inherit;
    box-sizing: border-box;
    outline: none;
    resize: vertical;
    transition: border-color 0.15s;
}
.suggestion-textarea::placeholder { color: rgba(255,255,255,0.25); }
.suggestion-textarea:focus { border-color: color-mix(in srgb, var(--color-base-1), transparent 60%); }

.suggest-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
}
.char-count { font-size: 0.78rem; color: rgba(255,255,255,0.3); }

.suggest-submit-btn { width: auto; padding: 0.55rem 1.25rem; }

.sugg-success { color: #6ee7b7; font-size: 0.88rem; margin: 0; text-align: center; }

.view-slide-enter-active { transition: opacity 0.22s ease, transform 0.22s ease; }
.view-slide-leave-active  { transition: opacity 0.15s ease, transform 0.15s ease; }
.view-slide-enter-from    { opacity: 0; transform: translateX(16px); }
.view-slide-leave-to      { opacity: 0; transform: translateX(-16px); }
</style>
