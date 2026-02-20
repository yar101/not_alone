<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SiteModal from '@/Components/Site/SiteModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const tab = ref('login');

function switchTab(t) {
    tab.value = t;
}

// ── Login Form ────────────────────────────────────────────
const loginForm = useForm({
    email: '',
    password: '',
    remember: false,
});

function submitLogin() {
    loginForm.post(route('login'), {
        onFinish: () => loginForm.reset('password'),
    });
}

// ── Register Form ─────────────────────────────────────────
const registerForm = useForm({
    name: '',
    gender: '',
    age: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submitRegister() {
    registerForm.post(route('register'), {
        onFinish: () => registerForm.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <SiteModal :show="show" variant="pink" :compact="true" @close="emit('close')">
        <div class="auth-modal">
            <!-- Tab switcher -->
            <div class="auth-tabs">
                <button
                    class="auth-tab"
                    :class="{ 'auth-tab--active': tab === 'login' }"
                    @click="switchTab('login')"
                >
                    Войти
                </button>
                <button
                    class="auth-tab"
                    :class="{ 'auth-tab--active': tab === 'register' }"
                    @click="switchTab('register')"
                >
                    Зарегистрироваться
                </button>
            </div>

            <!-- Forms with transition -->
            <Transition name="tab-slide" mode="out-in">
                <!-- Login Form -->
                <form v-if="tab === 'login'" key="login" @submit.prevent="submitLogin" class="auth-form">
                    <div class="auth-field">
                        <label class="auth-field-label">Email</label>
                        <input
                            v-model="loginForm.email"
                            type="email"
                            class="auth-input"
                            :class="{ 'auth-input--error': loginForm.errors.email }"
                            autocomplete="username"
                            placeholder="you@example.com"
                        />
                        <Transition name="err-fade">
                            <p v-show="loginForm.errors.email" class="auth-error">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                {{ loginForm.errors.email }}
                            </p>
                        </Transition>
                    </div>

                    <div class="auth-field">
                        <label class="auth-field-label">Пароль</label>
                        <input
                            v-model="loginForm.password"
                            type="password"
                            class="auth-input"
                            :class="{ 'auth-input--error': loginForm.errors.password }"
                            autocomplete="current-password"
                            placeholder="••••••••"
                        />
                        <Transition name="err-fade">
                            <p v-show="loginForm.errors.password" class="auth-error">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                {{ loginForm.errors.password }}
                            </p>
                        </Transition>
                    </div>

                    <div class="auth-remember">
                        <label class="auth-remember-label">
                            <input
                                v-model="loginForm.remember"
                                type="checkbox"
                                class="auth-checkbox-native"
                            />
                            <span class="auth-checkbox-box">
                                <svg class="auth-checkbox-check" viewBox="0 0 10 8" fill="none">
                                    <path d="M1 4L3.5 6.5L9 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span class="auth-remember-text">Запомнить меня</span>
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="auth-submit"
                        :disabled="loginForm.processing"
                    >
                        {{ loginForm.processing ? 'Вхожу…' : 'Войти' }}
                    </button>

                    <p class="auth-footer">
                        Нет аккаунта?
                        <button type="button" class="auth-switch-link" @click="switchTab('register')">Зарегистрироваться</button>
                    </p>
                </form>

                <!-- Register Form -->
                <form v-else key="register" @submit.prevent="submitRegister" class="auth-form">
                    <div class="auth-field">
                        <label class="auth-field-label">Имя</label>
                        <input
                            v-model="registerForm.name"
                            type="text"
                            class="auth-input"
                            :class="{ 'auth-input--error': registerForm.errors.name }"
                            autocomplete="name"
                            placeholder="Иван Иванов"
                        />
                        <Transition name="err-fade">
                            <p v-show="registerForm.errors.name" class="auth-error">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                {{ registerForm.errors.name }}
                            </p>
                        </Transition>
                    </div>

                    <div class="auth-field">
                        <label class="auth-field-label">Пол</label>
                        <div class="auth-gender-group">
                            <button
                                type="button"
                                class="auth-gender-btn"
                                :class="{ 'auth-gender-btn--active': registerForm.gender === 'male' }"
                                @click="registerForm.gender = 'male'"
                            >Мужской</button>
                            <button
                                type="button"
                                class="auth-gender-btn"
                                :class="{ 'auth-gender-btn--active': registerForm.gender === 'female' }"
                                @click="registerForm.gender = 'female'"
                            >Женский</button>
                            <button
                                type="button"
                                class="auth-gender-btn"
                                :class="{ 'auth-gender-btn--active': registerForm.gender === 'other' }"
                                @click="registerForm.gender = 'other'"
                            >Другой</button>
                        </div>
                        <Transition name="err-fade">
                            <p v-show="registerForm.errors.gender" class="auth-error">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                {{ registerForm.errors.gender }}
                            </p>
                        </Transition>
                    </div>

                    <div class="auth-field">
                        <label class="auth-field-label">Возраст</label>
                        <input
                            v-model="registerForm.age"
                            type="number"
                            min="18"
                            max="120"
                            class="auth-input"
                            :class="{ 'auth-input--error': registerForm.errors.age }"
                            placeholder="18"
                        />
                        <Transition name="err-fade">
                            <p v-show="registerForm.errors.age" class="auth-error">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                {{ registerForm.errors.age }}
                            </p>
                        </Transition>
                    </div>

                    <div class="auth-field">
                        <label class="auth-field-label">Email</label>
                        <input
                            v-model="registerForm.email"
                            type="email"
                            class="auth-input"
                            :class="{ 'auth-input--error': registerForm.errors.email }"
                            autocomplete="username"
                            placeholder="you@example.com"
                        />
                        <Transition name="err-fade">
                            <p v-show="registerForm.errors.email" class="auth-error">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                {{ registerForm.errors.email }}
                            </p>
                        </Transition>
                    </div>

                    <div class="auth-field">
                        <label class="auth-field-label">Пароль</label>
                        <input
                            v-model="registerForm.password"
                            type="password"
                            class="auth-input"
                            :class="{ 'auth-input--error': registerForm.errors.password }"
                            autocomplete="new-password"
                            placeholder="••••••••"
                        />
                        <Transition name="err-fade">
                            <p v-show="registerForm.errors.password" class="auth-error">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                {{ registerForm.errors.password }}
                            </p>
                        </Transition>
                    </div>

                    <div class="auth-field">
                        <label class="auth-field-label">Подтверждение пароля</label>
                        <input
                            v-model="registerForm.password_confirmation"
                            type="password"
                            class="auth-input"
                            :class="{ 'auth-input--error': registerForm.errors.password_confirmation }"
                            autocomplete="new-password"
                            placeholder="••••••••"
                        />
                        <Transition name="err-fade">
                            <p v-show="registerForm.errors.password_confirmation" class="auth-error">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                {{ registerForm.errors.password_confirmation }}
                            </p>
                        </Transition>
                    </div>

                    <button
                        type="submit"
                        class="auth-submit"
                        :disabled="registerForm.processing"
                    >
                        {{ registerForm.processing ? 'Регистрируюсь…' : 'Зарегистрироваться' }}
                    </button>

                    <p class="auth-footer">
                        Уже есть аккаунт?
                        <button type="button" class="auth-switch-link" @click="switchTab('login')">Войти</button>
                    </p>
                </form>
            </Transition>
        </div>
    </SiteModal>
</template>

<style scoped>
.auth-modal {
    padding: 0.25rem 0.25rem 0.5rem;
    padding-top: 2.25rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* ── Tab switcher ──────────────────────────────────────── */
.auth-tabs {
    display: flex;
    gap: 4px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 10px;
    padding: 4px;
}

.auth-tab {
    flex: 1;
    padding: 0.55rem 0.75rem;
    font-size: 0.9rem;
    border-radius: 7px;
    border: none;
    background: transparent;
    color: rgba(255, 255, 255, 0.45);
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease;
    font-family: inherit;
}

.auth-tab--active {
    background: rgba(200, 70, 126, 0.15);
    color: #fff;
}

/* ── Form ─────────────────────────────────────────────── */
.auth-form {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}

.auth-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.auth-field-label {
    font-size: 0.68rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: rgba(200, 70, 126, 0.5);
}

.auth-input {
    width: 100%;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 0.72rem 0.9rem;
    color: rgba(255, 255, 255, 0.88);
    font-size: 0.95rem;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    font-family: inherit;
}

.auth-input:focus {
    border-color: rgba(200, 70, 126, 0.45);
    box-shadow: 0 0 0 3px rgba(200, 70, 126, 0.08);
}

.auth-input--error {
    border-color: rgba(200, 70, 126, 0.6);
}

/* Remove number spinner arrows */
.auth-input[type="number"]::-webkit-inner-spin-button,
.auth-input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.auth-input[type="number"] { -moz-appearance: textfield; }

/* ── Error messages ───────────────────────────────────── */
.auth-error {
    font-size: 0.78rem;
    color: rgba(220, 100, 140, 0.9);
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin: 0;
}

/* ── Gender pills ─────────────────────────────────────── */
.auth-gender-group {
    display: flex;
    gap: 0.5rem;
}

.auth-gender-btn {
    flex: 1;
    padding: 0.5rem 0.5rem;
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: transparent;
    color: rgba(255, 255, 255, 0.45);
    font-size: 0.88rem;
    cursor: pointer;
    transition: border-color 0.2s ease, background 0.2s ease, color 0.2s ease;
    font-family: inherit;
}

.auth-gender-btn--active {
    border-color: rgba(200, 70, 126, 0.5);
    background: rgba(200, 70, 126, 0.12);
    color: #fff;
}

/* ── Remember checkbox ───────────────────────────────── */
.auth-remember {
    display: flex;
    align-items: center;
}

.auth-remember-label {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    cursor: pointer;
    user-select: none;
}

.auth-checkbox-native {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}

.auth-checkbox-box {
    width: 17px;
    height: 17px;
    flex-shrink: 0;
    border-radius: 5px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(255, 255, 255, 0.04);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
}

.auth-checkbox-check {
    width: 10px;
    height: 8px;
    color: #fff;
    opacity: 0;
    transform: scale(0.6);
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.auth-checkbox-native:checked ~ .auth-checkbox-box {
    background: rgba(200, 70, 126, 0.65);
    border-color: rgba(200, 70, 126, 0.8);
    box-shadow: 0 0 8px rgba(200, 70, 126, 0.3);
}

.auth-checkbox-native:checked ~ .auth-checkbox-box .auth-checkbox-check {
    opacity: 1;
    transform: scale(1);
}

.auth-remember-label:hover .auth-checkbox-box {
    border-color: rgba(200, 70, 126, 0.45);
}

.auth-remember-text {
    font-size: 0.88rem;
    color: rgba(255, 255, 255, 0.45);
    transition: color 0.15s ease;
}

.auth-checkbox-native:checked ~ .auth-remember-text {
    color: rgba(255, 255, 255, 0.65);
}

/* ── Submit button ────────────────────────────────────── */
.auth-submit {
    width: 100%;
    padding: 0.85rem;
    border-radius: 10px;
    border: 1px solid rgba(200, 70, 126, 0.35);
    background: linear-gradient(135deg, rgba(200, 70, 126, 0.25), rgba(200, 70, 126, 0.1));
    color: #fff;
    font-size: 0.95rem;
    cursor: pointer;
    transition: background 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
    font-family: inherit;
    margin-top: 0.25rem;
}

.auth-submit:hover:not(:disabled) {
    background: linear-gradient(135deg, rgba(200, 70, 126, 0.38), rgba(200, 70, 126, 0.18));
    box-shadow: 0 0 20px rgba(200, 70, 126, 0.2);
}

.auth-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ── Footer switcher ─────────────────────────────────── */
.auth-footer {
    text-align: center;
    font-size: 0.84rem;
    color: rgba(255, 255, 255, 0.35);
    margin: 0;
}

.auth-switch-link {
    background: none;
    border: none;
    color: rgba(200, 70, 126, 0.7);
    cursor: pointer;
    font-size: inherit;
    font-family: inherit;
    padding: 0;
    transition: color 0.2s ease;
}

.auth-switch-link:hover {
    color: rgba(200, 70, 126, 1);
}

/* ── Transitions ─────────────────────────────────────── */
.tab-slide-enter-active,
.tab-slide-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}
.tab-slide-enter-from {
    opacity: 0;
    transform: translateX(12px);
}
.tab-slide-leave-to {
    opacity: 0;
    transform: translateX(-12px);
}

.err-fade-enter-active,
.err-fade-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.err-fade-enter-from,
.err-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
