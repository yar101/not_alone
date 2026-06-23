<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    conversations: Object, // Paginated conversations list
    filters:       Object, // Search/filter fields
});

// Search & Filter fields reactive state
const filterId = ref(props.filters.id || '');
const filterOrderId = ref(props.filters.order_id || '');
const filterType = ref(props.filters.type || 'all');
const filterUserSearch = ref(props.filters.user_search || '');

function submitFilter() {
    router.get(route('admin.conversations.index'), {
        id: filterId.value,
        order_id: filterOrderId.value,
        type: filterType.value === 'all' ? '' : filterType.value,
        user_search: filterUserSearch.value,
    }, {
        preserveState: true,
        replace: true,
    });
}

function resetFilter() {
    filterId.value = '';
    filterOrderId.value = '';
    filterType.value = 'all';
    filterUserSearch.value = '';
    submitFilter();
}

// Active conversation state
const activeConversation = ref(null);
const messages = ref([]);
const messagesLoading = ref(false);
const hasMoreMessages = ref(false);
const messagesContainer = ref(null);

async function selectConversation(conv) {
    activeConversation.value = conv;
    messages.value = [];
    hasMoreMessages.value = false;
    messagesLoading.value = true;

    try {
        const res = await axios.get(route('admin.conversations.messages', conv.id));
        messages.value = res.data.messages;
        hasMoreMessages.value = res.data.has_more;
        scrollToBottom();
    } catch (e) {
        console.error('Ошибка при загрузке сообщений:', e);
    } finally {
        messagesLoading.value = false;
    }
}

async function loadMoreMessages() {
    if (messagesLoading.value || !hasMoreMessages.value || !activeConversation.value) return;

    messagesLoading.value = true;
    const firstMsgId = messages.value[0]?.id;

    try {
        const res = await axios.get(route('admin.conversations.messages', activeConversation.value.id), {
            params: { before_id: firstMsgId },
        });

        // Store scroll position before prepending messages
        const container = messagesContainer.value;
        const previousScrollHeight = container ? container.scrollHeight : 0;

        messages.value = [...res.data.messages, ...messages.value];
        hasMoreMessages.value = res.data.has_more;

        await nextTick();

        // Adjust scroll position after messages are rendered to keep place
        if (container) {
            container.scrollTop = container.scrollHeight - previousScrollHeight;
        }
    } catch (e) {
        console.error('Ошибка при загрузке более старых сообщений:', e);
    } finally {
        messagesLoading.value = false;
    }
}

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
}

