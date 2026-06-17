<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import HtmlEditor from '@/Components/Admin/HtmlEditor.vue';
import axios from 'axios';
import { ElNotification } from 'element-plus';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    active_version: Object,   // { id, html, label, created_at }
    versions: Array,           // [{ id, label, is_active, created_at }]
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

const page = usePage();

watch(() => page.props.flash?.success, (successMsg) => {
    if (successMsg) {
        ElNotification({
            title: 'Успешно',
            message: successMsg,
            type: 'success',
            duration: 5000,
        });
    }
}, { immediate: true });

function saveVersion() {
    savePending.value = true;
    saveForm.html  = editorHtml.value;
    saveForm.label = saveLabel.value.trim() || null;
    saveForm.post(route('admin.quiz.article.versions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showSaveModal.value = false;
            saveLabel.value = '';
        },
        onFinish: () => { savePending.value = false; },
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
    router.patch(
        route('admin.quiz.article.versions.activate', activateTarget.value.id),
        {},
        {
            preserveScroll: true,
            onSuccess: (page) => {
                // Load the activated HTML into editor
                editorHtml.value = page.props.active_version?.html ?? '';
                showActivateModal.value = false;
                activateTarget.value = null;
            },
        }
    );
}

// ── Load version ──────────────────────────────────────────
function loadVersion(version) {
    if (confirm(`Загрузить версию от ${fmtDate(version.created_at)} в редактор? Несохранённые изменения будут потеряны.`)) {
        axios.get(route('admin.quiz.article.versions.show', version.id))
            .then(r => {
                editorHtml.value = r.data.html ?? '';
            })
            .catch(err => {
                alert('Не удалось загрузить версию: ' + (err.response?.data?.message || err.message));
            });
    }
}

// ── Delete version ────────────────────────────────────────
const deleteTarget   = ref(null);
const showDeleteModal = ref(false);

function askDelete(version) {
    deleteTarget.value = version;
    showDeleteModal.value = true;
}

function confirmDelete() {
    router.delete(
        route('admin.quiz.article.versions.destroy', deleteTarget.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteModal.value = false;
                deleteTarget.value = null;
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
        // Fetch only B
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

    // Load both version contents in parallel
    Promise.all([
        axios.get(route('admin.quiz.article.versions.show', diffVersionA.value)),
        axios.get(route('admin.quiz.article.versions.show', diffVersionB.value))
    ])
    .then(([resA, resB]) => {
        htmlA.value = resA.data.html ?? '';
        htmlB.value = resB.data.html ?? '';
    })
    .catch(err => {
        alert('Ошибка при загрузке содержимого версий: ' + (err.response?.data?.message || err.message));
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
    importInput.value.click();
}

function onImportFile(e) {
    const file = e.target.files[0];
    if (!file) return;

    // Read file locally to load into the editor instantly
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
            e.target.value = '';
        },
    });
}

// ── Helpers ───────────────────────────────────────────────
function fmtDate(d) {
    return new Date(d).toLocaleString('ru-RU', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <div class="article-page">

        <!-- ── Header ── -->
        <div class="ap-header">
            <div>
                <h1 class="ap-title">Статья для вступления в Айдолы</h1>
                <p class="ap-sub">Текст, который пользователь читает перед тестом. Активная версия отображается на <code>/idol/apply</code>.</p>
            </div>
            <div class="ap-header-actions">
                <button class="btn-ghost btn-sm" @click="triggerImport" :disabled="importPending">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    {{ importPending ? 'Импорт...' : 'Импорт' }}
                </button>
                <button class="btn-ghost btn-sm" @click="exportArticle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Экспорт
                </button>
                <button class="btn-ghost btn-sm" @click="openDiffModal" :disabled="versions.length < 2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    Сравнить версии
                </button>
                <button class="btn-primary" @click="openSaveModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Сохранить как версию
                </button>
            </div>
            <input ref="importInput" type="file" accept=".html,.htm" style="display:none" @change="onImportFile" />
        </div>

        <!-- ── Top: horizontal version history ── -->
        <div class="ap-versions-top">
            <div class="versions-pane-horizontal">
                <div class="vph-head">
                    <span class="vph-title">История версий</span>
                    <span class="vph-count">{{ versions.length }}</span>
                </div>
                <div class="versions-list-horizontal">
                    <div v-if="!versions.length" class="versions-empty-horizontal">
                        Нет сохранённых версий
                    </div>
                    <div
                        v-for="v in versions"
                        :key="v.id"
                        class="version-card-horizontal"
                        :class="{ 'version-card-horizontal--active': v.is_active }"
                    >
                        <div class="vch-top">
                            <span v-if="v.is_active" class="vc-active-badge">● Активна</span>
                            <span class="vc-date">{{ fmtDate(v.created_at) }}</span>
                        </div>
                        <div class="vch-label" :title="v.label">
                            {{ v.label || '— автоматическая версия —' }}
                        </div>
                        <div class="vc-actions">
                            <button
                                v-if="!v.is_active"
                                class="vc-btn vc-btn--activate"
                                @click="askActivate(v)"
                                title="Активировать эту версию"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                Активировать
                            </button>
                            <button
                                class="vc-btn vc-btn--load"
                                @click="loadVersion(v)"
                                title="Загрузить эту версию в редактор"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                                Загрузить
                            </button>
                            <button
                                v-if="!v.is_active"
                                class="vc-btn vc-btn--delete"
                                @click="askDelete(v)"
                                title="Удалить версию"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Main layout: editor + preview ── -->
        <div class="ap-layout">

            <!-- Left: editor -->
            <div class="ap-editor-col">
                <div class="ap-pane">
                    <div class="pane-head">
                        <span class="pane-label">Редактор</span>
                        <span class="pane-tag">Tiptap · HTML</span>
                    </div>
                    <div class="pane-body editor-body">
                        <HtmlEditor v-model="editorHtml" />
                    </div>
                </div>
            </div>

            <!-- Right: preview -->
            <div class="ap-preview-col">
                <div class="ap-pane ap-pane--preview">
                    <div class="pane-head">
                        <span class="pane-label">Предпросмотр</span>
                        <span class="pane-tag pane-tag--pink">Живой вид · /idol/apply</span>
                    </div>
                    <div class="pane-body preview-body">
                        <div class="preview-article">
                            <div
                                class="article-body-preview"
                                v-html="editorHtml || '<p class=\'empty-preview\'>Начните вводить текст в редакторе выше...</p>'"
                            ></div>
                            <div class="preview-cta">
                                <button class="btn-preview-cta" disabled>Начать тест</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ════════════════════════════════════════════════════════
             MODALS
        ════════════════════════════════════════════════════════ -->

        <!-- Save version modal -->
        <Teleport to="body">
            <div v-if="showSaveModal" class="modal-overlay" @click.self="closeSaveModal">
                <div class="modal">
                    <div class="modal-head">
                        <span>Сохранить как версию</span>
                        <button class="modal-close" @click="closeSaveModal">✕</button>
                    </div>
                    <div class="modal-body">
                        <label class="field-label">Комментарий <span class="field-optional">(необязательно)</span></label>
                        <input
                            v-model="saveLabel"
                            class="field-input"
                            type="text"
                            maxlength="120"
                            placeholder="Например: правки после ревью, обновил требования..."
                            @keydown.enter="saveVersion"
                        />
                        <p class="field-hint">Если оставить пустым — версия будет автоматической (хранятся последние 10).</p>
                    </div>
                    <div class="modal-foot">
                        <button class="btn-ghost" @click="closeSaveModal">Отмена</button>
                        <button class="btn-primary" @click="saveVersion" :disabled="savePending">
                            {{ savePending ? 'Сохранение...' : 'Сохранить версию' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Activate confirm modal -->
        <Teleport to="body">
            <div v-if="showActivateModal" class="modal-overlay" @click.self="showActivateModal = false">
                <div class="modal modal--warn">
                    <div class="modal-head">
                        <span>Активировать версию?</span>
                        <button class="modal-close" @click="showActivateModal = false">✕</button>
                    </div>
                    <div class="modal-body">
                        <div class="warn-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </div>
                        <p class="modal-warn-text">
                            Версия <strong>«{{ activateTarget?.label || fmtDate(activateTarget?.created_at) }}»</strong> станет активной и будет показываться всем пользователям на <code>/idol/apply</code>.
                        </p>
                        <p class="modal-warn-sub">Несохранённые изменения в редакторе будут потеряны. Сохраните их как версию, если нужно.</p>
                    </div>
                    <div class="modal-foot">
                        <button class="btn-ghost" @click="showActivateModal = false">Отмена</button>
                        <button class="btn-danger" @click="confirmActivate">Активировать</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Delete confirm modal -->
        <Teleport to="body">
            <div v-if="showDeleteModal" class="modal-overlay" @click.self="showDeleteModal = false">
                <div class="modal modal--warn">
                    <div class="modal-head">
                        <span>Удалить версию?</span>
                        <button class="modal-close" @click="showDeleteModal = false">✕</button>
                    </div>
                    <div class="modal-body">
                        <p class="modal-warn-text">
                            Версия <strong>«{{ deleteTarget?.label || fmtDate(deleteTarget?.created_at) }}»</strong> будет удалена безвозвратно.
                        </p>
                    </div>
                    <div class="modal-foot">
                        <button class="btn-ghost" @click="showDeleteModal = false">Отмена</button>
                        <button class="btn-danger" @click="confirmDelete">Удалить</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Diff modal -->
        <Teleport to="body">
            <div v-if="showDiffModal" class="modal-overlay" @click.self="showDiffModal = false">
                <div class="modal modal--diff">
                    <div class="modal-head">
                        <span>Сравнение версий</span>
                        <button class="modal-close" @click="showDiffModal = false">✕</button>
                    </div>
                    <div class="modal-body modal-body--diff">
                        <!-- Version Selection Header -->
                        <div class="diff-selectors">
                            <div class="diff-sel-group">
                                <label class="diff-sel-label">Было (Старая версия)</label>
                                <select v-model="diffVersionA" @change="runCompare" class="diff-select">
                                    <option v-for="v in versions" :key="v.id" :value="v.id">
                                        {{ v.label || '— автоматическая версия —' }} ({{ fmtDate(v.created_at) }})
                                    </option>
                                </select>
                            </div>
                            <div class="diff-sel-arrow">→</div>
                            <div class="diff-sel-group">
                                <label class="diff-sel-label">Стало (Новая версия)</label>
                                <select v-model="diffVersionB" @change="runCompare" class="diff-select">
                                    <option v-for="v in versions" :key="v.id" :value="v.id">
                                        {{ v.label || '— автоматическая версия —' }} ({{ fmtDate(v.created_at) }})
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Diff Output: Side-by-side text/HTML -->
                        <div v-if="diffLoading" class="diff-loading">Загружаем содержимое версий...</div>
                        <div v-else class="diff-side-by-side">
                            <div class="diff-column">
                                <div class="diff-column-head">Было (Старая версия)</div>
                                <div class="diff-column-body" v-html="htmlA || '<p class=\'empty-column\'>Нет содержимого</p>'"></div>
                            </div>
                            <div class="diff-column">
                                <div class="diff-column-head">Стало (Новая версия)</div>
                                <div class="diff-column-body" v-html="htmlB || '<p class=\'empty-column\'>Нет содержимого</p>'"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
/* ═══════════════════════════════════════════════════════
   Page shell
   ═══════════════════════════════════════════════════════ */
.article-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    height: calc(100vh - 110px);
    font-family: 'Rubik', sans-serif;
    min-height: 0;
}

/* ── Header ── */
.ap-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    flex-shrink: 0;
}

.ap-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: rgba(255,255,255,0.9);
    margin: 0 0 0.2rem;
}

.ap-sub {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.38);
    margin: 0;
}

.ap-sub code {
    font-size: 0.78rem;
    background: rgba(255,178,239,0.08);
    border: 1px solid rgba(255,178,239,0.18);
    border-radius: 3px;
    padding: 0.05em 0.3em;
    color: #ffb2ef;
}

.ap-header-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-shrink: 0;
}

/* ── Main layout ── */
.ap-layout {
    display: flex;
    gap: 1.25rem;
    flex: 1;
    min-height: 0;
}

.ap-editor-col,
.ap-preview-col {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

/* ── Pane ── */
.ap-pane {
    display: flex;
    flex-direction: column;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 8px;
    overflow: hidden;
    flex: 1;
    min-height: 0;
}

.ap-pane--preview {
    flex: 1;
    min-height: 0;
}

.pane-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.65rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    background: rgba(255,255,255,0.01);
    flex-shrink: 0;
}

.pane-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: rgba(255,255,255,0.7);
}

