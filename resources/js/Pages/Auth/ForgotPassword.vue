<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

defineProps({
    status: String,
});

const form = useForm({ email: '' });

const submit = () => form.post(route('password.email'));
</script>

<template>
    <Head :title="__('auth.forgot.title')" />

    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>

            <h1 class="auth-title">{{ __('auth.forgot.title') }}</h1>
            <p class="auth-hint">{{ __('auth.forgot.hint') }}</p>

            <p v-if="status" class="auth-sent">{{ __('auth.forgot.sent') }}</p>

            <form @submit.prevent="submit" class="auth-form">
                <div class="field">
                    <label class="field-label">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="input"
                        :class="{ 'input--err': form.errors.email }"
                        autocomplete="username"
                        required
                        autofocus
                    />
                    <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
                </div>

                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? __('auth.forgot.sending') : __('auth.forgot.submit') }}
                </button>
            </form>
        </div>
    </div>
</template>

<style scoped>
.auth-page { min-height: 100vh; background: #0a0a0f; display: flex; align-items: center; justify-content: center; padding: 2rem 1.5rem; font-family: 'Rubik', sans-serif; box-sizing: border-box; }
.auth-card { width: 100%; max-width: 400px; border: 1px solid rgba(255,255,255,0.1); border-radius: 3px; background: rgba(255,255,255,0.025); padding: 2.5rem 2rem; display: flex; flex-direction: column; align-items: center; gap: 1.25rem; text-align: center; }
.auth-icon { width: 3rem; height: 3rem; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255, 178, 239,0.3); border-radius: 3px; background: rgba(255, 178, 239,0.06); color: #ffb2ef; flex-shrink: 0; }
.auth-icon svg { width: 1.4rem; height: 1.4rem; }
.auth-title { margin: 0; font-size: 1.35rem; font-weight: 600; color: rgba(255,255,255,0.92); letter-spacing: -0.01em; }
.auth-hint { margin: 0; font-size: 0.9rem; line-height: 1.65; color: rgba(255,255,255,0.45); }
.auth-sent { margin: 0; font-size: 0.85rem; color: rgba(74,222,128,0.85); border: 1px solid rgba(74,222,128,0.2); border-radius: 3px; background: rgba(74,222,128,0.05); padding: 0.55rem 0.9rem; width: 100%; box-sizing: border-box; }
.auth-form { display: flex; flex-direction: column; gap: 0.9rem; width: 100%; }
.field { display: flex; flex-direction: column; gap: 0.3rem; text-align: left; }
.field-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.35); }
.input { padding: 0.5rem 0.75rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 3px; color: rgba(255,255,255,0.85); font-family: inherit; font-size: 0.9rem; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.input:focus { border-color: rgba(255, 178, 239,0.45); }
.input--err { border-color: rgba(239,68,68,0.5); }
.err { font-size: 0.75rem; color: rgba(239,68,68,0.8); margin: 0; }
.btn-primary { width: 100%; padding: 0.65rem 1rem; border: 1px solid rgba(255, 178, 239,0.5); border-radius: 3px; background: rgba(255, 178, 239,0.1); color: #ffb2ef; font-size: 0.9rem; font-family: inherit; font-weight: 500; cursor: pointer; transition: background 0.15s, border-color 0.15s; }
.btn-primary:hover:not(:disabled) { background: rgba(255, 178, 239,0.18); border-color: rgba(255, 178, 239,0.75); }
.btn-primary:disabled { opacity: 0.45; cursor: default; }
</style>