function formatTime(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

function formatDate(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function truncate(str, len = 60) {
    if (!str) return '';
    return str.length > len ? str.slice(0, len) + '...' : str;
}

function getInitials(name) {
    if (!name) return '?';
    return name.slice(0, 2).toUpperCase();
}

function isSystemMessage(msg) {
    return !msg.sender_id || (msg.type && msg.type !== 'user' && msg.type !== 'image');
}

function getSystemText(msg) {
    const event = msg.metadata?.event ?? msg.type;
    const meta = msg.metadata ?? {};
    switch (event) {
        case 'order_created':
            return 'Оформлен новый заказ';
        case 'item_added':
            return 'В заказ добавлены новые услуги';
        case 'order_accepted':
            return 'Айдол принял предложение и готов выполнить заказ';
        case 'order_paid':
            return meta.by_admin ? 'Оплата заказа подтверждена администратором' : 'Заказ оплачен заказчиком';
        case 'order_cancelled':
            return 'Заказ отменён' + (meta.cancel_reason ? `: ${meta.cancel_reason}` : '');
        case 'order_completed':
            return 'Заказ завершён и закрыт';
        case 'order_auto_completed':
            return 'Заказ завершён автоматически по истечении времени';
        case 'completion_confirmed_by_idol':
            return `Айдол ${meta.actor_name || ''} подтвердил выполнение заказа`;
        case 'completion_confirmed_by_customer':
            return `Клиент ${meta.actor_name || ''} подтвердил выполнение заказа`;
        case 'review_submitted':
            return 'Клиент оставил отзыв о заказе';
        case 'order_disputed':
            return 'Был открыт спор по заказу';
        case 'chat_closed':
            return 'Чат закрыт';
        case 'chat_opened':
            return 'Чат открыт';
        default:
            return msg.body || 'Системное событие';
    }
}
</script>

<template>
    <div class="chats-page">
        <div class="chats-header">
            <h1 class="page-title">Просмотр переписок</h1>
            <span class="chats-total">{{ conversations.total }} переписок всего</span>
        </div>

        <div class="chats-layout">
            <!-- ─── Left column: Filters and Conversation List ─── -->
            <div class="chats-sidebar">
                <!-- Filters panel -->
                <div class="filter-card">
                    <div class="filter-row">
                        <div class="filter-field">
                            <label class="filter-label">Участник (Имя/Email/ID)</label>
                            <input v-model="filterUserSearch" type="text" class="filter-input" placeholder="Имя, email или ID..." @keyup.enter="submitFilter" />
                        </div>
                    </div>
                    <div class="filter-row filter-row--two">
                        <div class="filter-field">
                            <label class="filter-label">ID диалога</label>
                            <input v-model="filterId" type="text" class="filter-input" placeholder="ID..." @keyup.enter="submitFilter" />
                        </div>
                        <div class="filter-field">
                            <label class="filter-label">ID заказа</label>
                            <input v-model="filterOrderId" type="text" class="filter-input" placeholder="ID..." @keyup.enter="submitFilter" />
                        </div>
                    </div>
                    <div class="filter-row">
                        <div class="filter-field">
                            <label class="filter-label">Тип чата</label>
                            <select v-model="filterType" class="filter-select">
                                <option value="all">Все переписки</option>
                                <option value="direct">Прямые чаты (Личные)</option>
                                <option value="order">Чаты заказов</option>
                                <option value="support">Служба поддержки</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button @click="submitFilter" class="btn-filter btn-filter--apply">Применить</button>
                        <button @click="resetFilter" class="btn-filter btn-filter--reset">Сбросить</button>
                    </div>
                </div>

                <!-- Conversation list -->
                <div class="conv-list-container">
                    <div v-if="conversations.data.length === 0" class="conv-list-empty">
                        Переписки не найдены
                    </div>
                    <div v-else class="conv-list">
                        <div
                            v-for="conv in conversations.data"
                            :key="conv.id"
                            class="conv-item"
                            :class="{ 'conv-item--active': activeConversation && activeConversation.id === conv.id }"
                            @click="selectConversation(conv)"
                        >
                            <div class="conv-item-avatars">
                                <div v-for="user in conv.users.slice(0, 2)" :key="user.id" class="conv-avatar" :class="{ 'conv-avatar--idol': user.is_idol }">
                                    <img v-if="user.avatar_url" :src="user.avatar_url" alt="" />
                                    <span v-else>{{ getInitials(user.name) }}</span>
                                </div>
                            </div>
                            <div class="conv-item-info">
                                <div class="conv-item-header">
                                    <span class="conv-item-names">
                                        {{ conv.users.map(u => u.name || u.email).join(', ') }}
                                    </span>
                                    <span class="conv-item-time" v-if="conv.last_message">
                                        {{ formatTime(conv.last_message.created_at) }}
                                    </span>
                                </div>
                                <div class="conv-item-preview">
                                    <span v-if="conv.last_message" class="preview-text">
                                        {{ truncate(conv.last_message.body) }}
                                    </span>
                                    <span v-else class="preview-empty">Нет сообщений</span>
                                </div>
                                <div class="conv-item-tags">
                                    <span class="badge badge--id">ID: {{ conv.id }}</span>
                                    <span v-if="conv.order_id" class="badge badge--order">Заказ #{{ conv.order_id }}</span>
                                    <span v-else-if="conv.is_support" class="badge badge--support">Поддержка</span>
                                    <span v-else class="badge badge--direct">Прямой</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination" v-if="conversations.links && conversations.links.length > 3">
                        <Link
                            v-for="link in conversations.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            v-html="link.label"
                            class="pagination-link"
                            :class="{ 'pagination-link--active': link.active, 'pagination-link--disabled': !link.url }"
                            :disabled="!link.url"
                        />
                    </div>
                </div>
            </div>

            <!-- ─── Right column: Conversation Reader ─── -->
            <div class="chats-viewer">
                <div v-if="!activeConversation" class="viewer-empty">
                    <svg viewBox="0 0 24 24" fill="none" class="empty-icon" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 13.8214 2.48697 15.5291 3.33782 17L2 22L7 20.6622C8.47093 21.513 10.1786 22 12 22Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <p class="empty-title">Выберите диалог</p>
                    <p class="empty-subtitle">Выберите переписку из списка слева для просмотра истории сообщений</p>
                </div>
                <div v-else class="viewer-active">
                    <!-- Header -->
                    <div class="viewer-header">
                        <div class="viewer-header-title">
                            <span class="viewer-conv-id">Диалог #{{ activeConversation.id }}</span>
                            <span v-if="activeConversation.order_id" class="badge badge--order">Заказ #{{ activeConversation.order_id }}</span>
                            <span v-else-if="activeConversation.is_support" class="badge badge--support">Служба поддержки</span>
                            <span v-else class="badge badge--direct">Прямой чат</span>
                        </div>
                        <div class="viewer-participants">
                            <span class="participants-label">Участники:</span>
                            <div class="participants-list">
                                <Link
                                    v-for="user in activeConversation.users"
                                    :key="user.id"
                                    :href="route('admin.users.show', user.id)"
                                    class="participant-link"
                                    :class="{ 'participant-link--idol': user.is_idol }"
                                >
                                    {{ user.name }} ({{ user.is_idol ? 'Айдол' : 'Клиент' }})
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Messages stream -->
                    <div class="viewer-stream" ref="messagesContainer">
                        <!-- Load more button -->
                        <div v-if="hasMoreMessages" class="load-more-wrap">
                            <button @click="loadMoreMessages" class="btn-load-more" :disabled="messagesLoading">
                                {{ messagesLoading ? 'Загрузка...' : 'Загрузить предыдущие сообщения' }}
                            </button>
                        </div>

                        <!-- Empty chat placeholder -->
                        <div v-if="messages.length === 0 && !messagesLoading" class="stream-empty">
                            В этом диалоге пока нет сообщений
                        </div>

                        <!-- Message items -->
                        <div
                            v-for="(msg, index) in messages"
                            :key="msg.id"
                            class="msg-wrapper"
                            :class="{
                                'msg-wrapper--system': isSystemMessage(msg),
                                'msg-wrapper--left': !isSystemMessage(msg) && msg.sender_id === activeConversation.users[0]?.id,
                                'msg-wrapper--right': !isSystemMessage(msg) && msg.sender_id === activeConversation.users[1]?.id
                            }"
                        >
                            <!-- Date Separator if first message of the day -->
                            <div v-if="index === 0 || formatDate(msg.created_at) !== formatDate(messages[index - 1].created_at)" class="date-separator">
                                <span>{{ formatDate(msg.created_at) }}</span>
                            </div>

                            <!-- System event -->
                            <div v-if="isSystemMessage(msg)" class="system-msg" :class="{ 'system-msg--detailed': ['order_created', 'item_added'].includes(msg.metadata?.event) && msg.metadata?.services }">
                                <span class="system-msg-text">{{ getSystemText(msg) }}</span>
                                
                                <div v-if="['order_created', 'item_added'].includes(msg.metadata?.event) && msg.metadata?.services?.length" class="system-msg-details">
                                    <div v-for="svc in msg.metadata.services" :key="svc.id" class="sys-svc-line">
                                        <span class="sys-svc-name">{{ svc.name }} <span class="sys-svc-qty">x{{ svc.quantity || 1 }}</span></span>
                                        <span class="sys-svc-price">{{ (svc.price || 0).toLocaleString('ru-RU') }} ₽</span>
                                    </div>
                                    <div class="sys-svc-total">
                                        <span>Итого:</span>
                                        <span>{{ msg.metadata.services.reduce((sum, s) => sum + ((s.price || 0) * (s.quantity || 1)), 0).toLocaleString('ru-RU') }} ₽</span>
                                    </div>
                                </div>

                                <span class="system-msg-time">{{ formatTime(msg.created_at) }}</span>
                            </div>

                            <!-- Regular User message -->
                            <div v-else class="user-msg">
                                <div class="user-msg-meta">
                                    <span class="msg-sender-name">{{ msg.sender_name }}</span>
                                    <span class="msg-time">{{ formatTime(msg.created_at) }}</span>
                                </div>
                                <div class="user-msg-bubble">
                                    <!-- Image message type -->
                                    <div v-if="msg.type === 'image'" class="msg-image">
                                        <img :src="msg.body" alt="Изображение" class="msg-img-content" />
                                    </div>
                                    <!-- Normal Text message type -->
                                    <p v-else class="msg-body-text">{{ msg.body }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.chats-page {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 120px);
    box-sizing: border-box;
}

.chats-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    flex-shrink: 0;
}

