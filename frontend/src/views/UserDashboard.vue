<template>
  <div class="user-dashboard">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
      <div class="container-fluid px-3 px-md-4">
        <a class="navbar-brand fw-bold">
          <i class="bi bi-lightbulb-fill me-2"></i>
          My Ideas
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

    <div class="container-fluid px-3 px-md-4 py-4">
      <!-- Idea Submission Form -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
              <h5 class="mb-0">
                <i class="bi bi-plus-circle-fill me-2"></i>
                {{ editingIdea ? 'Edit Idea' : 'Submit New Idea' }}
              </h5>
            </div>
            <div class="card-body p-3 p-md-4">
              <form @submit.prevent="submitIdea">
                <div class="mb-3">
                  <label for="ideaName" class="form-label fw-semibold">
                    <i class="bi bi-pencil-fill me-1"></i>
                    Idea Name *
                  </label>
                  <input
                    v-model="form.name"
                    type="text"
                    class="form-control"
                    id="ideaName"
                    placeholder="Enter a descriptive name for your idea"
                    maxlength="255"
                  />
                </div>

                <div class="mb-3">
                  <label for="ideaDescription" class="form-label fw-semibold">
                    <i class="bi bi-card-text me-1"></i>
                    Description *
                  </label>
                  <textarea
                    v-model="form.description"
                    class="form-control"
                    id="ideaDescription"
                    rows="5"
                    placeholder="Describe your idea in detail..."
                    maxlength="5000"
                  ></textarea>
                  <div class="form-text">{{ form.description.length }}/5000 characters</div>
                </div>

                <div class="mb-3">
                  <label for="pdfFile" class="form-label fw-semibold">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i>
                    PDF Attachment (Optional)
                  </label>
                  <input
                    type="file"
                    class="form-control"
                    id="pdfFile"
                    accept=".pdf"
                    @change="handleFileChange"
                  />
                  <div class="form-text">
                    <small v-if="editingIdea?.pdf_file_path" class="text-success">
                      <i class="bi bi-check-circle-fill me-1"></i>
                      Current file: {{ editingIdea.pdf_file_path }}
                    </small>
                    <small v-else>Maximum file size: 10MB. PDF format only.</small>
                  </div>
                </div>

                <div class="d-flex gap-2">
                  <button type="submit" class="btn btn-primary" :disabled="loading">
                    <span v-if="loading">
                      <span class="spinner-border spinner-border-sm me-2"></span>
                      Saving...
                    </span>
                    <span v-else>
                      <i class="bi bi-save-fill me-2"></i>
                      {{ editingIdea ? 'Update Idea' : 'Create Draft' }}
                    </span>
                  </button>
                  <button
                    v-if="editingIdea"
                    type="button"
                    @click="cancelEdit"
                    class="btn btn-secondary"
                  >
                    <i class="bi bi-x-circle me-1"></i>
                    Cancel
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- My Ideas List -->
      <div class="row">
        <div class="col-12">
          <div class="card shadow-sm border-0">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap">
              <h5 class="mb-0">
                <i class="bi bi-list-ul me-2"></i>
                My Submitted Ideas
              </h5>
              <ul class="nav nav-pills">
                <li class="nav-item">
                  <a
                    class="nav-link"
                    :class="{ active: viewMode === 'cards' }"
                    @click="viewMode = 'cards'"
                    href="javascript:void(0)"
                  >
                    <i class="bi bi-grid-3x2-gap-fill me-1"></i>
                    Cards
                  </a>
                </li>
                <li class="nav-item">
                  <a
                    class="nav-link"
                    :class="{ active: viewMode === 'table' }"
                    @click="viewMode = 'table'"
                    href="javascript:void(0)"
                  >
                    <i class="bi bi-table me-1"></i>
                    Table
                  </a>
                </li>
              </ul>
            </div>
            <div class="card-body p-3 p-md-4">
              <!-- Loading State -->
              <div v-if="ideasLoading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted mt-3">Loading ideas...</p>
              </div>

              <!-- Empty State -->
              <div v-else-if="ideas.length === 0" class="text-center py-5">
                <i class="bi bi-inbox display-1 text-muted"></i>
                <p class="text-muted mt-3">No ideas yet. Create your first idea above!</p>
              </div>

              <!-- Table View -->
              <div v-else-if="viewMode === 'table'" class="table-responsive">
                <table class="table table-hover align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>ID</th>
                      <th>Name</th>
                      <th>Status</th>
                      <th>Step</th>
                      <th>Created</th>
                      <th>Progress</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="idea in ideas" :key="idea.id">
                      <td class="fw-bold text-muted">#{{ idea.id }}</td>
                      <td>
                        <div class="d-flex align-items-center">
                          <i class="bi bi-lightbulb text-warning me-2"></i>
                          <div>
                            <div class="fw-semibold">{{ idea.name }}</div>
                            <small class="text-muted d-block text-truncate" style="max-width: 300px;">
                              {{ idea.description }}
                            </small>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span :class="['badge', getStatusClass(idea.status)]">
                          {{ idea.status }}
                        </span>
                      </td>
                      <td>
                        <span v-if="idea.current_approval_step > 0" class="badge bg-info">
                          {{ idea.current_approval_step }}/4
                        </span>
                        <span v-else class="text-muted">-</span>
                      </td>
                      <td>
                        <small class="text-muted">{{ formatDate(idea.created_at) }}</small>
                      </td>
                      <td>
                        <div v-if="idea.approvals && idea.approvals.length > 0" style="min-width: 200px;">
                          <div class="progress" style="height: 20px;">
                            <div
                              class="progress-bar bg-success"
                              role="progressbar"
                              :style="{ width: getProgressWidth(idea.approvals) + '%' }"
                            >
                              {{ getProgressWidth(idea.approvals) }}%
                            </div>
                          </div>
                          <small class="text-muted">
                            {{ getApprovedCount(idea.approvals) }}/4 approved
                          </small>
                        </div>
                        <span v-else class="text-muted">Not submitted</span>
                      </td>
                      <td>
                        <div class="btn-group btn-group-sm" role="group">
                          <button
                            v-if="idea.status === 'draft' || idea.status === 'returned'"
                            @click="startEdit(idea)"
                            class="btn btn-outline-primary"
                            title="Edit"
                          >
                            <i class="bi bi-pencil-square"></i>
                          </button>
                          <button
                            v-if="idea.status === 'draft' || idea.status === 'returned'"
                            @click="submitForApproval(idea.id)"
                            class="btn btn-outline-success"
                            title="Submit"
                          >
                            <i class="bi bi-send-fill"></i>
                          </button>
                          <button
                            v-if="idea.status === 'draft'"
                            @click="deleteIdea(idea.id)"
                            class="btn btn-outline-danger"
                            title="Delete"
                          >
                            <i class="bi bi-trash-fill"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Cards View -->
              <div v-else class="ideas-list">
                <div
                  v-for="idea in ideas"
                  :key="idea.id"
                  class="idea-card card mb-3 border"
                >
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                      <h5 class="card-title mb-0">
                        <i class="bi bi-lightbulb me-2 text-warning"></i>
                        {{ idea.name }}
                      </h5>
                      <div class="d-flex gap-2 align-items-center">
                        <span :class="['badge', getStatusClass(idea.status)]">
                          {{ idea.status }}
                        </span>
                        <span v-if="idea.current_approval_step > 0" class="badge bg-info">
                          Step {{ idea.current_approval_step }}/4
                        </span>
                      </div>
                    </div>

                    <p class="card-text text-muted">{{ idea.description }}</p>

                    <div class="row g-2 mb-3 text-sm">
                      <div class="col-12 col-md-6">
                        <small class="text-muted">
                          <i class="bi bi-calendar-fill me-1"></i>
                          Created: {{ formatDate(idea.created_at) }}
                        </small>
                      </div>
                      <div v-if="idea.pdf_file_path" class="col-12 col-md-6">
                        <small>
                          <i class="bi bi-file-earmark-pdf-fill me-1 text-danger"></i>
                          <a :href="`http://localhost:8000/storage/${idea.pdf_file_path}`" target="_blank" class="text-decoration-none">
                            View PDF
                          </a>
                        </small>
                      </div>
                    </div>

                    <!-- Approval Progress -->
                    <div v-if="idea.approvals && idea.approvals.length > 0" class="mb-3">
                      <h6 class="fw-semibold mb-2">
                        <i class="bi bi-graph-up me-1"></i>
                        Approval Progress:
                      </h6>
                      <div class="row g-2">
                        <div
                          v-for="approval in idea.approvals"
                          :key="approval.id"
                          class="col-12 col-sm-6 col-lg-3"
                        >
                          <div :class="['approval-step', 'p-2', 'rounded', 'border', getApprovalClass(approval.status)]">
                            <strong class="d-block">
                              <i class="bi bi-building me-1"></i>
                              {{ approval.department?.name }}
                            </strong>
                            <span class="badge mt-1" :class="getApprovalBadgeClass(approval.status)">
                              {{ approval.status }}
                            </span>
                            <small v-if="approval.comments" class="d-block mt-1 text-muted">
                              {{ approval.comments }}
                            </small>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-2 flex-wrap">
                      <button
                        v-if="idea.status === 'draft' || idea.status === 'returned'"
                        @click="startEdit(idea)"
                        class="btn btn-sm btn-outline-primary"
                      >
                        <i class="bi bi-pencil-square me-1"></i>
                        Edit
                      </button>
                      <button
                        v-if="idea.status === 'draft' || idea.status === 'returned'"
                        @click="submitForApproval(idea.id)"
                        class="btn btn-sm btn-success"
                      >
                        <i class="bi bi-send-fill me-1"></i>
                        Submit for Approval
                      </button>
                      <button
                        v-if="idea.status === 'draft'"
                        @click="deleteIdea(idea.id)"
                        class="btn btn-sm btn-danger"
                      >
                        <i class="bi bi-trash-fill me-1"></i>
                        Delete
                      </button>
                    </div>
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

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api from '../services/api'
import type { Idea } from '../types'

