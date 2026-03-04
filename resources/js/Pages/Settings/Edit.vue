<script setup>
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link } from '@inertiajs/vue3';
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
        </div>
    </div>
</template>

<style scoped>
.page-wrap {
    background:
        radial-gradient(ellipse at 20% 30%, rgba(200, 70, 126, 0.07) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 70%, rgba(100, 60, 180, 0.06) 0%, transparent 60%);
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
    color: rgba(200, 70, 126, 0.7);
    text-decoration: none;
    transition: color 0.2s;
}
.back-link:hover { color: rgba(200, 70, 126, 1); }

.page-title {
    font-size: 1.4rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
}
</style>
