<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const emit = defineEmits(['click']);
const page = usePage();
const unreadMessages = computed(() => page.props.unread_messages_count ?? 0);
</script>

<template>
    <button class="chat-btn" @click="emit('click')" title="Сообщения">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        <span v-if="unreadMessages > 0" class="chat-btn__badge"></span>
    </button>
</template>

<style scoped>
.chat-btn {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: transparent;
    border: 1px solid transparent;
    cursor: pointer;
    color: rgba(255, 255, 255, 0.45);
    transition: color 0.15s, background 0.15s, border-color 0.15s;
}
.chat-btn:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
}
.chat-btn__badge {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #e0558f;
    pointer-events: none;
}
</style>
