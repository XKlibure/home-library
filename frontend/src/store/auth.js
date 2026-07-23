import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../services/api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('auth_token') || null)
  const user = ref(JSON.parse(localStorage.getItem('auth_user') || 'null'))

  const isAuthenticated = computed(() => !!token.value)

  async function login(credentials) {
    const response = await api.post('/auth/login', credentials)
    token.value = response.data.token
    user.value  = response.data.user
    localStorage.setItem('auth_token',  response.data.token)
    localStorage.setItem('auth_user',   JSON.stringify(response.data.user))
    return response.data  // includes must_change_password
  }

  async function refreshUser() {
    try {
      const response = await api.get('/auth/me')
      user.value = response.data.user
      localStorage.setItem('auth_user', JSON.stringify(response.data.user))
    } catch { /* silent */ }
  }

  function logout() {
    token.value = null
    user.value = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_user')
  }

  return { token, user, isAuthenticated, login, refreshUser, logout }
})
