<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import { watch, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Начните писать...' },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue,
    extensions: [StarterKit, Underline],
    onUpdate: () => {
        emit('update:modelValue', editor.value.getHTML());
    },
});

watch(() => props.modelValue, (value) => {
    if (!editor.value) return;
    if (editor.value.getHTML() === value) return;
    editor.value.commands.setContent(value, false);
});

onBeforeUnmount(() => { if (editor.value) editor.value.destroy(); });
</script>

<template>
    <div v-if="editor" class="html-editor">
        <div class="editor-toolbar">
            <!-- History -->
            <button type="button" class="toolbar-btn" @click="editor.chain().focus().undo().run()" :disabled="!editor.can().undo()" title="Отменить">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
            </button>
            <button type="button" class="toolbar-btn" @click="editor.chain().focus().redo().run()" :disabled="!editor.can().redo()" title="Повторить">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 14 20 9 15 4"/><path d="M4 20v-7a4 4 0 0 1 4-4h12"/></svg>
            </button>

            <div class="toolbar-sep"></div>

            <!-- Headings -->
            <button type="button" class="toolbar-btn toolbar-btn--text" :class="{ 'is-active': editor.isActive('heading', { level: 2 }) }" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" title="Заголовок H2">H2</button>
            <button type="button" class="toolbar-btn toolbar-btn--text" :class="{ 'is-active': editor.isActive('heading', { level: 3 }) }" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()" title="Заголовок H3">H3</button>
            <button type="button" class="toolbar-btn toolbar-btn--text" :class="{ 'is-active': editor.isActive('paragraph') }" @click="editor.chain().focus().setParagraph().run()" title="Обычный текст">P</button>

            <div class="toolbar-sep"></div>

            <!-- Inline formatting -->
            <button type="button" class="toolbar-btn" :class="{ 'is-active': editor.isActive('bold') }" @click="editor.chain().focus().toggleBold().run()" title="Жирный (Ctrl+B)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/></svg>
            </button>
            <button type="button" class="toolbar-btn" :class="{ 'is-active': editor.isActive('italic') }" @click="editor.chain().focus().toggleItalic().run()" title="Курсив (Ctrl+I)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>
            </button>
            <button type="button" class="toolbar-btn" :class="{ 'is-active': editor.isActive('underline') }" @click="editor.chain().focus().toggleUnderline().run()" title="Подчёркнутый (Ctrl+U)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v7a6 6 0 0 0 6 6 6 6 0 0 0 6-6V3"/><line x1="4" y1="21" x2="20" y2="21"/></svg>
            </button>
            <button type="button" class="toolbar-btn" :class="{ 'is-active': editor.isActive('strike') }" @click="editor.chain().focus().toggleStrike().run()" title="Зачёркнутый">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="12" x2="20" y2="12"/><path d="M7.5 4.27c-.62.62-.94 1.4-.94 2.23 0 .7.18 1.35.53 1.93"/><path d="M16.5 19.73c.62-.62.94-1.4.94-2.23 0-.7-.18-1.35-.53-1.93"/><path d="M10.5 4h3c1.1 0 2 .45 2 1.5S14.6 7 13.5 7h-3c-1.1 0-2-.45-2-1.5S9.4 4 10.5 4z"/><path d="M9.5 17h5c1.1 0 2 .45 2 1.5S15.6 20 14.5 20h-5c-1.1 0-2-.45-2-1.5S8.4 17 9.5 17z"/></svg>
            </button>

            <div class="toolbar-sep"></div>

            <!-- Lists -->
            <button type="button" class="toolbar-btn" :class="{ 'is-active': editor.isActive('bulletList') }" @click="editor.chain().focus().toggleBulletList().run()" title="Маркированный список">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1.5" fill="currentColor" stroke="none"/></svg>
            </button>
            <button type="button" class="toolbar-btn" :class="{ 'is-active': editor.isActive('orderedList') }" @click="editor.chain().focus().toggleOrderedList().run()" title="Нумерованный список">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
            </button>

            <div class="toolbar-sep"></div>

            <!-- Block elements -->
            <button type="button" class="toolbar-btn" :class="{ 'is-active': editor.isActive('blockquote') }" @click="editor.chain().focus().toggleBlockquote().run()" title="Цитата">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>
            </button>
            <button type="button" class="toolbar-btn" :class="{ 'is-active': editor.isActive('code') }" @click="editor.chain().focus().toggleCode().run()" title="Встроенный код">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            </button>
            <button type="button" class="toolbar-btn" @click="editor.chain().focus().setHorizontalRule().run()" title="Горизонтальная линия">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="2" y1="12" x2="22" y2="12"/></svg>
            </button>

            <div class="toolbar-sep"></div>

            <!-- Clear formatting -->
            <button type="button" class="toolbar-btn toolbar-btn--danger" @click="editor.chain().focus().clearNodes().unsetAllMarks().run()" title="Очистить форматирование">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l18 0"/><path d="M8 6l0 -2 a1 1 0 0 1 1 -1l6 0a1 1 0 0 1 1 1l0 2"/><path d="M5 6l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/></svg>
            </button>
        </div>

        <editor-content :editor="editor" class="editor-content-wrap" />
    </div>
