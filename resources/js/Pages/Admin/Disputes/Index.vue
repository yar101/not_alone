<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    disputes:      Object,
    status_filter: String,
    counts:        Object,
});

const selected    = ref(null);
const decision    = ref('');
const adminNote   = ref('');
const submitting  = ref(false);

// Chat panel
const chatMessages = ref([]);
const chatLoading  = ref(false);
const chatHasMore  = ref(false);
const chatOpen     = ref(false);
const chatConvId   = ref(null);

function openModal(dispute) {
    selected.value     = dispute;
    decision.value     = '';
    adminNote.value    = '';
    chatOpen.value     = false;
    chatMessages.value = [];
    chatConvId.value   = null;
}

function closeModal() {
    selected.value = null;
    chatOpen.value = false;
    chatMessages.value = [];
}

function setFilter(val) {
    router.get(route('admin.disputes.index'), { status: val }, { preserveState: false });
}

function submitResolve() {
    if (!decision.value || submitting.value) return;
    submitting.value = true;
    router.patch(
        route('admin.disputes.resolve', selected.value.id),
        { decision: decision.value, admin_note: adminNote.value },
        {
            onFinish:  () => { submitting.value = false; },
            onSuccess: () => { closeModal(); },
        }
    );
}

async function openChat(conversationId) {
    if (chatLoading.value) return;
    chatConvId.value   = conversationId;
    chatOpen.value     = true;
    chatMessages.value = [];
    chatHasMore.value  = false;
    chatLoading.value  = true;
    try {
        const res = await axios.get(route('admin.conversations.messages', conversationId));
        chatMessages.value = res.data.messages;
        chatHasMore.value  = res.data.has_more;
    } finally {
        chatLoading.value = false;
    }
}

async function loadMore() {
    if (chatLoading.value || !chatHasMore.value) return;
    chatLoading.value = true;
    const firstId = chatMessages.value[0]?.id;
    try {
        const res = await axios.get(route('admin.conversations.messages', chatConvId.value), {
            params: { before_id: firstId },
        });
        chatMessages.value = [...res.data.messages, ...chatMessages.value];
        chatHasMore.value  = res.data.has_more;
    } finally {
        chatLoading.value = false;
    }
}

function isSystem(msg) {
    return !msg.sender_id || (msg.type && msg.type !== 'user');
}

// Сообщение от заказчика → выравниваем вправо
function isCustomer(msg) {
    return selected.value && msg.sender_id === selected.value.order.customer.id;
}

function fmtTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleString('ru-RU', {
        day: '2-digit', month: '2-digit', year: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function truncate(str, len = 80) {
    if (!str) return '';
    return str.length > len ? str.slice(0, len) + '…' : str;
}

const STATUS_LABELS = { open: 'Открыт', approved: 'Удовлетворён', rejected: 'Отклонён' };
const STATUS_CLASS  = { open: 'badge--open', approved: 'badge--approved', rejected: 'badge--rejected' };

function systemLabel(msg) {
    const event = msg.metadata?.event ?? msg.type;
    const m     = msg.metadata ?? {};
    switch (event) {
        case 'order_accepted':
            return `Айдол готов выполнить заказ`;
        case 'order_paid':
            return m.by_admin ? 'Оплата подтверждена администратором' : 'Заказ оплачен заказчиком';
        case 'order_cancelled':
            if (m.by_admin) return `Заказ отменён администратором` + (m.cancel_reason ? `: ${m.cancel_reason}` : '');
            return `Заказ отменён` + (m.cancel_reason ? `: ${m.cancel_reason}` : '');
        case 'order_completed':
            return m.by_admin ? 'Заказ завершён администратором' : 'Заказ завершён';
        case 'order_auto_completed':
            return 'Заказ завершён автоматически (72ч без ответа)';
        case 'order_disputed':
            return 'Открыт спор';
        case 'order_refunded':
            return 'Средства возвращены заказчику';
        default:
            return event ?? 'Системное событие';
    }
}
</script>

<template>
    <div class="page-wrap">
        <div class="page-header">
            <h1 class="page-title">Споры</h1>
            <span class="page-count">{{ disputes.total }} всего</span>
        </div>

        <!-- Фильтр по статусу -->
        <div class="filter-tabs">
            <button
                v-for="tab in [
                    { key: 'open',     label: 'Открытые',        count: counts.open },
                    { key: 'approved', label: 'Удовлетворённые', count: counts.approved },
                    { key: 'rejected', label: 'Отклонённые',     count: counts.rejected },
                    { key: 'all',      label: 'Все',             count: disputes.total },
                ]"
                :key="tab.key"
                class="filter-tab"
                :class="{ 'filter-tab--active': status_filter === tab.key }"
                @click="setFilter(tab.key)"
            >
                {{ tab.label }}
                <span class="filter-tab__count">{{ tab.count }}</span>
            </button>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Заказчик</th>
                        <th>Айдол</th>
                        <th>Причина</th>
                        <th>Статус</th>
                        <th>Сумма</th>
                        <th>Подан</th>
                        <th>Решение</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="d in disputes.data"
                        :key="d.id"
                        class="data-row"
                        @click="openModal(d)"
                    >
                        <td class="cell-id">{{ d.order_id }}</td>
                        <td class="cell-user">
                            <img v-if="d.order.customer.avatar" :src="d.order.customer.avatar" class="user-avatar" />
                            <span>{{ d.order.customer.name }}</span>
                        </td>
                        <td class="cell-user">
                            <img v-if="d.order.idol.avatar" :src="d.order.idol.avatar" class="user-avatar" />
                            <span>{{ d.order.idol.name }}</span>
                        </td>
                        <td class="cell-reason">{{ d.reason }}</td>
                        <td>
                            <span class="badge" :class="STATUS_CLASS[d.status]">
                                {{ STATUS_LABELS[d.status] }}
                            </span>
                        </td>
                        <td class="cell-amount">{{ d.order.total }} ₽</td>
                        <td class="cell-date">{{ d.created_at }}</td>
                        <td class="cell-date">{{ d.resolved_at ?? '—' }}</td>
                    </tr>
                    <tr v-if="disputes.data.length === 0">
                        <td colspan="8" class="cell-empty">Споров нет</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="disputes.last_page > 1" class="pagination">
            <a
                v-for="page in disputes.last_page"
                :key="page"
                :href="`?page=${page}&status=${status_filter}`"
                class="page-link"
                :class="{ 'page-link--active': page === disputes.current_page }"
            >{{ page }}</a>
        </div>
    </div>

    <!-- Оверлей -->
    <div v-if="selected" class="modal-overlay" @click.self="closeModal">
        <div class="modal-wrap" :class="{ 'modal-wrap--with-chat': chatOpen }">

            <!-- Модалка спора -->
            <div class="modal-box">
                <button class="modal-close" @click="closeModal">×</button>
                <h2 class="modal-title">
                    Спор по заказу #{{ selected.order_id }}
                    <span class="badge" :class="STATUS_CLASS[selected.status]" style="margin-left:0.5rem;font-size:0.8rem;">
                        {{ STATUS_LABELS[selected.status] }}
                    </span>
                </h2>

                <div class="modal-grid">
                    <div class="modal-section">
                        <div class="section-label">Заказчик</div>
                        <div class="user-row">
                            <img v-if="selected.order.customer.avatar" :src="selected.order.customer.avatar" class="user-avatar" />
                            <span>{{ selected.order.customer.name }}</span>
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="section-label">Айдол</div>
                        <div class="user-row">
                            <img v-if="selected.order.idol.avatar" :src="selected.order.idol.avatar" class="user-avatar" />
                            <span>{{ selected.order.idol.name }}</span>
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="section-label">Статус заказа</div>
                        <span>{{ selected.order.status_label }}</span>
                    </div>
                    <div class="modal-section">
                        <div class="section-label">Сумма</div>
                        <span>{{ selected.order.total }} ₽</span>
                    </div>
                    <div class="modal-section">
                        <div class="section-label">Оплачен</div>
                        <span>{{ selected.order.paid_at ?? '—' }}</span>
                    </div>
                    <div class="modal-section">
                        <div class="section-label">Завершён</div>
                        <span>{{ selected.order.completed_at ?? '—' }}</span>
                    </div>
                    <div class="modal-section modal-section--full">
                        <div class="section-label">Причина</div>
                        <span>{{ selected.reason }}</span>
                    </div>
                    <div class="modal-section modal-section--full">
                        <div class="section-label">Детали</div>
                        <p class="details-text">{{ selected.details }}</p>
                    </div>

                    <div v-if="selected.status !== 'open'" class="modal-section modal-section--full">
                        <div class="section-label">Заметка администратора</div>
                        <p class="details-text">{{ selected.admin_note ?? '—' }}</p>
                    </div>

                    <div v-if="selected.order.conversation_id" class="modal-section modal-section--full">
                        <button
                            class="chat-toggle-btn"
                            :class="{ 'chat-toggle-btn--active': chatOpen }"
                            @click="chatOpen ? (chatOpen = false) : openChat(selected.order.conversation_id)"
                        >
                            {{ chatOpen ? '← Скрыть чат' : 'История чата →' }}
                        </button>
                    </div>

                    <template v-if="selected.status === 'open'">
                        <div class="modal-section modal-section--full resolve-section">
                            <div class="section-label">Решение</div>
                            <div class="decision-btns">
                                <button
                                    class="decision-btn decision-btn--approve"
                                    :class="{ 'decision-btn--active': decision === 'approved' }"
                                    @click="decision = 'approved'"
                                >Удовлетворить → Аннулирован</button>
                                <button
                                    class="decision-btn decision-btn--reject"
                                    :class="{ 'decision-btn--active': decision === 'rejected' }"
                                    @click="decision = 'rejected'"
                                >Отклонить → Выполнен</button>
                            </div>

                            <div class="section-label" style="margin-top:0.75rem;">Заметка (необязательно)</div>
                            <textarea
                                v-model="adminNote"
                                class="resolve-textarea"
                                placeholder="Комментарий к решению…"
                                rows="3"
                                maxlength="2000"
                            ></textarea>

                            <div class="resolve-footer">
                                <button class="resolve-cancel" @click="closeModal">Отмена</button>
                                <button
                                    class="resolve-submit"
                                    :disabled="!decision || submitting"
                                    @click="submitResolve"
                                >{{ submitting ? 'Сохраняем…' : 'Сохранить решение' }}</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Панель истории чата -->
            <div v-if="chatOpen" class="chat-panel">
                <div class="chat-panel__header">
                    <div class="chat-panel__header-inner">
                        <span class="chat-panel__title">История чата</span>
                        <div class="chat-legend">
                            <div class="chat-legend__user">
                                <span class="chat-legend__dot chat-legend__dot--idol"></span>
                                <span class="chat-legend__role">Айдол</span>
                                <span class="chat-legend__name chat-legend__name--idol">{{ selected.order.idol.name }}</span>
                            </div>
                            <span class="chat-legend__sep">·</span>
                            <div class="chat-legend__user">
                                <span class="chat-legend__dot chat-legend__dot--customer"></span>
                                <span class="chat-legend__role">Заказчик</span>
                                <span class="chat-legend__name chat-legend__name--customer">{{ selected.order.customer.name }}</span>
                            </div>
                        </div>
                    </div>
                    <button class="chat-panel__close" @click="chatOpen = false">×</button>
                </div>

                <div class="chat-panel__body">
                    <div v-if="chatLoading && chatMessages.length === 0" class="chat-empty">Загрузка…</div>

                    <button v-if="chatHasMore && !chatLoading" class="load-more-btn" @click="loadMore">
                        ↑ Загрузить раньше
                    </button>
                    <div v-if="chatLoading && chatMessages.length > 0" class="chat-empty chat-empty--sm">Загрузка…</div>

                    <template v-for="msg in chatMessages" :key="msg.id">

                        <!-- Системное событие -->
                        <div v-if="isSystem(msg)" class="sys-event">
                            <div class="sys-event__line"></div>
                            <div class="sys-event__content">
                                <span class="sys-event__label">{{ systemLabel(msg) }}</span>
                                <span class="sys-event__time">{{ fmtTime(msg.created_at) }}</span>
                            </div>
                            <div class="sys-event__line"></div>
                        </div>

                        <!-- Сообщение пользователя -->
                        <div
                            v-else
                            class="chat-bubble-wrap"
                            :class="isCustomer(msg) ? 'chat-bubble-wrap--right' : 'chat-bubble-wrap--left'"
                        >
                            <!-- Аватар слева для айдола -->
                            <div v-if="!isCustomer(msg)" class="bubble-avatar">
                                <img v-if="msg.sender_avatar" :src="msg.sender_avatar" class="bubble-avatar__img" />
                                <span v-else class="bubble-avatar__initials">{{ msg.sender_name?.charAt(0)?.toUpperCase() ?? '?' }}</span>
                            </div>

                            <div class="bubble-body">
                                <div class="bubble-meta" :class="isCustomer(msg) ? 'bubble-meta--right' : ''">
                                    <span class="bubble-meta__name" :class="isCustomer(msg) ? 'bubble-meta__name--customer' : 'bubble-meta__name--idol'">
                                        {{ msg.sender_name }}
                                    </span>
                                    <span class="bubble-meta__time">{{ fmtTime(msg.created_at) }}</span>
                                </div>
                                <div class="bubble" :class="isCustomer(msg) ? 'bubble--customer' : 'bubble--idol'">
                                    {{ msg.body }}
                                </div>
                            </div>

                            <!-- Аватар справа для заказчика -->
                            <div v-if="isCustomer(msg)" class="bubble-avatar">
                                <img v-if="msg.sender_avatar" :src="msg.sender_avatar" class="bubble-avatar__img" />
                                <span v-else class="bubble-avatar__initials bubble-avatar__initials--customer">{{ msg.sender_name?.charAt(0)?.toUpperCase() ?? '?' }}</span>
                            </div>
                        </div>

                    </template>

                    <div v-if="!chatLoading && chatMessages.length === 0" class="chat-empty">
                        Сообщений нет
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
.page-wrap { padding: 2rem; }

.page-header { display: flex; align-items: baseline; gap: 1rem; margin-bottom: 1.25rem; }
.page-title { font-size: 1.35rem; font-weight: 700; color: rgba(255,255,255,0.9); margin: 0; }
.page-count { color: rgba(255,255,255,0.3); font-size: 0.88rem; }

/* Filter tabs */
.filter-tabs { display: flex; gap: 0; margin-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.08); }
.filter-tab {
    display: flex; align-items: center; gap: 0.4rem;
    padding: 0.45rem 1rem;
    background: none; border: none; border-bottom: 2px solid transparent;
    color: rgba(255,255,255,0.4); font-size: 0.84rem; cursor: pointer;
    font-family: inherit; margin-bottom: -1px; transition: color 0.15s;
}
.filter-tab:hover { color: rgba(255,255,255,0.7); }
.filter-tab--active { color: #9B6EE8; border-bottom-color: #9B6EE8; }
.filter-tab__count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 18px; height: 18px; padding: 0 0.3rem;
    background: rgba(255,255,255,0.07); border-radius: 99px;
    font-size: 0.7rem; color: rgba(255,255,255,0.45);
}

