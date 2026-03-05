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
</script>

<template>
    <div>
        <h1 class="page-title">Вопросы теста (по этапам)</h1>

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

                <Transition name="stage-expand">
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
                </Transition>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ─── Page ────────────────────────────────────────────────────────── */
.page-title { font-size: 1.4rem; color: #fff; margin: 0 0 1.5rem; }

/* ─── Stages list ─────────────────────────────────────────────────── */
.stages-list { display: flex; flex-direction: column; gap: 0.75rem; }

.stage-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 12px;
    overflow: hidden;
}

/* ─── Stage header ────────────────────────────────────────────────── */
.stage-header {
    width: 100%; display: flex; align-items: center; gap: 0.9rem;
    padding: 0.85rem 1.25rem;
    background: transparent; border: none;
    color: #fff; cursor: pointer;
    transition: background 0.15s;
}
.stage-header:hover,
.stage-header.is-open { background: rgba(200,70,126,0.06); }

.stage-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 2rem; height: 2rem; border-radius: 8px; flex-shrink: 0;
    font-size: 0.8rem; font-weight: 700; letter-spacing: 0.04em;
    background: linear-gradient(135deg, rgba(200,70,126,0.7) 0%, rgba(150,40,100,0.7) 100%);
    color: #fff;
}

.stage-title { font-weight: 600; font-size: 0.95rem; }

.stage-count-pill {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 1.5rem; height: 1.3rem; padding: 0 0.45rem;
    border-radius: 99px; font-size: 0.72rem; font-weight: 600;
    background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.5);
}

.stage-chevron {
    width: 1rem; height: 1rem;
    margin-left: auto; color: rgba(255,255,255,0.3);
    transition: transform 0.22s ease;
    flex-shrink: 0;
}
.stage-chevron.is-open { transform: rotate(180deg); }

/* ─── Stage body animation ────────────────────────────────────────── */
.stage-expand-enter-active,
.stage-expand-leave-active {
    transition: opacity 0.22s ease, transform 0.22s ease;
    transform-origin: top;
}
.stage-expand-enter-from,
.stage-expand-leave-to {
    opacity: 0;
    transform: scaleY(0.95) translateY(-4px);
}

.stage-body {
    padding: 0 1.25rem 1.25rem;
    display: flex; flex-direction: column; gap: 0.75rem;
}

/* ─── Empty state ─────────────────────────────────────────────────── */
.empty-state {
    display: flex; align-items: center; gap: 0.6rem;
    padding: 0.9rem 1rem;
    border-radius: 8px;
    background: rgba(255,255,255,0.02);
    border: 1px dashed rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.3); font-size: 0.82rem;
}
.empty-state svg { width: 1.1rem; height: 1.1rem; flex-shrink: 0; }

/* ─── Question item ───────────────────────────────────────────────── */
.question-item {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 10px;
    padding: 1rem;
    transition: border-color 0.15s;
    position: relative;
}
.question-item:hover { border-color: rgba(255,255,255,0.12); }
.question-item:hover .q-actions { opacity: 1; }

/* ─── Question view ───────────────────────────────────────────────── */
.question-view { position: relative; }

.q-index {
    position: absolute; top: 0; right: 0;
    font-size: 0.7rem; color: rgba(255,255,255,0.2);
    font-weight: 600; letter-spacing: 0.05em;
}

.q-text {
    color: rgba(255,255,255,0.9); font-size: 0.92rem;
    margin: 0 0 0.65rem; padding-right: 2rem; line-height: 1.5;
}

.q-options {
    list-style: none; padding: 0; margin: 0 0 0.75rem;
    display: flex; flex-direction: column; gap: 0.3rem;
}
.q-options li {
    display: flex; align-items: center; gap: 0.55rem;
    font-size: 0.83rem; color: rgba(255,255,255,0.45);
    padding: 0.3rem 0.6rem 0.3rem 0.4rem; border-radius: 6px;
    background: rgba(255,255,255,0.02); border: 1px solid transparent;
}
.q-option--correct {
    color: rgba(255,255,255,0.85);
    background: rgba(76,222,143,0.07);
    border-color: rgba(76,222,143,0.18);
}

.opt-letter {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.3rem; height: 1.3rem; border-radius: 4px; flex-shrink: 0;
    font-size: 0.68rem; font-weight: 700; letter-spacing: 0.03em;
    background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.35);
    border: 1px solid rgba(255,255,255,0.08);
    transition: background 0.15s, color 0.15s;
}
.opt-letter.is-correct {
    background: rgba(76,222,143,0.18); color: #4cde8f;
    border-color: rgba(76,222,143,0.35);
}

.opt-text { flex: 1; }

.correct-badge {
    margin-left: auto; font-size: 0.68rem; font-weight: 600;
    color: #4cde8f;
    background: rgba(76,222,143,0.12); border: 1px solid rgba(76,222,143,0.25);
    padding: 0.1rem 0.4rem; border-radius: 4px;
    white-space: nowrap;
}

