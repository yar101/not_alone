<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <Head :title="__('verify.title')" />

    <div class="verify-page">
        <div class="verify-card">

            <!-- Icon -->
            <div class="verify-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"/>
                    <path d="M2 7l10 7 10-7"/>
                </svg>
            </div>

            <h1 class="verify-title">{{ __('verify.title') }}</h1>

            <p class="verify-text">{{ __('verify.text') }}</p>

            <p v-if="verificationLinkSent" class="verify-sent">
                {{ __('verify.resent') }}
            </p>

            <form @submit.prevent="submit" class="verify-actions">
                <button
                    type="submit"
                    class="verify-btn-primary"
                    :disabled="form.processing"
                >
                    {{ form.processing ? __('verify.resending') : __('verify.resend') }}
                </button>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="verify-btn-ghost"
                >
                    {{ __('verify.logout') }}
                </Link>
            </form>

        </div>
    </div>
</template>

<style scoped>
.verify-page {
    min-height: 100vh;
    background: #0a0a0f;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1.5rem;
    font-family: 'Rubik', sans-serif;
    box-sizing: border-box;
}

.verify-card {
    width: 100%;
    max-width: 420px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.025);
    padding: 2.5rem 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.25rem;
    text-align: center;
}

.verify-icon {
    width: 3rem;
    height: 3rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 178, 239, 0.3);
    border-radius: 3px;
    background: rgba(255, 178, 239, 0.06);
    color: #ffb2ef;
    flex-shrink: 0;
}
.verify-icon svg {
    width: 1.4rem;
    height: 1.4rem;
}

.verify-title {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.92);
    letter-spacing: -0.01em;
}

.verify-text {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.65;
    color: rgba(255, 255, 255, 0.45);
}

.verify-sent {
    margin: 0;
    font-size: 0.85rem;
    color: rgba(74, 222, 128, 0.85);
    border: 1px solid rgba(74, 222, 128, 0.2);
    border-radius: 3px;
    background: rgba(74, 222, 128, 0.05);
    padding: 0.55rem 0.9rem;
    width: 100%;
    box-sizing: border-box;
}

.verify-actions {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    width: 100%;
    margin-top: 0.25rem;
}

.verify-btn-primary {
    width: 100%;
    padding: 0.65rem 1rem;
    border: 1px solid rgba(255, 178, 239, 0.5);
    border-radius: 3px;
    background: rgba(255, 178, 239, 0.1);
    color: #ffb2ef;
    font-size: 0.9rem;
    font-family: inherit;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}
.verify-btn-primary:hover:not(:disabled) {
    background: rgba(255, 178, 239, 0.18);
    border-color: rgba(255, 178, 239, 0.75);
}
.verify-btn-primary:disabled {
    opacity: 0.45;
    cursor: default;
}

.verify-btn-ghost {
    width: 100%;
    padding: 0.55rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 3px;
    background: transparent;
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.85rem;
    font-family: inherit;
    cursor: pointer;
    text-decoration: none;
    transition: border-color 0.15s, color 0.15s;
    display: block;
    box-sizing: border-box;
}
.verify-btn-ghost:hover {
    border-color: rgba(255, 255, 255, 0.18);
    color: rgba(255, 255, 255, 0.55);
}
</style>
