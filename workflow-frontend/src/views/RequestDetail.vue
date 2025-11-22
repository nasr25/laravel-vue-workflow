<template>
  <div class="page-container">
    <div class="page-card">
      <div class="header">
        <button @click="goBack" class="btn-back">← Back</button>
        <h1>Request Details</h1>
      </div>

      <div v-if="error" class="alert alert-error">
        {{ error }}
      </div>

      <div v-if="isLoading" class="loading">
        Loading request details...
      </div>

      <div v-else-if="request" class="request-details">
        <!-- Request Info -->
        <div class="info-section">
          <div class="section-header">
            <h2>{{ request.title }}</h2>
            <span :class="['badge', `badge-${request.status}`]">
              {{ formatStatus(request.status) }}
            </span>
          </div>

          <div class="info-grid">
            <div class="info-item">
              <strong>Submitted By:</strong>
              <span>{{ request.user?.name }}</span>
            </div>
            <div class="info-item">
              <strong>Email:</strong>
              <span>{{ request.user?.email }}</span>
            </div>
            <div class="info-item">
              <strong>Submitted At:</strong>
              <span>{{ formatDate(request.submitted_at || request.created_at) }}</span>
            </div>
            <div v-if="request.completed_at" class="info-item">
              <strong>Completed At:</strong>
              <span>{{ formatDate(request.completed_at) }}</span>
            </div>
            <div v-if="request.current_department" class="info-item">
              <strong>Current Department:</strong>
              <span>{{ request.current_department.name }}</span>
            </div>
            <div v-if="request.workflow_path" class="info-item">
              <strong>Workflow Path:</strong>
              <span>{{ request.workflow_path.name }}</span>
            </div>
          </div>

          <div class="description-section">
            <strong>Description:</strong>
            <p>{{ request.description }}</p>
          </div>

          <div v-if="request.additional_details" class="description-section">
            <strong>Additional Details:</strong>
            <p>{{ request.additional_details }}</p>
          </div>

          <div v-if="request.rejection_reason" class="rejection-reason">
            <strong>Rejection Reason:</strong>
            <p>{{ request.rejection_reason }}</p>
          </div>

          <!-- Attachments -->
          <div v-if="request.attachments && request.attachments.length > 0" class="attachments-section">
            <strong>Attachments:</strong>
            <div class="attachments-list">
              <div v-for="attachment in request.attachments" :key="attachment.id" class="attachment-item">
                <span class="attachment-icon">📎</span>
                <span class="attachment-name">{{ attachment.original_name }}</span>
                <span class="attachment-size">({{ formatFileSize(attachment.file_size) }})</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Workflow History -->
        <div class="history-section">
          <h3>📋 Workflow History</h3>

          <div v-if="!request.transitions || request.transitions.length === 0" class="empty-history">
            <p>No workflow history yet.</p>
          </div>

          <div v-else class="timeline">
            <div v-for="(transition, index) in request.transitions" :key="transition.id" class="timeline-item">
              <div class="timeline-marker">
                <div :class="['marker-dot', `marker-${transition.action}`]"></div>
                <div v-if="index < request.transitions.length - 1" class="marker-line"></div>
              </div>

              <div class="timeline-content">
                <div class="timeline-header">
                  <span class="timeline-action">
                    {{ formatAction(transition.action) }}
                  </span>
                  <span class="timeline-date">
                    {{ formatDateTime(transition.created_at) }}
                  </span>
                </div>

                <div class="timeline-details">
                  <div v-if="transition.actioned_by" class="detail-item">
                    <strong>By:</strong> {{ transition.actioned_by.name }} ({{ transition.actioned_by.email }})
                  </div>

                  <div v-if="transition.from_department_id" class="detail-item">
                    <strong>From:</strong> {{ getDepartmentName(transition.from_department_id) }}
                  </div>

                  <div v-if="transition.to_department" class="detail-item">
                    <strong>To:</strong> {{ transition.to_department.name }}
                  </div>

                  <div v-if="transition.to_user_id" class="detail-item">
                    <strong>Assigned to:</strong> User ID {{ transition.to_user_id }}
                  </div>

                  <div class="detail-item">
                    <strong>Status Change:</strong>
                    <span :class="['mini-badge', `badge-${transition.from_status}`]">{{ formatStatus(transition.from_status) }}</span>
                    →
                    <span :class="['mini-badge', `badge-${transition.to_status}`]">{{ formatStatus(transition.to_status) }}</span>
                  </div>

                  <div v-if="transition.comments" class="transition-comments">
                    <strong>Comments:</strong>
                    <p>{{ transition.comments }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import axios from 'axios'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const request = ref(null)
const error = ref(null)
const isLoading = ref(true)

const API_URL = 'http://localhost:8000/api'

onMounted(async () => {
  await loadRequest()
})

const loadRequest = async () => {
  try {
    isLoading.value = true
    error.value = null

    const requestId = route.params.id
    const response = await axios.get(`${API_URL}/requests/${requestId}`, {
      headers: {
        Authorization: `Bearer ${authStore.token}`
      }
    })

    request.value = response.data.request
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load request details'
  } finally {
    isLoading.value = false
  }
}

const goBack = () => {
  router.push('/requests')
}

const formatStatus = (status) => {
  if (!status) return 'N/A'
  return status
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

const formatAction = (action) => {
  if (!action) return 'N/A'
  const actionMap = {
    'assign_path': 'Path Assigned',
    'assign': 'Assigned to Employee',
    'complete': 'Completed/Returned',
    'reject': 'Rejected',
    'request_details': 'More Details Requested',
    'return_to_department': 'Returned to Department',
    'submit': 'Submitted'
  }
  return actionMap[action] || action.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
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

const formatDateTime = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatFileSize = (bytes) => {
  if (!bytes) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

const getDepartmentName = (deptId) => {
  // Try to find department name from transitions
  const transition = request.value?.transitions?.find(t => t.from_department_id === deptId)
  return transition?.from_department?.name || `Department ${deptId}`
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
  max-width: 1200px;
  margin: 0 auto;
}

.header {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 30px;
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

/* Request Details */
.request-details {
  display: flex;
  flex-direction: column;
  gap: 30px;
}

.info-section {
  background: #f8f9fa;
  padding: 25px;
  border-radius: 12px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.section-header h2 {
  color: #333;
  font-size: 24px;
  margin: 0;
}

.badge {
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 13px;
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

.badge-completed {
  background: #d1e7dd;
  color: #0f5132;
}

.badge-rejected {
  background: #f8d7da;
  color: #842029;
}

.badge-need_more_details {
  background: #fff3cd;
  color: #856404;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 15px;
  margin-bottom: 20px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.info-item strong {
  color: #666;
  font-size: 13px;
}

.info-item span {
  color: #333;
  font-size: 15px;
}

.description-section {
  margin-top: 15px;
}

.description-section strong {
  color: #666;
  font-size: 14px;
  display: block;
  margin-bottom: 8px;
}

.description-section p {
  color: #333;
  line-height: 1.6;
  margin: 0;
}

.rejection-reason {
  background: #fff3cd;
  padding: 15px;
  border-radius: 8px;
  border: 1px solid #ffc107;
  margin-top: 15px;
}

.rejection-reason strong {
  color: #856404;
  display: block;
  margin-bottom: 8px;
}

.rejection-reason p {
  color: #856404;
  margin: 0;
}

.attachments-section {
  margin-top: 20px;
}

.attachments-section strong {
  color: #666;
  font-size: 14px;
  display: block;
  margin-bottom: 10px;
}

.attachments-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.attachment-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px;
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 6px;
}

.attachment-icon {
  font-size: 18px;
}

.attachment-name {
  color: #333;
  font-size: 14px;
  flex: 1;
}

.attachment-size {
  color: #999;
  font-size: 12px;
}

/* Workflow History */
.history-section {
  background: white;
  border: 2px solid #e0e0e0;
  border-radius: 12px;
  padding: 25px;
}

.history-section h3 {
  color: #333;
  font-size: 20px;
  margin: 0 0 25px 0;
}

.empty-history {
  text-align: center;
  padding: 40px;
  color: #999;
}

.timeline {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.timeline-item {
  display: flex;
  gap: 20px;
  position: relative;
}

.timeline-marker {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 30px;
  flex-shrink: 0;
}

.marker-dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 3px solid #667eea;
  background: white;
  z-index: 1;
}

.marker-dot.marker-assign_path {
  border-color: #4caf50;
}

.marker-dot.marker-assign {
  border-color: #2196f3;
}

.marker-dot.marker-complete {
  border-color: #4caf50;
}

.marker-dot.marker-reject {
  border-color: #ff6b6b;
}

.marker-dot.marker-return_to_department {
  border-color: #ff9800;
}

.marker-line {
  width: 2px;
  flex: 1;
  background: #e0e0e0;
  margin-top: 5px;
  margin-bottom: 5px;
}

.timeline-content {
  flex: 1;
  padding-bottom: 30px;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.timeline-action {
  color: #667eea;
  font-weight: 600;
  font-size: 16px;
}

.timeline-date {
  color: #999;
  font-size: 13px;
}

.timeline-details {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.detail-item {
  font-size: 14px;
  color: #666;
}

.detail-item strong {
  color: #333;
  margin-right: 5px;
}

.mini-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  margin: 0 5px;
}

.transition-comments {
  margin-top: 8px;
  padding-top: 12px;
  border-top: 1px solid #e0e0e0;
}

.transition-comments strong {
  display: block;
  margin-bottom: 6px;
  color: #333;
}

.transition-comments p {
  color: #555;
  font-style: italic;
  margin: 0;
  line-height: 1.5;
}
</style>