.page-title {
    font-size: 1.4rem;
    color: #fff;
    margin: 0;
}

.chats-total {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.4);
}

.chats-layout {
    display: flex;
    flex: 1;
    gap: 1.5rem;
    min-height: 0;
}

/* ─── Sidebar ─── */
.chats-sidebar {
    width: 380px;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    min-height: 0;
}

/* Filters */
.filter-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    width: 100%;
    box-sizing: border-box;
}

.filter-row {
    display: flex;
    gap: 0.75rem;
}

.filter-row--two .filter-field {
    flex: 1;
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    flex: 1;
    min-width: 0;
}

.filter-label {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.4);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.filter-input, .filter-select {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #fff;
    padding: 0.45rem 0.65rem;
    font-size: 0.85rem;
    outline: none;
    font-family: inherit;
    border-radius: 4px;
    width: 100%;
    box-sizing: border-box;
}

.filter-select {
    cursor: pointer;
}

.filter-select option {
    background: #181524;
    color: #fff;
}

.filter-input:focus, .filter-select:focus {
    border-color: rgba(155, 110, 232, 0.55);
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.25rem;
}

.btn-filter {
    flex: 1;
    padding: 0.45rem;
    font-size: 0.82rem;
    border-radius: 4px;
    cursor: pointer;
    font-family: inherit;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-filter--apply {
    background: rgba(155, 110, 232, 0.15);
    border: 1px solid rgba(155, 110, 232, 0.45);
    color: #a78bfa;
}

.btn-filter--apply:hover {
    background: rgba(155, 110, 232, 0.25);
}

.btn-filter--reset {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.5);
}

