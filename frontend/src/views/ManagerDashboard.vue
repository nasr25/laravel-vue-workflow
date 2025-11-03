<template>
  <div class="manager-dashboard">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
      <div class="container-fluid px-3 px-md-4">
        <a class="navbar-brand fw-bold">
          <i class="bi bi-clipboard-check-fill me-2"></i>
          Manager Dashboard
        </a>
        <div class="d-flex align-items-center">
          <span class="text-white me-3 d-none d-md-inline">
            <i class="bi bi-person-circle me-1"></i>
            {{ authStore.user?.name }}
          </span>
          <button @click="handleLogout" class="btn btn-outline-light btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i>
            <span class="d-none d-sm-inline">Logout</span>
          </button>
        </div>
      </div>
    </nav>

    <!-- Department Info Banner -->
    <div class="bg-light border-bottom">
      <div class="container-fluid px-3 px-md-4 py-3">
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <h6 class="mb-0 me-3">
            <i class="bi bi-building me-2"></i>
            Your Departments:
          </h6>
          <span
            v-for="dept in authStore.user?.managedDepartments"
            :key="dept.id"
            :class="['badge', dept.pivot?.permission === 'approver' ? 'bg-success' : 'bg-secondary']"
          >
            {{ dept.name }} (Step {{ dept.approval_order }})
            <span class="badge bg-light text-dark ms-1" style="font-size: 0.75em;">
              {{ dept.pivot?.permission === 'approver' ? '✓ Can Approve' : '👁️ View Only' }}
            </span>
          </span>
        </div>
        <div v-if="hasViewerPermissions" class="mt-2">
          <small class="text-muted">
            <i class="bi bi-info-circle me-1"></i>
            You have "View Only" access to some departments. You can only take actions on departments where you have "Can Approve" permission.
          </small>
        </div>
      </div>
    </div>

    <div class="container-fluid px-3 px-md-4 py-4">
      <!-- Tabs -->
      <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
          <button
            class="nav-link"
            :class="{ active: activeTab === 'pending' }"
            @click="activeTab = 'pending'; loadPendingIdeas()"
          >
            <i class="bi bi-hourglass-split me-2"></i>
            Pending Approvals
            <span class="badge bg-danger ms-2">{{ pendingIdeas.length }}</span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button
            class="nav-link"
            :class="{ active: activeTab === 'all' }"
            @click="activeTab = 'all'; loadAllIdeas()"
          >
            <i class="bi bi-list-ul me-2"></i>
            All Ideas
          </button>
        </li>
      </ul>

      <!-- Pending Ideas Tab -->
      <div v-if="activeTab === 'pending'">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
              <i class="bi bi-clipboard-check me-2"></i>
              Ideas Waiting for Your Review
            </h5>
          </div>
          <div class="card-body p-3 p-md-4">
            <!-- Loading State -->
            <div v-if="loading" class="text-center py-5">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
              <p class="text-muted mt-3">Loading...</p>
            </div>

            <!-- Empty State -->
            <div v-else-if="pendingIdeas.length === 0" class="text-center py-5">
              <i class="bi bi-check-circle display-1 text-success"></i>
              <p class="text-muted mt-3">No pending ideas for review</p>
            </div>

            <!-- Pending Ideas List -->
            <div v-else>
              <div
                v-for="idea in pendingIdeas"
                :key="idea.id"
                class="idea-review-card card mb-4 border"
              >
                <div class="card-body">
                  <!-- Header -->
                  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3 pb-3 border-bottom">
                    <div>
                      <h5 class="mb-1">
                        <i class="bi bi-lightbulb text-warning me-2"></i>
                        {{ idea.name }}
                      </h5>
                      <small class="text-muted">
                        <i class="bi bi-person-fill me-1"></i>
                        Submitted by: <strong>{{ idea.user?.name }}</strong>
                      </small>
                    </div>
                    <div class="d-flex flex-column gap-2 align-items-end">
                      <span v-if="idea.approvals" class="badge bg-info">
                        Step {{ idea.current_approval_step }}/{{ idea.approvals.length }}
                      </span>
                      <small class="text-muted">
                        <i class="bi bi-calendar-fill me-1"></i>
                        {{ formatDate(idea.created_at) }}
                      </small>
                    </div>
                  </div>

                  <!-- Description -->
                  <div class="mb-3">
                    <h6 class="fw-semibold mb-2">
                      <i class="bi bi-card-text me-1"></i>
                      Description:
                    </h6>
                    <p class="text-muted">{{ idea.description }}</p>
                  </div>

                  <!-- PDF Attachment -->
                  <div v-if="idea.pdf_file_path" class="mb-3">
                    <a
                      :href="`http://localhost:8000/storage/${idea.pdf_file_path}`"
                      target="_blank"
                      class="btn btn-sm btn-outline-danger"
                    >
                      <i class="bi bi-file-earmark-pdf-fill me-1"></i>
                      View PDF Document
                    </a>
                  </div>

                  <!-- Previous Approvals -->
                  <div v-if="idea.approvals && idea.approvals.length > 0" class="mb-4">
                    <h6 class="fw-semibold mb-2">
                      <i class="bi bi-clock-history me-1"></i>
                      Previous Approvals:
                    </h6>
                    <div class="row g-2">
                      <div
                        v-for="approval in idea.approvals.filter((a) => a.status !== 'pending')"
                        :key="approval.id"
                        class="col-12 col-md-6"
                      >
                        <div :class="['p-3', 'rounded', 'border', getApprovalClass(approval.status)]">
                          <div class="d-flex justify-content-between align-items-start mb-2">
                            <strong>
                              <i class="bi bi-building me-1"></i>
                              {{ approval.department?.name }}
                            </strong>
                            <span :class="['badge', getApprovalBadgeClass(approval.status)]">
                              {{ approval.status }}
                            </span>
                          </div>
                          <div v-if="approval.manager" class="text-muted small mb-1">
                            <i class="bi bi-person-fill me-1"></i>
                            by {{ approval.manager.name }}
                          </div>
                          <div v-if="approval.comments" class="text-muted small">
                            <i class="bi bi-chat-square-text me-1"></i>
                            {{ approval.comments }}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Action Form -->
                  <div class="action-form bg-light p-4 rounded border">
                    <h6 class="fw-bold mb-3">
                      <i class="bi bi-hand-thumbs-up me-2"></i>
                      Your Decision:
                    </h6>

                    <div class="mb-3">
                      <label for="comments-{{ idea.id }}" class="form-label fw-semibold">
                        <i class="bi bi-chat-left-text me-1"></i>
                        Comments
                        <small class="text-muted">(optional for approval, required for reject/return)</small>
                      </label>
                      <textarea
                        v-model="actionComments[idea.id]"
                        :id="'comments-' + idea.id"
                        class="form-control"
                        rows="3"
                        placeholder="Add your comments..."
                        maxlength="1000"
                      ></textarea>
                      <div class="form-text">{{ (actionComments[idea.id] || '').length }}/1000 characters</div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                      <button
                        @click="approveIdea(idea.id)"
                        class="btn btn-success"
                        :disabled="processing"
                      >
                        <span v-if="processing">
                          <span class="spinner-border spinner-border-sm me-2"></span>
                          Processing...
                        </span>
                        <span v-else>
                          <i class="bi bi-check-circle-fill me-2"></i>
                          Approve
                        </span>
                      </button>
                      <button
                        @click="rejectIdea(idea.id)"
                        class="btn btn-danger"
                        :disabled="processing || !actionComments[idea.id]"
                      >
                        <i class="bi bi-x-circle-fill me-2"></i>
                        Reject
                      </button>
                      <button
                        @click="returnIdea(idea.id)"
                        class="btn btn-warning"
                        :disabled="processing || !actionComments[idea.id]"
                      >
                        <i class="bi bi-arrow-return-left me-2"></i>
                        Return for Edit
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- All Ideas Tab -->
      <div v-if="activeTab === 'all'">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-info text-white">
            <h5 class="mb-0">
              <i class="bi bi-list-check me-2"></i>
              All Ideas in System
            </h5>
          </div>
          <div class="card-body p-3 p-md-4">
            <!-- Loading State -->
            <div v-if="loading" class="text-center py-5">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
              <p class="text-muted mt-3">Loading...</p>
            </div>

            <!-- All Ideas Table -->
            <div v-else class="table-responsive">
              <table class="table table-hover align-middle">
                <thead class="table-light">
                  <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Submitted By</th>
                    <th>Status</th>
                    <th>Current Step</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="idea in allIdeas" :key="idea.id">
                    <td class="fw-bold text-muted">#{{ idea.id }}</td>
                    <td>
                      <div class="d-flex align-items-center">
                        <i class="bi bi-lightbulb text-warning me-2"></i>
                        {{ idea.name }}
                      </div>
                    </td>
                    <td>{{ idea.user?.name }}</td>
                    <td>
                      <span :class="['badge', getStatusClass(idea.status)]">
                        {{ idea.status }}
                      </span>
                    </td>
                    <td>{{ idea.current_approval_step }}/{{ idea.approvals?.length || 0 }}</td>
                    <td>
                      <small class="text-muted">{{ formatDate(idea.created_at) }}</small>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api from '../services/api'
