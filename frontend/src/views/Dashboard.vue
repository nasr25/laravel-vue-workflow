<template>
  <div class="dashboard-container">
    <div class="language-switcher-wrapper">
      <LanguageSwitcher />
    </div>
    <div class="dashboard-card">
      <h1>{{ $t('dashboard.title') }}</h1>
      <p class="subtitle">{{ $t('dashboard.subtitle') }}</p>

      <div v-if="user" class="user-info">
        <h2>{{ $t('dashboard.userInfo') }}</h2>
        <p><strong>{{ $t('auth.name') }}:</strong> {{ user.name }}</p>
        <p><strong>{{ $t('auth.email') }}:</strong> {{ user.email }}</p>
        <p>
          <strong>{{ $t('dashboard.role') }}:</strong>
          <span :class="['badge', `badge-${user.role}`]">
            {{ user.role.toUpperCase() }}
          </span>
        </p>
      </div>

      <div class="notice">
        <strong>🎉 {{ $t('common.welcome') }}!</strong>
        {{ $t('dashboard.subtitle') }}
      </div>

      <div class="quick-actions">
        <div v-if="canViewOwnRequests" class="action-card" @click="goToRequests">
          <h3>📝 {{ $t('dashboard.myRequests.title') }}</h3>
          <p>{{ $t('dashboard.myRequests.description') }}</p>
        </div>
        <div v-if="canCreateRequest" class="action-card" @click="goToNewRequest">
          <h3>➕ {{ $t('dashboard.newRequest.title') }}</h3>
          <p>{{ $t('dashboard.newRequest.description') }}</p>
        </div>
        <div v-if="canViewWorkflow" class="action-card" @click="goToWorkflowReview">
          <h3>🔍 {{ $t('dashboard.reviewRequests.title') }}</h3>
          <p>{{ $t('dashboard.reviewRequests.description') }}</p>
        </div>
        <div v-if="canViewDepartment" class="action-card" @click="goDepartmentWorkflow">
          <h3>🔄 {{ $t('dashboard.departmentWorkflow.title') }}</h3>
          <p>{{ $t('dashboard.departmentWorkflow.description') }}</p>
        </div>
        <div v-if="canAccessAdmin" class="action-card" @click="goToAdmin">
          <h3>⚙️ {{ $t('dashboard.adminPanel.title') }}</h3>
          <p>{{ $t('dashboard.adminPanel.description') }}</p>
        </div>
      </div>

      <button @click="handleLogout" class="btn-logout">{{ $t('common.logout') }}</button>

      <div v-if="message" class="alert alert-info">
        {{ message }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LanguageSwitcher from '../components/LanguageSwitcher.vue'

const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user)
const message = ref(null)

// Permission-based access control
const canViewOwnRequests = computed(() =>
  authStore.hasPermission('request.view-own') || authStore.hasPermission('request.view-all')
)

const canCreateRequest = computed(() =>
  authStore.hasPermission('request.create')
)

const canViewWorkflow = computed(() =>
  authStore.hasAnyPermission(['workflow.view-pending', 'workflow.assign-path'])
)

const canViewDepartment = computed(() =>
  authStore.hasAnyPermission(['department.view-requests', 'department.assign-employee'])
)

const canAccessAdmin = computed(() =>
  authStore.hasAnyPermission(['user.view', 'department.view', 'role.view'])
)

const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}

const goToRequests = () => {
  router.push('/requests')
}

const goToNewRequest = () => {
  router.push('/requests/new')
}

const goToWorkflowReview = () => {
  router.push('/workflow/review')
}

const goToDepartmentWorkflow = () => {
  router.push('/department/workflow')
}

const goToAdmin = () => {
  router.push('/admin')
}

const showComingSoon = (feature) => {
  message.value = `${feature} feature will be implemented next!`
  setTimeout(() => {
    message.value = null
  }, 5000)
}
</script>

<style scoped>
.dashboard-container {
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

.dashboard-card {
  background: white;
  border-radius: 20px;
  padding: 40px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  max-width: 600px;
  width: 100%;
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

.user-info {
  background: #f5f5f5;
  padding: 20px;
  border-radius: 10px;
  margin-bottom: 20px;
}

.user-info h2 {
  color: #667eea;
  margin-bottom: 10px;
  font-size: 20px;
}

.user-info p {
  color: #666;
  margin: 5px 0;
  font-size: 14px;
}

.badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  margin-top: 5px;
}

.badge-admin {
  background: #ff6b6b;
  color: white;
}

.badge-manager {
  background: #4ecdc4;
  color: white;
}

.badge-user {
  background: #95e1d3;
  color: white;
}

.badge-employee {
  background: #ffd93d;
  color: #333;
}

.notice {
  background: #e8f5e9;
  border: 1px solid #4caf50;
  color: #2e7d32;
  padding: 15px;
  border-radius: 10px;
  margin-bottom: 20px;
  font-size: 13px;
}

.notice strong {
  display: block;
  margin-bottom: 5px;
}

.quick-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
  margin-bottom: 20px;
}

.action-card {
  padding: 20px;
  border: 2px solid #e0e0e0;
  border-radius: 10px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s;
  background: white;
}

.action-card:hover {
  border-color: #667eea;
  transform: translateY(-2px);
  background: #f8f9ff;
}

.action-card h3 {
  color: #667eea;
  font-size: 16px;
  margin-bottom: 5px;
}

.action-card p {
  color: #999;
  font-size: 12px;
}

.btn-logout {
  width: 100%;
  padding: 14px;
  background: #ff6b6b;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s;
}

.btn-logout:hover {
  transform: translateY(-2px);
}

.alert {
  padding: 12px;
  border-radius: 8px;
  margin-top: 20px;
  font-size: 14px;
}

.alert-info {
  background: #e3f2fd;
  color: #1976d2;
  border: 1px solid #90caf9;
}
</style>
