<script setup>
import { onMounted, ref } from 'vue'
import { useAuthStore } from './stores/auth'
import { isInTelegram } from './telegram'

const auth = useAuthStore()

// loading | outside | forbidden | error | ready
const state = ref('loading')
const error = ref('')

async function start() {
  if (!isInTelegram) {
    state.value = 'outside'
    return
  }

  state.value = 'loading'
  try {
    await auth.login()
    state.value = auth.isAdmin ? 'ready' : 'forbidden'
  } catch (e) {
    error.value = e.message
    state.value = 'error'
  }
}

onMounted(start)
</script>

<template>
  <RouterView v-if="state === 'ready'" />

  <div v-else class="screen-message">
    <div v-if="state === 'loading'" class="spinner" />
    <p v-else-if="state === 'outside'">{{ $t('app.openInTelegram') }}</p>
    <p v-else-if="state === 'forbidden'">{{ $t('app.forbidden') }}</p>
    <template v-else>
      <p>{{ error }}</p>
      <button class="button" @click="start">{{ $t('app.retry') }}</button>
    </template>
  </div>
</template>
