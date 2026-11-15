<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { ElMessageBox, ElMessage } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import {
    Star, StarFilled, Search, Delete, RefreshRight, Check, Close, Warning, User
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    reviews: Object,
    filters: Object,
    counts: Object,
    filter_user: Object,
    filter_idol: Object,
});

const currentStatus = ref(props.filters?.status ?? 'all');
const searchInput = ref(props.filters?.search ?? '');
const searchUserInput = ref(props.filters?.search_user ?? '');
const selectedRating = ref(props.filters?.rating ? String(props.filters.rating) : '');
const reviewBannedOnly = ref(Boolean(props.filters?.review_banned_only));
let debounceTimer = null;

function applyFilters(overrides = {}) {
    const params = {
        status: overrides.status !== undefined ? overrides.status : currentStatus.value,
        search: overrides.search !== undefined ? overrides.search : (searchInput.value || undefined),
        search_user: overrides.search_user !== undefined ? overrides.search_user : (searchUserInput.value || undefined),
        rating: overrides.rating !== undefined ? overrides.rating : (selectedRating.value || undefined),
        reviewer_id: overrides.reviewer_id !== undefined ? overrides.reviewer_id : (props.filters?.reviewer_id || undefined),
        idol_id: overrides.idol_id !== undefined ? overrides.idol_id : (props.filters?.idol_id || undefined),
        review_banned_only: overrides.review_banned_only !== undefined ? overrides.review_banned_only : (reviewBannedOnly.value ? 1 : undefined),
    };

    router.get(route('admin.reviews.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function switchStatus(val) {
    currentStatus.value = val;
    applyFilters({ status: val });
}

function onSearchDebounce() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        applyFilters();
    }, 350);
}

function resetReviewerFilter() {
    applyFilters({ reviewer_id: undefined });
}

function resetIdolFilter() {
    applyFilters({ idol_id: undefined });
}

function filterByReviewer(id) {
    applyFilters({ reviewer_id: id });
}

function filterByIdol(id) {
    applyFilters({ idol_id: id });
}

import AppAvatar from '@/Components/Common/AppAvatar.vue';

function approveReview(review) {
    ElMessageBox.confirm(
        `Одобрить отзыв #${review.id} (${review.rating}★)? Рейтинг айдола будет пересчитан, а айдолу отправится уведомление.`,
        'Одобрение отзыва',
        {
            confirmButtonText: 'Одобрить и опубликовать',
            cancelButtonText: 'Отмена',
            type: 'success',
        }
    ).then(() => {
        router.post(route('admin.reviews.approve', review.id), {}, {
            preserveScroll: true,
        });
    }).catch(() => {});
}

function rejectReview(review) {
    ElMessageBox.prompt(
        `Укажите причину отклонения отзыва #${review.id}:`,
        'Отклонить отзыв',
        {
            confirmButtonText: 'Отклонить',
            cancelButtonText: 'Отмена',
            inputPlaceholder: 'Причина отклонения (будет зафиксирована в аудите)...',
            inputType: 'textarea',
        }
    ).then(({ value }) => {
        router.post(route('admin.reviews.reject', review.id), {
            rejection_reason: value || null,
        }, {
            preserveScroll: true,
        });
    }).catch(() => {});
}

function toggleDeleteReview(review) {
    if (review.is_deleted) {
        ElMessageBox.confirm(
            `Восстановить отзыв #${review.id}? Он снова станет виден публично, а дельта рейтинга айдола вернётся.`,
            'Восстановление отзыва',
            {
                confirmButtonText: 'Восстановить',
                cancelButtonText: 'Отмена',
                type: 'info',
            }
        ).then(() => {
            router.post(route('admin.reviews.delete', review.id), {}, {
                preserveScroll: true,
            });
        }).catch(() => {});
    } else {
        ElMessageBox.prompt(
            `Удалить отзыв #${review.id}? Отзыв будет скрыт от ВСЕХ (включая автора и айдола), а начисленный рейтинг айдола (${review.rating_delta_applied > 0 ? '+' : ''}${review.rating_delta_applied}) будет гарантированно списан.`,
            'Удаление отзыва',
            {
                confirmButtonText: 'Удалить отзыв',
                cancelButtonText: 'Отмена',
                inputPlaceholder: 'Причина удаления отзыва (опционально)...',
                inputType: 'textarea',
            }
        ).then(({ value }) => {
            router.post(route('admin.reviews.delete', review.id), {
                reason: value || null,
            }, {
                preserveScroll: true,
            });
        }).catch(() => {});
    }
}

