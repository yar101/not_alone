<script setup>
import { ref } from "vue";
import AuthModal from "@/Components/Site/AuthModal.vue";
import { useTranslations } from "@/composables/useTranslations";

const { __ } = useTranslations();

const showAuth = ref(false);
const authTab = ref("login");

function open(tab = "login") {
    authTab.value = tab;
    showAuth.value = true;
}
</script>

<template>
    <div class="guest-banner">
        <p class="guest-banner__text">{{ __("profile.guest.text") }}</p>
        <div class="guest-banner__actions">
            <button
                class="guest-banner__btn guest-banner__btn--login"
                @click="open('login')"
            >
                {{ __("common.login") }}
            </button>
            <button
                class="guest-banner__btn guest-banner__btn--register"
                @click="open('register')"
            >
                {{ __("common.register") }}
            </button>
        </div>
    </div>

    <AuthModal
        :show="showAuth"
        :initial-tab="authTab"
        @close="showAuth = false"
    />
</template>

<style scoped>
.guest-banner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 1rem;
    text-align: center;
}
.guest-banner__text {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.4);
    margin: 0;
    line-height: 1.6;
}
.guest-banner__actions {
    display: flex;
    gap: 0.5rem;
}
.guest-banner__btn {
    padding: 0.45rem 1.1rem;
    border-radius: 3px;
    font-size: 0.82rem;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.15s;
}
.guest-banner__btn--login {
    background: transparent;
    border: 1px solid rgba(255, 178, 239, 0.35);
    color: var(--color-base-1);
    opacity: 0.8;
}
.guest-banner__btn--login:hover {
    border-color: var(--color-base-1);
    opacity: 1;
}
.guest-banner__btn--register {
    background: rgba(255, 178, 239, 0.12);
    border: 1px solid rgba(255, 178, 239, 0.3);
    color: rgba(255, 255, 255, 0.75);
}
.guest-banner__btn--register:hover {
    background: rgba(255, 178, 239, 0.22);
    color: #fff;
}
</style>
