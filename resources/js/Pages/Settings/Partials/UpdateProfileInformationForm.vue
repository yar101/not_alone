<script setup>
import { ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});

const NAME_RE = /^\p{L}+(\s\p{L}+)?$/u;
const nameError = ref('');

function validateName(value) {
    if (!value.trim()) return 'Имя обязательно.';
    if (value.trim().length < 2) return 'Имя слишком короткое.';
    if (value.trim().length > 100) return 'Имя слишком длинное.';
    if (!NAME_RE.test(value.trim())) return 'Одно или два слова, только буквы.';
    return '';
}

function submit() {
    nameError.value = validateName(form.name);
    if (nameError.value) return;
    form.name = form.name.trim();
    form.patch(route('settings.update'));
}
</script>

<template>
    <section class="settings-card">
        <div class="card-header">
            <span class="card-label">Информация профиля</span>
            <h2 class="card-title">Имя и Email</h2>
            <p class="card-desc">Обновите имя и email адрес аккаунта.</p>
        </div>

        <form @submit.prevent="submit" class="card-form">
            <div class="field">
                <label for="name" class="field-label">Имя</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="field-input"
                    :class="{ 'field-input--error': form.errors.name || nameError }"
                    required
                    autofocus
                    autocomplete="name"
                    @input="nameError = ''"
                />
                <p v-if="nameError || form.errors.name" class="field-error">{{ nameError || form.errors.name }}</p>
            </div>

            <div class="field">
                <label for="email" class="field-label">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="field-input"
                    :class="{ 'field-input--error': form.errors.email }"
                    required
                    autocomplete="username"
                />
                <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="verify-notice">
                <p>
                    Ваш email не подтверждён.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="verify-link"
                    >Отправить письмо повторно.</Link>
                </p>
                <p v-show="status === 'verification-link-sent'" class="verify-sent">
                    Новая ссылка подтверждения отправлена на ваш email.
                </p>
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
    border-radius: 3px;
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
    border-radius: 3px;
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
.verify-notice { font-size: 0.85rem; color: rgba(255,255,255,0.5); }
.verify-link {
    color: rgba(200,70,126,0.8); background: none; border: none;
    cursor: pointer; padding: 0; font-size: inherit; text-decoration: underline;
}
.verify-link:hover { color: rgba(200,70,126,1); }
.verify-sent { color: rgba(100,200,100,0.8); margin-top: 0.25rem; }
.card-actions { display: flex; align-items: center; gap: 1rem; padding-top: 0.25rem; }
.save-btn {
    padding: 0.65rem 1.5rem;
    border-radius: 3px;
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
