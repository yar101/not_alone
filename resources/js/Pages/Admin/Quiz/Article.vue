<script setup>
import { ref } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import AdminInput from '@/Components/Admin/UI/AdminInput.vue';
import HtmlEditor from '@/Components/Admin/HtmlEditor.vue';
import axios from 'axios';
import { ElNotification, ElMessageBox, ElMessage } from 'element-plus';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    active_version: Object,   // { id, html, label, created_at }
    versions: { type: Array, default: () => [] }, // [{ id, label, is_active, created_at }]
});

// ── Editor state ──────────────────────────────────────────
const editorHtml = ref(props.active_version?.html ?? '');

// ── Save version modal ────────────────────────────────────
const showSaveModal  = ref(false);
const saveLabel      = ref('');
const savePending    = ref(false);

function openSaveModal() {
    saveLabel.value = '';
    showSaveModal.value = true;
}

function closeSaveModal() {
    showSaveModal.value = false;
}

const saveForm = useForm({ html: '', label: '' });
const importForm = useForm({ file: null });

function saveVersion() {
    savePending.value = true;
    saveForm.html  = editorHtml.value;
    saveForm.label = saveLabel.value.trim() || null;
    saveForm.post(route('admin.quiz.article.versions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showSaveModal.value = false;
            saveLabel.value = '';
            ElMessage.success('Версия успешно сохранена');
        },
        onFinish: () => { savePending.value = false; },
    });
}

const activeUpdateForm = useForm({ html: '' });
const saveActivePending = ref(false);

function saveActiveDirectly() {
    saveActivePending.value = true;
    activeUpdateForm.html = editorHtml.value;
    activeUpdateForm.patch(route('admin.quiz.article.active.update'), {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Активная статья обновлена');
        },
        onFinish: () => { saveActivePending.value = false; }
    });
}

// ── Activate version ─────────────────────────────────────
const activateTarget  = ref(null);
const showActivateModal = ref(false);

function askActivate(version) {
    activateTarget.value = version;
    showActivateModal.value = true;
}

function confirmActivate() {
    if (!activateTarget.value) return;
    router.patch(
        route('admin.quiz.article.versions.activate', activateTarget.value.id),
        {},
        {
            preserveScroll: true,
            onSuccess: (page) => {
                editorHtml.value = page.props.active_version?.html ?? '';
                showActivateModal.value = false;
                activateTarget.value = null;
                ElMessage.success('Версия успешно активирована');
            },
        }
    );
}

// ── Load version ──────────────────────────────────────────
function loadVersion(version) {
    ElMessageBox.confirm(`Загрузить версию от ${fmtDate(version.created_at)} в редактор? Несохранённые изменения будут потеряны.`, 'Загрузка версии', {
        confirmButtonText: 'Загрузить',
        cancelButtonText: 'Отмена',
        type: 'warning',
    }).then(() => {
        axios.get(route('admin.quiz.article.versions.show', version.id))
            .then(r => {
                editorHtml.value = r.data.html ?? '';
                ElMessage.success('Версия загружена в редактор');
            })
            .catch(err => {
                ElMessage.error('Не удалось загрузить версию: ' + (err.response?.data?.message || err.message));
            });
    }).catch(() => {});
}

// ── Delete version ────────────────────────────────────────
const deleteTarget   = ref(null);
const showDeleteModal = ref(false);

function askDelete(version) {
    deleteTarget.value = version;
    showDeleteModal.value = true;
}

function confirmDelete() {
    if (!deleteTarget.value) return;
    router.delete(
        route('admin.quiz.article.versions.destroy', deleteTarget.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteModal.value = false;
                deleteTarget.value = null;
                ElMessage.success('Версия удалена');
            },
        }
    );
}

// ── Diff ──────────────────────────────────────────────────
const showDiffModal  = ref(false);
const diffLoading    = ref(false);
const diffVersionA   = ref(null);   // Old version (other)
const diffVersionB   = ref(null);   // New version (version)
const htmlA          = ref('');     // Old version HTML content
const htmlB          = ref('');     // New version HTML content

function openDiffModal() {
    showDiffModal.value = true;
    htmlA.value = '';
    htmlB.value = '';

    if (props.versions && props.versions.length >= 2) {
        diffVersionB.value = props.versions[0].id;
        diffVersionA.value = props.versions[1].id;
        runCompare();
    } else if (props.versions && props.versions.length === 1) {
        diffVersionB.value = props.versions[0].id;
        diffVersionA.value = null;
        diffLoading.value = true;
        axios.get(route('admin.quiz.article.versions.show', diffVersionB.value))
            .then(resB => { htmlB.value = resB.data.html ?? ''; })
            .catch(err => { console.error(err); })
            .finally(() => { diffLoading.value = false; });
    } else {
        diffVersionB.value = null;
        diffVersionA.value = null;
    }
}

