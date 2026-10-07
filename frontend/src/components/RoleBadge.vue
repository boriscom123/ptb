<script setup>
defineProps({ role: { type: String, required: true } })
</script>

<template>
  <span class="badge" :class="`badge--${role}`">{{ $t(`roles.${role}`) }}</span>
</template>

<style scoped>
.badge {
  flex-shrink: 0;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 500;
  background: var(--secondary-bg);
  color: var(--hint);
}

.badge--admin {
  background: var(--button);
  color: var(--button-text);
}
</style>
