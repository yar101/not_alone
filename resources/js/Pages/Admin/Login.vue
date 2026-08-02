<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

function submit() {
    form.post(route('admin.login.post'));
}
</script>

<template>
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-logo-wrap">
                <img src="/app-logo-v3.webp" alt="Not Alone" class="login-logo" />
            </div>
            <h1 class="login-title">Admin</h1>
            <p class="login-sub">Панель управления</p>

            <form @submit.prevent="submit" class="login-form">
                <div class="field">
                    <label class="field__label">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="field__input"
                        :class="{ 'field__input--error': form.errors.email }"
                        autocomplete="email"
                        autofocus
                    />
                    <span v-if="form.errors.email" class="field__error">{{ form.errors.email }}</span>
                </div>

                <div class="field">
                    <label class="field__label">Пароль</label>
                    <div class="password-input-wrapper">
                        <input
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            class="field__input field__input--password"
                            autocomplete="current-password"
                        />
                        <button
                            type="button"
                            class="password-toggle-btn"
                            @click="showPassword = !showPassword"
                            tabindex="-1"
                        >
                            <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit" :disabled="form.processing">
                    {{ form.processing ? 'Вход...' : 'Войти' }}
                </button>
            </form>
        </div>
    </div>
</template>

<style scoped>
.login-wrap {
    min-height: 100vh;
    background: #080812;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Rubik', sans-serif;
}

.login-card {
    width: min(380px, calc(100vw - 2rem));
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(155, 110, 232, 0.2);
    border-radius: 16px;
    padding: 2.5rem 2rem;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.6);
}

.login-logo-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 1rem;
}

.login-logo {
    height: 48px;
    width: auto;
}

.login-title {
    font-family: 'Imbue', serif;
    font-size: 1.2rem;
    color: #9B6EE8;
    text-align: center;
    margin: 0 0 0.25rem;
    text-shadow: 0 0 24px rgba(155, 110, 232, 0.4);
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.login-sub {
    text-align: center;
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.85rem;
    margin: 0 0 2rem;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.field { display: flex; flex-direction: column; gap: 0.4rem; }
.field__label { font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); }
.field__input {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 0.6rem 0.85rem;
    color: #fff;
    font-size: 0.95rem;
    outline: none;
    transition: border-color 0.15s;
}
.field__input:focus { border-color: rgba(155, 110, 232, 0.5); }
.field__input--error { border-color: rgba(255, 80, 80, 0.6); }
.field__error { font-size: 0.78rem; color: #ff6b6b; }

.btn-submit {
    margin-top: 0.5rem;
    padding: 0.7rem;
    background: linear-gradient(135deg, #9B6EE8, #a03466);
    border: none;
    border-radius: 8px;
    color: #fff;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.15s, transform 0.1s;
}
.btn-submit:hover { opacity: 0.9; transform: translateY(-1px); }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

.password-input-wrapper {
    position: relative;
    display: flex;
    width: 100%;
}

.field__input--password {
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
