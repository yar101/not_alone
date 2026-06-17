<script setup>
import { ref, reactive } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    questions_by_stage: Object,
});

const expandedStages = ref(new Set([1]));
function toggleStage(stage) {
    if (expandedStages.value.has(stage)) {
        expandedStages.value.delete(stage);
    } else {
        expandedStages.value.add(stage);
    }
}

// New question form state per stage
const newForms = reactive({});
for (let i = 1; i <= 10; i++) {
    newForms[i] = useForm({
        stage: i,
        question: '',
        options: ['', '', '', ''],
        correct_option_index: 0,
        sort_order: 0,
    });
}

function addOption(form) {
    if (form.options.length < 6) form.options.push('');
}
function removeOption(form, idx) {
    if (form.options.length > 2) {
        form.options.splice(idx, 1);
        if (form.correct_option_index >= form.options.length) form.correct_option_index = 0;
    }
}

function submitNew(stage) {
    const form = newForms[stage];
    form.post(route('admin.quiz.questions.store'), {
        onSuccess: () => {
            form.reset();
            form.options = ['', '', '', ''];
            form.stage = stage;
        },
    });
}

function deleteQuestion(id) {
    if (!confirm('Удалить вопрос?')) return;
    router.delete(route('admin.quiz.questions.destroy', id));
}

// Edit mode
const editingId = ref(null);
const editForm = ref(null);

function startEdit(question) {
    editingId.value = question.id;
    editForm.value = useForm({
        stage: question.stage,
        question: question.question,
        options: [...question.options],
        correct_option_index: question.correct_option_index,
        sort_order: question.sort_order,
    });
}

function cancelEdit() {
    editingId.value = null;
    editForm.value = null;
}

function submitEdit(id) {
    editForm.value.patch(route('admin.quiz.questions.update', id), {
        onSuccess: () => cancelEdit(),
    });
}

const stages = Array.from({ length: 10 }, (_, i) => i + 1);

// ── Import / Export ──────────────────────────────────────
const importFile   = ref(null);
const importMode   = ref('append');
const importError  = ref('');
const importFileInput = ref(null);

function triggerImport() {
    importFileInput.value?.click();
}

function onImportFileChange(e) {
    const f = e.target.files?.[0];
    if (!f) return;
    if (!f.name.endsWith('.json')) {
        importError.value = 'Выберите файл .json';
        return;
    }
    importError.value = '';
    importFile.value = f;
    submitImport();
}

function submitImport() {
    if (!importFile.value) return;
    const form = new FormData();
    form.append('file', importFile.value);
    form.append('mode', importMode.value);
    router.post(route('admin.quiz.questions.import'), form, {
        forceFormData: true,
        onError: (errors) => { importError.value = errors.file ?? 'Ошибка импорта'; },
        onSuccess: () => { importFile.value = null; importError.value = ''; },
        onFinish: () => { if (importFileInput.value) importFileInput.value.value = ''; },
    });
}
</script>

