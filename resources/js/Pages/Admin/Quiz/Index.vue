<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import { ElMessageBox } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import {
    Search, Plus, Delete, EditPen, Check, Close,
    Download, Upload, Document, ArrowRight, ArrowDown
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    questions_by_stage: Object,
});

const expandedStages = ref(new Set([1]));
const manualExpandedStages = ref(new Set([1]));

function toggleStage(stage) {
    if (expandedStages.value.has(stage)) {
        expandedStages.value.delete(stage);
        if (!searchQuery.value.trim()) {
            manualExpandedStages.value.delete(stage);
        }
    } else {
        expandedStages.value.add(stage);
        if (!searchQuery.value.trim()) {
            manualExpandedStages.value.add(stage);
        }
    }
}

const searchQuery = ref('');

const filteredQuestionsByStage = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.questions_by_stage;
    }
    const query = searchQuery.value.toLowerCase().trim();
    const result = {};
    for (const [stage, questions] of Object.entries(props.questions_by_stage)) {
        result[stage] = (questions || []).filter(q => {
            const questionMatch = q.question && q.question.toLowerCase().includes(query);
            const optionsMatch = q.options && q.options.some(opt => opt && opt.toLowerCase().includes(query));
            return questionMatch || optionsMatch;
        });
    }
    return result;
});

const totalFilteredCount = computed(() => {
    let count = 0;
    for (const questions of Object.values(filteredQuestionsByStage.value)) {
        count += (questions || []).length;
    }
    return count;
});

watch(searchQuery, (newVal) => {
    const query = newVal.trim();
    if (query) {
        const newExpanded = new Set();
        for (const [stage, questions] of Object.entries(props.questions_by_stage)) {
            const hasMatch = (questions || []).some(q => {
                const questionMatch = q.question && q.question.toLowerCase().includes(query.toLowerCase());
                const optionsMatch = q.options && q.options.some(opt => opt && opt.toLowerCase().includes(query.toLowerCase()));
                return questionMatch || optionsMatch;
            });
            if (hasMatch) {
                newExpanded.add(Number(stage));
            }
        }
        expandedStages.value = newExpanded;
    } else {
        expandedStages.value = new Set(manualExpandedStages.value);
    }
});

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
    ElMessageBox.confirm('Удалить вопрос?', 'Подтверждение удаления', {
        confirmButtonText: 'Удалить',
        cancelButtonText: 'Отмена',
        type: 'warning',
    }).then(() => {
        router.delete(route('admin.quiz.questions.destroy', id), { preserveScroll: true });
    }).catch(() => {});
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

const isDragging = ref(false);

function onDragOver(e) { e.preventDefault(); }
function onDragEnter(e) { e.preventDefault(); isDragging.value = true; }
function onDragLeave(e) {
    e.preventDefault();
    if (!e.relatedTarget) isDragging.value = false;
}

function onDrop(e) {
    e.preventDefault();
    isDragging.value = false;
    const files = e.dataTransfer?.files;
    if (files && files.length > 0) {
        const file = files[0];
        if (!file.name.endsWith('.json')) {
            importError.value = 'Выберите файл .json';
            return;
        }
        importError.value = '';
        importFile.value = file;
        submitImport();
    }
}
</script>

