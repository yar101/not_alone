<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const emit = defineEmits(['click']);
const page = usePage();
const unreadMessages = computed(() => page.props.unread_messages_count ?? 0);
</script>

<template>
    <button class="chat-btn" @click="emit('click')" title="Сообщения">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
    border-radius: 50%;
    background: transparent;
    border: none;
    cursor: pointer;
    color: rgba(255, 255, 255, 0.55);
    transition: color 0.18s, background 0.18s;
}
.chat-btn:hover {
    color: rgba(160, 160, 255, 0.9);
    background: rgba(110, 110, 210, 0.1);
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
