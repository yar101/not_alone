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
    <div id="tour-about" class="block-card">
        <div class="block-header">
            <h2 class="block-title">Обо мне</h2>
            <button v-if="isOwner" class="edit-btn" @click="editModal = true" title="Редактировать">
                <el-icon><Edit /></el-icon>
            </button>
        </div>

        <p v-if="about" class="about-text">{{ about }}</p>
        <p v-else-if="isOwner" class="about-empty">Расскажи о себе — нажми <el-icon class="inline-icon"><Edit /></el-icon> чтобы добавить</p>
        <p v-else class="about-empty">Пользователь пока ничего не написал</p>

        <SiteModal :show="editModal" variant="pink" :compact="true" @close="editModal = false">
            <div class="edit-form">
                <h3 class="edit-title">Обо мне</h3>
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
.about-text { color: rgba(255,255,255,0.8); font-size: 0.95rem; line-height: 1.65; margin: 0; white-space: pre-wrap; }
.about-empty { color: rgba(255,255,255,0.25); font-size: 0.9rem; font-style: italic; margin: 0; display: flex; align-items: center; gap: 0.25rem; flex-wrap: wrap; }
.inline-icon { font-size: 0.9rem; vertical-align: middle; }

.edit-form { padding: 0.5rem 0.25rem; }
.edit-title { font-size: 1.1rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0 0 1rem; }
.edit-textarea {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    color: rgba(255,255,255,0.85);
    font-size: 0.95rem;
    font-family: inherit;
    resize: vertical;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.2s;
}
.edit-textarea:focus { border-color: rgba(200,70,126,0.45); }
.char-count { text-align: right; font-size: 0.75rem; color: rgba(255,255,255,0.3); margin-top: 0.25rem; }
.edit-error { color: rgba(220,100,140,0.9); font-size: 0.8rem; margin: 0.25rem 0 0; }
.save-btn {
    width: 100%; margin-top: 0.75rem; padding: 0.8rem;
    border-radius: 10px; border: 1px solid rgba(200,70,126,0.35);
    background: linear-gradient(135deg, rgba(200,70,126,0.25), rgba(200,70,126,0.1));
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.save-btn:hover:not(:disabled) { background: linear-gradient(135deg, rgba(200,70,126,0.38), rgba(200,70,126,0.18)); }
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
