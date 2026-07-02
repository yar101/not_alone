<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { EditPen } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    about: { type: String, default: null },
    isOwner: { type: Boolean, default: false },
});

const editModal = ref(false);
const form = useForm({ about: props.about ?? '' });

function openEdit() {
    form.about = props.about ?? '';
    editModal.value = true;
}

function submit() {
    form.patch(route('profile.update.about'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            editModal.value = false;
        },
    });
}
</script>

<template>
    <div id="tour-about" class="block-section">
        <div class="section-header">
            <span class="section-title">{{ __('profile.about.title') }}</span>
            <button v-if="isOwner" class="edit-btn" @click="openEdit" :title="__('common.edit')">
                <el-icon><EditPen /></el-icon>
            </button>
        </div>

        <p v-if="about" class="about-text">{{ about }}</p>
        <p v-else-if="isOwner" class="about-empty">
            {{ __('profile.about.owner_hint_before') }} <el-icon class="inline-icon"><EditPen /></el-icon> {{ __('profile.about.owner_hint_after') }}
        </p>
        <p v-else class="about-empty">{{ __('profile.about.empty') }}</p>

        <SiteModal :show="editModal" variant="pink" :compact="true" :no-history="true" @close="editModal = false">
            <div class="edit-form">
                <h3 class="edit-title">{{ __('profile.about.title') }}</h3>
                <textarea
                    v-model="form.about"
                    class="edit-textarea"
                    :placeholder="__('profile.about.placeholder')"
                    rows="5"
                    maxlength="200"
                />
                <div class="char-count">{{ form.about.length }}/200</div>
                <p v-if="form.errors.about" class="edit-error">{{ form.errors.about }}</p>
                <button class="save-btn" :disabled="form.processing" @click="submit">{{ __('common.save') }}</button>
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
    color: var(--color-base-1);
}

.edit-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 26px; height: 26px;
    padding: 0;
    border-radius: var(--profile-border-radius, 8px);
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
    color: color-mix(in srgb, var(--color-base-1), transparent 20%);
    border-color: color-mix(in srgb, var(--color-base-1), transparent 65%);
    background: color-mix(in srgb, var(--color-base-1), transparent 92%);
}
.edit-btn:hover {
    color: var(--color-base-1);
    border-color: color-mix(in srgb, var(--color-base-1), transparent 30%);
    background: color-mix(in srgb, var(--color-base-1), transparent 84%);
    box-shadow: 0 0 8px color-mix(in srgb, var(--color-base-1), transparent 65%);
}

.about-text {
    color: rgba(255,255,255,0.82);
    font-size: 0.95rem;
    line-height: 1.65;
    margin: 0;
    white-space: pre-wrap;
    overflow-wrap: break-word;
    word-break: break-word;
    min-width: 0;
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
    border-radius: var(--profile-border-radius, 8px);
    padding: 0.75rem 1rem;
    color: rgba(255,255,255,0.85);
    font-size: 0.95rem;
    font-family: inherit;
    resize: vertical;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.15s;
}
.edit-textarea:focus { border-color: color-mix(in srgb, var(--color-base-1), transparent 50%); }
.char-count { text-align: right; font-size: 0.75rem; color: rgba(255,255,255,0.25); margin-top: 0.25rem; }
.edit-error { color: rgba(220,100,140,0.9); font-size: 0.8rem; margin: 0.25rem 0 0; }
.save-btn {
    width: 100%; margin-top: 0.75rem; padding: 0.75rem;
    border-radius: var(--profile-border-radius, 8px); border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 60%);
    background: color-mix(in srgb, var(--color-base-1), transparent 90%);
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.save-btn:hover:not(:disabled) { background: color-mix(in srgb, var(--color-base-1), transparent 80%); }
.save-btn:disabled { opacity: 0.45; cursor: not-allowed; }
</style>
