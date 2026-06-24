<script setup>
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});
</script>

<template>
    <Head title="Настройки" />

    <div class="page-wrap">
        <div class="settings-container">
            <div class="page-header">
                <Link :href="route('profile')" class="back-link">← Мой профиль</Link>
                <h1 class="page-title">Настройки аккаунта</h1>
            </div>

            <UpdateProfileInformationForm
                :must-verify-email="mustVerifyEmail"
                :status="status"
            />
            <UpdatePasswordForm />
            <DeleteUserForm />

            <div class="logout-section">
                <button class="logout-btn" @click="router.post(route('logout'))">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Выйти из аккаунта
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.page-wrap {
    padding: 2rem 1rem 4rem;
    flex: 1;
}

.settings-container {
    max-width: 680px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.page-header {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-bottom: 0.5rem;
}

.back-link {
    font-size: 0.85rem;
    color: rgba(255, 178, 239, 0.7);
    text-decoration: none;
    transition: color 0.2s;
}
.back-link:hover { color: rgba(255, 178, 239, 1); }

.page-title {
    font-size: 1.4rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
}

.logout-section {
    display: flex;
    justify-content: flex-end;
    padding-top: 0.5rem;
}

.logout-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    color: rgba(220, 80, 100, 0.75);
    background: none;
    border: 1px solid rgba(220, 80, 100, 0.2);
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.logout-btn:hover {
    background: rgba(220, 60, 80, 0.08);
    color: rgba(240, 100, 120, 0.95);
    border-color: rgba(220, 80, 100, 0.4);
}
</style>