<template>
    <div>
        <h1 class="page-title">Вопросы теста (по этапам)</h1>

        <!-- ── Import / Export panel ───────────────────────── -->
        <div class="io-panel">
            <div class="io-panel__left">
                <span class="io-label">Импорт / Экспорт вопросов (JSON)</span>
            </div>
            <div class="io-panel__right">
                <select v-model="importMode" class="io-select">
                    <option value="append">Добавить к существующим</option>
                    <option value="replace">Заменить все вопросы</option>
                </select>
                <input
                    ref="importFileInput"
                    type="file"
                    accept=".json"
                    class="io-file-hidden"
                    @change="onImportFileChange"
                />
                <button class="io-btn io-btn--import" @click="triggerImport">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Импорт
                </button>
                <a :href="route('admin.quiz.questions.export')" class="io-btn io-btn--export" download>
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 21V9m0 12l-4-4m4 4l4-4M4 7V5a2 2 0 012-2h12a2 2 0 012 2v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Экспорт
                </a>
            </div>
            <p v-if="importError" class="io-error">{{ importError }}</p>
        </div>

        <div class="stages-list">
            <div v-for="stage in stages" :key="stage" class="stage-card">
                <button class="stage-header" @click="toggleStage(stage)" :class="{ 'is-open': expandedStages.has(stage) }">
                    <span class="stage-badge">{{ String(stage).padStart(2, '0') }}</span>
                    <span class="stage-title">Этап {{ stage }}</span>
                    <span class="stage-count-pill">{{ (questions_by_stage[stage] || []).length }}</span>
                    <svg class="stage-chevron" :class="{ 'is-open': expandedStages.has(stage) }" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <div v-if="expandedStages.has(stage)" class="stage-body">
                        <!-- Existing questions -->
                        <div v-if="(questions_by_stage[stage] || []).length === 0" class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <span>Нет вопросов в этом этапе</span>
                        </div>

                        <div v-for="(q, qIndex) in (questions_by_stage[stage] || [])" :key="q.id" class="question-item">
                            <div v-if="editingId === q.id" class="question-edit-form">
                                <div class="edit-form-header">
                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span>Редактирование вопроса #{{ qIndex + 1 }}</span>
                                </div>
                                <input v-model="editForm.question" class="q-input" placeholder="Текст вопроса" />
                                <div v-for="(opt, i) in editForm.options" :key="i" class="option-row">
                                    <label class="option-label-wrap">
                                        <input
                                            type="radio"
                                            :name="`edit-correct-${q.id}`"
                                            :value="i"
                                            v-model="editForm.correct_option_index"
                                            class="radio-correct"
                                        />
                                        <span class="opt-letter" :class="{ 'is-correct': i === editForm.correct_option_index }">{{ 'ABCDEF'[i] }}</span>
                                    </label>
                                    <input v-model="editForm.options[i]" class="q-input opt-input" :placeholder="`Вариант ${i+1}`" />
                                    <button @click="removeOption(editForm, i)" class="btn-remove-opt" title="Удалить вариант">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </button>
                                </div>
                                <button @click="addOption(editForm)" class="btn-add-opt" v-if="editForm.options.length < 6">+ Вариант</button>
                                <div class="edit-actions">
                                    <button @click="submitEdit(q.id)" class="btn-save" :disabled="editForm.processing">Сохранить</button>
                                    <button @click="cancelEdit" class="btn-cancel">Отмена</button>
                                </div>
                            </div>

                            <div v-else class="question-view">
                                <span class="q-index">#{{ qIndex + 1 }}</span>
                                <p class="q-text">{{ q.question }}</p>
                                <ul class="q-options">
                                    <li v-for="(opt, i) in q.options" :key="i" :class="{ 'q-option--correct': i === q.correct_option_index }">
                                        <span class="opt-letter" :class="{ 'is-correct': i === q.correct_option_index }">{{ 'ABCDEF'[i] }}</span>
                                        <span class="opt-text">{{ opt }}</span>
                                        <span v-if="i === q.correct_option_index" class="correct-badge">✓ верный</span>
                                    </li>
                                </ul>
                                <div class="q-actions">
                                    <button @click="startEdit(q)" class="btn-edit" title="Редактировать">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                    <button @click="deleteQuestion(q.id)" class="btn-delete" title="Удалить">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <polyline points="3 6 5 6 21 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M10 11v6M14 11v6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Add new question form -->
                        <div class="new-question-separator">
                            <span>Новый вопрос</span>
                        </div>
                        <div class="new-question-form">
                            <div class="new-form-header">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M12 8v8M8 12h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <span>Добавить вопрос для этапа {{ stage }}</span>
                            </div>
                            <input v-model="newForms[stage].question" class="q-input" placeholder="Текст вопроса" />
                            <div v-for="(opt, i) in newForms[stage].options" :key="i" class="option-row">
                                <label class="option-label-wrap">
                                    <input
                                        type="radio"
                                        :name="`new-correct-${stage}`"
                                        :value="i"
                                        v-model="newForms[stage].correct_option_index"
                                        class="radio-correct"
                                    />
                                    <span class="opt-letter" :class="{ 'is-correct': i === newForms[stage].correct_option_index }">{{ 'ABCDEF'[i] }}</span>
                                </label>
                                <input v-model="newForms[stage].options[i]" class="q-input opt-input" :placeholder="`Вариант ${i+1}`" />
                                <button @click="removeOption(newForms[stage], i)" class="btn-remove-opt" v-if="newForms[stage].options.length > 2" title="Удалить вариант">
                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </button>
                            </div>
                            <button @click="addOption(newForms[stage])" class="btn-add-opt" v-if="newForms[stage].options.length < 6">+ Вариант</button>
                            <button @click="submitNew(stage)" class="btn-add-question" :disabled="newForms[stage].processing">
                                Добавить вопрос
                            </button>
                        </div>
                    </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ─── Page ────────────────────────────────────────────────────────── */
