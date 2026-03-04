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
                <button class="stage-header" @click="toggleStage(stage)">
                    <span class="stage-num">Этап {{ stage }}</span>
                    <span class="stage-count">{{ (questions_by_stage[stage] || []).length }} вопр.</span>
                    <span class="stage-toggle">{{ expandedStages.has(stage) ? '▲' : '▼' }}</span>
                </button>

                <div v-if="expandedStages.has(stage)" class="stage-body">
                    <!-- Existing questions -->
                    <div v-for="q in (questions_by_stage[stage] || [])" :key="q.id" class="question-item">
                        <div v-if="editingId === q.id" class="question-edit-form">
                            <input v-model="editForm.question" class="q-input" placeholder="Вопрос" />
                            <div v-for="(opt, i) in editForm.options" :key="i" class="option-row">
                                <input
                                    type="radio"
                                    :name="`edit-correct-${q.id}`"
                                    :value="i"
                                    v-model="editForm.correct_option_index"
                                    class="radio-correct"
                                />
                                <input v-model="editForm.options[i]" class="q-input opt-input" :placeholder="`Вариант ${i+1}`" />
                                <button @click="removeOption(editForm, i)" class="btn-remove-opt">✕</button>
                            </div>
                            <button @click="addOption(editForm)" class="btn-add-opt" v-if="editForm.options.length < 6">+ Вариант</button>
                            <div class="edit-actions">
                                <button @click="submitEdit(q.id)" class="btn-save" :disabled="editForm.processing">Сохранить</button>
                                <button @click="cancelEdit" class="btn-cancel">Отмена</button>
                            </div>
                        </div>
                        <div v-else class="question-view">
                            <p class="q-text">{{ q.question }}</p>
                            <ul class="q-options">
                                <li v-for="(opt, i) in q.options" :key="i" :class="{ 'q-option--correct': i === q.correct_option_index }">
                                    {{ opt }}
                                    <span v-if="i === q.correct_option_index" class="correct-mark">✓</span>
                                </li>
                            </ul>
                            <div class="q-actions">
                                <button @click="startEdit(q)" class="btn-edit">Редактировать</button>
                                <button @click="deleteQuestion(q.id)" class="btn-delete">Удалить</button>
                            </div>
                        </div>
                    </div>

                    <!-- Add new question form -->
                    <div class="new-question-form">
                        <h4 class="form-label">Добавить вопрос для этапа {{ stage }}</h4>
                        <input v-model="newForms[stage].question" class="q-input" placeholder="Текст вопроса" />
                        <div v-for="(opt, i) in newForms[stage].options" :key="i" class="option-row">
                            <input
                                type="radio"
                                :name="`new-correct-${stage}`"
                                :value="i"
                                v-model="newForms[stage].correct_option_index"
                                class="radio-correct"
                            />
                            <input v-model="newForms[stage].options[i]" class="q-input opt-input" :placeholder="`Вариант ${i+1}`" />
                            <button @click="removeOption(newForms[stage], i)" class="btn-remove-opt" v-if="newForms[stage].options.length > 2">✕</button>
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
.page-title { font-size: 1.4rem; color: #fff; margin: 0 0 1.5rem; }

.stages-list { display: flex; flex-direction: column; gap: 0.75rem; }

.stage-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    overflow: hidden;
}

.stage-header {
    width: 100%; display: flex; align-items: center; gap: 1rem;
    padding: 0.85rem 1.25rem;
    background: transparent; border: none;
    color: #fff; cursor: pointer;
    transition: background 0.15s;
}
.stage-header:hover { background: rgba(255,255,255,0.03); }

.stage-num { font-weight: 600; font-size: 0.95rem; }
.stage-count { font-size: 0.8rem; color: rgba(255,255,255,0.35); }
.stage-toggle { margin-left: auto; color: rgba(255,255,255,0.3); font-size: 0.75rem; }

.stage-body { padding: 0 1.25rem 1.25rem; display: flex; flex-direction: column; gap: 1rem; }

.question-item {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 8px;
    padding: 1rem;
}

.question-view {}
.q-text { color: rgba(255,255,255,0.85); font-size: 0.9rem; margin: 0 0 0.5rem; }
.q-options { list-style: none; padding: 0; margin: 0 0 0.75rem; display: flex; flex-direction: column; gap: 0.25rem; }
.q-options li { font-size: 0.82rem; color: rgba(255,255,255,0.4); padding: 0.2rem 0.5rem; border-radius: 4px; }
.q-option--correct { color: #4cde8f; background: rgba(76,222,143,0.06); }
.correct-mark { margin-left: 0.35rem; }
.q-actions { display: flex; gap: 0.5rem; }
.btn-edit {
    padding: 0.25rem 0.65rem; border-radius: 6px; font-size: 0.78rem;
    border: 1px solid rgba(200,70,126,0.3); color: #C8467E; background: transparent; cursor: pointer;
}
.btn-delete {
    padding: 0.25rem 0.65rem; border-radius: 6px; font-size: 0.78rem;
    border: 1px solid rgba(255,80,80,0.3); color: #ff6b6b; background: transparent; cursor: pointer;
}

.question-edit-form, .new-question-form { display: flex; flex-direction: column; gap: 0.6rem; }
.form-label { font-size: 0.8rem; color: rgba(255,255,255,0.4); margin: 0.5rem 0 0; }
.q-input {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px; color: #fff;
    padding: 0.5rem 0.75rem; font-size: 0.88rem;
    outline: none; transition: border-color 0.15s;
}
.q-input:focus { border-color: rgba(200,70,126,0.45); }
.opt-input { flex: 1; }

.option-row { display: flex; align-items: center; gap: 0.5rem; }
.radio-correct { accent-color: #4cde8f; cursor: pointer; }
.btn-remove-opt { background: transparent; border: none; color: rgba(255,80,80,0.5); cursor: pointer; font-size: 0.85rem; }
.btn-add-opt {
    align-self: flex-start; padding: 0.25rem 0.65rem; border-radius: 6px; font-size: 0.78rem;
    border: 1px dashed rgba(255,255,255,0.2); color: rgba(255,255,255,0.4); background: transparent; cursor: pointer;
}
.btn-save {
    padding: 0.4rem 0.9rem; border-radius: 6px; font-size: 0.82rem;
    background: rgba(76,222,143,0.12); border: 1px solid rgba(76,222,143,0.4); color: #4cde8f; cursor: pointer;
}
.btn-cancel {
    padding: 0.4rem 0.9rem; border-radius: 6px; font-size: 0.82rem;
    background: transparent; border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.4); cursor: pointer;
}
.edit-actions { display: flex; gap: 0.5rem; }
.btn-add-question {
    align-self: flex-start; padding: 0.4rem 1rem; border-radius: 7px; font-size: 0.85rem;
    background: rgba(200,70,126,0.1); border: 1px solid rgba(200,70,126,0.35); color: #C8467E; cursor: pointer; transition: all 0.15s;
}
.btn-add-question:hover { background: rgba(200,70,126,0.18); }
.btn-add-question:disabled { opacity: 0.4; }
</style>