import type { Idea } from '../types'

const router = useRouter()
const authStore = useAuthStore()

const activeTab = ref<'pending' | 'all'>('pending')
const pendingIdeas = ref<Idea[]>([])
const allIdeas = ref<Idea[]>([])
const loading = ref(false)
const processing = ref(false)
const actionComments = reactive<Record<number, string>>({})

// Check if user has any viewer-only permissions
const hasViewerPermissions = computed(() => {
  return authStore.user?.managedDepartments?.some((dept: any) => dept.pivot?.permission === 'viewer') || false
})

onMounted(async () => {
  await authStore.fetchUser()
  loadPendingIdeas()
})

async function loadPendingIdeas() {
  loading.value = true
  try {
    const response = await api.getPendingIdeas()
    if (response.data.success) {
      pendingIdeas.value = response.data.ideas
    }
  } catch (error) {
    console.error('Failed to load pending ideas:', error)
  } finally {
    loading.value = false
  }
}

async function loadAllIdeas() {
  loading.value = true
  try {
    const response = await api.getAllIdeas()
    if (response.data.success) {
      allIdeas.value = response.data.ideas
    }
  } catch (error) {
    console.error('Failed to load all ideas:', error)
  } finally {
    loading.value = false
  }
}

async function approveIdea(ideaId: number) {
  if (!confirm('Are you sure you want to approve this idea?')) return

  processing.value = true
  try {
    await api.approveIdea(ideaId, actionComments[ideaId] || undefined)
    delete actionComments[ideaId]
    loadPendingIdeas()
  } catch (error: any) {
    alert('Error: ' + (error.response?.data?.message || 'Failed to approve'))
  } finally {
    processing.value = false
  }
}