function runCompare() {
    if (!diffVersionA.value || !diffVersionB.value) {
        htmlA.value = '';
        htmlB.value = '';
        return;
    }

    diffLoading.value = true;
    Promise.all([
        axios.get(route('admin.quiz.article.versions.show', diffVersionA.value)),
        axios.get(route('admin.quiz.article.versions.show', diffVersionB.value))
    ])
    .then(([resA, resB]) => {
        htmlA.value = resA.data.html ?? '';
        htmlB.value = resB.data.html ?? '';
    })
    .catch(err => {
        ElMessage.error('Ошибка при загрузке содержимого версий: ' + (err.response?.data?.message || err.message));
    })
    .finally(() => {
        diffLoading.value = false;
    });
}

// ── Export ────────────────────────────────────────────────
function exportArticle() {
    window.location.href = route('admin.quiz.article.export');
}

// ── Import ────────────────────────────────────────────────
const importInput    = ref(null);
const importPending  = ref(false);

function triggerImport() {
    importInput.value?.click();
}

function onImportFile(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (evt) => {
        editorHtml.value = evt.target.result;
    };
    reader.readAsText(file);

    importPending.value = true;
    importForm.file = file;
    importForm.post(route('admin.quiz.article.import'), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            importPending.value = false;
            if (e?.target) e.target.value = '';
            ElMessage.success('Статья импортирована как новая версия');
        },
    });
}

// ── Drag & Drop ──────────────────────────────────────────
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
        if (!file.name.endsWith('.html') && !file.name.endsWith('.htm')) {
            ElNotification({
                title: 'Ошибка',
                message: 'Выберите файл .html или .htm для импорта статьи',
                type: 'error',
                duration: 5000,
            });
            return;
        }

        const reader = new FileReader();
        reader.onload = (evt) => {
            editorHtml.value = evt.target.result;
        };
        reader.readAsText(file);

        importPending.value = true;
        importForm.file = file;
        importForm.post(route('admin.quiz.article.import'), {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                importPending.value = false;
                ElMessage.success('Статья импортирована');
            },
        });
    }
}

