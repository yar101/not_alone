<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('admin.login.post'));
}
</script>

<template>
    <div class="login-wrap">
        <div class="login-card">
            <h1 class="login-title">NoAlone Admin</h1>
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
                    <input
                        v-model="form.password"
                        type="password"
                        class="field__input"
                        autocomplete="current-password"
                    />
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
    font-family: 'Figtree', sans-serif;
}

.login-card {
    width: 380px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(155, 110, 232, 0.2);
    border-radius: 16px;
    padding: 2.5rem 2rem;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.6);
}

.login-title {
    font-family: 'Imbue', serif;
    font-size: 1.6rem;
    color: #9B6EE8;
    text-align: center;
    margin: 0 0 0.25rem;
    text-shadow: 0 0 24px rgba(155, 110, 232, 0.4);
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
</style>
