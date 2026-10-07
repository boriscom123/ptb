import { defineStore } from 'pinia'
import { api } from '../api'
import { initData } from '../telegram'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: null,
    user: null,
  }),

  getters: {
    isAdmin: (state) => state.user?.role === 'admin',
  },

  actions: {
    async login() {
      const data = await api.login(initData)
      this.token = data.token
      this.user = data.user
    },
  },
})