// ── Helpers ───────────────────────────────────────────────
function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('ru-RU', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <div
        @dragover="onDragOver"
        @dragenter="onDragEnter"
        @dragleave="onDragLeave"
        @drop="onDrop"
        class="space-y-6 relative min-h-[calc(100vh-8rem)]"
    >
        <!-- Drag & Drop Overlay -->
        <div
            v-if="isDragging"
            class="fixed inset-0 z-50 bg-zinc-950/80 backdrop-blur-sm flex items-center justify-center p-6 border-2 border-dashed border-violet-500 m-4 rounded-2xl"
            @dragleave="isDragging = false"
            @dragover.prevent
        >
            <div class="text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-violet-500/10 border border-violet-500/20 flex items-center justify-center text-violet-400">
                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-zinc-100">Перетащите сюда .html файл</h3>
                <p class="text-sm text-zinc-400 mt-1">Файл будет автоматически импортирован как новая версия статьи</p>
            </div>
        </div>

        <!-- ── Page Header ── -->
        <AdminPageHeader
            title="Обучающая статья Айдола"
            subtitle="Текст регламента и стандартов, который кандидат обязан изучить перед прохождением квиза (/idol/apply)"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <AdminButton variant="outline" size="sm" @click="triggerImport" :disabled="importPending">
                        <svg class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        {{ importPending ? 'Импорт...' : 'Импорт .html' }}
                    </AdminButton>

                    <AdminButton variant="outline" size="sm" @click="exportArticle">
                        <svg class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Экспорт
                    </AdminButton>

                    <AdminButton variant="outline" size="sm" @click="openDiffModal" :disabled="versions.length < 2">
                        <svg class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                        Сравнить версии
                    </AdminButton>

                    <AdminButton variant="primary" size="sm" @click="saveActiveDirectly" :disabled="saveActivePending">
                        <svg class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                        </svg>
                        {{ saveActivePending ? 'Сохранение...' : 'Сохранить изменения' }}
                    </AdminButton>

                    <AdminButton variant="secondary" size="sm" @click="openSaveModal">
                        <svg class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
                        </svg>
                        Сохранить как версию
                    </AdminButton>
                </div>
            </template>
        </AdminPageHeader>

        <!-- Hidden input for file selection -->
        <input ref="importInput" type="file" accept=".html,.htm" class="hidden" @change="onImportFile" />

        <!-- ── Horizontal Version History Ribbon ── -->
        <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-violet-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <span class="text-xs font-semibold uppercase tracking-wider text-zinc-300">История сохранённых версий</span>
                </div>
                <span class="text-xs font-medium text-zinc-500">Всего: {{ versions.length }}</span>
            </div>

            <div class="flex items-center gap-3 overflow-x-auto pb-2 scrollbar-thin scrollbar-thumb-zinc-700">
                <div v-if="!versions.length" class="text-xs text-zinc-500 italic py-2">
                    Нет сохранённых версий
                </div>

                <div
                    v-for="v in versions"
                    :key="v.id"
                    class="flex-shrink-0 w-64 bg-zinc-950/70 border rounded-lg p-3 transition-colors flex flex-col justify-between"
                    :class="v.is_active ? 'border-violet-500/50 bg-violet-950/10 ring-1 ring-violet-500/30' : 'border-zinc-800 hover:border-zinc-700'"
                >
                    <div>
                        <div class="flex items-center justify-between gap-1 mb-1.5">
                            <AdminBadge v-if="v.is_active" variant="success" size="sm">● Активна</AdminBadge>
                            <span v-else class="text-[10px] text-zinc-500 font-mono">ID #{{ v.id }}</span>
                            <span class="text-[11px] text-zinc-400 font-mono">{{ fmtDate(v.created_at) }}</span>
                        </div>
                        <div class="text-xs text-zinc-200 font-medium truncate mb-2" :title="v.label">
                            {{ v.label || '— автоверсия —' }}
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 pt-2 border-t border-zinc-800/80">
                        <button
                            v-if="!v.is_active"
                            @click="askActivate(v)"
                            class="text-[11px] px-2 py-0.5 rounded bg-violet-500/10 hover:bg-violet-500/20 text-violet-300 transition-colors font-medium"
                            title="Сделать публичной активной версией"
                        >
                            Активировать
                        </button>
                        <button
                            @click="loadVersion(v)"
                            class="text-[11px] px-2 py-0.5 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300 transition-colors font-medium ml-auto"
                            title="Загрузить в редактор"
                        >
                            Загрузить
                        </button>
                        <button
                            v-if="!v.is_active"
                            @click="askDelete(v)"
                            class="text-[11px] p-1 rounded hover:bg-rose-500/20 text-zinc-400 hover:text-rose-400 transition-colors"
                            title="Удалить версию"
                        >
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Main 2-Column Split: Editor + Live Preview ── -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
            <!-- Left Column: Rich Tiptap Editor -->
            <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl overflow-hidden shadow-sm">
                <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800 bg-zinc-950/40">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                        </svg>
                        <span class="text-xs font-semibold uppercase tracking-wider text-zinc-300">Редактор регламента</span>
                    </div>
                    <span class="text-[11px] px-2 py-0.5 rounded bg-zinc-800 text-zinc-400 font-mono">Tiptap · HTML</span>
                </div>
                <div class="p-4 bg-zinc-950/20 min-h-[500px]">
                    <HtmlEditor v-model="editorHtml" />
                </div>
            </div>

            <!-- Right Column: Live Candidate Preview -->
            <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl overflow-hidden shadow-sm">
                <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800 bg-zinc-950/40">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-pink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                        <span class="text-xs font-semibold uppercase tracking-wider text-zinc-300">Предпросмотр на странице подачи</span>
                    </div>
                    <span class="text-[11px] px-2 py-0.5 rounded bg-pink-500/10 text-pink-400 border border-pink-500/20 font-mono">Живой вид · /idol/apply</span>
                </div>

                <div class="p-6 bg-zinc-950/40 min-h-[500px] flex flex-col justify-between">
                    <div
                        class="prose prose-invert prose-zinc max-w-none prose-headings:text-zinc-100 prose-p:text-zinc-300 prose-a:text-pink-400 text-sm leading-relaxed"
                        v-html="editorHtml || '<p class=\'text-zinc-500 italic\'>Текст статьи пока пуст. Начните редактирование слева...</p>'"
                    ></div>

                    <div class="mt-8 pt-4 border-t border-zinc-800/80 flex justify-center">
                        <button disabled class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-pink-500/40 to-violet-500/40 text-white/50 text-sm font-semibold cursor-not-allowed">
                            Перейти к квизу (демо)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Modals ── -->

        <!-- Save Version Modal -->
        <AdminModal
            :open="showSaveModal"
            title="Сохранить как именованную версию"
            description="Сохраните текущее состояние статьи с меткой, чтобы к ней можно было вернуться в любой момент"
            @close="closeSaveModal"
        >
            <div class="space-y-4 py-2">
                <AdminInput
                    v-model="saveLabel"
                    label="Комментарий к версии"
                    placeholder="Например: Добавлены требования по верификации СБП"
                    hint="Если оставить пустым, версия сохранится как автоматическая"
                    maxlength="120"
                    @keydown.enter="saveVersion"
                />
            </div>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <AdminButton variant="outline" size="sm" @click="closeSaveModal">Отмена</AdminButton>
                    <AdminButton variant="primary" size="sm" :loading="savePending" @click="saveVersion">
                        Сохранить версию
                    </AdminButton>
                </div>
            </template>
        </AdminModal>

        <!-- Activate Version Confirm Modal -->
        <AdminModal
            :open="showActivateModal"
            title="Активировать эту версию?"
            description="Указанная версия станет публичной для всех новых кандидатов в айдолы"
            variant="warning"
            @close="showActivateModal = false"
        >
            <div class="py-2 text-sm text-zinc-300 space-y-2">
                <p>
                    Версия <strong class="text-white">«{{ activateTarget?.label || fmtDate(activateTarget?.created_at) }}»</strong> будет активирована.
                </p>
                <p class="text-xs text-zinc-400">
                    Несохранённые правки в редакторе будут заменены содержимым активируемой версии.
                </p>
            </div>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <AdminButton variant="outline" size="sm" @click="showActivateModal = false">Отмена</AdminButton>
                    <AdminButton variant="danger" size="sm" @click="confirmActivate">
                        Активировать
                    </AdminButton>
                </div>
            </template>
        </AdminModal>

        <!-- Delete Version Confirm Modal -->
        <AdminModal
            :open="showDeleteModal"
            title="Удалить сохранённую версию?"
            description="Это действие необратимо удалит данную версию статьи из истории"
            variant="danger"
            @close="showDeleteModal = false"
        >
            <div class="py-2 text-sm text-zinc-300">
                Вы действительно хотите удалить версию <strong class="text-white">«{{ deleteTarget?.label || fmtDate(deleteTarget?.created_at) }}»</strong>?
            </div>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <AdminButton variant="outline" size="sm" @click="showDeleteModal = false">Отмена</AdminButton>
                    <AdminButton variant="danger" size="sm" @click="confirmDelete">
                        Удалить
                    </AdminButton>
                </div>
            </template>
        </AdminModal>

        <!-- Diff Modal -->
        <AdminModal
            :open="showDiffModal"
            title="Сравнение версий статьи"
            description="Сравните текст двух любых версий для анализа внесенных правок"
            size="2xl"
            @close="showDiffModal = false"
        >
            <div class="space-y-4 py-2">
                <!-- Selectors -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-zinc-400 mb-1.5">Старая версия (Было)</label>
                        <select
                            v-model="diffVersionA"
                            @change="runCompare"
                            class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-200 outline-none focus:border-violet-500"
                        >
                            <option v-for="v in versions" :key="v.id" :value="v.id">
                                {{ v.label || '— автоверсия —' }} ({{ fmtDate(v.created_at) }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-zinc-400 mb-1.5">Новая версия (Стало)</label>
                        <select
                            v-model="diffVersionB"
                            @change="runCompare"
                            class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-200 outline-none focus:border-violet-500"
                        >
                            <option v-for="v in versions" :key="v.id" :value="v.id">
                                {{ v.label || '— автоверсия —' }} ({{ fmtDate(v.created_at) }})
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Comparison Panes -->
                <div v-if="diffLoading" class="text-center py-12 text-zinc-500 text-xs font-medium">
                    Загрузка содержимого версий...
                </div>
                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[60vh] overflow-y-auto pt-2">
                    <div class="bg-zinc-950/60 border border-zinc-800/80 rounded-xl p-4">
                        <div class="text-[11px] font-semibold uppercase text-rose-400 mb-3 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            Было
                        </div>
                        <div
                            class="prose prose-invert prose-zinc max-w-none text-xs leading-relaxed"
                            v-html="htmlA || '<p class=\'text-zinc-600 italic\'>Нет содержимого</p>'"
                        ></div>
                    </div>

                    <div class="bg-zinc-950/60 border border-zinc-800/80 rounded-xl p-4">
                        <div class="text-[11px] font-semibold uppercase text-emerald-400 mb-3 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Стало
                        </div>
                        <div
                            class="prose prose-invert prose-zinc max-w-none text-xs leading-relaxed"
                            v-html="htmlB || '<p class=\'text-zinc-600 italic\'>Нет содержимого</p>'"
                        ></div>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end">
                    <AdminButton variant="outline" size="sm" @click="showDiffModal = false">Закрыть</AdminButton>
                </div>
            </template>
        </AdminModal>
    </div>
</template>