.pane-tag {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: rgba(255,255,255,0.35);
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 3px;
    padding: 0.18rem 0.5rem;
}

.pane-tag--pink {
    color: rgba(255,178,239,0.7);
    background: rgba(255,178,239,0.06);
    border-color: rgba(255,178,239,0.18);
}

.pane-body { padding: 0; }

.editor-body {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
}

/* ── Preview ── */
.preview-body {
    padding: 1.25rem 1.5rem;
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}

.preview-article {
    font-family: 'Rubik', sans-serif;
    max-width: 640px;
}

.article-body-preview {
    color: rgba(255,255,255,0.52);
    line-height: 1.85;
    font-size: 0.88rem;
}
.article-body-preview :deep(h1) { font-size: 1.5rem; font-weight: 700; color: rgba(255,255,255,0.88); margin: 0 0 0.85rem; }
.article-body-preview :deep(h2) { font-size: 1.2rem; font-weight: 700; color: rgba(255,255,255,0.82); margin: 2rem 0 0.75rem; }
.article-body-preview :deep(h2:first-child), .article-body-preview :deep(h1:first-child) { margin-top: 0; }
.article-body-preview :deep(h3) { font-size: 0.97rem; font-weight: 600; color: rgba(255,178,239,0.8); margin: 1.5rem 0 0.5rem; }
.article-body-preview :deep(p)  { margin: 0 0 1rem; color: rgba(255,255,255,0.52); }
.article-body-preview :deep(ul), .article-body-preview :deep(ol) { margin: 0 0 1rem; padding-left: 0; list-style: none; }
.article-body-preview :deep(li) { position: relative; padding-left: 1.4rem; margin-bottom: 0.6rem; color: rgba(255,255,255,0.52); }
.article-body-preview :deep(ul li::before) { content: ''; position: absolute; left: 0.25rem; top: 0.55em; width: 5px; height: 5px; border-radius: 50%; background: rgba(255,178,239,0.6); }
.article-body-preview :deep(li strong), .article-body-preview :deep(strong) { color: rgba(255,255,255,0.8); font-weight: 600; }
.article-body-preview :deep(blockquote) { border-left: 2px solid rgba(255,178,239,0.35); padding: 0.5em 0.9em; margin: 1em 0; background: rgba(255,178,239,0.03); color: rgba(255,255,255,0.42); font-style: italic; border-radius: 0 4px 4px 0; }
.article-body-preview :deep(code) { font-size: 0.82em; background: rgba(255,178,239,0.08); border: 1px solid rgba(255,178,239,0.18); border-radius: 3px; padding: 0.1em 0.3em; color: #ffb2ef; }
.article-body-preview :deep(hr) { border: none; border-top: 1px solid rgba(255,255,255,0.08); margin: 1.5em 0; }

:global(.empty-preview) { font-style: italic; color: rgba(255,255,255,0.2) !important; text-align: center; padding: 2rem 0; }

.preview-cta {
    display: flex;
    justify-content: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.07);
}

