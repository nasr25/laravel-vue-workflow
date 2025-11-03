<template>
  <div class="container-fluid py-4">
    <div class="row mb-4">
      <div class="col">
        <h2 class="mb-3">
          <i class="bi bi-person-badge me-2"></i>Employee Dashboard
        </h2>
        <p class="text-muted">Review and approve requests assigned to you</p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mt-3 text-muted">Loading pending requests...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="alert alert-danger" role="alert">
      <i class="bi bi-exclamation-triangle me-2"></i>
      {{ error }}
    </div>

    <!-- No Ideas State -->
    <div v-else-if="ideas.length === 0" class="card shadow-sm">
      <div class="card-body text-center py-5">
        <i class="bi bi-inbox display-1 text-muted mb-3"></i>
        <h4>No Pending Requests</h4>
        <p class="text-muted">You don't have any requests pending your approval at this time.</p>
      </div>
    </div>

    <!-- Ideas List -->
    <div v-else class="row">
      <div class="col-md-6 col-lg-4 mb-4" v-for="idea in ideas" :key="idea.id">
        <div class="card shadow-sm h-100">
          <div class="card-body d-flex flex-column">
            <!-- Header -->
            <div class="mb-3">
              <h5 class="card-title mb-2">
                <i class="bi bi-lightbulb me-1 text-warning"></i>
                {{ idea.name }}
              </h5>
              <div class="d-flex flex-wrap gap-2 mb-2">
                <span v-if="idea.form_type" class="badge bg-info">
                  {{ idea.form_type.name }}
                </span>
                <span class="badge" :class="getStatusBadgeClass(idea.status)">
                  {{ idea.status }}
                </span>
              </div>
            </div>

            <!-- Submitted By -->
            <div class="mb-2">
              <small class="text-muted">
                <i class="bi bi-person me-1"></i>
                Submitted by: <strong>{{ idea.user?.name }}</strong>
              </small>
            </div>

            <!-- Description -->
            <p class="card-text text-muted mb-3" style="font-size: 0.9rem">
              {{ truncateText(idea.description, 100) }}
            </p>

            <!-- Current Step Info -->
            <div v-if="getCurrentStepInfo(idea)" class="alert alert-light mb-3">
              <small>
                <strong>Current Step:</strong> {{ getCurrentStepInfo(idea)?.step_name }}<br>
                <strong>Approvals:</strong>
                {{ getCurrentStepInfo(idea)?.approvals_received }}/{{ getCurrentStepInfo(idea)?.approvals_required }}
              </small>
            </div>

            <!-- Actions -->
            <div class="mt-auto">
              <div class="btn-group w-100" role="group">
                <button
                  class="btn btn-success btn-sm"
                  @click="openApproveModal(idea)"
                  :disabled="processing[idea.id]"
                >
                  <i class="bi bi-check-circle me-1"></i>
                  Approve
                </button>
                <button
                  class="btn btn-danger btn-sm"
                  @click="openRejectModal(idea)"
                  :disabled="processing[idea.id]"
                >
                  <i class="bi bi-x-circle me-1"></i>
                  Reject
                </button>
                <button
                  class="btn btn-outline-primary btn-sm"
                  @click="viewDetails(idea.id)"
                >
                  <i class="bi bi-eye me-1"></i>
                  View
                </button>
              </div>
            </div>
          </div>

          <!-- Submitted Date Footer -->
          <div class="card-footer text-muted bg-light">
            <small>
              <i class="bi bi-clock me-1"></i>
              Submitted {{ formatDate(idea.created_at) }}
            </small>
          </div>
        </div>
      </div>
    </div>

    <!-- Approve Modal -->
    <div
      class="modal fade"
      id="approveModal"
      tabindex="-1"
      aria-labelledby="approveModalLabel"
      aria-hidden="true"
    >
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title" id="approveModalLabel">
              <i class="bi bi-check-circle me-2"></i>Approve Request
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p><strong>{{ selectedIdea?.name }}</strong></p>
            <div class="mb-3">
              <label for="approveComments" class="form-label">Comments (Optional)</label>
              <textarea
                id="approveComments"
                v-model="approveComments"
                class="form-control"
                rows="3"
                placeholder="Add any comments about your approval..."
              ></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success" @click="confirmApprove" :disabled="approving">
              <span v-if="approving">
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Approving...
              </span>
              <span v-else>
                <i class="bi bi-check-circle me-2"></i>Confirm Approval
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div
      class="modal fade"
      id="rejectModal"
      tabindex="-1"
      aria-labelledby="rejectModalLabel"
      aria-hidden="true"
    >
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="rejectModalLabel">
              <i class="bi bi-x-circle me-2"></i>Reject Request
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p><strong>{{ selectedIdea?.name }}</strong></p>
            <div class="mb-3">
              <label for="rejectComments" class="form-label">
                Comments <span class="text-danger">*</span>
              </label>
              <textarea
                id="rejectComments"
                v-model="rejectComments"
                class="form-control"
                rows="3"
                placeholder="Please provide a reason for rejection..."
                :class="{ 'is-invalid': rejectCommentsError }"
              ></textarea>
              <div v-if="rejectCommentsError" class="invalid-feedback">
                {{ rejectCommentsError }}
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" @click="confirmReject" :disabled="rejecting">
              <span v-if="rejecting">
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Rejecting...
              </span>
              <span v-else">
                <i class="bi bi-x-circle me-2"></i>Confirm Rejection
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import { Modal } from 'bootstrap'