.btn-filter--reset:hover {
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
}

/* List container */
.conv-list-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    min-height: 0;
}

.conv-list-empty {
    padding: 2rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.88rem;
}

.conv-list {
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.conv-item {
    display: flex;
    gap: 0.85rem;
    padding: 0.9rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    cursor: pointer;
    transition: background 0.2s;
}

.conv-item:hover {
    background: rgba(255, 255, 255, 0.015);
}

.conv-item--active {
    background: rgba(155, 110, 232, 0.08) !important;
    border-left: 3px solid #9B6EE8;
    padding-left: calc(0.9rem - 3px);
}

/* Avatars overlap layout */
.conv-item-avatars {
    display: flex;
    align-items: center;
    width: 46px;
    position: relative;
    flex-shrink: 0;
}

.conv-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #252131;
    border: 2px solid #141121;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.8);
    overflow: hidden;
}

.conv-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.conv-avatar:nth-child(2) {
    position: absolute;
    right: 0;
    z-index: 2;
}

.conv-avatar--idol {
    border-color: #9B6EE8;
    background: #31244c;
    color: #c084fc;
}

/* Info */
.conv-item-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.conv-item-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 0.5rem;
}

.conv-item-names {
    font-size: 0.88rem;
    font-weight: 600;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conv-item-time {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.35);
}

.conv-item-preview {
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.45);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conv-item-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    margin-top: 0.15rem;
}

/* Badges */
.badge {
    font-size: 0.65rem;
    padding: 0.1rem 0.4rem;
    border-radius: 2px;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.badge--id {
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.badge--order {
    background: rgba(76, 222, 143, 0.08);
    color: #4cde8f;
    border: 1px solid rgba(76, 222, 143, 0.2);
}

.badge--support {
    background: rgba(239, 68, 68, 0.08);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.badge--direct {
    background: rgba(56, 189, 248, 0.08);
    color: #38bdf8;
    border: 1px solid rgba(56, 189, 248, 0.2);
}

/* Pagination */
.pagination {
    display: flex;
    padding: 0.75rem;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    gap: 0.25rem;
    justify-content: center;
    flex-wrap: wrap;
}

.pagination-link {
    font-size: 0.78rem;
    padding: 0.25rem 0.55rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.6);
    border-radius: 3px;
    text-decoration: none;
}

.pagination-link:hover {
    background: rgba(155, 110, 232, 0.12);
    color: #fff;
    border-color: rgba(155, 110, 232, 0.3);
}

.pagination-link--active {
    background: rgba(155, 110, 232, 0.25) !important;
    border-color: rgba(155, 110, 232, 0.5) !important;
    color: #c084fc !important;
}

.pagination-link--disabled {
    opacity: 0.3;
    pointer-events: none;
}

/* ─── Chat Viewer ─── */
.chats-viewer {
    flex: 1;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.viewer-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.25);
}

.empty-icon {
    width: 3.5rem;
    height: 3.5rem;
    margin-bottom: 1.25rem;
}

.empty-title {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 600;
    margin: 0 0 0.4rem 0;
}

.empty-subtitle {
    font-size: 0.85rem;
    margin: 0;
    max-width: 320px;
    line-height: 1.5;
}

.viewer-active {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

/* Viewer Header */
.viewer-header {
    padding: 1.1rem 1.25rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.005);
}

.viewer-header-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.viewer-conv-id {
    font-size: 1.05rem;
    font-weight: 700;
    color: #fff;
}

.viewer-participants {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    font-size: 0.85rem;
}

.participants-label {
    color: rgba(255, 255, 255, 0.35);
}

.participants-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.participant-link {
    color: #38bdf8;
    text-decoration: none;
    font-weight: 500;
}

.participant-link:hover {
    text-decoration: underline;
}

.participant-link--idol {
    color: #c084fc;
}

/* Chat Stream */
.viewer-stream {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

.stream-empty {
    text-align: center;
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.88rem;
    padding: 3rem;
}

.load-more-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 0.5rem;
}

.btn-load-more {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.6);
    padding: 0.45rem 1rem;
    font-size: 0.8rem;
    border-radius: 4px;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}