.btn-preview-cta {
    padding: 0.65rem 1.8rem;
    background: transparent;
    border: 1px solid rgba(255,178,239,0.22);
    border-radius: 6px;
    color: rgba(255,178,239,0.5);
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    cursor: not-allowed;
    font-family: inherit;
    opacity: 0.7;
}

/* ── Versions list (Horizontal) ── */
.ap-versions-top {
    flex-shrink: 0;
}

.versions-pane-horizontal {
    display: flex;
    align-items: center;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    gap: 0.75rem;
    overflow: hidden;
}

.vph-head {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex-shrink: 0;
    border-right: 1px solid rgba(255,255,255,0.06);
    padding-right: 0.75rem;
}

.vph-title {
    font-size: 0.78rem;
    font-weight: 600;
    color: rgba(255,255,255,0.70);
}

.vph-count {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    color: rgba(255,255,255,0.35);
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 3px;
    padding: 0.05rem 0.3rem;
    width: fit-content;
}

.versions-list-horizontal {
    display: flex;
    gap: 0.6rem;
    overflow-x: auto;
    flex: 1;
    min-width: 0;
    padding: 0.35rem 0.2rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
}

.versions-list-horizontal::-webkit-scrollbar {
    height: 4px;
}

.versions-list-horizontal::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
}