function toggleUserReviewBan(user) {
    if (!user) return;
    if (user.is_review_banned) {
        ElMessageBox.confirm(
            `Снять блокировку отзывов (Review-Ban) с пользователя ${user.name}? Ему снова будет доступна отправка отзывов.`,
            'Снятие Review-Ban',
            {
                confirmButtonText: 'Снять блокировку',
                cancelButtonText: 'Отмена',
                type: 'info',
            }
        ).then(() => {
            router.post(route('admin.reviews.toggle-review-ban', user.id), {}, {
                preserveScroll: true,
            });
        }).catch(() => {});
    } else {
        ElMessageBox.prompt(
            `Установить Review-Ban для пользователя ${user.name}? Ему больше никогда не будет предлагаться форма отзыва в чате, а создание отзывов будет заблокировано.`,
            'Выдать Review-Ban',
            {
                confirmButtonText: 'Заблокировать отзывы',
                cancelButtonText: 'Отмена',
                inputPlaceholder: 'Причина Review-Ban (опционально)...',
            }
        ).then(({ value }) => {
            router.post(route('admin.reviews.toggle-review-ban', user.id), {
                reason: value || null,
            }, {
                preserveScroll: true,
            });
        }).catch(() => {});
    }
}

// ─── Details Modal ───────────────────────────────────────────────────────────
const selectedReview = ref(null);
function openDetails(r) {
    selectedReview.value = r;
}
function closeDetails() {
    selectedReview.value = null;
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru', {
        day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit'
    });
}
</script>