/* Badge */
.badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.72rem; font-weight: 600; }
.badge--open     { background: rgba(251,191,36,0.12);  color: rgba(251,191,36,0.9); }
.badge--approved { background: rgba(74,222,128,0.1);   color: rgba(74,222,128,0.85); }
.badge--rejected { background: rgba(239,68,68,0.1);    color: rgba(239,68,68,0.8); }

/* Table */
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
.data-table th {
    padding: 0.55rem 0.75rem; text-align: left;
    font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em;
    color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.07);
    white-space: nowrap;
}
.data-row { cursor: pointer; }
.data-row:hover td { background: rgba(255,255,255,0.02); }
.data-row td {
    padding: 0.65rem 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: middle; color: rgba(255,255,255,0.75);
}
.cell-id     { color: rgba(255,255,255,0.3); font-size: 0.78rem; white-space: nowrap; }
.cell-user   { display: flex; align-items: center; gap: 0.5rem; }
.user-avatar { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; }
.cell-reason { max-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: rgba(255,255,255,0.6); }
.cell-amount { font-weight: 600; white-space: nowrap; color: rgba(255,255,255,0.85); }
.cell-date   { white-space: nowrap; color: rgba(255,255,255,0.3); font-size: 0.78rem; }
.cell-empty  { text-align: center; color: rgba(255,255,255,0.2); padding: 2rem; font-size: 0.88rem; }