.versions-empty-horizontal {
    color: rgba(255,255,255,0.25);
    font-size: 0.78rem;
    padding: 0.5rem 0;
}

.version-card-horizontal {
    flex: 0 0 280px;
    padding: 0.5rem 0.65rem;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    transition: all 0.15s;
}

.version-card-horizontal:hover {
    border-color: rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.03);
}

.version-card-horizontal--active {
    border-color: rgba(255,178,239,0.3);
    background: rgba(255,178,239,0.04);
}

.vch-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.68rem;
}

.vc-active-badge {
    font-size: 0.68rem;
    font-weight: 700;
    color: #ffb2ef;
    letter-spacing: 0.04em;
}

.vc-date {
    font-size: 0.70rem;
    color: rgba(255,255,255,0.3);
}

.vch-label {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.65);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.version-card-horizontal--active .vch-label {
    color: rgba(255,255,255,0.78);
}

.vc-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin-top: 0.15rem;
}

.vc-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.25rem 0.45rem;
    font-size: 0.68rem;
    font-weight: 600;
    border-radius: 4px;
    cursor: pointer;
    font-family: inherit;
    border: 1px solid transparent;
    transition: all 0.12s;
    background: transparent;
}

.vc-btn svg { width: 10px; height: 10px; }

.vc-btn--activate {
    color: #4ade80;
    border-color: rgba(74,222,128,0.25);
}
.vc-btn--activate:hover {
    background: rgba(74,222,128,0.08);
    border-color: rgba(74,222,128,0.45);
}

