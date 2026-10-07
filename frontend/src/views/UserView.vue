<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '../api'
import RoleBadge from '../components/RoleBadge.vue'
import AppAvatar from '../components/AppAvatar.vue'
import { dateTime, fullName, relativeTime } from '../format'
import { useAuthStore } from '../stores/auth'
import { useBackButton } from '../composables'
import { alert, confirm, haptic } from '../telegram'

const props = defineProps({ id: { type: Number, required: true } })

const { t } = useI18n()
const auth = useAuthStore()

const user = ref(null)
const selectedRole = ref('')
const loading = ref(true)
const saving = ref(false)
const error = ref('')

const roles = ['admin', 'user']
const isSelf = computed(() => user.value?.id === auth.user.id)
const locked = computed(() => isSelf.value || user.value?.is_protected)
const changed = computed(() => user.value && selectedRole.value !== user.value.role)

async function load() {
  try {
    user.value = (await api.user(props.id)).data
    selectedRole.value = user.value.role
  } catch (e) {
    error.value = e.status === 404 ? t('user.notFound') : e.message
  } finally {
    loading.value = false
  }
}

async function save() {
  const ok = await confirm(t('user.confirm', { name: fullName(user.value), role: t(`roles.${selectedRole.value}`) }))
  if (!ok) return

  saving.value = true
  try {
    user.value = (await api.updateUser(user.value.id, { role: selectedRole.value })).data
    haptic('success')
  } catch (e) {
    haptic('error')
    await alert(e.message)
    selectedRole.value = user.value.role
  } finally {
    saving.value = false
  }
}

useBackButton(() => ({ name: 'users' }))
onMounted(load)
</script>

<template>
  <main class="page">
    <div v-if="loading" class="spinner" />
    <p v-else-if="error" class="error">{{ error }}</p>

    <template v-else>
      <header class="profile">
        <AppAvatar :name="fullName(user)" :seed="user.telegram_id" :size="80" />
        <h1 class="profile__name">{{ fullName(user) }}</h1>
        <a v-if="user.username" class="profile__username" :href="`https://t.me/${user.username}`">@{{ user.username }}</a>
        <RoleBadge :role="user.role" />
      </header>

      <section class="list">
        <div class="list-row">
          <span>{{ $t('user.telegramId') }}</span><span class="hint">{{ user.telegram_id }}</span>
        </div>
        <div class="list-row">
          <span>{{ $t('user.language') }}</span><span class="hint">{{ user.language_code || '—' }}</span>
        </div>
        <div class="list-row">
          <span>{{ $t('user.lastSeen') }}</span><span class="hint">{{ relativeTime(user.last_seen_at) }}</span>
        </div>
        <div class="list-row">
          <span>{{ $t('user.startedBot') }}</span><span class="hint">{{ dateTime(user.started_at) }}</span>
        </div>
        <div v-if="user.blocked_bot_at" class="list-row">
          <span>{{ $t('user.blockedBot') }}</span><span class="hint">{{ dateTime(user.blocked_bot_at) }}</span>
        </div>
        <div class="list-row">
          <span>{{ $t('user.registered') }}</span><span class="hint">{{ dateTime(user.created_at) }}</span>
        </div>
      </section>

      <h2 class="section-title">{{ $t('user.role') }}</h2>
      <section class="list">
        <label v-for="role in roles" :key="role" class="list-row list-row--clickable" :class="{ 'list-row--disabled': locked }">
          <span>{{ $t(`roles.${role}`) }}</span>
          <input v-model="selectedRole" type="radio" name="role" :value="role" :disabled="locked" />
        </label>
      </section>
      <p v-if="isSelf" class="hint section-hint">{{ $t('user.ownRole') }}</p>
      <p v-else-if="user.is_protected" class="hint section-hint">{{ $t('user.protected') }}</p>

      <button v-if="!locked" class="button" :disabled="!changed || saving" @click="save">
        {{ $t('user.save') }}
      </button>
    </template>
  </main>
</template>

<style scoped>
.profile {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  margin-bottom: 20px;
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
</style>
