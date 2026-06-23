<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
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
        // Optionally reload props to clear unread_strike globally
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
                <span class="strike-icon">⚠️</span>
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
                        Пожалуйста, будьте внимательны. Ваши страйки автоматически сгорят через 6 месяцев, 
                        если не будет новых нарушений. <strong>При получении 3 активных страйков ваш аккаунт будет заблокирован.</strong>
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
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.strike-modal-content {
    background: #1a0a0a;
    border: 2px solid #fc8181;
    border-radius: 12px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 0 40px rgba(229, 62, 62, 0.4);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: slideUp 0.4s ease-out forwards;
}

@keyframes slideUp {
    0% { transform: translateY(30px); opacity: 0; }
    100% { transform: translateY(0); opacity: 1; }
}

.strike-header {
    background: rgba(229, 62, 62, 0.15);
    padding: 1.5rem;
    text-align: center;
    border-bottom: 1px solid rgba(229, 62, 62, 0.3);
}

.strike-icon {
    font-size: 3rem;
    display: block;
    margin-bottom: 0.5rem;
}

.strike-header h2 {
    margin: 0;
    color: #fc8181;
    font-size: 1.4rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.strike-body {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.95rem;
    line-height: 1.5;
}

.strike-intro {
    margin: 0;
    text-align: center;
    font-weight: 500;
}

.strike-stats {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.03);
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.stat-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-label {
    color: rgba(255, 255, 255, 0.6);
}

.stat-value {
    font-weight: 700;
    font-size: 1.1rem;
}
.stat-value.danger { color: #fc8181; }
.stat-value.text-red { color: #f56565; }

.strike-note {
    background: rgba(0, 0, 0, 0.3);
    border-left: 4px solid #fc8181;
    padding: 1rem;
}

.note-label {
    font-size: 0.8rem;
    color: #fc8181;
    text-transform: uppercase;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.note-text {
    font-style: italic;
    color: rgba(255, 255, 255, 0.8);
}

.strike-warning {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    text-align: center;
}

.strike-warning strong {
    color: #fc8181;
}

.strike-footer {
    padding: 1.5rem;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(229, 62, 62, 0.2);
    display: flex;
    justify-content: center;
}

.btn-acknowledge {
    background: #e53e3e;
    color: #fff;
    border: none;
    padding: 0.8rem 2rem;
    font-size: 1.05rem;
    font-weight: 700;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    text-transform: uppercase;
    width: 100%;
}

.btn-acknowledge:hover:not(:disabled) {
    background: #c53030;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(229, 62, 62, 0.4);
}

.btn-acknowledge:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Адаптивность для мобильных */
@media (max-width: 640px) {
    .strike-modal-content {
        max-width: 100%;
        border-radius: 8px;
    }
    .strike-header {
        padding: 1rem;
    }
    .strike-header h2 {
        font-size: 1.2rem;
    }
    .strike-body {
        padding: 1rem;
        gap: 1rem;
    }
    .strike-icon {
        font-size: 2.5rem;
    }
    .btn-acknowledge {
        padding: 0.8rem 1rem;
        font-size: 0.95rem;
    }
    .strike-footer {
        padding: 1rem;
    }
}
</style>
