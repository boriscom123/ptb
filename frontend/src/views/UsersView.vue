<script setup>
import { onMounted, ref, watch } from 'vue'
import { api } from '../api'
import RoleBadge from '../components/RoleBadge.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { fullName, relativeTime } from '../format'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()

const search = ref('')
const role = ref('')
const users = ref([])
const meta = ref(null)
const loading = ref(false)
const error = ref('')

const roles = ['', 'admin', 'user']

async function load(page = 1) {
  loading.value = true
  error.value = ''

  try {
    const params = { page }
    if (search.value.trim()) params.search = search.value.trim()
    if (role.value) params.role = role.value

    const response = await api.users(params)
    users.value = page === 1 ? response.data : [...users.value, ...response.data]
    meta.value = response.meta
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

let debounce
watch(search, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => load(), 300)
})
watch(role, () => load())

onMounted(load)
</script>

<template>
  <main class="page">
    <h1 class="page-title">{{ $t('nav.users') }}</h1>

    <input v-model="search" class="input" type="search" :placeholder="$t('users.search')" />

    <div class="chips">
      <button
        v-for="item in roles"
        :key="item"
        class="chip"
        :class="{ 'chip--active': role === item }"
        @click="role = item"
      >
        {{ item ? $t(`roles.${item}`) : $t('users.all') }}
      </button>
    </div>

    <p v-if="meta" class="hint">{{ $t('users.total', { count: meta.total }) }}</p>

    <section class="list">
      <RouterLink v-for="user in users" :key="user.id" :to="{ name: 'user', params: { id: user.id } }" class="list-item">
        <UserAvatar :user="user" />
        <div class="list-item__body">
          <div class="list-item__title">
            {{ fullName(user) }}
            <span v-if="user.id === auth.user.id" class="hint">({{ $t('users.you') }})</span>
          </div>
          <div class="list-item__subtitle">
            <template v-if="user.username">@{{ user.username }} · </template>{{ relativeTime(user.last_seen_at) }}
          </div>
        </div>
        <RoleBadge :role="user.role" />
      </RouterLink>

      <p v-if="!loading && !error && users.length === 0" class="list-empty">{{ $t('users.empty') }}</p>
    </section>

    <p v-if="error" class="error">{{ error }}</p>
    <div v-if="loading" class="spinner" />
    <button v-else-if="meta && meta.current_page < meta.last_page" class="button button--secondary" @click="load(meta.current_page + 1)">
      {{ $t('users.loadMore') }}
    </button>
  </main>
</template>
