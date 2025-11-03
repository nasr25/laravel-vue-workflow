import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../services/api'
import type { User } from '../types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('token'))
  const loading = ref(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role?.name === 'admin')
  const isManager = computed(() => user.value?.role?.name === 'manager')
  const isEmployee = computed(() => user.value?.role?.name === 'employee')
  const isUser = computed(() => user.value?.role?.name === 'user')

  async function login(email: string, password: string) {
    loading.value = true
    error.value = null

    try {
      const response = await api.login(email, password)

      if (response.data.success) {
        token.value = response.data.token
        user.value = response.data.user
        localStorage.setItem('token', response.data.token)
        return true
      }
      return false
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Login failed'
      return false
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await api.logout()
    } catch (err) {
      console.error('Logout error:', err)
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('token')
    }
  }

  async function fetchUser() {
    if (!token.value) return

    try {
      const response = await api.me()
      if (response.data.success) {
        user.value = response.data.user
      }
    } catch (err) {
      console.error('Fetch user error:', err)
      // Token might be invalid, clear it
      logout()
    }
  }

  return {
    user,
    token,
    loading,
    error,
    isAuthenticated,
    isAdmin,
    isManager,
    isEmployee,
    isUser,
    login,
    logout,
    fetchUser,
  }
})