<template>
    <div
        class="space-y-6"
        @dragover="onDragOver"
        @dragenter="onDragEnter"
        @dragleave="onDragLeave"
        @drop="onDrop"
    >
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-100 flex items-center gap-2.5">
                    <el-icon class="text-violet-400"><Document /></el-icon>
                    <span>Вопросы онбординг-теста (10 этапов)</span>
                </h1>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Управление этапами и вариантами ответов проверочного квиза кандидатов в айдолы
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('admin.quiz.article.index')"
                    class="px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-xs font-medium text-zinc-300 hover:text-white hover:bg-zinc-800 transition flex items-center gap-1.5"
                >
                    <span>Обучающая статья</span>
                    <el-icon :size="12"><ArrowRight /></el-icon>
                </Link>
            </div>
        </div>

        <!-- Import / Export Bar -->
        <div class="p-3.5 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2">
                <span class="text-zinc-400 font-medium">Импорт / Экспорт (JSON):</span>
                <select
                    v-model="importMode"
                    class="h-8 px-2.5 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-200 focus:outline-none focus:border-violet-500"
                >
                    <option value="append">Добавить к существующим</option>
                    <option value="replace">Заменить все вопросы</option>
                </select>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <input
                    ref="importFileInput"
                    type="file"
                    accept=".json"
                    class="hidden"
                    @change="onImportFileChange"
                />
                <AdminButton variant="outline" size="sm" @click="triggerImport">
                    <el-icon class="mr-1"><Upload /></el-icon>
                    Импорт JSON
                </AdminButton>
                <a
                    :href="route('admin.quiz.questions.export')"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-300 hover:text-white text-xs font-medium transition"
                    download
                >
                    <el-icon><Download /></el-icon>
                    Экспорт
                </a>
                <a
                    :href="route('admin.quiz.questions.template')"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-200 text-xs font-medium transition"
                    download
                >
                    Шаблон
                </a>
            </div>
        </div>
        <p v-if="importError" class="text-xs text-rose-400">{{ importError }}</p>

        <!-- Search Bar -->
        <div class="relative">
            <el-icon class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500"><Search /></el-icon>
            <input
                v-model="searchQuery"
                type="text"
                placeholder="Поиск по тексту вопросов и вариантов ответов..."
                class="w-full h-9 pl-9 pr-8 rounded-lg border border-zinc-800 bg-zinc-900/80 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
            />
            <button
                v-if="searchQuery"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-300 text-xs"
                @click="searchQuery = ''"
            >
                ✕
            </button>
        </div>

        <!-- 10 Stages Accordion -->
        <div class="space-y-3">
            <div
                v-for="stage in stages"
                :key="stage"
                class="rounded-xl border border-zinc-800 bg-zinc-900/60 overflow-hidden backdrop-blur-sm"
            >
                <!-- Stage Header -->
                <button
                    type="button"
                    class="w-full p-4 flex items-center justify-between text-left transition hover:bg-zinc-800/40"
                    :class="{ 'border-b border-zinc-800 bg-zinc-900/40': expandedStages.has(stage) }"
                    @click="toggleStage(stage)"
                >
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 rounded-lg bg-violet-950/60 border border-violet-800/40 text-violet-300 font-mono font-bold text-xs flex items-center justify-center">
                            {{ String(stage).padStart(2, '0') }}
                        </span>
                        <span class="font-semibold text-xs text-zinc-100">Этап {{ stage }}</span>
                        <AdminBadge variant="neutral" size="sm">
                            <template v-if="searchQuery.trim()">
                                {{ (filteredQuestionsByStage[stage] || []).length }} / {{ (questions_by_stage[stage] || []).length }}
                            </template>
                            <template v-else>
                                {{ (questions_by_stage[stage] || []).length }} вопросов
                            </template>
                        </AdminBadge>
                    </div>

                    <el-icon class="text-zinc-500 transition-transform duration-200" :class="{ 'rotate-180': expandedStages.has(stage) }">
                        <ArrowDown />
                    </el-icon>
                </button>

                <!-- Stage Body -->
                <div v-if="expandedStages.has(stage)" class="p-4 space-y-4 text-xs">
                    <!-- Questions List -->
                    <div v-if="!filteredQuestionsByStage[stage]?.length" class="py-6 text-center text-zinc-500">
                        {{ searchQuery.trim() ? 'Нет совпадений для этого этапа' : 'В этом этапе пока нет вопросов' }}
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(q, qIndex) in (filteredQuestionsByStage[stage] || [])"
                            :key="q.id"
                            class="p-4 rounded-xl border border-zinc-800/80 bg-zinc-950/60 space-y-3"
                        >
                            <!-- Edit Mode -->
                            <div v-if="editingId === q.id" class="space-y-3">
                                <div class="font-semibold text-violet-300">Редактирование вопроса #{{ qIndex + 1 }}</div>
                                <input
                                    v-model="editForm.question"
                                    type="text"
                                    class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-900 text-xs text-zinc-100 focus:outline-none focus:border-violet-500"
                                    placeholder="Текст вопроса"
                                />

                                <div class="space-y-2">
                                    <div
                                        v-for="(opt, i) in editForm.options"
                                        :key="i"
                                        class="flex items-center gap-2"
                                    >
                                        <label class="flex items-center gap-1.5 cursor-pointer text-xs font-mono">
                                            <input
                                                type="radio"
                                                :name="`edit-correct-${q.id}`"
                                                :value="i"
                                                v-model="editForm.correct_option_index"
                                                class="text-violet-500 focus:ring-0"
                                            />
                                            <span class="font-bold" :class="i === editForm.correct_option_index ? 'text-emerald-400' : 'text-zinc-500'">
                                                {{ 'ABCDEF'[i] }}
                                            </span>
                                        </label>
                                        <input
                                            v-model="editForm.options[i]"
                                            type="text"
                                            class="flex-1 h-8 px-2.5 rounded-lg border border-zinc-800 bg-zinc-900 text-xs text-zinc-100 focus:outline-none focus:border-violet-500"
                                            :placeholder="`Вариант ${i+1}`"
                                        />
                                        <button
                                            type="button"
                                            class="p-1 text-zinc-500 hover:text-rose-400"
                                            title="Удалить вариант"
                                            @click="removeOption(editForm, i)"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-1">
                                    <button
                                        v-if="editForm.options.length < 6"
                                        type="button"
                                        class="text-xs text-violet-400 hover:underline"
                                        @click="addOption(editForm)"
                                    >
                                        + Добавить вариант
                                    </button>
                                    <div class="flex items-center gap-2">
                                        <AdminButton variant="outline" size="sm" type="button" @click="cancelEdit">
                                            Отмена
                                        </AdminButton>
                                        <AdminButton variant="primary" size="sm" type="button" :loading="editForm.processing" @click="submitEdit(q.id)">
                                            Сохранить
                                        </AdminButton>
                                    </div>
                                </div>
                            </div>

                            <!-- View Mode -->
                            <div v-else class="space-y-2">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-start gap-2">
                                        <span class="font-mono text-zinc-500 shrink-0 font-semibold">#{{ qIndex + 1 }}</span>
                                        <p class="font-medium text-zinc-100 text-xs leading-relaxed">{{ q.question }}</p>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <button
                                            type="button"
                                            class="p-1 rounded text-zinc-500 hover:text-zinc-200"
                                            title="Редактировать"
                                            @click="startEdit(q)"
                                        >
                                            <el-icon><EditPen /></el-icon>
                                        </button>
                                        <button
                                            type="button"
                                            class="p-1 rounded text-zinc-500 hover:text-rose-400"
                                            title="Удалить"
                                            @click="deleteQuestion(q.id)"
                                        >
                                            <el-icon><Delete /></el-icon>
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                                    <div
                                        v-for="(opt, i) in q.options"
                                        :key="i"
                                        class="p-2 rounded-lg border flex items-center justify-between text-xs"
                                        :class="i === q.correct_option_index
                                            ? 'border-emerald-500/50 bg-emerald-500/10 text-emerald-300 font-medium'
                                            : 'border-zinc-800/80 bg-zinc-900/60 text-zinc-300'"
                                    >
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="font-mono font-bold" :class="i === q.correct_option_index ? 'text-emerald-400' : 'text-zinc-500'">
                                                {{ 'ABCDEF'[i] }}
                                            </span>
                                            <span class="truncate">{{ opt }}</span>
                                        </div>
                                        <span v-if="i === q.correct_option_index" class="text-[10px] text-emerald-400 font-mono shrink-0">
                                            ✓ верный
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add New Question to Stage Form -->
                    <div class="p-4 rounded-xl border border-dashed border-zinc-800 bg-zinc-950/40 space-y-3 pt-3">
                        <div class="font-semibold text-xs text-zinc-300 flex items-center gap-1.5">
                            <el-icon class="text-violet-400"><Plus /></el-icon>
                            <span>Добавить вопрос в этап {{ stage }}</span>
                        </div>

                        <input
                            v-model="newForms[stage].question"
                            type="text"
                            placeholder="Текст нового вопроса..."
                            class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-900 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                        />

                        <div class="space-y-2">
                            <div
                                v-for="(opt, i) in newForms[stage].options"
                                :key="i"
                                class="flex items-center gap-2"
                            >
                                <label class="flex items-center gap-1.5 cursor-pointer text-xs font-mono">
                                    <input
                                        type="radio"
                                        :name="`new-correct-${stage}`"
                                        :value="i"
                                        v-model="newForms[stage].correct_option_index"
                                        class="text-violet-500 focus:ring-0"
                                    />
                                    <span class="font-bold" :class="i === newForms[stage].correct_option_index ? 'text-emerald-400' : 'text-zinc-500'">
                                        {{ 'ABCDEF'[i] }}
                                    </span>
                                </label>
                                <input
                                    v-model="newForms[stage].options[i]"
                                    type="text"
                                    class="flex-1 h-8 px-2.5 rounded-lg border border-zinc-800 bg-zinc-900 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                                    :placeholder="`Вариант ответа ${i+1}`"
                                />
                                <button
                                    type="button"
                                    class="p-1 text-zinc-500 hover:text-rose-400"
                                    title="Удалить вариант"
                                    @click="removeOption(newForms[stage], i)"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <button
                                v-if="newForms[stage].options.length < 6"
                                type="button"
                                class="text-xs text-violet-400 hover:underline"
                                @click="addOption(newForms[stage])"
                            >
                                + Вариант
                            </button>
                            <AdminButton
                                variant="primary"
                                size="sm"
                                :disabled="!newForms[stage].question.trim()"
                                :loading="newForms[stage].processing"
                                @click="submitNew(stage)"
                            >
                                Добавить вопрос
                            </AdminButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
