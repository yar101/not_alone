<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section class="settings-card">
        <div class="card-header">
            <span class="card-label">Безопасность</span>
            <h2 class="card-title">Изменить пароль</h2>
            <p class="card-desc">Используйте длинный случайный пароль для безопасности аккаунта.</p>
        </div>

        <form @submit.prevent="updatePassword" class="card-form">
            <div class="field">
                <label for="current_password" class="field-label">Текущий пароль</label>
                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="field-input"
                    :class="{ 'field-input--error': form.errors.current_password }"
                    autocomplete="current-password"
                />
                <p v-if="form.errors.current_password" class="field-error">{{ form.errors.current_password }}</p>
            </div>

            <div class="field">
                <label for="password" class="field-label">Новый пароль</label>
                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="field-input"
                    :class="{ 'field-input--error': form.errors.password }"
                    autocomplete="new-password"
                />
                <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
            </div>

            <div class="field">
                <label for="password_confirmation" class="field-label">Подтвердите пароль</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="field-input"
                    :class="{ 'field-input--error': form.errors.password_confirmation }"
                    autocomplete="new-password"
                />
                <p v-if="form.errors.password_confirmation" class="field-error">{{ form.errors.password_confirmation }}</p>
            </div>

            <div class="card-actions">
                <button type="submit" class="save-btn" :disabled="form.processing">Сохранить</button>
                <Transition
                    enter-active-class="fade-active"
                    enter-from-class="fade-from"
                    leave-active-class="fade-active"
                    leave-to-class="fade-from"
                >
                    <p v-if="form.recentlySuccessful" class="save-success">Сохранено.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>

<style scoped>
.settings-card {
    padding: 1.5rem;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 16px;
}
.card-header { margin-bottom: 1.5rem; }
.card-label {
    font-size: 0.72rem; letter-spacing: 0.16em; text-transform: uppercase;
    color: rgba(200,70,126,0.6);
}
.card-title { font-size: 1.05rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0.35rem 0 0.25rem; }
.card-desc { font-size: 0.85rem; color: rgba(255,255,255,0.35); margin: 0; }
.card-form { display: flex; flex-direction: column; gap: 1.1rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
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
    border-color: rgba(200,70,126,0.45);
    box-shadow: 0 0 0 3px rgba(200,70,126,0.08);
}
.field-input--error { border-color: rgba(220,60,60,0.5); }
.field-error { font-size: 0.8rem; color: rgba(220,100,100,0.9); margin: 0; }
.card-actions { display: flex; align-items: center; gap: 1rem; padding-top: 0.25rem; }
.save-btn {
    padding: 0.65rem 1.5rem;
    border-radius: 10px;
    border: 1px solid rgba(200,70,126,0.35);
    background: linear-gradient(135deg, rgba(200,70,126,0.25), rgba(200,70,126,0.1));
    color: #fff; font-size: 0.92rem; cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.save-btn:hover:not(:disabled) { background: linear-gradient(135deg, rgba(200,70,126,0.38), rgba(200,70,126,0.18)); }
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.save-success { font-size: 0.85rem; color: rgba(100,200,100,0.8); margin: 0; }
.fade-active { transition: opacity 0.3s ease; }
.fade-from { opacity: 0; }
</style>
