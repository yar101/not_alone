<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { WarningFilled } from '@element-plus/icons-vue';
import axios from 'axios';

const page = usePage();

// Get the unread strike from Inertia shared props
const unreadStrike = computed(() => page.props.unread_strike);
const isVisible = ref(true);
const isSubmitting = ref(false);

const strikeData = computed(() => {
    if (!unreadStrike.value) return null;
    return unreadStrike.value.data || {};
});

async function markAsRead() {
    if (!unreadStrike.value) return;
    
    isSubmitting.value = true;
    try {
        await axios.patch(route('notifications.read', unreadStrike.value.id));
        isVisible.value = false;
        router.reload({ only: ['unread_strike'] });
    } catch (e) {
        console.error('Failed to mark strike as read', e);
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <div v-if="unreadStrike && isVisible" class="strike-modal-overlay">
        <div class="strike-modal-content">
            <div class="strike-header">
                <el-icon class="strike-icon"><WarningFilled /></el-icon>
                <h2>Предупреждение о нарушении</h2>
            </div>
            
            <div class="strike-body">
                <p class="strike-intro">
                    Администрация платформы вынесла вам предупреждение (страйк) за нарушение правил.
                </p>

                <div class="strike-stats">
                    <div class="stat-box">
                        <span class="stat-label">Текущие страйки:</span>
                        <span class="stat-value danger">{{ strikeData.active_strikes_total }} / 3</span>
                    </div>
                    <div class="stat-box" v-if="strikeData.rating_deducted && strikeData.rating_deducted < 0">
                        <span class="stat-label">Списание рейтинга:</span>
                        <span class="stat-value text-red">{{ strikeData.rating_deducted }}</span>
                    </div>
                </div>

                <div v-if="strikeData.admin_note" class="strike-note">
                    <div class="note-label">Примечание от модератора:</div>
                    <div class="note-text">"{{ strikeData.admin_note }}"</div>
                </div>

                <div class="strike-warning">
                    <p>
                        <strong>При получении 3 активных страйков ваш аккаунт будет заблокирован.</strong>
                    </p>
                </div>
            </div>

            <div class="strike-footer">
                <button 
                    class="btn-acknowledge" 
                    @click="markAsRead" 
                    :disabled="isSubmitting"
                >
                    {{ isSubmitting ? 'Обработка...' : 'Я ознакомился и понял' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.strike-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.strike-modal-content {
    background: rgba(25, 10, 15, 0.85);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(229, 62, 62, 0.2);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5), inset 0 0 0 1px rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    width: 100%;
    max-width: 480px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes slideUp {
    0% { transform: translateY(40px) scale(0.95); opacity: 0; }
    100% { transform: translateY(0) scale(1); opacity: 1; }
}

.strike-header {
    background: rgba(229, 62, 62, 0.08);
    padding: 1.5rem;
    text-align: center;
    border-bottom: 1px solid rgba(229, 62, 62, 0.15);
}

.strike-icon {
    font-size: 3.5rem;
    color: rgba(252, 129, 129, 0.9);
    margin-bottom: 0.5rem;
    display: inline-flex;
}

.strike-header h2 {
    margin: 0;
    color: #fc8181;
    font-size: 1.25rem;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.strike-body {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.95rem;
    line-height: 1.5;
}

.strike-intro {
    margin: 0;
    text-align: center;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.9);
}

.strike-stats {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.03);
    padding: 1rem 1.25rem;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.06);
}

.stat-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
}

.stat-value {
    font-weight: 600;
    font-size: 1.05rem;
}
.stat-value.danger { color: #fc8181; }
.stat-value.text-red { color: #f56565; }

.strike-note {
    background: rgba(0, 0, 0, 0.25);
    border-radius: 8px;
    padding: 1rem 1.25rem;
    border-left: 3px solid rgba(252, 129, 129, 0.6);
}

.note-label {
    font-size: 0.75rem;
    color: rgba(252, 129, 129, 0.8);
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.05em;
    margin-bottom: 0.4rem;
}

.note-text {
    font-style: italic;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.strike-warning {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    text-align: center;
}

.strike-warning strong {
    color: #fc8181;
    font-weight: 500;
}

.strike-footer {
    padding: 1.25rem 1.5rem;
    background: rgba(0, 0, 0, 0.15);
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: center;
}

.btn-acknowledge {
    background: rgba(229, 62, 62, 0.15);
    color: #fc8181;
    border: 1px solid rgba(229, 62, 62, 0.3);
    padding: 0.75rem 2rem;
    font-size: 0.95rem;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
}

.btn-acknowledge:hover:not(:disabled) {
    background: rgba(229, 62, 62, 0.25);
    border-color: rgba(229, 62, 62, 0.5);
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(229, 62, 62, 0.2);
}

.btn-acknowledge:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 640px) {
    .strike-modal-content {
        max-width: 100%;
        border-radius: 12px;
    }
    .strike-header {
        padding: 1.25rem;
    }
    .strike-icon {
        font-size: 3rem;
    }
    .strike-body {
        padding: 1.25rem;
    }
    .btn-acknowledge {
        padding: 0.75rem 1rem;
    }
    .strike-footer {
        padding: 1.25rem;
    }
}
</style>
