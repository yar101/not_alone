<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { EditPen } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';

const props = defineProps({
    languages: { default: null },
    isOwner:   { type: Boolean, default: false },
});

const editModal = ref(false);

const ALL_LANGUAGES = [
    { code: 'ru', name: 'Русский', flag: '🇷🇺' },
    { code: 'en', name: 'English', flag: '🇬🇧' },
    { code: 'uk', name: 'Українська', flag: '🇺🇦' },
    { code: 'be', name: 'Беларуская', flag: '🇧🇾' },
    { code: 'de', name: 'Deutsch', flag: '🇩🇪' },
    { code: 'fr', name: 'Français', flag: '🇫🇷' },
    { code: 'es', name: 'Español', flag: '🇪🇸' },
    { code: 'it', name: 'Italiano', flag: '🇮🇹' },
    { code: 'pt', name: 'Português', flag: '🇵🇹' },
    { code: 'pl', name: 'Polski', flag: '🇵🇱' },
    { code: 'cs', name: 'Čeština', flag: '🇨🇿' },
    { code: 'sk', name: 'Slovenčina', flag: '🇸🇰' },
    { code: 'nl', name: 'Nederlands', flag: '🇳🇱' },
    { code: 'sv', name: 'Svenska', flag: '🇸🇪' },
    { code: 'no', name: 'Norsk', flag: '🇳🇴' },
    { code: 'da', name: 'Dansk', flag: '🇩🇰' },
    { code: 'fi', name: 'Suomi', flag: '🇫🇮' },
    { code: 'tr', name: 'Türkçe', flag: '🇹🇷' },
    { code: 'ar', name: 'العربية', flag: '🇸🇦' },
    { code: 'zh', name: '中文', flag: '🇨🇳' },
    { code: 'ja', name: '日本語', flag: '🇯🇵' },
    { code: 'ko', name: '한국어', flag: '🇰🇷' },
    { code: 'hi', name: 'हिन्दी', flag: '🇮🇳' },
    { code: 'kk', name: 'Қазақша', flag: '🇰🇿' },
    { code: 'uz', name: "O'zbek", flag: '🇺🇿' },
];

function langInfo(code) {
    return ALL_LANGUAGES.find(l => l.code === code) ?? { code, name: code, flag: '🌐' };
}

const selected = ref(new Set(Array.isArray(props.languages) ? props.languages : []));
const form = useForm({ languages: [] });

function toggleLang(code) {
    if (selected.value.has(code)) selected.value.delete(code);
    else selected.value.add(code);
}

function submit() {
    form.languages = [...selected.value];
    form.patch(route('profile.update.languages'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => editModal.value = false,
    });
}

function openEdit() {
    selected.value = new Set(Array.isArray(props.languages) ? props.languages : []);
    editModal.value = true;
}
</script>

<template>
    <div id="tour-languages" class="block-section">
        <div class="section-header">
            <span class="section-title">Языки</span>
            <button v-if="isOwner" class="edit-btn" @click="openEdit" title="Редактировать">
                <el-icon><EditPen /></el-icon>
            </button>
        </div>

        <div v-if="languages?.length" class="tags-row">
            <span v-for="code in languages" :key="code" class="tag">
                {{ langInfo(code).flag }} {{ langInfo(code).name }}
            </span>
        </div>
        <p v-else-if="isOwner" class="empty">Укажи языки, которыми владеешь</p>
        <p v-else class="empty">Не указано</p>

        <SiteModal :show="editModal" variant="pink" :compact="true" @close="editModal = false">
            <div class="edit-form">
                <h3 class="edit-title">Языки</h3>
                <div class="lang-grid">
                    <button
                        v-for="l in ALL_LANGUAGES"
                        :key="l.code"
                        type="button"
                        class="lang-btn"
                        :class="{ active: selected.has(l.code) }"
                        @click="toggleLang(l.code)"
                    >{{ l.flag }} {{ l.name }}</button>
                </div>
                <button class="save-btn" :disabled="form.processing" @click="submit">Сохранить</button>
            </div>
        </SiteModal>
    </div>
</template>

<style scoped>
.block-section {
    padding: 1.5rem 2rem;
    position: relative;
    cursor: default;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.9rem;
}

.section-title {
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #a0a0ff;
}

.edit-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 26px; height: 26px;
    padding: 0;
    border-radius: 3px;
    border: 1px solid transparent;
    background: transparent;
    cursor: pointer;
    color: rgba(255,255,255,0.25);
    font-size: 0.95rem;
    opacity: 0.75;
    transition: opacity 0.18s, color 0.18s, border-color 0.18s, background 0.18s, box-shadow 0.18s;
}
.block-section:hover .edit-btn {
    opacity: 1;
    color: rgba(160, 160, 255, 0.8);
    border-color: rgba(160, 160, 255, 0.35);
    background: rgba(160, 160, 255, 0.08);
}
.edit-btn:hover {
    color: #a0a0ff;
    border-color: rgba(160, 160, 255, 0.7);
    background: rgba(160, 160, 255, 0.16);
    box-shadow: 0 0 8px rgba(160, 160, 255, 0.35);
}

.tags-row { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.tag {
    padding: 0.28rem 0.65rem;
    border-radius: 3px;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.8);
    font-size: 1rem;
}
.empty { color: rgba(255,255,255,0.25); font-size: 1rem; font-style: italic; margin: 0; }

/* ── Форма редактирования ─────────────────────────────────── */
.edit-form { padding: 0.5rem 0.25rem; }
.edit-title { font-size: 1.1rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0 0 0.75rem; }
.lang-grid { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1rem; }
.lang-btn {
    padding: 0.3rem 0.75rem;
    border-radius: 3px;
    border: 1px solid rgba(255,255,255,0.1); background: transparent;
    color: rgba(255,255,255,0.5); font-size: 0.9rem; cursor: pointer; font-family: inherit; transition: all 0.15s;
}
.lang-btn.active { border-color: rgba(160,160,255,0.55); background: rgba(160,160,255,0.1); color: #fff; }
.save-btn {
    width: 100%; padding: 0.75rem;
    border-radius: 3px; border: 1px solid rgba(160,160,255,0.4);
    background: rgba(160,160,255,0.1);
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.save-btn:hover:not(:disabled) { background: rgba(160,160,255,0.2); }
.save-btn:disabled { opacity: 0.45; cursor: not-allowed; }
</style>