const router = useRouter()
const authStore = useAuthStore()

const ideas = ref<Idea[]>([])
const ideasLoading = ref(false)
const loading = ref(false)
const editingIdea = ref<Idea | null>(null)
const viewMode = ref<'cards' | 'table'>('table')

const form = ref({
  name: '',
  description: '',
  file: null as File | null,
})

onMounted(() => {
  loadIdeas()
})

async function loadIdeas() {
  ideasLoading.value = true
  try {
    const response = await api.getMyIdeas()
    if (response.data.success) {
      ideas.value = response.data.ideas
    }
  } catch (error) {
    console.error('Failed to load ideas:', error)
  } finally {
    ideasLoading.value = false
  }
}

function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]

    // Security: Validate file type
    if (file.type !== 'application/pdf') {
      alert('Only PDF files are allowed')
      target.value = ''
      return
    }

    // Security: Validate file size (10MB max)
    if (file.size > 10 * 1024 * 1024) {
      alert('File size must not exceed 10MB')
      target.value = ''
      return
    }

    form.value.file = file
  }
}

async function submitIdea() {
  loading.value = true

  try {
    // Allow saving drafts with minimal content
    // Backend will validate completeness when submitting for approval
    const formData = new FormData()
    formData.append('name', form.value.name.trim() || 'Untitled')
    formData.append('description', form.value.description.trim() || '')
    if (form.value.file) {
      formData.append('pdf_file', form.value.file)
    }

    if (editingIdea.value) {
      await api.updateIdea(editingIdea.value.id, formData)
      alert('Idea updated successfully!')
      editingIdea.value = null
    } else {
      await api.createIdea(formData)
      alert('Idea created successfully!')
    }

    // Reset form
    form.value = { name: '', description: '', file: null }
    // Clear file input
    const fileInput = document.getElementById('pdfFile') as HTMLInputElement
    if (fileInput) fileInput.value = ''

    loadIdeas()
  } catch (error: any) {
    alert('Error: ' + (error.response?.data?.message || 'Failed to save idea'))
  } finally {
    loading.value = false
  }
}