.vc-btn--load {
    color: #38bdf8;
    border-color: rgba(56,189,248,0.25);
}
.vc-btn--load:hover {
    background: rgba(56,189,248,0.08);
    border-color: rgba(56,189,248,0.45);
}

.vc-btn--diff {
    color: rgba(255,255,255,0.4);
    border-color: rgba(255,255,255,0.1);
}
.vc-btn--diff:hover {
    color: rgba(255,255,255,0.75);
    border-color: rgba(255,255,255,0.22);
    background: rgba(255,255,255,0.04);
}

.vc-btn--delete {
    color: rgba(255,100,100,0.55);
    border-color: rgba(255,100,100,0.15);
    padding: 0.25rem 0.35rem;
    margin-left: auto;
}
.vc-btn--delete:hover {
    color: #ff6b6b;
    border-color: rgba(255,80,80,0.35);
    background: rgba(255,80,80,0.06);
}

/* ═══════════════════════════════════════════════════════
   Buttons
   ═══════════════════════════════════════════════════════ */
.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1.1rem;
    font-size: 0.82rem;
    font-weight: 600;
    font-family: inherit;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    background: linear-gradient(135deg, #9b6ee8, #a03466);
    color: #fff;
    box-shadow: 0 4px 14px rgba(155,110,232,0.22);
    transition: opacity 0.15s, transform 0.1s;
}
.btn-primary svg { width: 14px; height: 14px; }
.btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
.btn-primary:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }

