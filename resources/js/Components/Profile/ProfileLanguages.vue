<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Edit } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';

const props = defineProps({
    languages: { type: Array, default: () => [] },
    isOwner: { type: Boolean, default: false },
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

const selected = ref(new Set(props.languages));
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
    selected.value = new Set(props.languages);
    editModal.value = true;
}
</script>

<template>
    <div id="tour-languages" class="block-card">
        <div class="block-header">
            <h2 class="block-title">Языки</h2>
            <button v-if="isOwner" class="edit-btn" @click="openEdit" title="Редактировать">
                <el-icon><Edit /></el-icon>
            </button>
        </div>

        <div v-if="languages.length" class="tags-row">
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
                <button class="save-btn" :disabled="form.processing" @click="submit">
                    Сохранить
                </button>
            </div>
        </SiteModal>
    </div>
</template>

<style scoped>
.block-card {
    padding: 1.25rem 1.5rem;
    background: rgba(255,255,255,0.045);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 16px;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: transform 0.2s ease, box-shadow 0.25s ease, border-color 0.2s ease;
    cursor: default;
}
.block-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(200,70,126,0.4) 40%, rgba(120,70,200,0.3) 70%, transparent 100%);
    border-radius: 16px 16px 0 0;
}
.block-card:hover {
    transform: translateY(-2px);
    border-color: rgba(200,70,126,0.2);
    box-shadow: 0 12px 36px rgba(0,0,0,0.4), 0 0 0 1px rgba(200,70,126,0.08), 0 0 40px rgba(200,70,126,0.06);
}
.block-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; min-height: 1.5rem; }
.block-title {
    font-size: 0.72rem; letter-spacing: 0.16em; text-transform: uppercase;
    color: rgba(200,70,126,0.8); margin: 0;
    display: flex; align-items: center; gap: 0.5rem;
}
.block-title::before {
    content: '';
    display: block; width: 3px; height: 12px; border-radius: 2px; flex-shrink: 0;
    background: linear-gradient(180deg, rgba(200,70,126,0.95) 0%, rgba(140,60,200,0.75) 100%);
}
.edit-btn {
    display: flex; align-items: center; justify-content: center;
    width: 26px; height: 26px; border-radius: 7px;
    border: none; background: transparent; cursor: pointer;
    color: rgba(255,255,255,0.25); font-size: 0.95rem; padding: 0;
    opacity: 0;
    transition: opacity 0.2s ease, color 0.2s ease, background 0.2s ease;
}
.block-card:hover .edit-btn { opacity: 1; }
.edit-btn:hover { color: rgba(200,70,126,0.9); background: rgba(200,70,126,0.1); }
.tags-row { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.tag {
    padding: 0.3rem 0.75rem; border-radius: 20px;
    background: linear-gradient(135deg, rgba(200,70,126,0.13) 0%, rgba(180,60,100,0.08) 100%);
    border: 1px solid rgba(200,70,126,0.28);
    color: rgba(255,255,255,0.8); font-size: 0.85rem;
    transition: transform 0.15s ease, border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}
.tag:hover {
    transform: scale(1.06);
    background: linear-gradient(135deg, rgba(200,70,126,0.25) 0%, rgba(180,60,100,0.18) 100%);
    border-color: rgba(200,70,126,0.52);
    box-shadow: 0 2px 12px rgba(200,70,126,0.18);
}
.empty { color: rgba(255,255,255,0.25); font-size: 0.9rem; font-style: italic; margin: 0; }

.edit-form { padding: 0.5rem 0.25rem; }
.edit-title { font-size: 1.1rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0 0 0.75rem; }
.lang-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
.lang-btn {
    padding: 0.35rem 0.85rem; border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.1); background: transparent;
    color: rgba(255,255,255,0.5); font-size: 0.85rem; cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.lang-btn.active { border-color: rgba(200,70,126,0.5); background: rgba(200,70,126,0.15); color: #fff; }
.save-btn {
    width: 100%; padding: 0.8rem;
    border-radius: 10px; border: 1px solid rgba(200,70,126,0.35);
    background: linear-gradient(135deg, rgba(200,70,126,0.25), rgba(200,70,126,0.1));
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.save-btn:hover:not(:disabled) { background: linear-gradient(135deg, rgba(200,70,126,0.38), rgba(200,70,126,0.18)); }
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
