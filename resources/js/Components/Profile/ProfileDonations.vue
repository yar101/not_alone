<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    donations: {
        type: Object,
        default: null,
    },
    isOwner: {
        type: Boolean,
        default: false,
    },
    profileUserId: {
        type: Number,
        required: true,
    },
});

const localDonations = ref([]);
const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    total: 0,
});
const loading = ref(false);

function applyDonationsData(data) {
    if (!data) return;
    localDonations.value = data.data ?? [];
    pagination.value = {
        currentPage: data.current_page ?? 1,
        lastPage: data.last_page ?? 1,
        total: data.total ?? (data.data?.length ?? 0),
    };
}

watch(
    () => props.donations,
    (newVal) => {
        if (newVal) {
            applyDonationsData(newVal);
        }
    },
    { immediate: true }
);

async function loadPage(page = 1) {
    loading.value = true;
    try {
        const response = await axios.get(route('users.donations', props.profileUserId), {
            params: { page },
        });
        applyDonationsData(response.data);
    } catch (err) {
        console.error('Ошибка загрузки донатов:', err);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    if (!props.donations && props.isOwner) {
        loadPage(1);
    }
});

const totalAmountReceived = computed(() => {
    return localDonations.value.reduce((acc, curr) => acc + Number(curr.amount || 0), 0).toFixed(2);
});
</script>

<template>
    <div class="profile-donations">
        <!-- Stats summary -->
        <div class="profile-donations__stats-grid">
            <div class="profile-donations__stat-card">
                <span class="profile-donations__stat-label">Всего пожертвований</span>
                <span class="profile-donations__stat-value">
                    {{ pagination.total }}
                </span>
            </div>
            <div class="profile-donations__stat-card">
                <span class="profile-donations__stat-label">На странице получено</span>
                <div class="profile-donations__stat-value profile-donations__stat-value--pink">
                    <span>+{{ totalAmountReceived }}</span>
                    <CoinIcon color="pink" size="sm" />
                </div>
            </div>
        </div>

        <!-- Donations List -->
        <div v-if="loading" class="profile-donations__loading">
            <span class="profile-donations__spinner"></span>
            <span>Загрузка пожертвований...</span>
        </div>

        <div v-else-if="localDonations.length === 0" class="profile-donations__empty">
            <div class="profile-donations__empty-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M20 12v10H4V12M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z" />
                </svg>
            </div>
            <h4 class="profile-donations__empty-title">Пока нет пожертвований</h4>
            <p class="profile-donations__empty-desc">
                Здесь будут отображаться полученные донаты от ваших поклонников.
            </p>
        </div>

        <div v-else class="profile-donations__list">
            <div
                v-for="donation in localDonations"
                :key="donation.id"
                class="profile-donations__item"
            >
                <div class="profile-donations__item-main">
                    <div class="profile-donations__donor">
                        <UserAvatar :user="donation.donor" :size="42" />
                        <div class="profile-donations__donor-meta">
                            <span class="profile-donations__donor-name">
                                {{ donation.donor?.name || 'Пользователь' }}
                            </span>
                            <span class="profile-donations__date">
                                {{ donation.created_at }}
                            </span>
                        </div>
                    </div>

                    <div class="profile-donations__amount-block">
                        <div class="profile-donations__amount">
                            <span>+{{ donation.amount }}</span>
                            <CoinIcon color="pink" size="sm" />
                        </div>
                        <span
                            v-if="donation.fee_covered_by_donor"
                            class="profile-donations__fee-tag profile-donations__fee-tag--covered"
                        >
                            Комиссия оплачена донатором
                        </span>
                        <span
                            v-else-if="donation.platform_fee > 0"
                            class="profile-donations__fee-tag"
                        >
                            Комиссия сервиса: {{ donation.platform_fee }}
                        </span>
                    </div>
                </div>

                <!-- Message bubble -->
                <div v-if="donation.message" class="profile-donations__message">
                    <p class="profile-donations__message-text">«{{ donation.message }}»</p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.lastPage > 1" class="profile-donations__pagination">
                <button
                    type="button"
                    class="profile-donations__page-btn"
                    :disabled="pagination.currentPage <= 1 || loading"
                    @click="loadPage(pagination.currentPage - 1)"
                >
                    ← Назад
                </button>
                <span class="profile-donations__page-info">
                    Страница {{ pagination.currentPage }} из {{ pagination.lastPage }}
                </span>
                <button
                    type="button"
                    class="profile-donations__page-btn"
                    :disabled="pagination.currentPage >= pagination.lastPage || loading"
                    @click="loadPage(pagination.currentPage + 1)"
                >
                    Вперёд →
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.profile-donations {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 16px 0;
}

.profile-donations__stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
}

.profile-donations__stat-card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 16px 20px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.profile-donations__stat-label {
    font-size: 0.85rem;
    color: var(--text-muted, #9ca3af);
}

.profile-donations__stat-value {
    font-size: 1.4rem;
    font-weight: 700;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 6px;
}

.profile-donations__stat-value--pink {
    color: #f472b6;
}

.profile-donations__loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 48px;
    color: var(--text-muted, #9ca3af);
}

.profile-donations__spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(244, 114, 182, 0.2);
    border-top-color: #f472b6;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.profile-donations__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 56px 20px;
    text-align: center;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px dashed rgba(255, 255, 255, 0.1);
}

.profile-donations__empty-icon {
    color: var(--text-muted, #6b7280);
    margin-bottom: 14px;
}

.profile-donations__empty-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #ffffff;
    margin: 0 0 6px 0;
}

.profile-donations__empty-desc {
    font-size: 0.875rem;
    color: var(--text-muted, #9ca3af);
    max-width: 380px;
    margin: 0;
}

.profile-donations__list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.profile-donations__item {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 16px 18px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    transition: border-color 0.15s;
}

.profile-donations__item:hover {
    border-color: rgba(244, 114, 182, 0.3);
}

.profile-donations__item-main {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.profile-donations__donor {
    display: flex;
    align-items: center;
    gap: 12px;
}

.profile-donations__avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(244, 114, 182, 0.3);
}

.profile-donations__donor-meta {
    display: flex;
    flex-direction: column;
}

.profile-donations__donor-name {
    font-size: 0.95rem;
    font-weight: 600;
    color: #ffffff;
}

.profile-donations__date {
    font-size: 0.78rem;
    color: var(--text-muted, #9ca3af);
}

.profile-donations__amount-block {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}

.profile-donations__amount {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 1.15rem;
    font-weight: 700;
    color: #f472b6;
}

.profile-donations__fee-tag {
    font-size: 0.72rem;
    color: var(--text-muted, #9ca3af);
    background: rgba(255, 255, 255, 0.06);
    padding: 2px 6px;
    border-radius: 6px;
}

.profile-donations__fee-tag--covered {
    color: #34d399;
    background: rgba(52, 211, 153, 0.12);
}

.profile-donations__message {
    padding: 10px 14px;
    border-radius: 10px;
    background: rgba(244, 114, 182, 0.08);
    border-left: 3px solid #f472b6;
}

.profile-donations__message-text {
    font-size: 0.88rem;
    color: var(--text-secondary, #e5e7eb);
    margin: 0;
    font-style: italic;
}

.profile-donations__pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
    padding-top: 12px;
}

.profile-donations__page-btn {
    padding: 8px 14px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #ffffff;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background 0.15s;
}

.profile-donations__page-btn:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.12);
}

.profile-donations__page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.profile-donations__page-info {
    font-size: 0.85rem;
    color: var(--text-muted, #9ca3af);
}
</style>
