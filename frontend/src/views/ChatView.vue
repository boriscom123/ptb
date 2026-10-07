<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '../api'
import AppAvatar from '../components/AppAvatar.vue'
import RuleCard from '../components/RuleCard.vue'
import ToggleSwitch from '../components/ToggleSwitch.vue'
import { useBackButton } from '../composables'
import { alert, haptic } from '../telegram'

const props = defineProps({ id: { type: Number, required: true } })

const { t } = useI18n()

const chat = ref(null)
const rules = ref([])
const loading = ref(true)
const savingRule = ref('')
const error = ref('')

useBackButton(() => ({ name: 'chats' }))

onMounted(async () => {
  try {
    const response = await api.chat(props.id)
    chat.value = response.data
    rules.value = response.rules
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})

async function fail(e) {
  haptic('error')
  await alert(e.message || t('errors.generic'))
}

async function toggleModeration(enabled) {
  try {
    chat.value = (await api.updateChat(chat.value.id, { moderation_enabled: enabled })).data
    haptic('success')
  } catch (e) {
    await fail(e)
  }
}

async function saveRule(key, data) {
  savingRule.value = key
  try {
    rules.value = (await api.updateRule(chat.value.id, key, data)).rules
    haptic('success')
  } catch (e) {
    await fail({ message: e.errors ? Object.values(e.errors).flat().join('\n') : e.message })
  } finally {
    savingRule.value = ''
  }
}
</script>

<template>
  <main class="page">
    <div v-if="loading" class="spinner" />
    <p v-else-if="error" class="error">{{ error }}</p>

    <template v-else>
      <header class="profile">
        <AppAvatar :name="chat.title" :seed="chat.telegram_id" :size="72" />
        <h1 class="profile__name">{{ chat.title }}</h1>
        <a v-if="chat.username" class="profile__username" :href="`https://t.me/${chat.username}`">t.me/{{ chat.username }}</a>
        <span class="hint">{{ $t(`chats.types.${chat.type}`) }} · {{ $t(`chats.status.${chat.bot_status}`) }}</span>
      </header>

      <p v-if="chat.type === 'channel'" class="notice">{{ $t('chat.channelHint') }}</p>

      <template v-else>
        <p v-if="chat.bot_status !== 'administrator'" class="notice notice--error">{{ $t('chat.notAdmin') }}</p>
        <template v-else>
          <p v-if="!chat.can_delete_messages" class="notice notice--error">{{ $t('chat.noDelete') }}</p>
          <p v-if="!chat.can_restrict_members" class="notice">{{ $t('chat.noRestrict') }}</p>
        </template>

        <section class="list">
          <div class="list-row">
            <span>{{ $t('chat.moderation') }}</span>
            <ToggleSwitch :model-value="chat.moderation_enabled" @update:model-value="toggleModeration" />
          </div>
          <RouterLink :to="{ name: 'chat-events', params: { id: chat.id } }" class="list-row list-row--link">
            <span>{{ $t('chat.journal') }}</span>
            <span class="hint">›</span>
          </RouterLink>
        </section>
        <p class="hint section-hint">{{ $t('chat.moderationHint') }}</p>

        <h2 class="section-title">{{ $t('chat.rules') }}</h2>
        <RuleCard
          v-for="rule in rules"
          :key="rule.key"
          :rule="rule"
          :saving="savingRule === rule.key"
          @save="saveRule(rule.key, $event)"
        />
      </template>
    </template>
  </main>
</template>

<style scoped>
.profile {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  margin-bottom: 16px;
  text-align: center;
}

.profile__name {
  margin: 8px 0 0;
  font-size: 22px;
}

.profile__username {
  color: var(--link);
  text-decoration: none;
}

.notice {
  margin: 0 0 12px;
  padding: 12px 16px;
  border-radius: var(--radius);
  background: var(--section-bg);
  font-size: 14px;
}

.notice--error {
  color: var(--destructive);
}

.list-row--link {
  color: inherit;
  text-decoration: none;
}
</style>