<template>
    <div class="reviews-page">
        <AdminPageHeader
            title="Отзывы"
            subtitle="Модерация отзывов, поиск по словам, теневое скрытие и управление Review-Ban"
            :count="counts.all"
        >
            <template #badge>
                <span v-if="counts.pending > 0" class="badge-pending-header">
                    {{ counts.pending }} на проверке
                </span>
            </template>
        </AdminPageHeader>

        <!-- Active Filter Banners -->
        <div v-if="filter_user" class="active-filter-banner">
            <div class="banner-content">
                <span class="banner-label">Фильтр по автору:</span>
                <span class="banner-user">
                    <strong>{{ filter_user.name }}</strong> ({{ filter_user.email }})
                    <span v-if="filter_user.is_review_banned" class="tag-review-banned">Review-Ban</span>
                </span>
            </div>
            <button class="btn-clear-filter" @click="resetReviewerFilter">✕ Сбросить фильтр по автору</button>
        </div>

        <div v-if="filter_idol" class="active-filter-banner banner-idol">
            <div class="banner-content">
                <span class="banner-label">Фильтр по айдолу:</span>
                <span class="banner-user">
                    <strong>{{ filter_idol.name }}</strong> ({{ filter_idol.email }})
                </span>
            </div>
            <button class="btn-clear-filter" @click="resetIdolFilter">✕ Сбросить фильтр по айдолу</button>
        </div>

        <!-- Status Tabs -->
        <div class="tabs-wrap">
            <button
                v-for="t in [
                    { key: 'all', label: 'Все отзывы', count: counts.all },
                    { key: 'pending', label: 'Ожидают модерации', count: counts.pending, alert: counts.pending > 0 },
                    { key: 'approved', label: 'Одобренные', count: counts.approved },
                    { key: 'rejected', label: 'Отклонённые', count: counts.rejected },
                    { key: 'deleted', label: 'Удалённые', count: counts.deleted },
                ]"
                :key="t.key"
                class="tab-btn"
                :class="{
                    'tab-btn--active': currentStatus === t.key,
                    'tab-btn--alert': t.alert
                }"
                @click="switchStatus(t.key)"
            >
                {{ t.label }}
                <span class="tab-count">{{ t.count }}</span>
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <!-- Text Search in Review Words -->
            <div class="filter-item filter-search-words">
                <el-input
                    v-model="searchInput"
                    placeholder="Поиск по словам в тексте отзыва..."
                    clearable
                    :prefix-icon="Search"
                    @input="onSearchDebounce"
                    @clear="applyFilters({ search: '' })"
                />
            </div>

            <!-- User / Idol Search -->
            <div class="filter-item filter-search-user">
                <el-input
                    v-model="searchUserInput"
                    placeholder="Поиск автора или айдола (имя, email)..."
                    clearable
                    :prefix-icon="User"
                    @input="onSearchDebounce"
                    @clear="applyFilters({ search_user: '' })"
                />
            </div>

            <!-- Rating Filter -->
            <div class="filter-item filter-rating">
                <el-select
                    v-model="selectedRating"
                    placeholder="Оценка (любая)"
                    clearable
                    @change="applyFilters({ rating: selectedRating })"
                    style="width: 150px;"
                >
                    <el-option label="Все оценки" value="" />
                    <el-option label="5 ★★★★★" value="5" />
                    <el-option label="4 ★★★★" value="4" />
                    <el-option label="3 ★★★" value="3" />
                    <el-option label="2 ★★" value="2" />
                    <el-option label="1 ★" value="1" />
                </el-select>
            </div>

            <!-- Review Banned Only Checkbox -->
            <div class="filter-item filter-checkbox">
                <label class="custom-chk">
                    <input
                        type="checkbox"
                        v-model="reviewBannedOnly"
                        @change="applyFilters({ review_banned_only: reviewBannedOnly ? 1 : undefined })"
                    />
                    <span>Только Review-Ban авторы</span>
                </label>
            </div>
        </div>

        <!-- Table -->
        <div class="table-wrap">
            <table class="reviews-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Автор (клиент)</th>
                        <th>Айдол</th>
                        <th>Оценка</th>
                        <th>Текст отзыва и услуги</th>
                        <th>Статус</th>
                        <th>Дата</th>
                        <th style="text-align: right; width: 220px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!reviews.data?.length">
                        <td colspan="8" class="empty-cell">
                            Отзывов по заданным критериям не найдено.
                        </td>
                    </tr>
                    <tr
                        v-for="r in reviews.data"
                        :key="r.id"
                        class="review-row"
                        :class="{
                            'row--pending': r.status === 'pending',
                            'row--deleted': r.is_deleted,
                            'row--rejected': r.status === 'rejected'
                        }"
                    >
                        <!-- ID -->
                        <td class="cell-id">
                            <span class="id-tag">#{{ r.id }}</span>
                        </td>

                        <!-- Reviewer -->
                        <td class="cell-user">
                            <div class="user-block">
                                <AppAvatar
                                    :src="r.reviewer?.avatar_url"
                                    :name="r.reviewer?.name || '?'"
                                    size="md"
                                />
                                <div class="user-details">
                                    <div class="user-name-line">
                                        <Link
                                            v-if="r.reviewer"
                                            :href="route('admin.users.show', r.reviewer.id)"
                                            class="user-link"
                                        >
                                            {{ r.reviewer.name }}
                                        </Link>
                                        <span v-else class="text-muted">Удалён</span>
                                        <span v-if="r.reviewer?.is_review_banned" class="tag-review-banned" title="У пользователя активен Review-Ban">
                                            Review-Ban
                                        </span>
                                    </div>
                                    <div class="user-sub">
                                        <span class="text-muted">{{ r.reviewer?.email || '—' }}</span>
                                    </div>
                                    <div class="user-actions-micro">
                                        <button
                                            type="button"
                                            class="btn-micro"
                                            title="Показать все отзывы этого автора"
                                            @click="filterByReviewer(r.reviewer.id)"
                                        >
                                            Все отзывы
                                        </button>
                                        <button
                                            v-if="r.reviewer"
                                            type="button"
                                            class="btn-micro"
                                            :class="{ 'btn-micro--danger': !r.reviewer.is_review_banned, 'btn-micro--success': r.reviewer.is_review_banned }"
                                            @click="toggleUserReviewBan(r.reviewer)"
                                        >
                                            {{ r.reviewer.is_review_banned ? 'Снять Ban' : 'Review-Ban' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Idol -->
                        <td class="cell-idol">
                            <div class="user-block">
                                <AppAvatar
                                    :src="r.idol?.avatar_url"
                                    :name="r.idol?.name || '?'"
                                    size="md"
                                />
                                <div class="user-details">
                                    <div class="user-name-line">
                                        <Link
                                            v-if="r.idol"
                                            :href="route('admin.users.show', r.idol.id)"
                                            class="user-link"
                                        >
                                            {{ r.idol.name }}
                                        </Link>
                                        <span v-else class="text-muted">Удалён</span>
                                    </div>
                                    <div class="user-sub">
                                        <span>Рейтинг: <strong>{{ r.idol?.rating ?? 20 }}</strong></span>
                                        <button
                                            type="button"
                                            class="btn-micro"
                                            title="Показать отзывы этому айдолу"
                                            @click="filterByIdol(r.idol.id)"
                                        >
                                            Отзывы айдола
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Rating & Delta -->
                        <td class="cell-rating">
                            <div class="rating-stars">
                                <span v-for="i in 5" :key="i" class="star" :class="{ 'star--active': i <= r.rating }">★</span>
                            </div>
                            <div class="rating-delta-info">
                                <span
                                    v-if="r.rating_applied"
                                    class="delta-badge"
                                    :class="r.rating_delta_applied >= 0 ? 'delta--pos' : 'delta--neg'"
                                >
                                    {{ r.rating_delta_applied > 0 ? '+' : '' }}{{ r.rating_delta_applied }} к рейтингу
                                </span>
                                <span v-else class="delta-badge delta--none">
                                    Не начислен
                                </span>
                            </div>
                            <div v-if="r.epithets?.length" class="epithets-tags">
                                <span v-for="ep in r.epithets" :key="ep.id" class="ep-tag">{{ ep.label }}</span>
                            </div>
                        </td>

                        <!-- Text & Order -->
                        <td class="cell-text">
                            <div class="review-text-wrap" @click="openDetails(r)">
                                <p class="review-text-single-line">{{ r.text || '— (без текста)' }}</p>
                            </div>
                        </td>

                        <!-- Status -->
                        <td class="cell-status">
                            <div class="status-stack">
                                <span
                                    v-if="r.is_deleted"
                                    class="status-pill pill--deleted"
                                >
                                    Удалён (Скрыт)
                                </span>
                                <span
                                    v-else-if="r.status === 'pending'"
                                    class="status-pill pill--pending"
                                >
                                    На модерации
                                </span>
                                <span
                                    v-else-if="r.status === 'approved'"
                                    class="status-pill pill--approved"
                                >
                                    Одобрен
                                </span>
                                <span
                                    v-else-if="r.status === 'rejected'"
                                    class="status-pill pill--rejected"
                                >
                                    Отклонён
                                </span>

                                <span v-if="r.is_hidden && !r.is_deleted" class="sub-pill pill--hidden">
                                    Скрыт спором
                                </span>
                            </div>
                        </td>

                        <!-- Date -->
                        <td class="cell-date">
                            <div class="date-text">{{ formatDate(r.created_at) }}</div>
                            <div v-if="r.moderated_at" class="meta-micro">
                                Модерация: {{ formatDate(r.moderated_at) }} ({{ r.moderated_by?.name || 'Админ' }})
                            </div>
                        </td>

                        <!-- Actions -->
                        <td class="cell-actions">
                            <div class="action-buttons-wrap">
                                <!-- Approve button (for pending) -->
                                <button
                                    v-if="r.status === 'pending' || r.status === 'rejected'"
                                    type="button"
                                    class="btn-act btn-act--approve"
                                    title="Одобрить и опубликовать"
                                    @click="approveReview(r)"
                                >
                                    <el-icon><Check /></el-icon>
                                    Одобрить
                                </button>

                                <!-- Reject button (for pending) -->
                                <button
                                    v-if="r.status === 'pending'"
                                    type="button"
                                    class="btn-act btn-act--reject"
                                    title="Отклонить отзыв"
                                    @click="rejectReview(r)"
                                >
                                    <el-icon><Close /></el-icon>
                                    Отклонить
                                </button>

                                <!-- Delete / Restore button -->
                                <button
                                    v-if="!r.is_deleted"
                                    type="button"
                                    class="btn-act btn-act--delete"
                                    title="Удалить отзыв (скрыть от всех и откатить рейтинг)"
                                    @click="toggleDeleteReview(r)"
                                >
                                    <el-icon><Delete /></el-icon>
                                    Удалить
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="btn-act btn-act--restore"
                                    title="Восстановить отзыв"
                                    @click="toggleDeleteReview(r)"
                                >
                                    <el-icon><RefreshRight /></el-icon>
                                    Восстановить
                                </button>

                                <!-- Details button -->
                                <button
                                    type="button"
                                    class="btn-act btn-act--details"
                                    title="Подробная информация"
                                    @click="openDetails(r)"
                                >
                                    Детали
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AdminPagination :pagination="reviews" />

        <!-- Details Modal -->
        <el-dialog
            v-model="selectedReview"
            title="Карточка отзыва"
            width="620px"
            destroy-on-close
            custom-class="dark-dialog"
        >
            <div v-if="selectedReview" class="modal-body">
                <div class="m-row">
                    <span class="m-label">ID отзыва:</span>
                    <span class="m-val">#{{ selectedReview.id }}</span>
                </div>
                <div class="m-row">
                    <span class="m-label">Статус:</span>
                    <span class="m-val">
                        <strong v-if="selectedReview.is_deleted" style="color: #f87171;">Удалён (скрыт от всех)</strong>
                        <strong v-else-if="selectedReview.status === 'pending'" style="color: #fbbf24;">На модерации</strong>
                        <strong v-else-if="selectedReview.status === 'approved'" style="color: #4ade80;">Одобрен и опубликован</strong>
                        <strong v-else style="color: #ef4444;">Отклонён</strong>
                    </span>
                </div>
                <div class="m-row">
                    <span class="m-label">Оценка:</span>
                    <span class="m-val">{{ selectedReview.rating }} из 5 ★</span>
                </div>
                <div class="m-row">
                    <span class="m-label">Дельта рейтинга:</span>
                    <span class="m-val">
                        {{ selectedReview.rating_applied ? (selectedReview.rating_delta_applied > 0 ? '+' : '') + selectedReview.rating_delta_applied : 'Не начислена' }}
                    </span>
                </div>
                <div class="m-row">
                    <span class="m-label">Автор отзыва:</span>
                    <span class="m-val">
                        {{ selectedReview.reviewer?.name }} ({{ selectedReview.reviewer?.email }})
                        <span v-if="selectedReview.reviewer?.is_review_banned" class="tag-review-banned">Review-Ban</span>
                    </span>
                </div>
                <div class="m-row">
                    <span class="m-label">Айдол:</span>
                    <span class="m-val">{{ selectedReview.idol?.name }} ({{ selectedReview.idol?.email }})</span>
                </div>
                <div class="m-row">
                    <span class="m-label">Дата отправки:</span>
                    <span class="m-val">{{ formatDate(selectedReview.created_at) }}</span>
                </div>
                <div class="m-section">
                    <div class="m-sec-title">Текст отзыва:</div>
                    <div class="m-text-box">{{ selectedReview.text || 'Текст отсутствует' }}</div>
                </div>
                <div v-if="selectedReview.rejection_reason" class="m-section">
                    <div class="m-sec-title" style="color: #ef4444;">Причина отклонения:</div>
                    <div class="m-text-box" style="border-color: rgba(239, 68, 68, 0.4);">
                        {{ selectedReview.rejection_reason }}
                    </div>
                </div>
                <div v-if="selectedReview.delete_reason" class="m-section">
                    <div class="m-sec-title" style="color: #f87171;">Причина удаления:</div>
                    <div class="m-text-box" style="border-color: rgba(248, 113, 113, 0.4);">
                        {{ selectedReview.delete_reason }}
                    </div>
                </div>
                <div v-if="selectedReview.services_snapshot?.length" class="m-section">
                    <div class="m-sec-title">Услуги в заказе:</div>
                    <div class="m-services-list">
                        <div v-for="(s, idx) in selectedReview.services_snapshot" :key="idx" class="m-service-item">
                            <span>{{ s.name }}</span>
                            <span class="text-muted">{{ s.category_name }} • {{ s.price }} ₽</span>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <button type="button" class="btn-modal-close" @click="closeDetails">Закрыть</button>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
.reviews-page {
    padding-bottom: 3rem;
}

.badge-pending-header {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.35);
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Active Filter Banner */
.active-filter-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.3);
    padding: 0.75rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.25rem;
    color: #c4b5fd;
    font-size: 0.9rem;
}
.banner-idol {
    background: rgba(236, 72, 153, 0.1);
    border-color: rgba(236, 72, 153, 0.3);
    color: #f472b6;
}
.banner-content {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.btn-clear-filter {
    background: transparent;
    border: 1px solid currentColor;
    color: inherit;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.2s;
}
.btn-clear-filter:hover {
    background: rgba(255, 255, 255, 0.1);
}

/* Tabs */
.tabs-wrap {
    display: flex;
    gap: 0.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}
.tab-btn {
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.6);
    padding: 0.75rem 1.1rem;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}