.page-title { font-size: 1.4rem; color: #fff; margin: 0 0 1.5rem; }

/* ─── Import / Export ────────────────────────────────────────────── */
.io-panel {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.io-panel__left {
    display: flex;
    align-items: center;
}

.io-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.85);
}

.io-panel__right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.io-select {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #fff;
    padding: 0.45rem 2rem 0.45rem 0.7rem;
    font-size: 0.85rem;
    outline: none;
    font-family: inherit;
    border-radius: 4px;
    cursor: pointer;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.25em 1.25em;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}
.io-select option {
    background: #1a1625;
    color: #fff;
}
.io-select:focus {
    border-color: rgba(155, 110, 232, 0.55);
}

.io-file-hidden {
    display: none !important;
}

.io-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 0.9rem;
    font-size: 0.85rem;
    font-weight: 500;
    border-radius: 4px;
    cursor: pointer;
    font-family: inherit;
    text-decoration: none;
    transition: all 0.2s ease;
}

.io-btn svg {
    width: 1rem;
    height: 1rem;
    flex-shrink: 0;
}

.io-btn--import {
    background: rgba(155, 110, 232, 0.1);
    border: 1px solid rgba(155, 110, 232, 0.4);
    color: #c084fc;
}
.io-btn--import:hover {
    background: rgba(155, 110, 232, 0.2);
    border-color: rgba(155, 110, 232, 0.6);
}

.io-btn--export {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.8);
}
.io-btn--export:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.25);
    color: #fff;
}

.io-error {
    width: 100%;
    margin: 0;
    font-size: 0.8rem;
    color: #ff6b6b;
    border-top: 1px solid rgba(255, 80, 80, 0.15);
    padding-top: 0.5rem;
}

/* ─── Stages list ─────────────────────────────────────────────────── */
.stages-list { display: flex; flex-direction: column; gap: 2px; }

.stage-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.1);
    overflow: hidden;
}

/* ─── Stage header ────────────────────────────────────────────────── */
.stage-header {
    width: 100%; display: flex; align-items: center; gap: 0.9rem;
    padding: 0.75rem 1rem;
    background: transparent; border: none;
    color: #fff; cursor: pointer;
}
.stage-header:hover,
.stage-header.is-open { background: rgba(155,110,232,0.08); }

.stage-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.9rem; height: 1.9rem; flex-shrink: 0;
    font-size: 0.78rem; font-weight: 700; letter-spacing: 0.04em;
    background: rgba(155,110,232,0.6);
    color: #fff;
}

.stage-title { font-weight: 600; font-size: 0.92rem; }

.stage-count-pill {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 1.4rem; height: 1.25rem; padding: 0 0.4rem;
    font-size: 0.72rem; font-weight: 600;
    background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.5);
    border: 1px solid rgba(255,255,255,0.1);
}

