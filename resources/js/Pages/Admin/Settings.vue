<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    settings: Object,
});

const form = useForm({
    rating_low_threshold: props.settings.rating_low_threshold,
});

function save() {
    form.patch(route('admin.settings.update'), { preserveScroll: true });
}
</script>

<template>
    <div>
        <div class="page-header">
            <h1 class="page-title">Настройки платформы</h1>
        </div>

        <form @submit.prevent="save" class="settings-form">
            <div class="section">
                <h2 class="section-title">Рейтинг</h2>

                <div class="field">
                    <label class="label">Порог низкого рейтинга</label>
                    <p class="hint">Айдолы с рейтингом ниже этого значения ограничены в ценах на услуги.</p>
                    <input
                        v-model.number="form.rating_low_threshold"
                        type="number"
                        min="0"
                        max="100"
                        class="input"
                        :class="{ 'input--err': form.errors.rating_low_threshold }"
                    />
                    <p v-if="form.errors.rating_low_threshold" class="err">{{ form.errors.rating_low_threshold }}</p>
                </div>
            </div>

            <div class="form-actions">
                <span v-if="form.wasSuccessful" class="success-msg">Настройки сохранены</span>
                <button type="submit" class="btn-submit" :disabled="form.processing">Сохранить</button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.page-header { margin-bottom: 1.5rem; }
.page-title  { font-size: 1.4rem; color: #fff; margin: 0; }

.settings-form { max-width: 480px; display: flex; flex-direction: column; gap: 1.5rem; }

.section { background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.08); border-radius: 3px; padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.section-title { font-size: 0.78rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(190,145,255,0.6); margin: 0; }

.field { display: flex; flex-direction: column; gap: 0.3rem; }
.label { font-size: 0.82rem; font-weight: 600; color: rgba(255,255,255,0.75); }
.hint  { font-size: 0.78rem; color: rgba(255,255,255,0.3); margin: 0; }

.input {
    padding: 0.5rem 0.75rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    color: rgba(255,255,255,0.85);
    font-family: inherit;
    font-size: 0.88rem;
    outline: none;
    max-width: 160px;
    transition: border-color 0.15s;
}
.input:focus { border-color: rgba(190,145,255,0.45); }
.input--err  { border-color: rgba(239,68,68,0.5); }

.err { font-size: 0.75rem; color: rgba(239,68,68,0.8); margin: 0; }

.form-actions { display: flex; align-items: center; gap: 1rem; }

.success-msg { font-size: 0.82rem; color: rgba(74,222,128,0.8); }

.btn-submit {
    padding: 0.5rem 1.25rem;
    border: 1px solid rgba(190,145,255,0.45);
    border-radius: 3px;
    background: rgba(190,145,255,0.1);
    color: rgba(255,255,255,0.9);
    font-family: inherit;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-submit:hover:not(:disabled) { background: rgba(190,145,255,0.2); }
.btn-submit:disabled { opacity: 0.5; cursor: default; }
</style>
