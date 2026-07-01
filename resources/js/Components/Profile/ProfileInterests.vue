<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { EditPen } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import { useTranslations } from '@/composables/useTranslations';

const { __, locale } = useTranslations();

const props = defineProps({
    interests:     { default: null },
    allCategories: { default: null },
    isOwner:       { type: Boolean, default: false },
});

const editModal = ref(false);
const openCat = ref(null);
const interestSearch = ref('');

const selected = ref(new Set(Array.isArray(props.interests) ? props.interests.map(i => i.id) : []));
const form = useForm({ interest_ids: [] });

const view = ref('list');
const suggestionText = ref('');
const suggSuccess = ref(false);
const suggForm = useForm({ name: '' });

function localName(item) {
    return locale.value?.current === 'en' && item.name_en ? item.name_en : item.name_ru;
}

const filteredCategories = computed(() => {
    if (!Array.isArray(props.allCategories)) return [];
    const q = interestSearch.value.trim().toLowerCase();
    if (!q) return props.allCategories;
    return props.allCategories
        .map(cat => ({
            ...cat,
            interests: cat.interests.filter(i =>
                i.name_ru.toLowerCase().includes(q) ||
                (i.name_en && i.name_en.toLowerCase().includes(q)) ||
                cat.name_ru.toLowerCase().includes(q) ||
                (cat.name_en && cat.name_en.toLowerCase().includes(q))
            )
        }))
        .filter(cat => cat.interests.length > 0);
});

const expandedCats = computed(() => {
    if (interestSearch.value.trim()) {
        return new Set(filteredCategories.value.map(c => c.id));
    }
    return openCat.value ? new Set([openCat.value]) : new Set();
});

function toggleInterest(id) {
    if (selected.value.has(id)) selected.value.delete(id);
    else selected.value.add(id);
}

function toggleCat(id) {
    openCat.value = openCat.value === id ? null : id;
}

function submit() {
    form.interest_ids = [...selected.value];
    form.patch(route('profile.update.interests'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => editModal.value = false,
    });
}

function openEdit() {
    selected.value = new Set(Array.isArray(props.interests) ? props.interests.map(i => i.id) : []);
    interestSearch.value = '';
    openCat.value = null;
    view.value = 'list';
    suggestionText.value = '';
    suggSuccess.value = false;
    editModal.value = true;
}