/* ─── Question actions (icon buttons) ────────────────────────────── */
.q-actions {
    display: flex; gap: 0.4rem; justify-content: flex-end;
    opacity: 0; transition: opacity 0.15s;
}
.btn-edit, .btn-delete {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.9rem; height: 1.9rem; border-radius: 7px;
    background: transparent; border: 1px solid transparent; cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}
.btn-edit svg, .btn-delete svg { width: 0.9rem; height: 0.9rem; }

.btn-edit {
    color: #C8467E; border-color: rgba(200,70,126,0.25);
}
.btn-edit:hover { background: rgba(200,70,126,0.12); border-color: rgba(200,70,126,0.45); }

.btn-delete {
    color: #ff6b6b; border-color: rgba(255,80,80,0.2);
}
.btn-delete:hover { background: rgba(255,80,80,0.1); border-color: rgba(255,80,80,0.4); }

/* ─── Edit form ───────────────────────────────────────────────────── */
.question-edit-form {
    display: flex; flex-direction: column; gap: 0.65rem;
    border: 1px solid rgba(108,99,255,0.35);
    background: rgba(108,99,255,0.04);
    border-radius: 10px; padding: 1rem;
    margin: -1rem;
}

.edit-form-header {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.82rem; font-weight: 600; color: rgba(150,140,255,0.9);
    margin-bottom: 0.1rem;
}
.edit-form-header svg { width: 0.9rem; height: 0.9rem; flex-shrink: 0; }

/* ─── New question separator + form ──────────────────────────────── */
.new-question-separator {
    display: flex; align-items: center; gap: 0.75rem;
    margin-top: 0.25rem; color: rgba(255,255,255,0.18); font-size: 0.75rem; font-weight: 600;
}
.new-question-separator::before,
.new-question-separator::after {
    content: ''; flex: 1; height: 1px;
    background: repeating-linear-gradient(90deg, rgba(200,70,126,0.25) 0, rgba(200,70,126,0.25) 4px, transparent 4px, transparent 8px);
}
.new-question-separator span { color: #C8467E; white-space: nowrap; }

.new-question-form {
    display: flex; flex-direction: column; gap: 0.65rem;
    background: rgba(200,70,126,0.03);
    border: 1px solid rgba(200,70,126,0.12);
    border-radius: 10px; padding: 1rem;
}

.new-form-header {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.82rem; font-weight: 600; color: #C8467E;
    margin-bottom: 0.1rem;
}
.new-form-header svg { width: 0.9rem; height: 0.9rem; flex-shrink: 0; }

/* ─── Shared form elements ────────────────────────────────────────── */
.q-input {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 7px; color: #fff;
    padding: 0.5rem 0.75rem; font-size: 0.88rem;
    outline: none; transition: border-color 0.15s;
}
.q-input:focus { border-color: rgba(200,70,126,0.45); }
.opt-input { flex: 1; }

.option-row { display: flex; align-items: center; gap: 0.5rem; }
.option-label-wrap { display: flex; align-items: center; gap: 0.35rem; flex-shrink: 0; cursor: pointer; }
.radio-correct { display: none; }

.btn-remove-opt {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.6rem; height: 1.6rem; border-radius: 5px; flex-shrink: 0;
    background: transparent; border: none; color: rgba(255,80,80,0.45); cursor: pointer;
    transition: color 0.15s;
}
.btn-remove-opt:hover { color: #ff6b6b; }
.btn-remove-opt svg { width: 0.75rem; height: 0.75rem; }

.btn-add-opt {
    align-self: flex-start; padding: 0.25rem 0.65rem; border-radius: 6px; font-size: 0.78rem;
    border: 1px dashed rgba(255,255,255,0.18); color: rgba(255,255,255,0.35); background: transparent; cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
}
.btn-add-opt:hover { border-color: rgba(255,255,255,0.35); color: rgba(255,255,255,0.6); }

.edit-actions { display: flex; gap: 0.5rem; }

.btn-save {
    padding: 0.4rem 0.9rem; border-radius: 7px; font-size: 0.82rem;
    background: rgba(76,222,143,0.1); border: 1px solid rgba(76,222,143,0.35); color: #4cde8f; cursor: pointer;
    transition: background 0.15s;
}
.btn-save:hover { background: rgba(76,222,143,0.18); }
.btn-save:disabled { opacity: 0.4; cursor: default; }

.btn-cancel {
    padding: 0.4rem 0.9rem; border-radius: 7px; font-size: 0.82rem;
    background: transparent; border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.4); cursor: pointer;
    transition: background 0.15s;
}
.btn-cancel:hover { background: rgba(255,255,255,0.04); }

.btn-add-question {
    align-self: flex-start; padding: 0.42rem 1rem; border-radius: 7px; font-size: 0.85rem; font-weight: 500;
    background: rgba(200,70,126,0.1); border: 1px solid rgba(200,70,126,0.35); color: #C8467E; cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}
.btn-add-question:hover { background: rgba(200,70,126,0.18); border-color: rgba(200,70,126,0.5); }
.btn-add-question:disabled { opacity: 0.4; cursor: default; }
</style>
