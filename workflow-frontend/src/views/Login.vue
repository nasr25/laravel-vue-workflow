<template>
  <div class="login-container">
    <div class="language-switcher-wrapper">
      <LanguageSwitcher />
    </div>
    <div class="login-card">
      <h1>🚀 Workflow System</h1>
      <p class="subtitle">Login to access your workflow dashboard</p>

      <div v-if="error" class="alert alert-error">
        {{ error }}
      </div>

      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label for="email">Email</label>
          <input
            type="email"
            id="email"
            v-model="form.email"
            placeholder="Enter your email"
            required
          />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input
            type="password"
            id="password"
            v-model="form.password"
            placeholder="Enter your password"
            required
          />
        </div>

        <button type="submit" :disabled="isLoading" class="btn-primary">
          {{ isLoading ? 'Logging in...' : 'Login' }}
        </button>
      </form>

      <div class="test-accounts">
        <h3>📋 Test Accounts (Click to auto-fill):</h3>

        <!-- Admin & User -->
        <div class="account-category">
          <h4>System Users</h4>
          <div class="accounts-grid">
            <div
              v-for="account in testAccounts.filter(a => ['Admin', 'User'].includes(a.category))"
              :key="account.email"
              class="test-account"
              @click="fillLogin(account.email)"
            >
              <span class="account-icon">{{ account.icon }}</span>
              <div class="account-info">
                <strong>{{ account.role }}</strong>
                <code>{{ account.email }}</code>
              </div>
            </div>
          </div>
        </div>

        <!-- Managers -->
        <div class="account-category">
          <h4>Managers</h4>
          <div class="accounts-grid">
            <div
              v-for="account in testAccounts.filter(a => a.category === 'Managers')"
              :key="account.email"
              class="test-account"
              @click="fillLogin(account.email)"
            >
              <span class="account-icon">{{ account.icon }}</span>
              <div class="account-info">
                <strong>{{ account.role }}</strong>
                <code>{{ account.email }}</code>
              </div>
            </div>
          </div>
        </div>

        <!-- Employees -->
        <div class="account-category">
          <h4>Employees</h4>
          <div class="accounts-grid">
            <div
              v-for="account in testAccounts.filter(a => a.category === 'Employees')"
              :key="account.email"
              class="test-account"
              @click="fillLogin(account.email)"
            >
              <span class="account-icon">{{ account.icon }}</span>
              <div class="account-info">
                <strong>{{ account.role }}</strong>
                <code>{{ account.email }}</code>
              </div>
            </div>
          </div>
        </div>

        <p class="password-note">
          🔑 Password for all accounts: <strong>password</strong>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LanguageSwitcher from '../components/LanguageSwitcher.vue'

const router = useRouter()
const authStore = useAuthStore()

const form = ref({
  email: '',
  password: ''
})

const error = ref(null)
const isLoading = ref(false)

const testAccounts = [
  { icon: '👨‍💼', role: 'Admin', email: 'admin@workflow.com', category: 'Admin' },
  { icon: '👤', role: 'User', email: 'user@workflow.com', category: 'User' },
  { icon: '👔', role: 'Dept A Manager', email: 'manager.a@workflow.com', category: 'Managers' },
  { icon: '🔧', role: 'Tech Manager', email: 'manager.tech@workflow.com', category: 'Managers' },
  { icon: '💰', role: 'Finance Manager', email: 'manager.finance@workflow.com', category: 'Managers' },
  { icon: '⚖️', role: 'Legal Manager', email: 'manager.legal@workflow.com', category: 'Managers' },
  { icon: '📊', role: 'Strategy Manager', email: 'manager.strategy@workflow.com', category: 'Managers' },
  { icon: '👥', role: 'HR Manager', email: 'manager.hr@workflow.com', category: 'Managers' },
  { icon: '🔧', role: 'Tech Employee 1', email: 'emp.tech1@workflow.com', category: 'Employees' },
  { icon: '🔧', role: 'Tech Employee 2', email: 'emp.tech2@workflow.com', category: 'Employees' },
  { icon: '💰', role: 'Finance Employee', email: 'emp.finance@workflow.com', category: 'Employees' },
  { icon: '⚖️', role: 'Legal Employee', email: 'emp.legal@workflow.com', category: 'Employees' },
  { icon: '📊', role: 'Strategy Employee 1', email: 'emp.strategy1@workflow.com', category: 'Employees' },
  { icon: '📊', role: 'Strategy Employee 2', email: 'emp.strategy2@workflow.com', category: 'Employees' },
  { icon: '👥', role: 'HR Employee 1', email: 'emp.hr1@workflow.com', category: 'Employees' },
  { icon: '👥', role: 'HR Employee 2', email: 'emp.hr2@workflow.com', category: 'Employees' }
]

