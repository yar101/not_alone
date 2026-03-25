<script setup>
/**
 * Компонент элегантной кнопки в стиле "Start"
 * Использует шрифт Brygada 1918 и внутренние тени для создания эффекта обводки
 */
defineProps({
    label: {
        type: String,
        default: 'НАЧАТЬ'
    }
});

const emit = defineEmits(['click']);

const handleClick = (event) => {
    emit('click', event);
};
</script>

<template>
    <button class="start-button" @click="handleClick">
        <span class="label">{{ label }}</span>
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
    width: 440px;
    height: 140px;

    /* Яркий фон: насыщенный градиент для глубины */
    background: linear-gradient(135deg, #2a1b2d 0%, #121212 50%, rgba(64, 166, 166, 0.18) 100%);

    /* Эффект внутренней обводки через spread (spread = 2px) */
    box-shadow: inset 0 0 0 3px rgba(255, 255, 255, 3%);

    /* Плавные переходы */
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    overflow: hidden;

    /* Предотвращение выделения текста */
    user-select: none;
}

.label {
    color: #ffffff;
    font-size: 80px;
    letter-spacing: 0.02em;
    text-transform: uppercase;

    /* Применяем шрифт Imbue */
    font-family: "Brygada 1918", serif;
    font-weight: 100;

    /* Усиленное свечение букв */
    text-shadow: 0 0 15px rgba(255, 255, 255, 0.3),
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
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: radial-gradient(circle at center, rgba(255,255,255,0.05) 0%, transparent 70%);
    pointer-events: none;
}

/* Состояния при взаимодействии */
.start-button:hover {
    /* Усиливаем яркость обводки и фона */
    box-shadow: inset 0 0 0 2px rgba(110, 110, 210, 0.1);
    //background: linear-gradient(135deg, #3a263d 0%, #1a1a1a 50%, #1e2d2d 100%);
    transform: translateY(-15px);
}

.start-button:hover .label {
    opacity: 1;
    text-shadow: 0 0 25px rgba(110, 110, 210, 1);
    color: rgba(110, 110, 210, 1);
}

.start-button:active {
    transform: scale(0.97);
    box-shadow: inset 0 0 0 2px rgba(110, 110, 210, 0.3);
    transition: all 0.1s ease;
}

/* Адаптивность для мобильных устройств */
@media (max-width: 480px) {
    .start-button {
        width: 280px;
        height: 90px;
    }
    .label {
        font-size: 70px;
    }
}
</style>