.stage-chevron {
    width: 1rem; height: 1rem;
    margin-left: auto; color: rgba(255,255,255,0.3);
    flex-shrink: 0;
}
.stage-chevron.is-open { transform: rotate(180deg); }

.stage-body {
    padding: 0 1rem 1rem;
    display: flex; flex-direction: column; gap: 0.5rem;
    border-top: 1px solid rgba(255,255,255,0.07);
}

/* ─── Empty state ─────────────────────────────────────────────────── */
.empty-state {
    display: flex; align-items: center; gap: 0.6rem;
    padding: 0.75rem 0.85rem;
    background: rgba(255,255,255,0.02);
    border: 1px dashed rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.3); font-size: 0.82rem;
}
.empty-state svg { width: 1rem; height: 1rem; flex-shrink: 0; }

/* ─── Question item ───────────────────────────────────────────────── */
.question-item {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.08);
    padding: 0.85rem;
    position: relative;
}
.question-item:hover { border-color: rgba(255,255,255,0.15); }
.question-item:hover .q-actions { opacity: 1; }

/* ─── Question view ───────────────────────────────────────────────── */
.question-view { position: relative; }

.q-index {
    position: absolute; top: 0; right: 0;
    font-size: 0.7rem; color: rgba(255,255,255,0.2);
    font-weight: 600; letter-spacing: 0.05em;
}

.q-text {
    color: rgba(255,255,255,0.9); font-size: 0.9rem;
    margin: 0 0 0.6rem; padding-right: 2rem; line-height: 1.5;
}

.q-options {
    list-style: none; padding: 0; margin: 0 0 0.65rem;
    display: flex; flex-direction: column; gap: 2px;
}
.q-options li {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.82rem; color: rgba(255,255,255,0.45);
    padding: 0.28rem 0.55rem 0.28rem 0.35rem;
    background: rgba(255,255,255,0.02); border: 1px solid transparent;
}
.q-option--correct {
    color: rgba(255,255,255,0.85);
    background: rgba(76,222,143,0.07);
    border-color: rgba(76,222,143,0.2);
}

.opt-letter {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.25rem; height: 1.25rem; flex-shrink: 0;
    font-size: 0.65rem; font-weight: 700; letter-spacing: 0.03em;
    background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.35);
    border: 1px solid rgba(255,255,255,0.1);
}
.opt-letter.is-correct {
    background: rgba(76,222,143,0.18); color: #4cde8f;
    border-color: rgba(76,222,143,0.35);
}

.opt-text { flex: 1; }

.correct-badge {
    margin-left: auto; font-size: 0.65rem; font-weight: 700;
    color: #4cde8f; letter-spacing: 0.04em; text-transform: uppercase;
    background: rgba(76,222,143,0.1); border: 1px solid rgba(76,222,143,0.25);
    padding: 0.08rem 0.4rem;
    white-space: nowrap;
}

/* ─── Question actions ────────────────────────────────────────────── */
.q-actions {
    display: flex; gap: 0.35rem; justify-content: flex-end;
    opacity: 0;
}
.btn-edit, .btn-delete {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.8rem; height: 1.8rem;
    background: transparent; border: 1px solid transparent; cursor: pointer;
}
.btn-edit svg, .btn-delete svg { width: 0.85rem; height: 0.85rem; }