const handleLogin = async () => {
  error.value = null
  isLoading.value = true

  const result = await authStore.login(form.value.email, form.value.password)

  isLoading.value = false

  if (result.success) {
    router.push('/dashboard')
  } else {
    error.value = result.error || 'Login failed. Please try again.'
  }
}

const fillLogin = (email) => {
  form.value.email = email
  form.value.password = 'password'
}
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 20px;
  position: relative;
}

.language-switcher-wrapper {
  position: absolute;
  top: 20px;
  right: 20px;
  z-index: 10;
}

html[dir="rtl"] .language-switcher-wrapper {
  right: auto;
  left: 20px;
}

.login-card {
  background: white;
  border-radius: 20px;
  padding: 40px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  max-width: 800px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

h1 {
  color: #333;
  margin-bottom: 10px;
  font-size: 28px;
}

.subtitle {
  color: #666;
  margin-bottom: 30px;
  font-size: 14px;
}

.alert {
  padding: 12px;
  border-radius: 8px;
  margin-bottom: 20px;
  font-size: 14px;
}

.alert-error {
  background: #fee;
  color: #c33;
  border: 1px solid #fcc;
}

.form-group {
  margin-bottom: 20px;
}

label {
  display: block;
  margin-bottom: 8px;
  color: #555;
  font-weight: 500;
  font-size: 14px;
}

input {
  width: 100%;
  padding: 12px 15px;
  border: 2px solid #e0e0e0;
  border-radius: 10px;
  font-size: 15px;
  transition: all 0.3s;
}

input:focus {
  outline: none;
  border-color: #667eea;
}

.btn-primary {
  width: 100%;
  padding: 14px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.test-accounts {
  margin-top: 30px;
  padding: 20px;
  background: #f8f9fa;
  border-radius: 10px;
  border: 2px solid #e0e0e0;
}

.test-accounts h3 {
  color: #667eea;
  margin-bottom: 15px;
  font-size: 16px;
  text-align: center;
}

.account-category {
  margin-bottom: 20px;
}

.account-category:last-of-type {
  margin-bottom: 10px;
}

.account-category h4 {
  color: #555;
  font-size: 13px;
  margin-bottom: 10px;
  padding-bottom: 5px;
  border-bottom: 2px solid #dee2e6;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.accounts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 8px;
}

.test-account {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  background: white;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.test-account:hover {
  border-color: #667eea;
  background: #f8f9ff;
  transform: translateY(-2px);
  box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
}

.account-icon {
  font-size: 20px;
  flex-shrink: 0;
}

.account-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.account-info strong {
  color: #333;
  font-size: 12px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.account-info code {
  color: #667eea;
  background: #f0f3ff;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 10px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.password-note {
  margin-top: 15px;
  padding: 10px;
  background: #fff3cd;
  border: 1px solid #ffc107;
  color: #856404;
  border-radius: 6px;
  text-align: center;
  font-size: 13px;
}

.password-note strong {
  color: #d39e00;
}
</style>
