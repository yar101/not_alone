<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const form = useForm({ password: '' });

const submit = () => form.post(route('password.confirm'), {
    onFinish: () => form.reset(),
});
</script>

<template>
    <Head :title="__('auth.confirm.title')" />

    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    <circle cx="12" cy="16" r="1" fill="currentColor"/>
                </svg>
            </div>

            <h1 class="auth-title">{{ __('auth.confirm.title') }}</h1>
            <p class="auth-hint">{{ __('auth.confirm.hint') }}</p>

            <form @submit.prevent="submit" class="auth-form">
                <div class="field">
                    <label class="field-label">{{ __('auth.password') }}</label>
                    <input
                        v-model="form.password"
                        type="password"
                        class="input"
                        :class="{ 'input--err': form.errors.password }"
                        autocomplete="current-password"
                        required
                        autofocus
                    />
                    <p v-if="form.errors.password" class="err">{{ form.errors.password }}</p>
                </div>

                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? __('auth.confirm.sending') : __('auth.confirm.submit') }}
                </button>
            </form>
        </div>
    </div>
</template>

<style scoped>
.auth-page { min-height: 100vh; background: #0a0a0f; display: flex; align-items: center; justify-content: center; padding: 2rem 1.5rem; font-family: 'Rubik', sans-serif; box-sizing: border-box; }
.auth-card { width: 100%; max-width: 400px; border: 1px solid rgba(255,255,255,0.1); border-radius: 3px; background: rgba(255,255,255,0.025); padding: 2.5rem 2rem; display: flex; flex-direction: column; align-items: center; gap: 1.25rem; text-align: center; }
.auth-icon { width: 3rem; height: 3rem; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(160,160,255,0.3); border-radius: 3px; background: rgba(160,160,255,0.06); color: #a0a0ff; flex-shrink: 0; }
.auth-icon svg { width: 1.4rem; height: 1.4rem; }
.auth-title { margin: 0; font-size: 1.35rem; font-weight: 600; color: rgba(255,255,255,0.92); letter-spacing: -0.01em; }
.auth-hint { margin: 0; font-size: 0.9rem; line-height: 1.65; color: rgba(255,255,255,0.45); }
.auth-form { display: flex; flex-direction: column; gap: 0.9rem; width: 100%; }
.field { display: flex; flex-direction: column; gap: 0.3rem; text-align: left; }
.field-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.35); }
.input { padding: 0.5rem 0.75rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 3px; color: rgba(255,255,255,0.85); font-family: inherit; font-size: 0.9rem; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.input:focus { border-color: rgba(160,160,255,0.45); }
.input--err { border-color: rgba(239,68,68,0.5); }
.err { font-size: 0.75rem; color: rgba(239,68,68,0.8); margin: 0; }
.btn-primary { width: 100%; padding: 0.65rem 1rem; border: 1px solid rgba(160,160,255,0.5); border-radius: 3px; background: rgba(160,160,255,0.1); color: #a0a0ff; font-size: 0.9rem; font-family: inherit; font-weight: 500; cursor: pointer; transition: background 0.15s, border-color 0.15s; }
.btn-primary:hover:not(:disabled) { background: rgba(160,160,255,0.18); border-color: rgba(160,160,255,0.75); }
.btn-primary:disabled { opacity: 0.45; cursor: default; }
</style>