.btn-load-more:hover {
    background: rgba(155, 110, 232, 0.1);
    color: #fff;
    border-color: rgba(155, 110, 232, 0.3);
}

.msg-wrapper {
    display: flex;
    flex-direction: column;
}

/* Date separator */
.date-separator {
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 1.5rem 0 0.5rem 0;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.3);
    position: relative;
}

.date-separator::before {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    height: 1px;
    background: rgba(255, 255, 255, 0.05);
    z-index: 1;
}

.date-separator span {
    background: #110e1c;
    padding: 0 0.75rem;
    position: relative;
    z-index: 2;
}

/* System message bubble */
.msg-wrapper--system {
    align-items: center;
}

.system-msg {
    max-width: 80%;
    background: rgba(255, 255, 255, 0.03);
    border: 1px dashed rgba(255, 255, 255, 0.08);
    padding: 0.5rem 0.85rem;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.15rem;
}

.system-msg-text {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.45);
    line-height: 1.4;
}

.system-msg-time {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.25);
}

.system-msg--detailed {
    max-width: 90%;
    align-items: stretch;
    text-align: left;
}

.system-msg--detailed .system-msg-text {
    text-align: center;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.system-msg-details {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 4px;
    padding: 0.5rem 0.75rem;
    margin-bottom: 0.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.sys-svc-line {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.7);
    gap: 1rem;
}

.sys-svc-qty {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.75rem;
    margin-left: 0.25rem;
}

.sys-svc-price {
    font-family: monospace;
    color: rgba(255, 255, 255, 0.8);
}

.sys-svc-total {
    display: flex;
    justify-content: space-between;
    margin-top: 0.25rem;
    padding-top: 0.25rem;
    border-top: 1px dashed rgba(255, 255, 255, 0.15);
    font-size: 0.85rem;
    font-weight: 600;
    color: #4cde8f;
}

/* User message bubble */
.user-msg {
    display: flex;
    flex-direction: column;
    max-width: 70%;
    gap: 0.25rem;
}

.msg-wrapper--left .user-msg {
    align-items: flex-start;
}

.msg-wrapper--right .user-msg {
    align-items: flex-end;
    align-self: flex-end;
}

.user-msg-meta {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    font-size: 0.75rem;
}

.msg-wrapper--left .user-msg-meta {
    flex-direction: row;
}

.msg-wrapper--right .user-msg-meta {
    flex-direction: row-reverse;
}

.msg-sender-name {
    font-weight: 600;
    color: rgba(255, 255, 255, 0.5);
}

.msg-wrapper--left .msg-sender-name {
    color: #38bdf8;
}

.msg-wrapper--right .msg-sender-name {
    color: #c084fc;
}

.msg-time {
    color: rgba(255, 255, 255, 0.25);
}

.user-msg-bubble {
    border-radius: 12px;
    padding: 0.65rem 0.85rem;
    font-size: 0.88rem;
    line-height: 1.5;
    position: relative;
    word-break: break-word;
}

.msg-wrapper--left .user-msg-bubble {
    background: rgba(56, 189, 248, 0.05);
    border: 1px solid rgba(56, 189, 248, 0.18);
    color: rgba(255, 255, 255, 0.9);
    border-top-left-radius: 2px;
}

.msg-wrapper--right .user-msg-bubble {
    background: rgba(155, 110, 232, 0.08);
    border: 1px solid rgba(155, 110, 232, 0.25);
    color: rgba(255, 255, 255, 0.9);
    border-top-right-radius: 2px;
}

.msg-body-text {
    margin: 0;
    white-space: pre-wrap;
}

/* Image message attachment */
.msg-image {
    max-width: 280px;
    max-height: 200px;
    border-radius: 4px;
    overflow: hidden;
}

.msg-img-content {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    cursor: pointer;
}
</style>