function submitSuggestion() {
    suggForm.name = suggestionText.value.trim();
    suggForm.post(route('profile.interest-suggestions.store'), {
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
    <div id="tour-interests" class="block-section">
        <div class="section-header">
            <span class="section-title">{{ __('profile.interests.title') }}</span>
            <button v-if="isOwner" class="edit-btn" @click="openEdit" :title="__('common.edit')">
                <el-icon><EditPen /></el-icon>
            </button>
        </div>

        <div v-if="interests?.length" class="tags-row">
            <span v-for="i in interests" :key="i.id" class="tag">{{ localName(i) }}</span>
        </div>
        <p v-else-if="isOwner" class="empty">{{ __('profile.interests.empty') }}</p>
        <p v-else class="empty">{{ __('profile.interests.not_specified') }}</p>

        <SiteModal :show="editModal" variant="pink" :compact="true" @close="editModal = false">
            <div class="edit-form">
                <h3 class="edit-title">{{ __('profile.interests.title') }}</h3>

                <Transition name="view-slide" mode="out-in">
                <div v-if="view === 'list'" key="list">
                    <p class="edit-hint">{{ __('profile.interests.subtitle') }}</p>

                    <input
                        v-model="interestSearch"
                        type="text"
                        class="search-input"
                        :placeholder="__('profile.interests.search')"
                    />

                    <div class="categories">
                        <template v-if="filteredCategories.length">
                            <div v-for="cat in filteredCategories" :key="cat.id" class="cat-block">
                                <button type="button" class="cat-header" @click="toggleCat(cat.id)">
                                    <span>{{ localName(cat) }}</span>
                                    <span class="cat-count" v-if="cat.interests.some(i => selected.has(i.id))">
                                        ({{ cat.interests.filter(i => selected.has(i.id)).length }})
                                    </span>
                                    <span class="cat-arrow" :class="{ open: expandedCats.has(cat.id) }">›</span>
                                </button>
                                <div class="cat-interests-wrapper" :class="{ 'is-open': expandedCats.has(cat.id) }">
                                    <div class="cat-interests-inner">
                                        <div class="cat-interests">
                                            <button
                                                v-for="i in cat.interests"
                                                :key="i.id"
                                                type="button"
                                                class="interest-btn"
                                                :class="{ active: selected.has(i.id) }"
                                                @click="toggleInterest(i.id)"
                                                :disabled="!selected.has(i.id) && selected.size >= 10"
                                            >{{ localName(i) }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <p v-else class="no-results">{{ __('common.not_found') }}</p>
                    </div>

                    <div class="list-footer">
                        <button class="suggest-btn" @click="view = 'suggest'">{{ __('profile.interests.suggest_btn') }}</button>
                        <button class="save-btn save-btn--inline" :disabled="form.processing" @click="submit">
                            {{ __('common.save') }} ({{ selected.size }}/10)
                        </button>
                    </div>
                </div>

                <div v-else key="suggest" class="suggest-form">
                    <button class="back-btn" @click="view = 'list'">{{ __('profile.interests.back') }}</button>
                    <h4 class="suggest-title">{{ __('profile.interests.suggest.title') }}</h4>
                    <p class="suggest-hint">{{ __('profile.interests.suggest.hint') }}</p>
                    <textarea
                        v-model="suggestionText"
                        class="suggestion-textarea"
                        maxlength="100"
                        rows="3"
                        :placeholder="__('profile.interests.suggest.ph')"
                    />
                    <div class="suggest-footer">
                        <span class="char-count">{{ suggestionText.length }}/100</span>
                        <button class="save-btn suggest-submit-btn" :disabled="!suggestionText.trim() || suggForm.processing" @click="submitSuggestion">
                            {{ __('common.send') }}
                        </button>
                    </div>
                    <p v-if="suggSuccess" class="sugg-success">{{ __('profile.interests.sent') }}</p>
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
.search-input {
    width: 100%; padding: 0.55rem 0.9rem; margin-bottom: 0.75rem;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    border-radius: var(--profile-border-radius, 8px); color: rgba(255,255,255,0.88); font-size: 0.9rem; font-family: inherit;
    box-sizing: border-box; outline: none; transition: border-color 0.15s;
}
.search-input::placeholder { color: rgba(255,255,255,0.25); }
.search-input:focus { border-color: color-mix(in srgb, var(--color-base-1), transparent 50%); }
.categories { display: flex; flex-direction: column; gap: 0.2rem; margin-bottom: 1rem; }
.cat-block { border: 1px solid rgba(255,255,255,0.07); border-radius: var(--profile-border-radius, 8px); }
.cat-header {
    width: 100%; display: flex; align-items: center;
    padding: 0.6rem 0.9rem;
    background: rgba(255,255,255,0.03); border: none; color: rgba(255,255,255,0.75);
    font-size: 0.9rem; cursor: pointer; font-family: inherit; text-align: left;
    border-radius: var(--profile-border-radius, 8px);
}
.cat-count { color: color-mix(in srgb, var(--color-base-1), transparent 25%); font-size: 0.8rem; margin-left: 0.4rem; }
.cat-arrow { color: rgba(255,255,255,0.35); font-size: 1.1rem; transition: transform 0.2s; margin-left: auto; }
.cat-arrow.open { transform: rotate(90deg); }
.cat-interests-wrapper {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.cat-interests-wrapper.is-open {
    grid-template-rows: 1fr;
}
.cat-interests-inner {
    overflow: hidden;
}
.cat-interests { display: flex; flex-wrap: wrap; gap: 0.35rem; padding: 0.6rem 0.9rem; background: rgba(0,0,0,0.12); border-radius: 0 0 var(--profile-border-radius, 8px) var(--profile-border-radius, 8px); }
.interest-btn {
    padding: 0.25rem 0.65rem;
    border-radius: var(--profile-border-radius, 8px);
    border: 1px solid rgba(255,255,255,0.1); background: transparent;
    color: rgba(255,255,255,0.5); font-size: 0.88rem; cursor: pointer; font-family: inherit; transition: all 0.15s;
}
.interest-btn.active { border-color: color-mix(in srgb, var(--color-base-1), transparent 45%); background: color-mix(in srgb, var(--color-base-1), transparent 90%); color: #fff; }
.interest-btn:disabled:not(.active) { opacity: 0.3; cursor: not-allowed; }
.no-results { color: rgba(255,255,255,0.3); font-size: 0.9rem; text-align: center; padding: 1rem 0; margin: 0; }
.save-btn {
    width: 100%; padding: 0.75rem;
    border-radius: var(--profile-border-radius, 8px); border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 60%);
    background: color-mix(in srgb, var(--color-base-1), transparent 90%);
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.save-btn:hover:not(:disabled) { background: color-mix(in srgb, var(--color-base-1), transparent 80%); }
.save-btn:disabled { opacity: 0.45; cursor: not-allowed; }

.list-footer { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
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
.save-btn--inline { width: auto; padding: 0.55rem 1.25rem; }

/* ── Форма предложения интереса ──────────────────────────── */
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
.suggest-footer { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; }
.char-count { font-size: 0.78rem; color: rgba(255,255,255,0.3); }
.suggest-submit-btn { width: auto; padding: 0.55rem 1.25rem; }
.sugg-success { color: #6ee7b7; font-size: 0.88rem; margin: 0; text-align: center; }

.view-slide-enter-active { transition: opacity 0.22s ease, transform 0.22s ease; }
.view-slide-leave-active  { transition: opacity 0.15s ease, transform 0.15s ease; }
.view-slide-enter-from    { opacity: 0; transform: translateX(16px); }
.view-slide-leave-to      { opacity: 0; transform: translateX(-16px); }
</style>
