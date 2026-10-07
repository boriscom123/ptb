<script setup>
import { onMounted, ref } from 'vue'

const status = ref('…')

onMounted(async () => {
  try {
    const response = await fetch('/api/health')
    status.value = response.ok ? (await response.json()).status : `HTTP ${response.status}`
  } catch (error) {
    status.value = error.message
  }
})
</script>

<template>
  <main>
    <h1>PTB</h1>
    <p>API: {{ status }}</p>
  </main>
</template>