function startEdit(idea: Idea) {
  editingIdea.value = idea
  form.value.name = idea.name
  form.value.description = idea.description
  form.value.file = null
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function cancelEdit() {
  editingIdea.value = null
  form.value = { name: '', description: '', file: null }
  const fileInput = document.getElementById('pdfFile') as HTMLInputElement
  if (fileInput) fileInput.value = ''
}

async function submitForApproval(ideaId: number) {
  if (!confirm('Are you sure you want to submit this idea for approval?')) return

  try {
    await api.submitIdea(ideaId)
    alert('Idea submitted for approval!')
    loadIdeas()
  } catch (error: any) {
    alert('Error: ' + (error.response?.data?.message || 'Failed to submit idea'))
  }
}

async function deleteIdea(ideaId: number) {
  if (!confirm('Are you sure you want to delete this idea? This action cannot be undone.')) return

  try {
    await api.deleteIdea(ideaId)
    alert('Idea deleted!')
    loadIdeas()
  } catch (error: any) {
    alert('Error: ' + (error.response?.data?.message || 'Failed to delete idea'))
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
    pending: 'border-info bg-light',
    approved: 'border-success bg-success bg-opacity-10',
    rejected: 'border-danger bg-danger bg-opacity-10'
  }
  return classes[status] || 'border-secondary'
}

function getApprovalBadgeClass(status: string) {
  const classes: Record<string, string> = {
    pending: 'bg-info',
    approved: 'bg-success',
    rejected: 'bg-danger'
  }
  return classes[status] || 'bg-secondary'
}

function getApprovedCount(approvals: any[]) {
  return approvals.filter(a => a.status === 'approved').length
}

function getProgressWidth(approvals: any[]) {
  const approvedCount = getApprovedCount(approvals)
  return Math.round((approvedCount / 4) * 100)
}

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.user-dashboard {
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

.btn-primary {
  background-color: #667eea;
  border-color: #667eea;
}

.btn-primary:hover:not(:disabled) {
  background-color: #5568d3;
  border-color: #5568d3;
}

.idea-card {
  transition: transform 0.2s, box-shadow 0.2s;
}

.idea-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.nav-pills .nav-link {
  color: rgba(255, 255, 255, 0.7);
  background-color: transparent;
  border: 1px solid rgba(255, 255, 255, 0.3);
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
}

.nav-pills .nav-link.active {
  color: #28a745;
  background-color: white;
  border-color: white;
}

.nav-pills .nav-link:hover:not(.active) {
  color: white;
  background-color: rgba(255, 255, 255, 0.1);
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

  .nav-pills {
    margin-top: 0.5rem;
  }

  .table {
    font-size: 0.875rem;
  }

  .table td, .table th {
    padding: 0.5rem;
  }
}
</style>
