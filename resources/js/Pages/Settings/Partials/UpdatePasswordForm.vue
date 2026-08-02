<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showCurrentPassword.value = false;
            showNewPassword.value = false;
            showConfirmPassword.value = false;
        },
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
                <div class="password-input-wrapper">
                    <input
                        id="current_password"
                        ref="currentPasswordInput"
                        v-model="form.current_password"
                        :type="showCurrentPassword ? 'text' : 'password'"
                        class="field-input field-input--password"
                        :class="{ 'field-input--error': form.errors.current_password }"
                        autocomplete="new-password"
                    />
                    <button
                        type="button"
                        class="password-toggle-btn"
                        @click="showCurrentPassword = !showCurrentPassword"
                        tabindex="-1"
                    >
                        <svg v-if="showCurrentPassword" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
                <p v-if="form.errors.current_password" class="field-error">{{ form.errors.current_password }}</p>
            </div>

            <div class="field">
                <label for="password" class="field-label">Новый пароль</label>
                <div class="password-input-wrapper">
                    <input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        :type="showNewPassword ? 'text' : 'password'"
                        class="field-input field-input--password"
                        :class="{ 'field-input--error': form.errors.password }"
                        autocomplete="new-password"
                    />
                    <button
                        type="button"
                        class="password-toggle-btn"
                        @click="showNewPassword = !showNewPassword"
                        tabindex="-1"
                    >
                        <svg v-if="showNewPassword" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
                <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
            </div>

            <div class="field">
                <label for="password_confirmation" class="field-label">Подтвердите пароль</label>
                <div class="password-input-wrapper">
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :type="showConfirmPassword ? 'text' : 'password'"
                        class="field-input field-input--password"
                        :class="{ 'field-input--error': form.errors.password_confirmation }"
                        autocomplete="new-password"
                    />
                    <button
                        type="button"
                        class="password-toggle-btn"
                        @click="showConfirmPassword = !showConfirmPassword"
                        tabindex="-1"
                    >
                        <svg v-if="showConfirmPassword" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
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
    background: rgba(10, 7, 20, 0.7);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14), 0 4px 20px rgba(0, 0, 0, 0.3);
    border-radius: 12px;
}
.card-header { margin-bottom: 1.5rem; }
.card-label {
    font-size: 0.72rem; letter-spacing: 0.16em; text-transform: uppercase;
    color: rgba(155,110,232,0.6);
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
    border-color: rgba(155,110,232,0.45);
    box-shadow: 0 0 0 3px rgba(155,110,232,0.08);
}
.field-input--error { border-color: rgba(220,60,60,0.5); }
.field-error { font-size: 0.8rem; color: rgba(220,100,100,0.9); margin: 0; }
.card-actions { display: flex; flex-direction: row-reverse; justify-content: flex-start; align-items: center; gap: 1rem; padding-top: 0.25rem; }
.save-btn {
    padding: 0.65rem 1.5rem;
    border-radius: 8px;
    background: rgba(155, 110, 232, 0.25);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(155, 110, 232, 0.4);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.15), 0 4px 10px rgba(0, 0, 0, 0.2);
    color: #fff; font-size: 0.92rem; font-weight: 500; cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.save-btn:hover:not(:disabled) { 
    background: rgba(155, 110, 232, 0.4);
    border-color: rgba(155, 110, 232, 0.6);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 6px 15px rgba(0, 0, 0, 0.3);
}
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.save-success { font-size: 0.85rem; color: rgba(100,200,100,0.8); margin: 0; }
.fade-active { transition: opacity 0.3s ease; }
.fade-from { opacity: 0; }

.password-input-wrapper {
    position: relative;
    display: flex;
    width: 100%;
}

.field-input--password {
    width: 100%;
    padding-right: 2.75rem;
}

.password-toggle-btn {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    padding: 0.4rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s;
    outline: none;
}

.password-toggle-btn:hover {
    color: rgba(255, 255, 255, 0.8);
}
</style>