.btn-ghost {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.52rem 0.9rem;
    font-size: 0.8rem;
    font-weight: 500;
    font-family: inherit;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 6px;
    cursor: pointer;
    background: rgba(255,255,255,0.03);
    color: rgba(255,255,255,0.55);
    transition: all 0.14s;
}
.btn-ghost svg { width: 13px; height: 13px; }
.btn-ghost:hover { border-color: rgba(255,255,255,0.25); color: rgba(255,255,255,0.85); background: rgba(255,255,255,0.06); }
.btn-ghost:disabled { opacity: 0.4; cursor: not-allowed; }

.btn-sm { padding: 0.42rem 0.8rem; font-size: 0.76rem; }

.btn-danger {
    padding: 0.55rem 1.1rem;
    font-size: 0.82rem;
    font-weight: 600;
    font-family: inherit;
    border: 1px solid rgba(255,80,80,0.35);
    border-radius: 6px;
    cursor: pointer;
    background: rgba(255,80,80,0.08);
    color: #ff6b6b;
    transition: all 0.14s;
}
.btn-danger:hover { background: rgba(255,80,80,0.15); border-color: rgba(255,80,80,0.6); }

/* ═══════════════════════════════════════════════════════
   Modals
   ═══════════════════════════════════════════════════════ */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.65);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
}

.modal {
    background: #0f0f18;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    width: 100%;
    max-width: 460px;
    box-shadow: 0 24px 60px rgba(0,0,0,0.7);
    overflow: hidden;
    animation: modal-in 0.18s ease-out;
}

.modal--warn { max-width: 420px; }

.modal--diff {
    max-width: 1200px;
    width: 92%;
    height: 85vh;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
}

@keyframes modal-in {
    from { opacity: 0; transform: scale(0.96) translateY(8px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    font-size: 0.9rem;
    font-weight: 600;
    color: rgba(255,255,255,0.85);
}

.modal-close {
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.35);
    cursor: pointer;
    font-size: 1rem;
    padding: 0.15rem 0.3rem;
    border-radius: 3px;
    transition: color 0.12s;
}
.modal-close:hover { color: rgba(255,255,255,0.75); }

.modal-body {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.modal-body--diff {
    overflow: hidden;
    flex: 1;
    min-height: 0;
    padding: 0;
}

.modal-foot {
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    padding: 0.9rem 1.25rem;
    border-top: 1px solid rgba(255,255,255,0.07);
}

/* Form fields */
.field-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: rgba(255,255,255,0.55);
}

.field-optional {
    font-weight: 400;
    color: rgba(255,255,255,0.3);
}

.field-input {
    width: 100%;
    padding: 0.6rem 0.8rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: rgba(255,255,255,0.85);
    font-family: inherit;
    font-size: 0.88rem;
    outline: none;
    transition: border-color 0.14s;
    box-sizing: border-box;
}
.field-input:focus { border-color: rgba(255,178,239,0.45); }

.field-hint {
    font-size: 0.74rem;
    color: rgba(255,255,255,0.28);
    margin: 0;
    line-height: 1.45;
}

/* Warn modal */
.warn-icon {
    display: flex;
    justify-content: center;
}
.warn-icon svg {
    width: 2.2rem;
    height: 2.2rem;
    color: #fbb740;
}
.modal-warn-text {
    font-size: 0.88rem;
    color: rgba(255,255,255,0.7);
    text-align: center;
    margin: 0;
    line-height: 1.5;
}
.modal-warn-text strong { color: rgba(255,255,255,0.9); }
.modal-warn-text code {
    font-size: 0.82em;
    background: rgba(255,178,239,0.08);
    border: 1px solid rgba(255,178,239,0.18);
    border-radius: 3px;
    padding: 0.05em 0.3em;
    color: #ffb2ef;
}
.modal-warn-sub {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.3);
    text-align: center;
    margin: 0;
}

/* Diff modal */
.diff-loading {
    padding: 2rem;
    text-align: center;
    color: rgba(255,255,255,0.3);
    font-size: 0.88rem;
}

.diff-selectors {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    background: rgba(255,255,255,0.01);
}

.diff-sel-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1;
    min-width: 0;
}