.pagination { display: flex; gap: 0.25rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link { padding: 0.28rem 0.6rem; border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); font-size: 0.8rem; text-decoration: none; }
.page-link--active { border-color: rgba(155,110,232,0.6); color: #9B6EE8; background: rgba(155,110,232,0.1); }

/* ── Overlay ─────────────────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.75);
    z-index: 1000;
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
}

.modal-wrap {
    display: flex; align-items: flex-start; gap: 0.75rem;
    max-height: 90vh;
    width: 100%;
    max-width: 620px;
    transition: max-width 0.2s;
}
.modal-wrap--with-chat { max-width: 1140px; }

/* ── Modal box ───────────────────────────────────────────── */
.modal-box {
    background: #0f0f1a;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    padding: 1.5rem;
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    flex-shrink: 0;
}
.modal-wrap--with-chat .modal-box { max-width: 500px; }

.modal-close {
    position: absolute; top: 0.9rem; right: 1rem;
    background: none; border: none; font-size: 1.3rem;
    cursor: pointer; color: rgba(255,255,255,0.3); line-height: 1;
}
.modal-close:hover { color: rgba(255,255,255,0.7); }

.modal-title {
    font-size: 1rem; font-weight: 600; margin: 0 0 1.25rem;
    display: flex; align-items: center; flex-wrap: wrap;
    color: rgba(255,255,255,0.9);
}

.modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.modal-section { display: flex; flex-direction: column; gap: 0.25rem; }
.modal-section--full { grid-column: 1 / -1; }

.section-label {
    font-size: 0.72rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.07em; color: rgba(255,255,255,0.25);
}
.modal-section > span,
.modal-section > p { color: rgba(255,255,255,0.75); font-size: 0.88rem; }

.user-row { display: flex; align-items: center; gap: 0.5rem; color: rgba(255,255,255,0.8); font-size: 0.88rem; }
.details-text { margin: 0; white-space: pre-wrap; color: rgba(255,255,255,0.65); line-height: 1.5; font-size: 0.88rem; }

.role-tag {
    font-size: 0.65rem; font-weight: 600; padding: 0.1rem 0.45rem;
    border-radius: 99px; text-transform: uppercase; letter-spacing: 0.06em;
}
.role-tag--customer { background: rgba(99,179,237,0.12); color: rgba(99,179,237,0.8); }
.role-tag--idol     { background: rgba(155,110,232,0.12); color: rgba(155,110,232,0.8); }

/* Chat toggle */
.chat-toggle-btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.4rem 1rem;
    border: 1px solid rgba(155,110,232,0.4); border-radius: 4px;
    background: transparent; color: rgba(155,110,232,0.85);
    font-size: 0.82rem; font-family: inherit; cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}