.btn-edit { color: #9B6EE8; border-color: rgba(155,110,232,0.3); }
.btn-edit:hover { background: rgba(155,110,232,0.14); border-color: rgba(155,110,232,0.5); }

.btn-delete { color: #ff6b6b; border-color: rgba(255,80,80,0.25); }
.btn-delete:hover { background: rgba(255,80,80,0.12); border-color: rgba(255,80,80,0.45); }

/* ─── Edit form ───────────────────────────────────────────────────── */
.question-edit-form {
    display: flex; flex-direction: column; gap: 0.6rem;
    border: 1px solid rgba(108,99,255,0.4);
    background: rgba(108,99,255,0.04);
    padding: 0.85rem;
    margin: -0.85rem;
}

.edit-form-header {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.8rem; font-weight: 600; color: rgba(150,140,255,0.9);
    margin-bottom: 0.1rem;
}
.edit-form-header svg { width: 0.85rem; height: 0.85rem; flex-shrink: 0; }

/* ─── New question separator + form ──────────────────────────────── */
.new-question-separator {
    display: flex; align-items: center; gap: 0.75rem;
    margin-top: 0.25rem; color: rgba(255,255,255,0.18); font-size: 0.75rem; font-weight: 600;
}
.new-question-separator::before,
.new-question-separator::after {
    content: ''; flex: 1; height: 1px;
    background: repeating-linear-gradient(90deg, rgba(155,110,232,0.25) 0, rgba(155,110,232,0.25) 4px, transparent 4px, transparent 8px);
}
.new-question-separator span { color: #9B6EE8; white-space: nowrap; }

.new-question-form {
    display: flex; flex-direction: column; gap: 0.6rem;
    background: rgba(155,110,232,0.03);
    border: 1px solid rgba(155,110,232,0.18);
    padding: 0.85rem;
}

.new-form-header {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.8rem; font-weight: 600; color: #9B6EE8;
    margin-bottom: 0.1rem;
}
.new-form-header svg { width: 0.85rem; height: 0.85rem; flex-shrink: 0; }

/* ─── Shared form elements ────────────────────────────────────────── */
.q-input {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.12);
    color: #fff;
    padding: 0.45rem 0.7rem; font-size: 0.87rem;
    outline: none; font-family: inherit;
}
.q-input:focus { border-color: rgba(155,110,232,0.55); }
.opt-input { flex: 1; }

.option-row { display: flex; align-items: center; gap: 0.5rem; }
.option-label-wrap { display: flex; align-items: center; gap: 0.35rem; flex-shrink: 0; cursor: pointer; }
.radio-correct { display: none; }

.btn-remove-opt {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.5rem; height: 1.5rem; flex-shrink: 0;
    background: transparent; border: none; color: rgba(255,80,80,0.45); cursor: pointer;
}
.btn-remove-opt:hover { color: #ff6b6b; }
.btn-remove-opt svg { width: 0.75rem; height: 0.75rem; }

.btn-add-opt {
    align-self: flex-start; padding: 0.22rem 0.6rem; font-size: 0.77rem;
    border: 1px dashed rgba(255,255,255,0.2); color: rgba(255,255,255,0.35); background: transparent; cursor: pointer;
    font-family: inherit;
}
.btn-add-opt:hover { border-color: rgba(255,255,255,0.38); color: rgba(255,255,255,0.65); }

.edit-actions { display: flex; gap: 0.4rem; }

.btn-save {
    padding: 0.38rem 0.85rem; font-size: 0.82rem;
    background: rgba(76,222,143,0.1); border: 1px solid rgba(76,222,143,0.4); color: #4cde8f; cursor: pointer;
    font-family: inherit;
}
.btn-save:hover { background: rgba(76,222,143,0.2); }
.btn-save:disabled { opacity: 0.4; cursor: default; }

.btn-cancel {
    padding: 0.38rem 0.85rem; font-size: 0.82rem;
    background: transparent; border: 1px solid rgba(255,255,255,0.12); color: rgba(255,255,255,0.4); cursor: pointer;
    font-family: inherit;
}
.btn-cancel:hover { background: rgba(255,255,255,0.05); }

.btn-add-question {
    align-self: flex-start; padding: 0.4rem 0.95rem; font-size: 0.84rem; font-weight: 500;
    background: rgba(155,110,232,0.1); border: 1px solid rgba(155,110,232,0.4); color: #9B6EE8; cursor: pointer;
    font-family: inherit;
}
.btn-add-question:hover { background: rgba(155,110,232,0.2); border-color: rgba(155,110,232,0.6); }
.btn-add-question:disabled { opacity: 0.4; cursor: default; }
</style>
