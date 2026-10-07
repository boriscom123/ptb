<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import AppAvatar from '../components/AppAvatar.vue'

const chats = ref([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    chats.value = (await api.chats()).data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})

const inactive = (chat) => ['left', 'kicked'].includes(chat.bot_status)
</script>

<template>
  <main class="page">
    <h1 class="page-title">{{ $t('nav.chats') }}</h1>

    <div v-if="loading" class="spinner" />
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="chats.length === 0" class="list list-empty">{{ $t('chats.empty') }}</p>

    <section v-else class="list">
      <RouterLink
        v-for="chat in chats"
        :key="chat.id"
        :to="{ name: 'chat', params: { id: chat.id } }"
        class="list-item"
        :class="{ 'list-item--inactive': inactive(chat) }"
      >
        <AppAvatar :name="chat.title" :seed="chat.telegram_id" />
        <div class="list-item__body">
          <div class="list-item__title">{{ chat.title }}</div>
          <div class="list-item__subtitle">
            {{ $t(`chats.types.${chat.type}`) }} · {{ $t(`chats.status.${chat.bot_status}`) }}
            <template v-if="chat.type !== 'channel' && !inactive(chat)">
              · {{ chat.moderation_enabled ? $t('chats.moderationOn') : $t('chats.moderationOff') }}
            </template>
          </div>
        </div>
        <span v-if="chat.events_count" class="counter">{{ chat.events_count }}</span>
      </RouterLink>
    </section>
  </main>
</template>

<style scoped>
.list-item--inactive {
  opacity: 0.5;
}

.counter {
  flex-shrink: 0;
  min-width: 22px;
  padding: 2px 7px;
  border-radius: 11px;
  background: var(--secondary-bg);
  color: var(--hint);
  font-size: 13px;
  text-align: center;
}
</style>
