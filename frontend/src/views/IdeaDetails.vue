<template>
  <div class="idea-details">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
      <div class="container-fluid px-3 px-md-4">
        <a class="navbar-brand fw-bold">
          <i class="bi bi-file-earmark-text-fill me-2"></i>
          Idea Details
        </a>
        <div class="d-flex align-items-center">
          <button @click="router.back()" class="btn btn-outline-light btn-sm me-2">
            <i class="bi bi-arrow-left me-1"></i>
            Back
          </button>
          <button @click="handleLogout" class="btn btn-outline-light btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i>
            Logout
          </button>
        </div>
      </div>
    </nav>

    <div class="container-fluid px-3 px-md-4 py-4">
      <!-- Loading State -->
      <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="text-muted mt-3">Loading idea details...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        {{ error }}
      </div>

      <!-- Idea Details -->
      <div v-else-if="idea" class="row">
        <div class="col-12">
          <!-- Main Info Card -->
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-primary text-white">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                  <h4 class="mb-1">
                    <i class="bi bi-lightbulb-fill me-2"></i>
                    {{ idea.name }}
                  </h4>
                  <small>ID: #{{ idea.id }}</small>
                </div>
                <span :class="['badge', 'fs-6', getStatusClass(idea.status)]">
                  {{ idea.status }}
                </span>
              </div>
            </div>
            <div class="card-body p-3 p-md-4">
              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <h6 class="text-muted mb-1">
                    <i class="bi bi-person-fill me-1"></i>
                    Submitted By
                  </h6>
                  <p class="mb-0 fw-semibold">{{ idea.user?.name }}</p>
                </div>
                <div class="col-md-6">
                  <h6 class="text-muted mb-1">
                    <i class="bi bi-calendar-fill me-1"></i>
                    Submitted On
                  </h6>
                  <p class="mb-0 fw-semibold">{{ formatDate(idea.created_at) }}</p>
                </div>
                <div v-if="idea.current_approval_step > 0 && idea.approvals" class="col-md-6">
                  <h6 class="text-muted mb-1">
                    <i class="bi bi-diagram-3-fill me-1"></i>
                    Current Step
                  </h6>
                  <p class="mb-0 fw-semibold">
                    Step {{ idea.current_approval_step }} of {{ idea.approvals.length }}
                  </p>
                </div>
                <div v-if="idea.approvals && idea.approvals.length > 0" class="col-md-6">
                  <h6 class="text-muted mb-1">
                    <i class="bi bi-graph-up me-1"></i>
                    Progress
                  </h6>
                  <div class="progress" style="height: 25px;">
                    <div
                      class="progress-bar bg-success"
                      role="progressbar"
                      :style="{ width: getProgressWidth(idea.approvals) + '%' }"
                    >
                      {{ getProgressWidth(idea.approvals) }}%
                    </div>
                  </div>
                  <small class="text-muted">
                    {{ getApprovedCount(idea.approvals) }}/{{ idea.approvals.length }} approved
                  </small>
                </div>
              </div>

              <hr class="my-4" />

              <div class="mb-3">
                <h6 class="text-muted mb-2">
                  <i class="bi bi-card-text me-1"></i>
                  Description
                </h6>
                <p class="mb-0" style="white-space: pre-wrap;">{{ idea.description }}</p>
              </div>

              <div v-if="idea.pdf_file_path" class="mt-3">
                <h6 class="text-muted mb-2">
                  <i class="bi bi-file-earmark-pdf-fill me-1"></i>
                  Attachment
                </h6>
                <a
                  :href="`http://localhost:8000/storage/${idea.pdf_file_path}`"
                  target="_blank"
                  class="btn btn-outline-danger btn-sm"
                >
                  <i class="bi bi-download me-1"></i>
                  View PDF Attachment
                </a>
              </div>
            </div>
          </div>

          <!-- Approval Timeline -->
          <div v-if="idea.approvals && idea.approvals.length > 0" class="card shadow-sm border-0">
            <div class="card-header bg-success text-white">
              <h5 class="mb-0">
                <i class="bi bi-clock-history me-2"></i>
                Approval Timeline & History
              </h5>
            </div>
            <div class="card-body p-3 p-md-4">
              <div class="timeline">
                <div
                  v-for="(approval, index) in sortedApprovals"
                  :key="approval.id"
                  class="timeline-item mb-4"
                  :class="getTimelineClass(approval.status)"
                >
                  <div class="timeline-marker">
                    <i :class="getTimelineIcon(approval.status)"></i>
                  </div>
                  <div class="timeline-content">
                    <div class="card border">
                      <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                          <div>
                            <h6 class="mb-1 fw-bold">
                              <i class="bi bi-building me-1"></i>
                              {{ approval.department?.name }}
                              <span class="badge bg-secondary ms-2">Step {{ approval.step }}</span>
                            </h6>
                            <small class="text-muted">
                              Approval Order: {{ approval.department?.approval_order }}
                            </small>
                          </div>
                          <span :class="['badge', getApprovalBadgeClass(approval.status)]">
                            {{ approval.status }}
                          </span>
                        </div>

                        <div v-if="approval.status !== 'pending'" class="mt-3">
                          <div class="row g-2 text-sm">
                            <div class="col-md-6">
                              <small class="text-muted d-block">
                                <i class="bi bi-person-badge-fill me-1"></i>
                                Reviewed By:
                              </small>
                              <strong>{{ approval.manager?.name || 'N/A' }}</strong>
                            </div>
                            <div class="col-md-6">
                              <small class="text-muted d-block">
                                <i class="bi bi-calendar-event-fill me-1"></i>
                                Action Date:
                              </small>
                              <strong>{{ formatDateTime(approval.updated_at) }}</strong>
                            </div>
                          </div>

                          <div v-if="approval.comments" class="mt-3 p-3 bg-light rounded">
                            <small class="text-muted d-block mb-1">
                              <i class="bi bi-chat-left-quote-fill me-1"></i>
                              Manager Comments:
                            </small>
                            <p class="mb-0" style="white-space: pre-wrap;">{{ approval.comments }}</p>
                          </div>
                        </div>

                        <div v-else class="mt-3">
                          <p class="text-muted mb-0 fst-italic">
                            <i class="bi bi-hourglass-split me-1"></i>
                            Waiting for review...
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="sortedApprovals.length === 0" class="text-center py-4">
                <i class="bi bi-inbox display-4 text-muted"></i>
                <p class="text-muted mt-3">No approval history available</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api from '../services/api'