async function rejectIdea(ideaId: number) {
  if (!actionComments[ideaId]) {
    alert('Please provide comments for rejection')
    return
  }

  if (!confirm('Are you sure you want to reject this idea? This will end the approval process.')) {
    return
  }

  processing.value = true
  try {
    await api.rejectIdea(ideaId, actionComments[ideaId])
    delete actionComments[ideaId]
    loadPendingIdeas()
  } catch (error: any) {
    alert('Error: ' + (error.response?.data?.message || 'Failed to reject'))
  } finally {
    processing.value = false
  }
}

async function returnIdea(ideaId: number) {
  if (!actionComments[ideaId]) {
    alert('Please provide comments explaining what needs to be changed')
    return
  }

  if (!confirm('Return this idea to the user for editing?')) return

  processing.value = true
  try {
    await api.returnIdea(ideaId, actionComments[ideaId])
    delete actionComments[ideaId]
    loadPendingIdeas()
  } catch (error: any) {
    alert('Error: ' + (error.response?.data?.message || 'Failed to return'))
  } finally {
    processing.value = false
  }
}

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
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

function getApprovalClass(status: string) {
  const classes: Record<string, string> = {
    approved: 'border-success bg-success bg-opacity-10',
    rejected: 'border-danger bg-danger bg-opacity-10'
  }
  return classes[status] || 'border-secondary'
}

function getApprovalBadgeClass(status: string) {
  const classes: Record<string, string> = {
    approved: 'bg-success',
    rejected: 'bg-danger'
  }
  return classes[status] || 'bg-secondary'
}

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.manager-dashboard {
  min-height: 100vh;
  background-color: #f8f9fa;
}

.navbar {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.nav-tabs .nav-link {
  color: #495057;
  font-weight: 500;
}

.nav-tabs .nav-link.active {
  color: #667eea;
  font-weight: 600;
}

.nav-tabs .nav-link:hover:not(.active) {
  color: #667eea;
}

.card-header.bg-warning {
  background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%) !important;
}

.card-header.bg-info {
  background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%) !important;
}

.idea-review-card {
  transition: transform 0.2s, box-shadow 0.2s;
}

.idea-review-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.action-form {
  background-color: #f8f9fa;
}

.table th {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.875rem;
  color: #6c757d;
}

.table-hover tbody tr:hover {
  background-color: rgba(0, 0, 0, 0.02);
}

@media (max-width: 768px) {
  .container-fluid {
    padding-left: 1rem !important;
    padding-right: 1rem !important;
  }

  .table {
    font-size: 0.875rem;
  }

  .table td, .table th {
    padding: 0.5rem;
  }

  .action-form {
    padding: 1rem !important;
  }
}
</style>
