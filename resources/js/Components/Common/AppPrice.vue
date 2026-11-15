<script setup>
import { computed } from 'vue'
import CoinIcon from './CoinIcon.vue'

const props = defineProps({
  amount: {
    type: [Number, String],
    required: true,
  },
  color: {
    type: String,
    default: 'blue',
    validator: (v) => ['blue', 'pink'].includes(v),
  },
  size: {
    type: [String, Number],
    default: 'md',
  },
  showSign: {
    type: Boolean,
    default: false,
  },
  freeZero: {
    type: Boolean,
    default: false,
  },
  textClass: {
    type: String,
    default: '',
  },
})

const numericAmount = computed(() => {
  const val = typeof props.amount === 'string' ? parseFloat(props.amount) : props.amount
  return isNaN(val) ? 0 : val
})

const formattedText = computed(() => {
  const num = numericAmount.value
  const abs = Math.abs(num)
  
  // Format with Russian spaces e.g. 1 000 or 1 000.50
  const parts = abs.toFixed(2).split('.')
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
  
  let formatted = parts[1] === '00' ? parts[0] : `${parts[0]}.${parts[1]}`

  if (num < 0) {
    return `-${formatted}`
  }
  if (props.showSign && num > 0) {
    return `+${formatted}`
  }
  return formatted
})
</script>

<template>
  <span class="inline-flex items-center gap-1 font-medium whitespace-nowrap">
    <span v-if="freeZero && numericAmount === 0" class="text-emerald-500 font-semibold">
      Бесплатно
    </span>
    <template v-else>
      <span :class="textClass">{{ formattedText }}</span>
      <CoinIcon :color="color" :size="size" />
    </template>
  </span>
</template>
