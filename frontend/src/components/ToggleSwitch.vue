<script setup>
defineProps({ disabled: Boolean })
const model = defineModel({ type: Boolean })
</script>

<template>
  <label class="toggle" :class="{ 'toggle--disabled': disabled }">
    <input v-model="model" type="checkbox" :disabled="disabled" />
    <span class="toggle__track" />
  </label>
</template>

<style scoped>
.toggle {
  position: relative;
  flex-shrink: 0;
  width: 51px;
  height: 31px;
  cursor: pointer;
}

.toggle--disabled {
  opacity: 0.5;
  cursor: default;
}

.toggle input {
  position: absolute;
  opacity: 0;
}

.toggle__track {
  position: absolute;
  inset: 0;
  border-radius: 16px;
  background: var(--separator);
  transition: background 0.2s;
}

.toggle__track::after {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: 27px;
  height: 27px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  transition: transform 0.2s;
}

.toggle input:checked + .toggle__track {
  background: var(--button);
}

.toggle input:checked + .toggle__track::after {
  transform: translateX(20px);
}
</style>
