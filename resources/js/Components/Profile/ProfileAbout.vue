<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Edit } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';

const props = defineProps({
    about: { type: String, default: null },
    isOwner: { type: Boolean, default: false },
});

const editModal = ref(false);
const form = useForm({ about: props.about ?? '' });

function submit() {
    form.patch(route('profile.update.about'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => editModal.value = false,
    });
}
</script>

<template>
    <div id="tour-about" class="block-section">
        <div class="section-header">
            <span class="section-title">О себе</span>
            <button v-if="isOwner" class="edit-btn" @click="editModal = true" title="Редактировать">
                <el-icon><Edit /></el-icon>
            </button>
        </div>

        <p v-if="about" class="about-text">{{ about }}</p>
        <p v-else-if="isOwner" class="about-empty">
            Расскажи о себе — нажми <el-icon class="inline-icon"><Edit /></el-icon> чтобы добавить
        </p>
        <p v-else class="about-empty">Пользователь пока ничего не написал</p>

        <SiteModal :show="editModal" variant="pink" :compact="true" @close="editModal = false">
            <div class="edit-form">
                <h3 class="edit-title">О себе</h3>
                <textarea
                    v-model="form.about"
                    class="edit-textarea"
                    placeholder="Напиши что-нибудь о себе…"
                    rows="5"
                    maxlength="1000"
                />
                <div class="char-count">{{ form.about.length }}/1000</div>
                <p v-if="form.errors.about" class="edit-error">{{ form.errors.about }}</p>
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
    color: #FE28A2;
}

.edit-btn {
    display: flex; align-items: center; justify-content: center;
    width: 26px; height: 26px;
    border: none; background: transparent; cursor: pointer;
    color: rgba(255,255,255,0.2); font-size: 0.95rem; padding: 0;
    opacity: 0;
    transition: opacity 0.15s, color 0.15s;
}
.block-section:hover .edit-btn { opacity: 1; }
.edit-btn:hover { color: rgba(254,40,162,0.9); }

.about-text {
    color: rgba(255,255,255,0.82);
    font-size: 1.05rem;
    line-height: 1.7;
    margin: 0;
    white-space: pre-wrap;
}
.about-empty {
    color: rgba(255,255,255,0.25);
    font-size: 1rem;
    font-style: italic;
    margin: 0;
    display: flex; align-items: center; gap: 0.25rem; flex-wrap: wrap;
}
.inline-icon { font-size: 0.9rem; vertical-align: middle; }

/* ── Форма редактирования ─────────────────────────────────── */
.edit-form { padding: 0.5rem 0.25rem; }
.edit-title { font-size: 1.1rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0 0 1rem; }
.edit-textarea {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    padding: 0.75rem 1rem;
    color: rgba(255,255,255,0.85);
    font-size: 0.95rem;
    font-family: inherit;
    resize: vertical;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.15s;
}
.edit-textarea:focus { border-color: rgba(254,40,162,0.5); }
.char-count { text-align: right; font-size: 0.75rem; color: rgba(255,255,255,0.25); margin-top: 0.25rem; }
.edit-error { color: rgba(220,100,140,0.9); font-size: 0.8rem; margin: 0.25rem 0 0; }
.save-btn {
    width: 100%; margin-top: 0.75rem; padding: 0.75rem;
    border-radius: 3px; border: 1px solid rgba(254,40,162,0.4);
    background: rgba(254,40,162,0.1);
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.save-btn:hover:not(:disabled) { background: rgba(254,40,162,0.2); }
.save-btn:disabled { opacity: 0.45; cursor: not-allowed; }
</style>