.chat-toggle-btn:hover,
.chat-toggle-btn--active { background: rgba(155,110,232,0.1); border-color: rgba(155,110,232,0.7); }

/* Resolve section */
.resolve-section { border-top: 1px solid rgba(255,255,255,0.07); padding-top: 1rem; margin-top: 0.25rem; }
.decision-btns { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.4rem; }
.decision-btn {
    padding: 0.45rem 1rem; border-radius: 3px; border: 1px solid transparent;
    font-size: 0.82rem; font-weight: 600; cursor: pointer; font-family: inherit;
    transition: all 0.15s; background: transparent;
}
.decision-btn--approve { border-color: rgba(74,222,128,0.3); color: rgba(74,222,128,0.8); }
.decision-btn--approve:hover,
.decision-btn--approve.decision-btn--active { background: rgba(74,222,128,0.1); border-color: rgba(74,222,128,0.6); color: rgba(74,222,128,1); }
.decision-btn--reject { border-color: rgba(239,68,68,0.3); color: rgba(239,68,68,0.7); }
.decision-btn--reject:hover,
.decision-btn--reject.decision-btn--active { background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.55); color: rgba(239,68,68,0.9); }

.resolve-textarea {
    width: 100%; margin-top: 0.4rem; padding: 0.45rem 0.7rem;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px; color: rgba(255,255,255,0.85); font-size: 0.85rem;
    font-family: inherit; resize: vertical; outline: none; box-sizing: border-box;
}
.resolve-textarea:focus { border-color: rgba(190,145,255,0.45); }
.resolve-textarea::placeholder { color: rgba(255,255,255,0.2); }

