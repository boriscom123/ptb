<script setup>
import { computed } from 'vue'
import { initials } from '../format'

const props = defineProps({
  user: { type: Object, required: true },
  size: { type: Number, default: 40 },
})

// Цвет аватара стабилен для пользователя, как в Telegram
const palette = ['#e17076', '#faa774', '#a695e7', '#7bc862', '#6ec9cb', '#65aadd', '#ee7aae']
const color = computed(() => palette[props.user.telegram_id % palette.length])
</script>

<template>
  <div class="avatar" :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${size * 0.4}px`, background: color }">
    {{ initials(user) }}
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