.tab-btn:hover {
    color: #fff;
}
.tab-btn--active {
    color: #fff;
    border-bottom-color: #ec4899;
    font-weight: 600;
}
.tab-count {
    background: rgba(255, 255, 255, 0.1);
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
}
.tab-btn--alert .tab-count {
    background: rgba(245, 158, 11, 0.3);
    color: #fbbf24;
    font-weight: bold;
}

/* Filter Bar */
.filter-bar {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    align-items: center;
}
.filter-search-words {
    flex: 1;
    min-width: 260px;
}
.filter-search-user {
    flex: 1;
    min-width: 240px;
}
.filter-checkbox {
    display: flex;
    align-items: center;
}
.custom-chk {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.75);
    font-size: 0.85rem;
    cursor: pointer;
}

/* Table */
.table-wrap {
    background: #18181f;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    overflow-x: auto;
    margin-bottom: 1.5rem;
}
.reviews-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    text-align: left;
}
.reviews-table th {
    padding: 0.85rem 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.5);
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.reviews-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    vertical-align: top;
}
.review-row:hover {
    background: rgba(255, 255, 255, 0.02);
}
.row--pending {
    background: rgba(245, 158, 11, 0.04);
}
.row--deleted {
    opacity: 0.65;
    background: rgba(239, 68, 68, 0.03);
}
.empty-cell {
    text-align: center;
    padding: 3rem !important;
    color: rgba(255, 255, 255, 0.4);
}

