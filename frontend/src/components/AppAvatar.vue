<script setup>
import { computed } from 'vue'

const props = defineProps({
  name: { type: String, required: true },
  // Число для стабильного цвета (Telegram ID пользователя или чата)
  seed: { type: Number, required: true },
  size: { type: Number, default: 40 },
})

const palette = ['#e17076', '#faa774', '#a695e7', '#7bc862', '#6ec9cb', '#65aadd', '#ee7aae']
const color = computed(() => palette[Math.abs(props.seed) % palette.length])
const initials = computed(() =>
  props.name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => [...part][0])
    .join('')
    .toUpperCase(),
)
</script>

<template>
  <div class="avatar" :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${size * 0.4}px`, background: color }">
    {{ initials }}
  </div>
</template>

<style scoped>
.avatar {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: 50%;
  color: #fff;
  font-weight: 600;
}
</style>