.diff-sel-label {
    font-size: 0.65rem;
    font-weight: 600;
    color: rgba(255,255,255,0.35);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.diff-select {
    width: 100%;
    background: rgba(0,0,0,0.25);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 6px;
    padding: 0.4rem 0.6rem;
    font-size: 0.76rem;
    color: rgba(255,255,255,0.8);
    font-family: inherit;
    outline: none;
    transition: all 0.15s;
    cursor: pointer;
}

.diff-select:focus {
    border-color: rgba(255,178,239,0.35);
    box-shadow: 0 0 0 2px rgba(255,178,239,0.08);
}

.diff-select option {
    background: #111;
    color: #fff;
}

.diff-sel-arrow {
    color: rgba(255,255,255,0.15);
    font-size: 1rem;
    margin-top: 1rem;
    user-select: none;
}

.diff-empty-state {
    padding: 3rem;
    text-align: center;
    color: rgba(255,255,255,0.2);
    font-size: 0.82rem;
    font-style: italic;
}

.diff-side-by-side {
    display: flex;
    flex: 1;
    min-height: 0;
    border-top: 1px solid rgba(255,255,255,0.06);
    background: rgba(0,0,0,0.15);
}

.diff-column {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.diff-column:first-child {
    border-right: 1px solid rgba(255,255,255,0.06);
}

.diff-column-head {
    padding: 0.5rem 1rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255,255,255,0.38);
    background: rgba(255,255,255,0.01);
    border-bottom: 1px solid rgba(255,255,255,0.04);
}

.diff-column-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    color: rgba(255,255,255,0.52);
    line-height: 1.85;
    font-size: 0.88rem;
}

.diff-column-body :deep(h1) { font-size: 1.5rem; font-weight: 700; color: rgba(255,255,255,0.88); margin: 0 0 0.85rem; }
.diff-column-body :deep(h2) { font-size: 1.2rem; font-weight: 700; color: rgba(255,255,255,0.82); margin: 2rem 0 0.75rem; }
.diff-column-body :deep(h2:first-child), .diff-column-body :deep(h1:first-child) { margin-top: 0; }
.diff-column-body :deep(h3) { font-size: 0.97rem; font-weight: 600; color: rgba(255,178,239,0.8); margin: 1.5rem 0 0.5rem; }
.diff-column-body :deep(p)  { margin: 0 0 1rem; color: rgba(255,255,255,0.52); }
.diff-column-body :deep(ul), .diff-column-body :deep(ol) { margin: 0 0 1rem; padding-left: 0; list-style: none; }
.diff-column-body :deep(li) { position: relative; padding-left: 1.4rem; margin-bottom: 0.6rem; color: rgba(255,255,255,0.52); }
.diff-column-body :deep(ul li::before) { content: ''; position: absolute; left: 0.25rem; top: 0.55em; width: 5px; height: 5px; border-radius: 50%; background: rgba(255,178,239,0.6); }
.diff-column-body :deep(li strong), .diff-column-body :deep(strong) { color: rgba(255,255,255,0.8); font-weight: 600; }
.diff-column-body :deep(blockquote) { border-left: 2px solid rgba(255,178,239,0.35); padding: 0.5em 0.9em; margin: 1em 0; background: rgba(255,178,239,0.03); color: rgba(255,255,255,0.42); font-style: italic; border-radius: 0 4px 4px 0; }
.diff-column-body :deep(code) { font-size: 0.82em; background: rgba(255,178,239,0.08); border: 1px solid rgba(255,178,239,0.18); border-radius: 3px; padding: 0.1em 0.3em; color: #ffb2ef; }
.diff-column-body :deep(hr) { border: none; border-top: 1px solid rgba(255,255,255,0.08); margin: 1.5em 0; }

.empty-column {
    font-style: italic;
    color: rgba(255,255,255,0.2) !important;
    text-align: center;
    padding: 2rem 0;
}

/* ═══════════════════════════════════════════════════════
   Responsive
   ═══════════════════════════════════════════════════════ */
@media (max-width: 960px) {
    .article-page { height: auto; }
    .ap-layout { flex-direction: column; }
    .ap-versions-col { width: 100%; }
    .versions-pane { max-height: 300px; }
}
</style>
