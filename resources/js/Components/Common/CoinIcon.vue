<script setup>
import { computed, useId } from 'vue'

const props = defineProps({
  color: {
    type: String,
    default: 'blue',
    validator: (v) => ['blue', 'pink'].includes(v),
  },
  size: {
    type: [String, Number],
    default: 'md',
  },
  title: {
    type: String,
    default: null,
  },
})

const uid = useId()

const sizeClass = computed(() => {
  if (typeof props.size === 'number') {
    return ''
  }
  const sizes = {
    xs: 'w-[18px] h-[18px]',
    sm: 'w-[21px] h-[21px]',
    md: 'w-[24px] h-[24px]',
    lg: 'w-[30px] h-[30px]',
    xl: 'w-[36px] h-[36px]',
  }
  return sizes[props.size] || 'w-[24px] h-[24px]'
})

const sizeStyle = computed(() => {
  if (typeof props.size === 'number') {
    const s = Math.round(props.size * 1.5)
    return {
      width: `${s}px`,
      height: `${s}px`,
    }
  }
  return {}
})

const isPink = computed(() => props.color === 'pink')

const coinTitle = computed(() => {
  if (props.title) return props.title
  return isPink.value ? 'Розовые монеты' : 'Синие монеты'
})
</script>

<template>
  <svg
    :class="['inline-block align-middle shrink-0 select-none transition-transform duration-150', sizeClass]"
    :style="sizeStyle"
    viewBox="0 0 24 24"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    role="img"
    :aria-label="coinTitle"
  >
    <title>{{ coinTitle }}</title>
    <defs>
      <!-- Blue Gradients -->
      <linearGradient :id="`rim-blue-${uid}`" x1="2" y1="2" x2="22" y2="22" gradientUnits="userSpaceOnUse">
        <stop offset="0%" stop-color="#93c5fd" />
        <stop offset="45%" stop-color="#3b82f6" />
        <stop offset="100%" stop-color="#1d4ed8" />
      </linearGradient>
      <radialGradient :id="`face-blue-${uid}`" cx="35%" cy="30%" r="70%">
        <stop offset="0%" stop-color="#3b82f6" />
        <stop offset="60%" stop-color="#2563eb" />
        <stop offset="100%" stop-color="#1e3a8a" />
      </radialGradient>

      <!-- Pink Gradients -->
      <linearGradient :id="`rim-pink-${uid}`" x1="2" y1="2" x2="22" y2="22" gradientUnits="userSpaceOnUse">
        <stop offset="0%" stop-color="#fbcfe8" />
        <stop offset="45%" stop-color="#ec4899" />
        <stop offset="100%" stop-color="#be185d" />
      </linearGradient>
      <radialGradient :id="`face-pink-${uid}`" cx="35%" cy="30%" r="70%">
        <stop offset="0%" stop-color="#f472b6" />
        <stop offset="60%" stop-color="#db2777" />
        <stop offset="100%" stop-color="#831843" />
      </radialGradient>
    </defs>

    <!-- Outer Rim -->
    <circle
      cx="12"
      cy="12"
      r="10.5"
      :fill="isPink ? `url(#rim-pink-${uid})` : `url(#rim-blue-${uid})`"
    />

    <!-- Inner Coin Face -->
    <circle
      cx="12"
      cy="12"
      r="8.75"
      :fill="isPink ? `url(#face-pink-${uid})` : `url(#face-blue-${uid})`"
    />

    <!-- Decorative Inner Ring -->
    <circle
      cx="12"
      cy="12"
      r="7.5"
      stroke="#ffffff"
      stroke-opacity="0.35"
      stroke-width="0.75"
      stroke-dasharray="1.5 1"
      fill="none"
    />

    <!-- Top Highlight Arc -->
    <path
      d="M5.5 10C6.5 6.5 9 4.5 12 4.5C15 4.5 17.5 6.5 18.5 10"
      stroke="#ffffff"
      stroke-opacity="0.45"
      stroke-width="0.75"
      stroke-linecap="round"
      fill="none"
    />

    <!-- Center 4-Point Star Emblem -->
    <path
      d="M12 6.5L13.3 10.7L17.5 12L13.3 13.3L12 17.5L10.7 13.3L6.5 12L10.7 10.7L12 6.5Z"
      fill="#ffffff"
      fill-opacity="0.95"
    />

    <!-- Star Core Highlight -->
    <circle
      cx="12"
      cy="12"
      r="1.25"
      fill="#ffffff"
    />
  </svg>
</template>
