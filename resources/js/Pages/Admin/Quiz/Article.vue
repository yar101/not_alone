<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import HtmlEditor from '@/Components/Admin/HtmlEditor.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    article_html: String,
});

const form = useForm({
    html: props.article_html || '',
});

function save() {
    form.patch(route('admin.quiz.article.update'), {
        preserveScroll: true,
    });
}

const activeTab = ref('edit'); // 'edit' | 'preview'
</script>

<template>
    <div class="article-page-wrap">
        <div class="header-section">
            <div>
                <h1 class="page-title">Статья о вступлении в Айдолы</h1>
                <p class="page-subtitle">Текст, который видит пользователь перед началом тестирования для получения статуса Айдола.</p>
            </div>
            <div class="header-actions">
                <Transition name="fade">
                    <span v-if="form.wasSuccessful" class="save-success-msg">✓ Сохранено успешно</span>
                </Transition>
                <button @click="save" class="btn-save-article" :disabled="form.processing">
                    {{ form.processing ? 'Сохранение...' : 'Сохранить изменения' }}
                </button>
            </div>
        </div>

        <!-- Layout split: Editor on Left, Live Preview on Right -->
        <div class="editor-layout">
            <!-- Left Pane: Editor -->
            <div class="editor-pane">
                <div class="pane-header">
                    <span class="pane-title">Текстовый редактор</span>
                    <span class="pane-badge">HTML / TipTap</span>
                </div>
                <div class="pane-body">
                    <HtmlEditor v-model="form.html" placeholder="Начните писать требования, правила или вдохновляющее вступление..." />
                    <p class="editor-help-text">
                        Используйте панель инструментов сверху для выделения жирным, курсивом, создания списков и структурирования заголовками.
                    </p>
                </div>
            </div>

            <!-- Right Pane: Live Preview -->
            <div class="preview-pane">
                <div class="pane-header">
                    <span class="pane-title">Предпросмотр на сайте</span>
                    <span class="pane-badge pane-badge--pink">Живой вид</span>
                </div>
                <div class="pane-body preview-scroll">
                    <!-- Preview simulating site styling -->
                    <div class="apply-card-preview">
                        <div class="step-eyebrow">ЗАЯВКА НА СТАТУС</div>
                        <div class="article-body-preview" v-html="form.html || '<p class=empty-preview>Текст статьи пуст. Начните вводить текст в редакторе слева...</p>'"></div>
                        <button class="btn-primary-preview" disabled>Начать тест</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.article-page-wrap {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    height: calc(100vh - 120px);
    font-family: 'Rubik', sans-serif;
}

.header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    padding-bottom: 1rem;
}

.page-title {
    font-size: 1.4rem;
    color: #fff;
    margin: 0 0 0.25rem;
    font-weight: 600;
}

.page-subtitle {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.45);
    margin: 0;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.save-success-msg {
    font-size: 0.88rem;
    color: #4ade80;
    font-weight: 500;
}

.btn-save-article {
    padding: 0.65rem 1.5rem;
    background: linear-gradient(135deg, #9B6EE8, #a03466);
    border: none;
    border-radius: 4px;
    color: #fff;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.15s, transform 0.1s;
    box-shadow: 0 4px 16px rgba(155, 110, 232, 0.25);
}

.btn-save-article:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.btn-save-article:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* ── Split Layout ── */
.editor-layout {
    display: flex;
    gap: 1.5rem;
    flex: 1;
    min-height: 0;
}

.editor-pane, .preview-pane {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 6px;
    min-width: 0;
}

.pane-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.85rem 1.1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    background: rgba(255, 255, 255, 0.01);
}

.pane-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.85);
}

.pane-badge {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 0.2rem 0.5rem;
    background: rgba(155, 110, 232, 0.15);
    border: 1px solid rgba(155, 110, 232, 0.3);
    color: #ffb2ef;
    border-radius: 3px;
}

.pane-badge--pink {
    background: rgba(255, 178, 239, 0.15);
    border-color: rgba(255, 178, 239, 0.3);
    color: #ffb2ef;
}

.pane-body {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    flex: 1;
    min-height: 0;
}

.preview-scroll {
    overflow-y: auto;
}

.editor-help-text {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.3);
    margin: 0;
    line-height: 1.45;
}

/* ── Live Preview Styles ── */
.apply-card-preview {
    width: 100%;
    max-width: 500px;
    margin: 1rem auto;
    background: #0a0a0f;
    border: 1px solid rgba(255, 178, 239, 0.18);
    border-radius: 4px;
    padding: 2.5rem 2rem;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.6);
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    text-align: center;
}

.step-eyebrow {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #ffb2ef;
}

.article-body-preview {
    text-align: left;
    width: 100%;
    color: rgba(255, 255, 255, 0.85);
    line-height: 1.7;
    font-size: 0.95rem;
}

.article-body-preview :deep(h2) {
    font-size: 1.45rem;
    font-weight: 700;
    color: #fff;
    margin-top: 0;
    margin-bottom: 0.9rem;
    background: linear-gradient(120deg, #fff, #ffb2ef);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.article-body-preview :deep(h3) {
    font-size: 1.12rem;
    font-weight: 600;
    color: #ffb2ef;
    margin-top: 1.4rem;
    margin-bottom: 0.7rem;
}

.article-body-preview :deep(p) {
    margin-top: 0;
    margin-bottom: 0.9rem;
    color: rgba(255, 255, 255, 0.7);
}

.article-body-preview :deep(ul), .article-body-preview :deep(ol) {
    margin-top: 0;
    margin-bottom: 1.1rem;
    padding-left: 1.25rem;
}

.article-body-preview :deep(li) {
    margin-bottom: 0.45rem;
    color: rgba(255, 255, 255, 0.7);
}

.article-body-preview :deep(li strong) {
    color: rgba(255, 255, 255, 0.95);
    font-weight: 600;
}

.empty-preview {
    font-style: italic;
    color: rgba(255, 255, 255, 0.25) !important;
    text-align: center;
    padding: 2rem 0;
}

.btn-primary-preview {
    padding: 0.7rem 2rem;
    background: #ffb2ef;
    border: none;
    border-radius: 3px;
    color: #fff;
    font-size: 0.9rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    opacity: 0.6;
    cursor: not-allowed;
    margin-top: 0.75rem;
    align-self: center;
    width: fit-content;
}

@media (max-width: 900px) {
    .editor-layout {
        flex-direction: column;
        height: auto;
        overflow: visible;
    }
    .article-page-wrap {
        height: auto;
    }
}
</style>