</template>

<style scoped>
.html-editor {
    display: flex;
    flex-direction: column;
    width: 100%;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    overflow: hidden;
    flex: 1;
    min-height: 0;
}

/* ── Toolbar ── */
.editor-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 2px;
    padding: 6px 8px;
    background: rgba(255, 255, 255, 0.02);
    border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    flex-shrink: 0;
}

.toolbar-sep {
    width: 1px;
    height: 20px;
    background: rgba(255, 255, 255, 0.09);
    margin: 0 4px;
    flex-shrink: 0;
}

.toolbar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 28px;
    padding: 0;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 5px;
    color: rgba(255, 255, 255, 0.42);
    cursor: pointer;
    transition: color 0.12s, background 0.12s, border-color 0.12s;
    flex-shrink: 0;
}

.toolbar-btn svg {
    width: 14px;
    height: 14px;
    pointer-events: none;
}

.toolbar-btn--text {
    font-size: 11px;
    font-weight: 700;
    font-family: inherit;
    width: auto;
    padding: 0 8px;
    letter-spacing: 0.03em;
}

.toolbar-btn:hover:not(:disabled) {
    color: rgba(255, 255, 255, 0.88);
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.1);
}

.toolbar-btn.is-active {
    color: #ffb2ef;
    background: rgba(255, 178, 239, 0.1);
    border-color: rgba(255, 178, 239, 0.24);
}

.toolbar-btn--danger:hover:not(:disabled) {
    color: #ff8080;
    background: rgba(255, 80, 80, 0.07);
    border-color: rgba(255, 80, 80, 0.18);
}

.toolbar-btn:disabled {
    opacity: 0.22;
    cursor: not-allowed;
}

/* ── Editor area ── */
.editor-content-wrap {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: auto;
}

:deep(.tiptap) {
    flex: 1;
    min-height: 320px;
    padding: 1.1rem 1.3rem;
    color: rgba(255, 255, 255, 0.75);
    background: rgba(0, 0, 0, 0.18);
    outline: none;
    font-size: 0.93rem;
    line-height: 1.72;
    font-family: 'Rubik', sans-serif;
}

:deep(.tiptap p) { margin: 0 0 0.8em; }
:deep(.tiptap p:last-child) { margin-bottom: 0; }

:deep(.tiptap h2) {
    font-size: 1.25rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.88);
    margin: 1.5em 0 0.5em;
}
:deep(.tiptap h2:first-child) { margin-top: 0; }

:deep(.tiptap h3) {
    font-size: 1.05rem;
    font-weight: 600;
    color: rgba(255, 178, 239, 0.85);
    margin: 1.2em 0 0.4em;
}

:deep(.tiptap ul) { list-style: disc; padding-left: 1.4rem; margin: 0 0 0.8em; }
:deep(.tiptap ol) { list-style: decimal; padding-left: 1.4rem; margin: 0 0 0.8em; }
:deep(.tiptap li) { margin-bottom: 0.3em; color: rgba(255, 255, 255, 0.62); }

:deep(.tiptap strong) { font-weight: 700; color: rgba(255, 255, 255, 0.9); }
:deep(.tiptap em)     { font-style: italic; color: rgba(255, 255, 255, 0.58); }
:deep(.tiptap u)      { text-decoration: underline; text-decoration-color: rgba(255, 178, 239, 0.5); }
:deep(.tiptap s)      { text-decoration: line-through; color: rgba(255, 255, 255, 0.32); }

:deep(.tiptap blockquote) {
    border-left: 2px solid rgba(255, 178, 239, 0.4);
    padding: 0.5em 1em;
    margin: 1em 0;
    background: rgba(255, 178, 239, 0.03);
    color: rgba(255, 255, 255, 0.48);
    font-style: italic;
    border-radius: 0 4px 4px 0;
}

:deep(.tiptap code) {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.85em;
    background: rgba(255, 178, 239, 0.08);
    border: 1px solid rgba(255, 178, 239, 0.18);
    border-radius: 3px;
    padding: 0.1em 0.35em;
    color: #ffb2ef;
}

:deep(.tiptap hr) {
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin: 1.5em 0;
}
</style>