.id-tag {
    font-family: monospace;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
}

/* User block */
.user-block {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
}
.user-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    flex-shrink: 0;
}
.user-details {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.user-name-line {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.user-link {
    color: #fff;
    font-weight: 500;
    text-decoration: none;
}
.user-link:hover {
    color: #ec4899;
}
.user-sub {
    font-size: 0.775rem;
    color: rgba(255, 255, 255, 0.45);
    display: flex;
    gap: 0.5rem;
    align-items: center;
}
.user-actions-micro {
    display: flex;
    gap: 0.35rem;
    margin-top: 0.2rem;
}
.btn-micro {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.6);
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    font-size: 0.65rem;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-micro:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border-color: rgba(255, 255, 255, 0.2);
}
.btn-micro--danger {
    color: #f87171;
}
.btn-micro--danger:hover {
    background: rgba(239, 68, 68, 0.2);
}
.btn-micro--success {
    color: #4ade80;
}

.tag-review-banned {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.35);
    padding: 0.05rem 0.4rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
}

/* Rating Stars */
.rating-stars {
    display: flex;
    gap: 2px;
}
.star {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.15);
}
.star--active {
    color: #f59e0b;
}
.rating-delta-info {
    margin-top: 0.25rem;
}
.delta-badge {
    display: inline-block;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
}
.delta--pos {
    background: rgba(74, 222, 128, 0.15);
    color: #4ade80;
}
.delta--neg {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}
.delta--none {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.4);
}
.epithets-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    margin-top: 0.35rem;
}
.ep-tag {
    background: rgba(236, 72, 153, 0.12);
    color: #f472b6;
    border: 1px solid rgba(236, 72, 153, 0.25);
    font-size: 0.7rem;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

/* Text */
.review-text-single-line {
    margin: 0;
    line-height: 1.4;
    color: rgba(255, 255, 255, 0.9);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 300px;
}

/* Status Pill */
.status-stack {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.status-pill {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}
.pill--pending {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
.pill--approved {
    background: rgba(74, 222, 128, 0.15);
    color: #4ade80;
    border: 1px solid rgba(74, 222, 128, 0.3);
}
.pill--rejected {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.pill--deleted {
    background: rgba(148, 163, 184, 0.15);
    color: #cbd5e1;
    border: 1px solid rgba(148, 163, 184, 0.3);
}
.pill--hidden {
    background: rgba(168, 85, 247, 0.15);
    color: #c084fc;
    border: 1px solid rgba(168, 85, 247, 0.3);
}

/* Date */
.date-text {
    color: rgba(255, 255, 255, 0.65);
    font-size: 0.8rem;
}
.meta-micro {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.35);
    margin-top: 0.25rem;
}

/* Actions */
.action-buttons-wrap {
    display: flex;
    gap: 0.25rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}
.btn-act {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s;
}
.btn-act--approve {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border-color: rgba(16, 185, 129, 0.2);
}
.btn-act--approve:hover {
    background: rgba(16, 185, 129, 0.2);
}
.btn-act--reject {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.2);
}
.btn-act--reject:hover {
    background: rgba(239, 68, 68, 0.2);
}
.btn-act--delete {
    background: rgba(244, 63, 94, 0.1);
    color: #f43f5e;
    border-color: rgba(244, 63, 94, 0.2);
}
.btn-act--delete:hover {
    background: rgba(244, 63, 94, 0.2);
}
.btn-act--restore {
    background: rgba(14, 165, 233, 0.1);
    color: #0ea5e9;
    border-color: rgba(14, 165, 233, 0.2);
}
.btn-act--restore:hover {
    background: rgba(14, 165, 233, 0.2);
}
.btn-act--details {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.6);
    border-color: rgba(255, 255, 255, 0.1);
}
.btn-act--details:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

/* Modal details */
.modal-body {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    font-size: 0.9rem;
}
.m-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding-bottom: 0.4rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}
.m-label {
    color: rgba(255, 255, 255, 0.45);
}
.m-val {
    color: #fff;
    text-align: right;
}
.m-section {
    margin-top: 0.5rem;
}
.m-sec-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
    margin-bottom: 0.35rem;
    text-transform: uppercase;
}
.m-text-box {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    padding: 0.75rem;
    line-height: 1.4;
    color: #fff;
}
.m-services-list {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.m-service-item {
    display: flex;
    justify-content: space-between;
    background: rgba(255, 255, 255, 0.03);
    padding: 0.4rem 0.6rem;
    border-radius: 4px;
    font-size: 0.85rem;
}
.btn-modal-close {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: 6px;
    cursor: pointer;
}
.btn-modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
}
</style>
