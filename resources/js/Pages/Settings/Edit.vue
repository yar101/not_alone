<script setup>
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

defineOptions({ layout: AppLayout });

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});

const activeTab = ref('profile');

const tabs = [
    { id: 'profile', label: 'Информация профиля', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { id: 'security', label: 'Безопасность', icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z' },
    { id: 'danger', label: 'Опасная зона', icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' }
];

const showLogoutModal = ref(false);

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <Head title="Настройки" />

    <div class="page-wrap">
        <div class="settings-container">
            <!-- Sidebar -->
            <aside class="settings-sidebar">
                <div class="page-header">
                    <Link :href="route('profile')" class="back-link">← Мой профиль</Link>
                    <h1 class="page-title">Настройки</h1>
                </div>

                <nav class="settings-nav">
                    <button 
                        v-for="tab in tabs" 
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        class="nav-tab"
                        :class="{ 'nav-tab--active': activeTab === tab.id }"
                    >
                        <svg class="tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
                        </svg>
                        {{ tab.label }}
                    </button>
                </nav>


            </aside>

            <!-- Main Content Area -->
            <main class="settings-content">
                <transition name="fade" mode="out-in">
                    <div :key="activeTab">
                        <UpdateProfileInformationForm
                            v-if="activeTab === 'profile'"
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                        />
                        <UpdatePasswordForm v-if="activeTab === 'security'" />
                        <div v-if="activeTab === 'danger'" class="danger-tab-wrapper">
                            <section class="settings-card">
                                <div class="card-header">
                                    <span class="card-label danger-label">Сессия</span>
                                    <h2 class="card-title">Выход из аккаунта</h2>
                                    <p class="card-desc">Завершить текущую сессию на этом устройстве.</p>
                                </div>
                                <button class="danger-btn logout-action-btn" @click="showLogoutModal = true">
                                    Выйти из аккаунта
                                </button>
                            </section>

                            <DeleteUserForm />
                        </div>
                    </div>
                </transition>
            </main>
        </div>
    </div>

    <!-- Modal for Logout Confirmation -->
    <SiteModal :show="showLogoutModal" @close="showLogoutModal = false" variant="pink" :compact="true">
        <div class="logout-modal">
            <h2 class="modal-title">Выход из аккаунта</h2>
            <p class="modal-desc">Вы действительно хотите выйти?</p>
            <div class="modal-actions">
                <button type="button" class="cancel-btn" @click="showLogoutModal = false">Отмена</button>
                <button type="button" class="confirm-logout-btn" @click="logout">Выйти</button>
            </div>
        </div>
    </SiteModal>
</template>

<style scoped>
.page-wrap {
    padding: 2rem 1rem 4rem;
    flex: 1;
}

.settings-container {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 2rem;
    align-items: flex-start;
}

.settings-sidebar {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    position: sticky;
    top: 2rem;
}

.page-header {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.back-link {
    font-size: 0.85rem;
    color: rgba(255, 178, 239, 0.7);
    text-decoration: none;
    transition: color 0.2s;
}
.back-link:hover { color: rgba(255, 178, 239, 1); }

.page-title {
    font-size: 1.8rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.95);
    margin: 0;
}

.settings-nav {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.nav-tab {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1.25rem;
    border-radius: 10px;
    background: transparent;
    border: 1px solid transparent;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    font-family: inherit;
    cursor: pointer;
    text-align: left;
    transition: all 0.2s ease;
}

.nav-tab:hover {
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.85);
}

.nav-tab--active {
    background: rgba(10, 7, 20, 0.7);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 4px 15px rgba(0, 0, 0, 0.2);
    color: rgba(255, 178, 239, 1);
}

.tab-icon {
    width: 20px;
    height: 20px;
    opacity: 0.7;
}

.nav-tab--active .tab-icon {
    opacity: 1;
    color: rgba(255, 178, 239, 1);
}

.danger-tab-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.mt-6 {
    margin-top: 1.5rem;
}

.settings-card {
    padding: 1.5rem;
    background: rgba(10, 7, 20, 0.7);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14), 0 4px 20px rgba(0, 0, 0, 0.3);
    border-radius: 12px;
}
.card-header { margin-bottom: 1.5rem; }
.card-label {
    font-size: 0.72rem; letter-spacing: 0.16em; text-transform: uppercase;
    color: rgba(155,110,232,0.6);
}
.danger-label { color: rgba(220,60,60,0.6); }
.card-title { font-size: 1.05rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0.35rem 0 0.25rem; }
.card-desc { font-size: 0.85rem; color: rgba(255,255,255,0.35); margin: 0; }

.danger-btn {
    padding: 0.65rem 1.5rem;
    border-radius: 8px;
    background: rgba(220, 60, 60, 0.2);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(220, 60, 60, 0.4);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 4px 10px rgba(0, 0, 0, 0.2);
    color: #ffcccc;
    font-size: 0.92rem;
    font-weight: 500;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}
.danger-btn:hover {
    background: rgba(220, 60, 60, 0.35);
    border-color: rgba(220, 60, 60, 0.6);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.15), 0 6px 15px rgba(0, 0, 0, 0.3);
    color: #fff;
}

.settings-content {
    min-width: 0; /* Prevents grid blowout */
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-enter-from {
    opacity: 0;
    transform: translateY(5px);
}
.fade-leave-to {
    opacity: 0;
    transform: translateY(-5px);
}

/* Modal styles */
.logout-modal {
    padding: 1rem 0.5rem;
}
.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.95);
    margin: 0 0 0.5rem;
}
.modal-desc {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0 0 1.5rem;
}
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}
.cancel-btn {
    padding: 0.65rem 1.25rem;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 4px 10px rgba(0, 0, 0, 0.2);
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.92rem;
    font-weight: 500;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}
.cancel-btn:hover { 
    background: rgba(255, 255, 255, 0.15); 
    border-color: rgba(255, 255, 255, 0.3); 
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 6px 15px rgba(0, 0, 0, 0.3);
    color: #fff;
}
.confirm-logout-btn {
    padding: 0.65rem 1.25rem;
    border-radius: 8px;
    background: rgba(220, 60, 60, 0.25);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(220, 60, 60, 0.5);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 4px 10px rgba(0, 0, 0, 0.2);
    color: #fff;
    font-size: 0.92rem;
    font-weight: 500;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}
.confirm-logout-btn:hover { 
    background: rgba(220, 60, 60, 0.4); 
    border-color: rgba(220, 60, 60, 0.7); 
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 6px 15px rgba(0, 0, 0, 0.3);
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .settings-container {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .settings-sidebar {
        position: static;
        gap: 1rem;
    }

    .settings-nav {
        flex-direction: row;
        overflow-x: auto;
        padding-bottom: 0.5rem;
        -webkit-overflow-scrolling: touch;
        gap: 0.5rem;
        /* Hiding scrollbar */
        scrollbar-width: none;
    }
    .settings-nav::-webkit-scrollbar {
        display: none;
    }
    
    .nav-tab {
        flex-shrink: 0;
        padding: 0.7rem 1rem;
    }
    
    .sidebar-footer {
        border-top: none;
        padding-top: 0;
    }
}
</style>
