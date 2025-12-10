<template>
  <div class="page-container">
    <div class="page-card">
      <div class="header">
        <button @click="goBack" class="btn-back">← {{ $t('common.back') }}</button>
        <h1>{{ $t('nav.myRequests') }}</h1>
      </div>

      <div v-if="error" class="alert alert-error">
        {{ error }}
      </div>

      <div v-if="isLoading" class="loading">
        {{ $t('common.loading') }}
      </div>

      <div v-else-if="requests.length === 0" class="empty-state">
        <p>{{ $t('request.noRequests') }}</p>
        <button @click="createNew" class="btn-primary">{{ $t('request.newRequest') }}</button>
      </div>

      <div v-else class="requests-list">
        <div class="filter-bar">
          <button
            v-for="status in statuses"
            :key="status.value"
            @click="filterStatus = status.value"
            :class="['filter-btn', { active: filterStatus === status.value }]"
          >
            {{ status.label }}
          </button>
        </div>

        <div
          v-for="request in filteredRequests"
          :key="request.id"
          class="request-item"
          @click="viewDetails(request.id)"
        >
          <div class="request-header">
            <h3>{{ request.title }}</h3>
            <span :class="['badge', `badge-${request.status}`]">
              {{ formatStatus(request.status) }}
            </span>
          </div>

          <p class="request-description">{{ truncate(request.description, 150) }}</p>

          <div class="request-meta">
            <span class="meta-item">
              <strong>{{ $t('request.submittedAt') }}:</strong>
              {{ formatDate(request.submitted_at || request.created_at) }}
            </span>
            <span v-if="request.current_department" class="meta-item">
              <strong>{{ $t('request.currentDepartment') }}:</strong> {{ request.current_department.name }}
            </span>
            <span v-if="request.workflow_path" class="meta-item">
              <strong>{{ $t('request.workflowPath') }}:</strong> {{ request.workflow_path.name }}
            </span>
          </div>

          <div v-if="request.attachments && request.attachments.length > 0" class="attachments">
            {{ request.attachments.length }} {{ $t('request.attachments') }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useI18n } from 'vue-i18n'
import axios from 'axios'

const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

const requests = ref([])
const error = ref(null)
const isLoading = ref(true)
const filterStatus = ref('all')

const API_URL = 'http://localhost:8000/api'

const statuses = computed(() => [
  { label: t('common.all'), value: 'all' },
  { label: t('status.draft'), value: 'draft' },
  { label: t('status.pending'), value: 'pending' },
  { label: t('status.in_review'), value: 'in_review' },
  { label: t('status.need_more_details'), value: 'need_more_details' },
  { label: t('status.approved'), value: 'approved' },
  { label: t('status.rejected'), value: 'rejected' },
  { label: t('status.completed'), value: 'completed' }
])

const filteredRequests = computed(() => {
  if (filterStatus.value === 'all') {
    return requests.value
  }
  return requests.value.filter(r => r.status === filterStatus.value)
})

onMounted(async () => {
  await loadRequests()
})

const loadRequests = async () => {
  try {
    isLoading.value = true
    const response = await axios.get(`${API_URL}/requests`, {
      headers: {
        Authorization: `Bearer ${authStore.token}`
      }
    })

    requests.value = response.data.requests
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load requests'
  } finally {
    isLoading.value = false
  }
}

const goBack = () => {
  router.push('/dashboard')
}

const createNew = () => {
  router.push('/requests/new')
}

const viewDetails = (id) => {
  router.push(`/requests/${id}`)
}

const formatStatus = (status) => {
  return t(`status.${status}`)
}

const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const truncate = (text, length) => {
  if (!text) return ''
  return text.length > length ? text.substring(0, length) + '...' : text
}
</script>

<style scoped>
.page-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 20px;
}

.page-card {
  background: white;
  border-radius: 20px;
  padding: 40px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  max-width: 1000px;
  margin: 0 auto;
}

.header {
  display: flex;
  align-items: center;
  margin-bottom: 30px;
  gap: 15px;
}

.btn-back {
  padding: 8px 16px;
  background: #f5f5f5;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.2s;
}

.btn-back:hover {
  background: #e0e0e0;
}

h1 {
  color: #333;
  font-size: 28px;
  margin: 0;
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

.loading {
  text-align: center;
  padding: 40px;
  color: #666;
  font-size: 16px;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
}

.empty-state p {
  color: #666;
  font-size: 16px;
  margin-bottom: 20px;
}

.filter-bar {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.filter-btn {
  padding: 8px 16px;
  background: #f5f5f5;
  border: 2px solid transparent;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
  color: #666;
}

.filter-btn:hover {
  background: #e0e0e0;
}

.filter-btn.active {
  background: #667eea;
  color: white;
  border-color: #667eea;
}

.requests-list {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.request-item {
  padding: 20px;
  border: 2px solid #e0e0e0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.3s;
  background: white;
}

.request-item:hover {
  border-color: #667eea;
  transform: translateY(-2px);
  background: #f8f9ff;
}

.request-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.request-header h3 {
  color: #333;
  font-size: 18px;
  margin: 0;
}

.badge {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.badge-draft {
  background: #e0e0e0;
  color: #666;
}

.badge-pending {
  background: #fff3cd;
  color: #856404;
}

.badge-in_review {
  background: #cfe2ff;
  color: #084298;
}

.badge-in_progress {
  background: #d1ecf1 !important;
  color: #0c5460 !important;
}

.badge-need_more_details {
  background: #f8d7da;
  color: #842029;
}

.badge-approved {
  background: #d1e7dd;
  color: #0f5132;
}

.badge-rejected {
  background: #f8d7da;
  color: #842029;
}

.badge-completed {
  background: #d1e7dd;
  color: #0f5132;
}

.request-description {
  color: #666;
  font-size: 14px;
  margin-bottom: 15px;
  line-height: 1.5;
}

.request-meta {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  font-size: 13px;
  color: #999;
}

.meta-item strong {
  color: #666;
}

.attachments {
  margin-top: 10px;
  font-size: 12px;
  color: #667eea;
  font-weight: 500;
}

.btn-primary {
  padding: 14px 28px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s;
}

.btn-primary:hover {
  transform: translateY(-2px);
}
</style>