import type { Idea } from '../types'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const idea = ref<Idea | null>(null)
const loading = ref(false)
const error = ref('')

onMounted(() => {
  loadIdea()
})

const sortedApprovals = computed(() => {
  if (!idea.value?.approvals) return []
  return [...idea.value.approvals].sort((a, b) => a.step - b.step)
})

async function loadIdea() {
  loading.value = true
  error.value = ''
  try {
    const ideaId = parseInt(route.params.id as string)
    const response = await api.getIdea(ideaId)
    if (response.data.success) {
      idea.value = response.data.idea
    } else {
      error.value = 'Failed to load idea details'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load idea details'
  } finally {
    loading.value = false
  }
}

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

function formatDateTime(date: string) {
  return new Date(date).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function getStatusClass(status: string) {
  const classes: Record<string, string> = {
    draft: 'bg-warning text-dark',
    pending: 'bg-info',
    approved: 'bg-success',
    rejected: 'bg-danger',
    returned: 'bg-warning text-dark'
  }
  return classes[status] || 'bg-secondary'
}

function getApprovalBadgeClass(status: string) {
  const classes: Record<string, string> = {
    pending: 'bg-info',
    approved: 'bg-success',
    rejected: 'bg-danger'
  }
  return classes[status] || 'bg-secondary'
}

function getTimelineClass(status: string) {
  return status === 'approved' ? 'timeline-success' : status === 'rejected' ? 'timeline-danger' : 'timeline-pending'
}

function getTimelineIcon(status: string) {
  if (status === 'approved') return 'bi bi-check-circle-fill text-success'
  if (status === 'rejected') return 'bi bi-x-circle-fill text-danger'
  return 'bi bi-clock-fill text-info'
}

function getApprovedCount(approvals: any[]) {
  return approvals.filter(a => a.status === 'approved').length
}

function getProgressWidth(approvals: any[]) {
  const approvedCount = getApprovedCount(approvals)
  return approvals.length > 0 ? Math.round((approvedCount / approvals.length) * 100) : 0
}

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.idea-details {
  min-height: 100vh;
  background-color: #f8f9fa;
}

.navbar {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.card-header.bg-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.card-header.bg-success {
  background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
}

.timeline {
  position: relative;
  padding-left: 30px;
}

.timeline::before {
  content: '';
  position: absolute;
  left: 8px;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #dee2e6;
}

.timeline-item {
  position: relative;
}

.timeline-marker {
  position: absolute;
  left: -30px;
  top: 0;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: white;
  border: 2px solid #dee2e6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  z-index: 1;
}

.timeline-success .timeline-marker {
  background: #d4edda;
  border-color: #28a745;
}

.timeline-danger .timeline-marker {
  background: #f8d7da;
  border-color: #dc3545;
}

.timeline-pending .timeline-marker {
  background: #d1ecf1;
  border-color: #17a2b8;
}

.timeline-content {
  margin-left: 10px;
}

.timeline-item:last-child .timeline-content {
  padding-bottom: 0;
}

@media (max-width: 768px) {
  .container-fluid {
    padding-left: 1rem !important;
    padding-right: 1rem !important;
  }

  .timeline {
    padding-left: 25px;
  }

  .timeline-marker {
    left: -25px;
    width: 16px;
    height: 16px;
    font-size: 10px;
  }
}
</style>