.resolve-footer { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.75rem; }
.resolve-cancel {
    padding: 0.45rem 0.9rem; border: 1px solid rgba(255,255,255,0.12);
    border-radius: 3px; background: transparent; color: rgba(255,255,255,0.4);
    font-family: inherit; font-size: 0.82rem; cursor: pointer;
}
.resolve-cancel:hover { background: rgba(255,255,255,0.04); }
.resolve-submit {
    padding: 0.45rem 1rem; border: 1px solid rgba(190,145,255,0.45);
    border-radius: 3px; background: rgba(190,145,255,0.1);
    color: rgba(255,255,255,0.9); font-family: inherit; font-size: 0.82rem;
    font-weight: 600; cursor: pointer; transition: background 0.15s;
}
.resolve-submit:hover:not(:disabled) { background: rgba(190,145,255,0.18); }
.resolve-submit:disabled { opacity: 0.4; cursor: default; }

/* ── Chat panel ──────────────────────────────────────────── */
.chat-panel {
    display: flex; flex-direction: column;
    width: 560px; flex-shrink: 0;
    max-height: 90vh;
    background: #0f0f1a;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    overflow: hidden;
}

.chat-panel__header {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    flex-shrink: 0;
    gap: 0.5rem;
}
.chat-panel__header-inner { display: flex; flex-direction: column; gap: 0.3rem; }
.chat-panel__title { font-size: 0.88rem; font-weight: 600; color: rgba(255,255,255,0.75); }
.chat-panel__close {
    background: none; border: none; font-size: 1.1rem;
    cursor: pointer; color: rgba(255,255,255,0.3);
    line-height: 1; padding: 0; flex-shrink: 0;
}
.chat-panel__close:hover { color: rgba(255,255,255,0.7); }

