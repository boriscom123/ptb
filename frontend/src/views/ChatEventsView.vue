<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import { useBackButton } from '../composables'
import { dateTime, fullName } from '../format'

const props = defineProps({ id: { type: Number, required: true } })

const events = ref([])
const meta = ref(null)
const loading = ref(false)
const error = ref('')

useBackButton(() => ({ name: 'chat', params: { id: props.id } }))

async function load(page = 1) {
  loading.value = true
  try {
    const response = await api.chatEvents(props.id, page)
    events.value = [...events.value, ...response.data]
    meta.value = response.meta
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <main class="page">
    <h1 class="page-title">{{ $t('chat.journal') }}</h1>

    <section v-if="events.length" class="list">
      <article v-for="event in events" :key="event.id" class="event">
        <div class="event__header">
          <RouterLink v-if="event.user" :to="{ name: 'user', params: { id: event.user.id } }" class="event__author">
            {{ fullName(event.user) }}<template v-if="event.user.username"> · {{ '@' + event.user.username }}</template>
          </RouterLink>
          <span v-else class="hint">{{ $t('events.unknown') }}</span>
          <span class="hint event__date">{{ dateTime(event.created_at) }}</span>
        </div>
        <div class="event__meta">
          <span class="event__tag">{{ $t(`rules.${event.rule}.name`) }}: {{ event.reason }}</span>
          <span class="event__tag" :class="{ 'event__tag--error': event.action === 'failed' }">{{ $t(`actions.${event.action}`) }}</span>
        </div>
        <p v-if="event.message_text" class="event__text">{{ event.message_text }}</p>
      </article>
    </section>

    <p v-else-if="!loading && !error" class="list list-empty">{{ $t('events.empty') }}</p>
    <p v-if="error" class="error">{{ error }}</p>
    <div v-if="loading" class="spinner" />
    <button v-else-if="meta && meta.current_page < meta.last_page" class="button button--secondary" @click="load(meta.current_page + 1)">
      {{ $t('events.loadMore') }}
    </button>
  </main>
</template>

<style scoped>
.event {
  padding: 12px 16px;
}

.event + .event {
  border-top: 0.5px solid var(--separator);
}

.event__header {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 14px;
}

.event__author {
  overflow: hidden;
  color: var(--link);
  text-decoration: none;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.event__date {
  flex-shrink: 0;
  font-size: 13px;
}

.event__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 6px;
}

.event__tag {
  padding: 2px 8px;
  border-radius: 8px;
  background: var(--secondary-bg);
  font-size: 12px;
}

.event__tag--error {
  color: var(--destructive);
}

.event__text {
  margin: 8px 0 0;
  color: var(--hint);
  font-size: 14px;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
</style>
