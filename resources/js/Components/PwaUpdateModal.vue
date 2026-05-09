<script setup>
import { useTranslations } from '@/composables/useTranslations';

const { __, locale, switchLocale } = useTranslations();

defineProps({
    show: Boolean
});

function reload() {
    window.location.reload();
}
</script>

<template>
    <Teleport to="body">
        <Transition name="update-fade">
            <div v-if="show" class="pwa-update-overlay">
                <div class="pwa-update-modal">
                    <!-- Language switcher -->
                    <div class="update-locale">
                        <button
                            v-for="(label, code) in locale?.available"
                            :key="code"
                            class="update-locale__btn"
                            :class="{ 'update-locale__btn--active': locale?.current === code }"
                            @click="switchLocale(code)"
                        >
                            <span class="update-locale__flag">{{ code === 'ru' ? '🇷🇺' : '🇬🇧' }}</span>
                            {{ label }}
                        </button>
                    </div>

                    <div class="pwa-update-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                    </div>
                    <h3 class="pwa-update-title">{{ __('app.update.title') }}</h3>
                    <p class="pwa-update-msg">{{ __('app.update.message') }}</p>
                    <button class="cm-btn cm-btn--primary pwa-update-btn" @click="reload">
                        {{ __('app.update.button') }}
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.pwa-update-overlay {
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: rgba(5, 5, 10, 0.9);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}

.pwa-update-modal {
    background: linear-gradient(145deg, #161122 0%, #0d0a14 100%);
    border: 1px solid rgba(255, 178, 239, 0.2);
    border-radius: 12px;
    padding: 2.5rem 2rem;
    text-align: center;
    max-width: 380px;
    width: 100%;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255, 178, 239, 0.05);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.2rem;
    position: relative;
}

.update-locale {
    display: flex;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.04);
    padding: 3px;
    border-radius: 100px;
    margin-bottom: 0.5rem;
}

.update-locale__btn {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.8rem;
    border-radius: 100px;
    border: none;
    background: transparent;
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.update-locale__btn--active {
    background: rgba(255, 178, 239, 0.12);
    color: var(--color-base-1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.update-locale__flag {
    font-size: 1rem;
    filter: saturate(0.8);
}

.update-locale__btn--active .update-locale__flag {
    filter: saturate(1.2);
}

.pwa-update-icon {
    color: var(--color-base-1);
    background: rgba(255, 178, 239, 0.08);
    width: 72px;
    height: 72px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.2rem;
    border: 1px solid rgba(255, 178, 239, 0.15);
}

.pwa-update-icon svg {
    width: 32px;
    height: 32px;
}

.pwa-update-title {
    margin: 0;
    font-size: 1.4rem;
    color: #fff;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.pwa-update-msg {
    margin: 0;
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.65);
    line-height: 1.5;
}

.pwa-update-btn {
    width: 100%;
    margin-top: 0.8rem;
    font-size: 1.05rem;
    padding: 0.85rem;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(255, 178, 239, 0.2);
    animation: pwa-pulse 2s infinite;
}

@keyframes pwa-pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(255, 178, 239, 0.4);
    }
    70% {
        box-shadow: 0 0 0 12px rgba(255, 178, 239, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(255, 178, 239, 0);
    }
}

.update-fade-enter-active,
.update-fade-leave-active {
    transition: opacity 0.3s ease;
}
.update-fade-enter-from,
.update-fade-leave-to {
    opacity: 0;
}

.update-fade-enter-active .pwa-update-modal,
.update-fade-leave-active .pwa-update-modal {
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.update-fade-enter-from .pwa-update-modal,
.update-fade-leave-to .pwa-update-modal {
    transform: scale(0.9);
}
</style>
