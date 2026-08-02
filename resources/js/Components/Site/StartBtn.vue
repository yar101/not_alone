<script setup>
/**
 * Компонент элегантной кнопки в стиле "Start"
 * Использует шрифт Brygada 1918 и внутренние тени для создания эффекта обводки
 */
import { computed } from "vue";
import { useTranslations } from "@/composables/useTranslations";

const { __ } = useTranslations();

const props = defineProps({
    label: {
        type: String,
        default: null,
    },
});

const effectiveLabel = computed(() => props.label ?? __("welcome.start"));

const emit = defineEmits(["click"]);

const handleClick = (event) => {
    emit("click", event);
};
</script>

<template>
    <button class="start-button" @click="handleClick">
        <span class="label">{{ effectiveLabel }}</span>
    </button>
</template>

<style scoped>
.start-button {
    /* Сброс стандартных стилей браузера */
    appearance: none;
    border: none;
    cursor: pointer;
    padding: 0;
    outline: none;

    /* Размеры и позиционирование */
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.8rem 3rem;
    min-width: 320px;

    /* Яркий фон: насыщенный градиент для глубины */
    background-image: linear-gradient(
        135deg,
        rgba(56, 194, 194, 0.2) 0%,
        rgba(199, 40, 130, 0.2) 100%
    );
    background-color: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);

    /* Эффект внутренней обводки через spread (spread = 2px) + стеклянный блик сверху */
    box-shadow: inset 0 0 0 3px rgba(255, 255, 255, 0.03), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    
    border-radius: 12px;

    /* Плавные переходы */
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    overflow: hidden;

    /* Предотвращение выделения текста */
    user-select: none;
}

.label {
    color: #ffffff;
    font-size: 5rem;
    letter-spacing: 0.02em;
    text-transform: uppercase;

    /* Применяем шрифт Imbue */
    font-family: "Brygada 1918", serif;
    font-weight: 300;

    /* Усиленное свечение букв */
    text-shadow:
        0 0 15px rgba(255, 255, 255, 0.3),
        0 0 2px rgba(255, 255, 255, 0.5);

    /* Центровка (компенсация letter-spacing) */
    padding-left: 0.02em;
    position: relative;
    z-index: 2;
    opacity: 0.95;
    transition: all 0.4s ease;
}

/* Эффект мягкого блика по центру */
.start-button::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(
        circle at center,
        rgba(255, 255, 255, 0.05) 0%,
        transparent 70%
    );
    border-radius: 12px;
    pointer-events: none;
}

/* Состояния при взаимодействии */
.start-button:hover {
    /* Усиливаем яркость обводки и фона */
    box-shadow: inset 0 0 0 2px rgba(255, 178, 239, 0.1);
}

.start-button:hover .label {
    opacity: 1;
    text-shadow: 0 0 25px rgba(255, 178, 239, 1);
    color: rgba(255, 178, 239, 1);
}

.start-button:active {
    transform: scale(0.97);
    box-shadow: inset 0 0 0 2px rgba(255, 178, 239, 0.3);
    transition: all 0.1s ease;
}

/* Адаптивность для мобильных устройств */
@media (max-width: 480px) {
    .start-button {
        width: 280px;
        height: 90px;
    }

    .label {
        font-size: 3rem;
    }
}
</style>
