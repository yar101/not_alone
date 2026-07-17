<script setup>
import SiteModal from '@/Components/Site/SiteModal.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({ password: '' });

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('settings.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="settings-card">
        <div class="card-header">
            <span class="card-label danger-label">Опасная зона</span>
            <h2 class="card-title">Удалить аккаунт</h2>
            <p class="card-desc">После удаления аккаунта все данные будут безвозвратно удалены. Сохраните всё нужное перед удалением.</p>
        </div>

        <button class="danger-btn" @click="confirmUserDeletion">Удалить аккаунт</button>

        <SiteModal :show="confirmingUserDeletion" variant="pink" :compact="true" @close="closeModal">
            <div class="delete-modal">
                <h2 class="modal-title">Удалить аккаунт?</h2>
                <p class="modal-desc">Все данные будут удалены навсегда. Введите пароль для подтверждения.</p>

                <div class="field">
                    <label for="delete-password" class="field-label">Пароль</label>
                    <input
                        id="delete-password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="field-input"
                        :class="{ 'field-input--error': form.errors.password }"
                        placeholder="Введите пароль"
                        @keyup.enter="deleteUser"
                    />
                    <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
                </div>

                <div class="modal-actions">
                    <button type="button" class="cancel-btn" @click="closeModal">Отмена</button>
                    <button
                        type="button"
                        class="confirm-danger-btn"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >Удалить аккаунт</button>
                </div>
            </div>
        </SiteModal>
    </section>
</template>

<style scoped>
.settings-card {
    padding: 1.5rem;
    background: rgba(40, 10, 15, 0.7);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(220, 60, 60, 0.25);
    box-shadow: inset 0 1px 0 rgba(220, 60, 60, 0.15), 0 4px 20px rgba(0, 0, 0, 0.3);
    border-radius: 12px;
}
.card-header { margin-bottom: 1.5rem; }
.card-label {
    font-size: 0.72rem; letter-spacing: 0.16em; text-transform: uppercase;
    color: rgba(155,110,232,0.6);
}
.danger-label { color: rgba(220,60,60,0.6); }
.card-title { font-size: 1.05rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0.35rem 0 0.25rem; }
.card-desc { font-size: 0.85rem; color: rgba(255,255,255,0.35); margin: 0; }
.danger-btn {
    padding: 0.65rem 1.5rem;
    border-radius: 8px;
    background: rgba(220, 60, 60, 0.2);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(220, 60, 60, 0.4);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 4px 10px rgba(0, 0, 0, 0.2);
    color: #ffcccc;
    font-size: 0.92rem;
    font-weight: 500;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}
.danger-btn:hover {
    background: rgba(220, 60, 60, 0.35);
    border-color: rgba(220, 60, 60, 0.6);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.15), 0 6px 15px rgba(0, 0, 0, 0.3);
    color: #fff;
}
.delete-modal { padding: 0.5rem 0.25rem; }
.modal-title { font-size: 1.1rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0 0 0.5rem; }
.modal-desc { font-size: 0.85rem; color: rgba(255,255,255,0.4); margin: 0 0 1.25rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 1.25rem; }
.field-label { font-size: 0.83rem; color: rgba(255,255,255,0.55); }
.field-input {
    padding: 0.65rem 0.9rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 8px;
    color: rgba(255,255,255,0.88);
    font-size: 0.92rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.field-input:focus {
    border-color: rgba(155,110,232,0.45);
    box-shadow: 0 0 0 3px rgba(155,110,232,0.08);
}
.field-input--error { border-color: rgba(220,60,60,0.5); }
.field-error { font-size: 0.8rem; color: rgba(220,100,100,0.9); margin: 0; }
.modal-actions { display: flex; justify-content: flex-end; gap: 0.75rem; }
.cancel-btn {
    padding: 0.65rem 1.25rem;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 4px 10px rgba(0, 0, 0, 0.2);
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.92rem;
    font-weight: 500;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}
.cancel-btn:hover { 
    background: rgba(255, 255, 255, 0.15); 
    border-color: rgba(255, 255, 255, 0.3); 
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 6px 15px rgba(0, 0, 0, 0.3);
    color: #fff;
}
.confirm-danger-btn {
    padding: 0.65rem 1.25rem;
    border-radius: 8px;
    background: rgba(220, 60, 60, 0.25);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(220, 60, 60, 0.5);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 4px 10px rgba(0, 0, 0, 0.2);
    color: #fff;
    font-size: 0.92rem;
    font-weight: 500;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}
.confirm-danger-btn:hover:not(:disabled) { 
    background: rgba(220, 60, 60, 0.4); 
    border-color: rgba(220, 60, 60, 0.7); 
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 6px 15px rgba(0, 0, 0, 0.3);
}
.confirm-danger-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
