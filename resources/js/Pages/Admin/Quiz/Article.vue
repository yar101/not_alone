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
                    <div class="apply-card-preview">
                        <div class="article-body-preview" v-html="form.html || '<p class=empty-preview>Текст статьи пуст. Начните вводить текст в редакторе слева...</p>'"></div>
                        <div class="preview-actions">
                            <button class="btn-primary-preview" disabled>Начать тест</button>
                        </div>
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
    padding: 1.5rem 0.5rem 2rem;
    display: flex;
    flex-direction: column;
    gap: 0;
    font-family: 'Rubik', sans-serif;
}

.article-body-preview {
    text-align: left;
    width: 100%;
    max-width: 100%;
    color: rgba(255, 255, 255, 0.52);
    line-height: 1.85;
    font-size: 0.88rem;
}

.article-body-preview :deep(h1) {
    font-size: 1.5rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.88);
    margin: 0 0 0.85rem;
}

.article-body-preview :deep(h2) {
    font-size: 1.2rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.82);
    margin: 2rem 0 0.75rem;
}

.article-body-preview :deep(h2:first-child),
.article-body-preview :deep(h1:first-child) {
    margin-top: 0;
}

.article-body-preview :deep(h3) {
    font-size: 0.97rem;
    font-weight: 600;
    color: rgba(255, 178, 239, 0.8);
    margin: 1.5rem 0 0.5rem;
}

.article-body-preview :deep(p) {
    margin: 0 0 1rem;
    color: rgba(255, 255, 255, 0.52);
}

.article-body-preview :deep(ul),
.article-body-preview :deep(ol) {
    margin: 0 0 1rem;
    padding-left: 0;
    list-style: none;
}

.article-body-preview :deep(li) {
    position: relative;
    padding-left: 1.4rem;
    margin-bottom: 0.6rem;
    color: rgba(255, 255, 255, 0.52);
}

.article-body-preview :deep(ul li::before) {
    content: '';
    position: absolute;
    left: 0.25rem;
    top: 0.55em;
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: rgba(255, 178, 239, 0.6);
}

.article-body-preview :deep(li strong) {
    color: rgba(255, 255, 255, 0.8);
    font-weight: 600;
}

.article-body-preview :deep(strong) {
    color: rgba(255, 255, 255, 0.8);
    font-weight: 600;
}

.article-body-preview :deep(blockquote) {
    border-left: 2px solid rgba(255, 178, 239, 0.35);
    padding: 0.5em 0.9em;
    margin: 1em 0;
    background: rgba(255, 178, 239, 0.03);
    color: rgba(255, 255, 255, 0.42);
    font-style: italic;
    border-radius: 0 4px 4px 0;
}

.article-body-preview :deep(code) {
    font-size: 0.82em;
    background: rgba(255, 178, 239, 0.08);
    border: 1px solid rgba(255, 178, 239, 0.18);
    border-radius: 3px;
    padding: 0.1em 0.3em;
    color: #ffb2ef;
}

.article-body-preview :deep(hr) {
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    margin: 1.5em 0;
}

.empty-preview {
    font-style: italic;
    color: rgba(255, 255, 255, 0.2) !important;
    text-align: center;
    padding: 2.5rem 0;
}

.preview-actions {
    display: flex;
    justify-content: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.07);
}

.btn-primary-preview {
    padding: 0.7rem 2rem;
    background: transparent;
    border: 1px solid rgba(255, 178, 239, 0.25);
    border-radius: 6px;
    color: rgba(255, 178, 239, 0.6);
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    opacity: 0.7;
    cursor: not-allowed;
    font-family: inherit;
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
