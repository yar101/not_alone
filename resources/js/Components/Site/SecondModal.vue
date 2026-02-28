<script setup>
import SiteModal from './SiteModal.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <SiteModal :show="show" variant="cyan" @close="emit('close')">
        <div class="modal-content">
            <h2 class="modal-title">Заголовок</h2>
            <p class="modal-text">
                Контент второго модального окна. Здесь будет полезная информация.
            </p>
        </div>
    </SiteModal>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Imbue:opsz,wght@10..100,200;10..100,300&display=swap');

.modal-content {
    color: #fff;
}

.modal-title {
    font-family: "Imbue", serif;
    font-size: 2rem;
    font-weight: 300;
    letter-spacing: 0.05em;
    margin-bottom: 1rem;
    text-shadow: 0 0 15px rgba(42, 255, 220, 0.3);
}

.modal-text {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    line-height: 1.6;
}
</style>
