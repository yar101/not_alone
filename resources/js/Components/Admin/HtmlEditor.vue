<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import { watch, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Начните писать...',
    },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Underline,
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none focus:outline-none min-h-[150px] p-3 text-white bg-[#0e0e15] border border-gray-800 rounded-b-md focus:border-cyan-500',
        },
    },
    onUpdate: () => {
        emit('update:modelValue', editor.value.getHTML());
    },
});

watch(() => props.modelValue, (value) => {
    if (!editor.value) return;
    const isSame = editor.value.getHTML() === value;
    if (isSame) return;
    editor.value.commands.setContent(value, false);
});

onBeforeUnmount(() => {
    if (editor.value) {
        editor.value.destroy();
    }
});
</script>

<template>
    <div v-if="editor" class="html-editor">
        <!-- Toolbar -->
        <div class="editor-toolbar">
            <button
                type="button"
                :class="{ 'is-active': editor.isActive('bold') }"
                @click="editor.chain().focus().toggleBold().run()"
                title="Жирный"
            >
                <b>B</b>
            </button>
            <button
                type="button"
                :class="{ 'is-active': editor.isActive('italic') }"
                @click="editor.chain().focus().toggleItalic().run()"
                title="Курсив"
            >
                <i>I</i>
            </button>
            <button
                type="button"
                :class="{ 'is-active': editor.isActive('underline') }"
                @click="editor.chain().focus().toggleUnderline().run()"
                title="Подчеркнутый"
            >
                <u>U</u>
            </button>
            <div class="editor-divider"></div>
            <button
                type="button"
                :class="{ 'is-active': editor.isActive('heading', { level: 2 }) }"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                title="Заголовок H2"
            >
                H2
            </button>
            <button
                type="button"
                :class="{ 'is-active': editor.isActive('heading', { level: 3 }) }"
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                title="Заголовок H3"
            >
                H3
            </button>
            <button
                type="button"
                :class="{ 'is-active': editor.isActive('paragraph') }"
                @click="editor.chain().focus().setParagraph().run()"
                title="Обычный текст"
            >
                P
            </button>
            <div class="editor-divider"></div>
            <button
                type="button"
                :class="{ 'is-active': editor.isActive('bulletList') }"
                @click="editor.chain().focus().toggleBulletList().run()"
                title="Маркированный список"
            >
                • Список
            </button>
            <button
                type="button"
                :class="{ 'is-active': editor.isActive('orderedList') }"
                @click="editor.chain().focus().toggleOrderedList().run()"
                title="Нумерованный список"
            >
                1. Список
            </button>
        </div>

        <!-- Content Area -->
        <editor-content :editor="editor" />
    </div>
</template>

<style scoped>
.html-editor {
    display: flex;
    flex-direction: column;
    width: 100%;
}

.editor-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
    padding: 6px;
    background-color: #161622;
    border: 1px solid #1f1f2e;
    border-bottom: none;
    border-top-left-radius: 6px;
    border-top-right-radius: 6px;
}

.editor-toolbar button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    padding: 2px 6px;
    font-size: 13px;
    font-family: inherit;
    color: #a0a0b0;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.editor-toolbar button:hover {
    color: #ffffff;
    background-color: #242436;
}

.editor-toolbar button.is-active {
    color: #00ffff;
    background-color: #242436;
    border-color: rgba(0, 255, 255, 0.3);
}

.editor-divider {
    width: 1px;
    height: 18px;
    background-color: #2b2b3d;
    margin: 0 4px;
}

:deep(.tiptap) {
    min-height: 160px;
    max-height: 350px;
    overflow-y: auto;
    border: 1px solid #1f1f2e;
    border-bottom-left-radius: 6px;
    border-bottom-right-radius: 6px;
    padding: 12px;
    color: #e2e2e9;
    background-color: #0d0d14;
    outline: none;
    font-size: 14px;
    line-height: 1.5;
}

:deep(.tiptap:focus) {
    border-color: #00ffff;
    box-shadow: 0 0 0 1px rgba(0, 255, 255, 0.15);
}

/* Basic prose styles for the editor */
:deep(.tiptap p) {
    margin-top: 0;
    margin-bottom: 0.75em;
}
:deep(.tiptap h2) {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    color: #ffffff;
}
:deep(.tiptap h3) {
    font-size: 1.1rem;
    font-weight: 600;
    margin-top: 0.75rem;
    margin-bottom: 0.4rem;
    color: #ffffff;
}
:deep(.tiptap ul) {
    list-style-type: disc;
    padding-left: 1.25rem;
    margin-bottom: 0.75rem;
}
:deep(.tiptap ol) {
    list-style-type: decimal;
    padding-left: 1.25rem;
    margin-bottom: 0.75rem;
}
:deep(.tiptap li) {
    margin-bottom: 0.25rem;
}
:deep(.tiptap strong) {
    font-weight: bold;
}
:deep(.tiptap em) {
    font-style: italic;
}
:deep(.tiptap u) {
    text-decoration: underline;
}
</style>
