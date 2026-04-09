<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { EditPen } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';

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

const filteredTraits = computed(() => {
    if (!Array.isArray(props.allTraits)) return [];
    if (!traitSearch.value.trim()) return props.allTraits;
    const q = traitSearch.value.toLowerCase();
    return props.allTraits.filter(t => t.name_ru.toLowerCase().includes(q));
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
            <span class="section-title">Характер</span>
            <button v-if="isOwner" class="edit-btn" @click="openEdit" title="Редактировать">
                <el-icon><EditPen /></el-icon>
            </button>
        </div>

        <div v-if="traits?.length" class="tags-row">
            <span v-for="t in traits" :key="t.id" class="tag">
                {{ applyGender(t.name_ru, gender) }}
            </span>
        </div>
        <p v-else-if="isOwner" class="empty">Добавь свои черты характера</p>
        <p v-else class="empty">Не указано</p>

        <SiteModal :show="editModal" variant="pink" :compact="true" @close="editModal = false">
            <div class="edit-form">
                <h3 class="edit-title">Характер</h3>
                <p class="edit-hint">Выбери подходящие (до 10)</p>

                <Transition name="view-slide" mode="out-in">
                <div v-if="view === 'list'" key="list">
                    <div class="search-row">
                        <input
                            v-model="traitSearch"
                            type="text"
                            class="search-input"
                            placeholder="Поиск по чертам..."
                        />
                        <button class="suggest-btn" @click="view = 'suggest'">Свой вариант</button>
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
                        >{{ applyGender(t.name_ru, gender) }}</button>
                    </div>
                    <p v-if="filteredTraits.length === 0" class="no-results">Ничего не найдено</p>
                    <button class="save-btn" :disabled="form.processing" @click="submit">
                        Сохранить ({{ selected.size }})
                    </button>
                </div>

                <div v-else key="suggest" class="suggest-form">
                    <button class="back-btn" @click="view = 'list'">← Назад</button>
                    <h4 class="suggest-title">Предложить черту</h4>
                    <p class="suggest-hint">Напиши название — мы рассмотрим его и добавим, если подойдёт</p>
                    <textarea
                        v-model="suggestionText"
                        class="suggestion-textarea"
                        maxlength="100"
                        rows="3"
                        placeholder="Например: Меланхоличный..."
                    />
                    <div class="suggest-footer">
                        <span class="char-count">{{ suggestionText.length }}/100</span>
                        <button class="save-btn suggest-submit-btn" :disabled="!suggestionText.trim() || suggForm.processing" @click="submitSuggestion">
                            Отправить
                        </button>
                    </div>
                    <p v-if="suggSuccess" class="sugg-success">Предложение отправлено!</p>
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
    color: #a0a0ff;
}

.edit-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 26px; height: 26px;
    padding: 0;
    border-radius: 3px;
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
    color: rgba(160, 160, 255, 0.8);
    border-color: rgba(160, 160, 255, 0.35);
    background: rgba(160, 160, 255, 0.08);
}
.edit-btn:hover {
    color: #a0a0ff;
    border-color: rgba(160, 160, 255, 0.7);
    background: rgba(160, 160, 255, 0.16);
    box-shadow: 0 0 8px rgba(160, 160, 255, 0.35);
}

.tags-row { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.tag {
    padding: 0.28rem 0.65rem;
    border-radius: 3px;
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
    border-radius: 3px; color: rgba(255,255,255,0.88); font-size: 0.9rem; font-family: inherit;
    box-sizing: border-box; outline: none; transition: border-color 0.15s;
}
.search-input::placeholder { color: rgba(255,255,255,0.25); }
.search-input:focus { border-color: rgba(160,160,255,0.5); }

.suggest-btn {
    flex-shrink: 0;
    padding: 0.5rem 0.85rem;
    border-radius: 3px;
    border: 1px solid rgba(160,160,255,0.3);
    background: rgba(160,160,255,0.07);
    color: rgba(160,160,255,0.85);
    font-size: 0.85rem;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.15s, border-color 0.15s;
    white-space: nowrap;
}
.suggest-btn:hover { background: rgba(160,160,255,0.15); border-color: rgba(160,160,255,0.5); }

.trait-grid { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1rem; }
.trait-btn {
    padding: 0.3rem 0.75rem;
    border-radius: 3px;
    border: 1px solid rgba(255,255,255,0.1);
    background: transparent;
    color: rgba(255,255,255,0.5);
    font-size: 0.9rem;
    cursor: pointer;
    font-family: inherit;
    transition: border-color 0.15s, background 0.15s, color 0.15s;
}
.trait-btn.active { border-color: rgba(160,160,255,0.55); background: rgba(160,160,255,0.1); color: #fff; }
.trait-btn:disabled:not(.active) { opacity: 0.3; cursor: not-allowed; }
.no-results { color: rgba(255,255,255,0.3); font-size: 0.9rem; text-align: center; padding: 0.5rem 0 1rem; margin: 0; }
.save-btn {
    width: 100%; padding: 0.75rem;
    border-radius: 3px; border: 1px solid rgba(160,160,255,0.4);
    background: rgba(160,160,255,0.1);
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.save-btn:hover:not(:disabled) { background: rgba(160,160,255,0.2); }
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
    border-radius: 6px;
    color: rgba(255,255,255,0.88);
    font-size: 0.9rem;
    font-family: inherit;
    box-sizing: border-box;
    outline: none;
    resize: vertical;
    transition: border-color 0.15s;
}
.suggestion-textarea::placeholder { color: rgba(255,255,255,0.25); }
.suggestion-textarea:focus { border-color: rgba(160,160,255,0.4); }

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