/* Legend */
.chat-legend {
    display: flex; align-items: center; gap: 0.6rem;
    margin-top: 0.35rem;
}
.chat-legend__user { display: flex; align-items: center; gap: 0.3rem; }
.chat-legend__dot {
    width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
}
.chat-legend__dot--idol     { background: rgba(155,110,232,0.7); }
.chat-legend__dot--customer { background: rgba(99,179,237,0.7); }
.chat-legend__role {
    font-size: 0.65rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.06em; color: rgba(255,255,255,0.25);
}
.chat-legend__name { font-size: 0.72rem; font-weight: 500; }
.chat-legend__name--idol     { color: rgba(155,110,232,0.85); }
.chat-legend__name--customer { color: rgba(99,179,237,0.85); }
.chat-legend__sep { color: rgba(255,255,255,0.15); font-size: 0.8rem; }

/* Messages area */
.chat-panel__body {
    flex: 1; overflow-y: auto;
    padding: 0.75rem 1rem;
    display: flex; flex-direction: column; gap: 0.35rem;
}

.chat-empty { text-align: center; color: rgba(255,255,255,0.2); font-size: 0.82rem; padding: 1rem 0; }
.chat-empty--sm { padding: 0.25rem 0 0.5rem; }

.load-more-btn {
    display: block; width: 100%;
    padding: 0.3rem;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07); border-radius: 3px;
    color: rgba(255,255,255,0.3); font-size: 0.75rem; font-family: inherit;
    cursor: pointer; margin-bottom: 0.5rem; transition: background 0.15s;
}
.load-more-btn:hover { background: rgba(255,255,255,0.06); }

/* ── System event ────────────────────────────────────────── */
.sys-event {
    display: flex; align-items: center; gap: 0.6rem;
    margin: 0.5rem 0;
}
.sys-event__line {
    flex: 1; height: 1px;
    background: rgba(255,255,255,0.06);
}
.sys-event__content {
    display: flex; flex-direction: column; align-items: center; gap: 0.15rem;
    flex-shrink: 0;
}
.sys-event__label {
    font-size: 0.72rem; font-weight: 600;
    color: rgba(155,110,232,0.75);
    white-space: nowrap;
}
.sys-event__time {
    font-size: 0.65rem;
    color: rgba(255,255,255,0.2);
    white-space: nowrap;
}

/* ── Chat bubbles ────────────────────────────────────────── */
.chat-bubble-wrap {
    display: flex; align-items: flex-end; gap: 0.5rem;
    max-width: 88%;
}
.chat-bubble-wrap--left  { align-self: flex-start; flex-direction: row; }
.chat-bubble-wrap--right { align-self: flex-end;   flex-direction: row-reverse; }

.bubble-avatar {
    width: 28px; height: 28px; border-radius: 50%;
    overflow: hidden; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(155,110,232,0.15);
}
.bubble-avatar__img { width: 100%; height: 100%; object-fit: cover; }
.bubble-avatar__initials {
    font-size: 0.7rem; font-weight: 700;
    color: rgba(155,110,232,0.9);
}
.bubble-avatar__initials--customer { color: rgba(99,179,237,0.9); }

.bubble-body { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; }

.bubble-meta { display: flex; align-items: baseline; gap: 0.45rem; }
.bubble-meta--right { flex-direction: row-reverse; }
.bubble-meta__name { font-size: 0.72rem; font-weight: 600; white-space: nowrap; }
.bubble-meta__name--idol     { color: rgba(155,110,232,0.7); }
.bubble-meta__name--customer { color: rgba(99,179,237,0.7); }
.bubble-meta__time { font-size: 0.65rem; color: rgba(255,255,255,0.2); white-space: nowrap; }

.bubble {
    padding: 0.5rem 0.75rem;
    border-radius: 10px;
    font-size: 0.85rem;
    line-height: 1.45;
    color: rgba(255,255,255,0.85);
    word-break: break-word;
    white-space: pre-wrap;
}
.bubble--idol {
    background: rgba(155,110,232,0.12);
    border: 1px solid rgba(155,110,232,0.18);
    border-bottom-left-radius: 3px;
}
.bubble--customer {
    background: rgba(99,179,237,0.1);
    border: 1px solid rgba(99,179,237,0.18);
    border-bottom-right-radius: 3px;
}
</style>