const router = useRouter()

const ideas = ref<any[]>([])
const loading = ref(true)
const error = ref('')
const processing = ref<{ [key: number]: boolean }>({})

const selectedIdea = ref<any>(null)
const approveComments = ref('')
const rejectComments = ref('')
const rejectCommentsError = ref('')
const approving = ref(false)
const rejecting = ref(false)

let approveModal: Modal | null = null
let rejectModal: Modal | null = null

onMounted(async () => {
  // Initialize modals
  const approveModalEl = document.getElementById('approveModal')
  const rejectModalEl = document.getElementById('rejectModal')
  if (approveModalEl) approveModal = new Modal(approveModalEl)
  if (rejectModalEl) rejectModal = new Modal(rejectModalEl)

  await loadPendingIdeas()
})

async function loadPendingIdeas() {
  try {
    loading.value = true
    error.value = ''
    const response = await api.getEmployeePendingIdeas()
    ideas.value = response.data.ideas || []
  } catch (err: any) {
    console.error('Error loading pending ideas:', err)
    error.value = err.response?.data?.message || 'Failed to load pending requests'
  } finally {
    loading.value = false
  }
}

function getCurrentStepInfo(idea: any) {
  if (!idea.approvals || idea.approvals.length === 0) return null
  return idea.approvals.find((approval: any) =>
    approval.step === idea.current_approval_step && approval.status === 'pending'
  )
}

function getStatusBadgeClass(status: string) {
  const classes: { [key: string]: string } = {
    draft: 'bg-secondary',
    pending: 'bg-warning text-dark',
    approved: 'bg-success',
    rejected: 'bg-danger',
    returned: 'bg-info text-dark'
  }
  return classes[status] || 'bg-secondary'
}

function truncateText(text: string, maxLength: number) {
  if (!text) return ''
  return text.length > maxLength ? text.substring(0, maxLength) + '...' : text
}

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function viewDetails(ideaId: number) {
  router.push(`/idea/${ideaId}`)
}

function openApproveModal(idea: any) {
  selectedIdea.value = idea
  approveComments.value = ''
  approveModal?.show()
}

function openRejectModal(idea: any) {
  selectedIdea.value = idea
  rejectComments.value = ''
  rejectCommentsError.value = ''
  rejectModal?.show()
}

async function confirmApprove() {
  if (!selectedIdea.value) return

  try {
    approving.value = true
    await api.approveIdeaAsEmployee(selectedIdea.value.id, approveComments.value)

    approveModal?.hide()

    // Remove from list
    ideas.value = ideas.value.filter(idea => idea.id !== selectedIdea.value.id)

    // Show success message
    alert('Request approved successfully!')
  } catch (err: any) {
    console.error('Error approving idea:', err)
    alert(err.response?.data?.message || 'Failed to approve request')
  } finally {
    approving.value = false
  }
}

async function confirmReject() {
  if (!selectedIdea.value) return

  // Validate comments
  if (!rejectComments.value || rejectComments.value.trim().length < 3) {
    rejectCommentsError.value = 'Please provide a reason for rejection (at least 3 characters)'
    return
  }

  try {
    rejecting.value = true
    rejectCommentsError.value = ''

    await api.rejectIdeaAsEmployee(selectedIdea.value.id, rejectComments.value)

    rejectModal?.hide()

    // Remove from list
    ideas.value = ideas.value.filter(idea => idea.id !== selectedIdea.value.id)

    // Show success message
    alert('Request rejected successfully!')
  } catch (err: any) {
    console.error('Error rejecting idea:', err)
    alert(err.response?.data?.message || 'Failed to reject request')
  } finally {
    rejecting.value = false
  }
}
</script>

<style scoped>
.card {
  transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
</style>
