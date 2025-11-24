import { defineStore } from 'pinia'
import axios from 'axios'

const API_URL = 'http://localhost:8000/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token') || null,
    permissions: [],
    roles: [],
    isLoading: false,
    error: null
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    isAdmin: (state) => state.user?.role === 'admin',
    isManager: (state) => state.user?.role === 'manager',
    isUser: (state) => state.user?.role === 'user',

    // Permission-based getters
    hasPermission: (state) => (permission) => {
      return state.permissions.includes(permission)
    },
    hasAnyPermission: (state) => (permissions) => {
      return permissions.some(permission => state.permissions.includes(permission))
    },
    hasAllPermissions: (state) => (permissions) => {
      return permissions.every(permission => state.permissions.includes(permission))
    },
    hasRole: (state) => (role) => {
      return state.roles.includes(role)
    }
  },

  actions: {
    async login(email, password) {
      this.isLoading = true
      this.error = null

      try {
        const response = await axios.post(`${API_URL}/auth/login`, {
          email,
          password
        })

        this.token = response.data.token
        this.user = response.data.user
        this.permissions = response.data.permissions || []
        this.roles = response.data.roles || []

        localStorage.setItem('token', this.token)
        localStorage.setItem('permissions', JSON.stringify(this.permissions))
        localStorage.setItem('roles', JSON.stringify(this.roles))

        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`

        return { success: true }
      } catch (error) {
        this.error = error.response?.data?.message || 'Login failed'
        return { success: false, error: this.error }
      } finally {
        this.isLoading = false
      }
    },

    async logout() {
      try {
        if (this.token) {
          await axios.post(`${API_URL}/auth/logout`, {}, {
            headers: { Authorization: `Bearer ${this.token}` }
          })
        }
      } catch (error) {
        console.error('Logout error:', error)
      } finally {
        this.token = null
        this.user = null
        this.permissions = []
        this.roles = []
        localStorage.removeItem('token')
        localStorage.removeItem('permissions')
        localStorage.removeItem('roles')
        delete axios.defaults.headers.common['Authorization']
      }
    },

    async fetchUser() {
      if (!this.token) return

      try {
        const response = await axios.get(`${API_URL}/auth/user`, {
          headers: { Authorization: `Bearer ${this.token}` }
        })
        this.user = response.data.user
        this.permissions = response.data.permissions || []
        this.roles = response.data.roles || []

        localStorage.setItem('permissions', JSON.stringify(this.permissions))
        localStorage.setItem('roles', JSON.stringify(this.roles))
      } catch (error) {
        console.error('Fetch user error:', error)
        this.logout()
      }
    },

    initializeAuth() {
      if (this.token) {
        // Load permissions and roles from localStorage
        const storedPermissions = localStorage.getItem('permissions')
        const storedRoles = localStorage.getItem('roles')

        if (storedPermissions) {
          try {
            this.permissions = JSON.parse(storedPermissions)
          } catch (e) {
            this.permissions = []
          }
        }

        if (storedRoles) {
          try {
            this.roles = JSON.parse(storedRoles)
          } catch (e) {
            this.roles = []
          }
        }

        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`
        this.fetchUser()
      }
    }
  }
})
