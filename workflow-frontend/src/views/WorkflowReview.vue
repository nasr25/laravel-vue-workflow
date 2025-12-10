<template>
  <div class="page-container">
    <div class="page-card">
      <div class="header">
        <button @click="goBack" class="btn-back">← Back</button>
        <h1>Workflow Review</h1>
        <button @click="loadRequests" class="btn-refresh">🔄 Refresh</button>
      </div>

      <p class="subtitle">Review and assign requests to workflow paths</p>

      <div v-if="error" class="alert alert-error">
        {{ error }}
      </div>

      <div v-if="success" class="alert alert-success">
        {{ success }}
      </div>

      <div v-if="isLoading" class="loading">
        Loading pending requests...
      </div>

      <div v-else-if="requests.length === 0" class="empty-state">
        <p>No pending requests to review.</p>
      </div>

      <div v-else class="requests-grid">
        <div v-for="request in requests" :key="request.id" class="request-card">
          <div class="request-header">
            <h3>{{ request.title }}</h3>
            <span :class="['badge', `badge-${request.status}`]">
              {{ formatStatus(request.status) }}
            </span>
          </div>

          <div class="request-body">
            <p class="description">{{ request.description }}</p>

            <div class="request-meta">
              <div class="meta-item">
                <strong>Submitted by:</strong> {{ request.user?.name }}
              </div>
              <div class="meta-item">
                <strong>Email:</strong> {{ request.user?.email }}
              </div>
              <div class="meta-item">
                <strong>Submitted:</strong> {{ formatDate(request.submitted_at) }}
              </div>
              <div v-if="request.attachments?.length > 0" class="meta-item">
                <strong>Attachments:</strong> {{ request.attachments.length }} file(s)
              </div>
            </div>
          </div>

          <!-- Evaluation Status Badge -->
          <div v-if="!requestEvaluationStatus[request.id]" class="evaluation-warning">
            ⚠️ Complete evaluation before taking action
          </div>
          <div v-else class="evaluation-complete">
            ✅ Evaluation completed
          </div>

          <div class="request-actions">
            <!-- Initial pending request actions -->
            <template v-if="request.status === 'pending' && !request.workflow_path_id">
              <button
                @click="checkEvaluationAndOpen(request, 'assign')"
                :disabled="!requestEvaluationStatus[request.id]"
                :class="['btn-action', 'btn-assign', { 'disabled': !requestEvaluationStatus[request.id] }]"
              >
                ✓ Assign Path
              </button>
              <button
                @click="checkEvaluationAndOpen(request, 'details')"
                :disabled="!requestEvaluationStatus[request.id]"
                :class="['btn-action', 'btn-details', { 'disabled': !requestEvaluationStatus[request.id] }]"
              >
                📝 Request Details
              </button>
              <button
                @click="checkEvaluationAndOpen(request, 'reject')"
                :disabled="!requestEvaluationStatus[request.id]"
                :class="['btn-action', 'btn-reject', { 'disabled': !requestEvaluationStatus[request.id] }]"
              >
                ✗ Reject
              </button>
              <!-- Evaluation button always enabled -->
              <button @click="openEvaluationModal(request, null)" class="btn-action btn-evaluate">
                📋 {{ requestEvaluationStatus[request.id] ? 'View/Edit Evaluation' : 'Start Evaluation' }}
              </button>
            </template>

            <!-- Request returned from department for final validation -->
            <template v-else-if="request.status === 'in_review' && request.workflow_path_id">
              <button
                @click="checkEvaluationAndOpen(request, 'complete')"
                :disabled="!requestEvaluationStatus[request.id]"
                :class="['btn-action', 'btn-complete', { 'disabled': !requestEvaluationStatus[request.id] }]"
              >
                ✓ Complete Request
              </button>
              <button
                @click="checkEvaluationAndOpen(request, 'returnPrevious')"
                :disabled="!requestEvaluationStatus[request.id]"
                :class="['btn-action', 'btn-return-previous', { 'disabled': !requestEvaluationStatus[request.id] }]"
              >
                ↩️ Return to Previous Dept
              </button>
              <button
                @click="checkEvaluationAndOpen(request, 'reject')"
                :disabled="!requestEvaluationStatus[request.id]"
                :class="['btn-action', 'btn-reject', { 'disabled': !requestEvaluationStatus[request.id] }]"
              >
                ✗ Reject
              </button>
              <!-- Evaluation button always enabled -->
              <button @click="openEvaluationModal(request, null)" class="btn-action btn-evaluate">
                📋 {{ requestEvaluationStatus[request.id] ? 'View/Edit Evaluation' : 'Start Evaluation' }}
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Evaluation Modal -->
    <div v-if="evaluationModal.show" class="modal-overlay" @click="closeEvaluationModal">
      <div class="modal-content evaluation-modal" @click.stop>
        <h2>📋 Request Evaluation</h2>
        <p class="modal-subtitle">Please evaluate this request before proceeding</p>

        <div v-if="evaluationModal.isLoading" class="loading">Loading questions...</div>

        <div v-else-if="evaluationQuestions.length === 0" class="alert alert-warning">
          No evaluation questions configured. Please contact admin to set up evaluation questions.
        </div>

        <div v-else class="evaluation-form">
          <div v-for="(question, index) in evaluationQuestions" :key="question.id" class="evaluation-question">
            <div class="question-header">
              <span class="question-number">Q{{ index + 1 }}</span>
              <span class="question-weight">Weight: {{ question.weight }}%</span>
            </div>
            <p class="question-text">{{ question.question }}</p>

            <div class="answer-section">
              <label>Answer (1-10) *</label>
              <div class="rating-scale">
                <button
                  v-for="rating in 10"
                  :key="rating"
                  type="button"
                  :class="['rating-btn', { active: evaluationModal.answers[question.id]?.answer === rating }]"
                  @click="setAnswer(question.id, rating)"
                >
                  {{ rating }}
                </button>
              </div>
            </div>

            <div class="notes-section">
              <label>Notes (Optional)</label>
              <textarea
                v-model="evaluationModal.answers[question.id].notes"
                placeholder="Add any notes about this evaluation..."
                rows="2"
              ></textarea>
            </div>
          </div>

          <div class="evaluation-summary">
            <strong>Progress:</strong> {{ answeredCount }} / {{ evaluationQuestions.length }} questions answered
          </div>
        </div>

        <div class="modal-actions">
          <button @click="closeEvaluationModal" class="btn-secondary">Cancel</button>
          <button
            @click="submitEvaluationAndProceed"
            :disabled="!allQuestionsAnswered || evaluationModal.isSaving"
            class="btn-primary"
          >
            {{ evaluationModal.isSaving ? 'Saving...' : 'Submit & Continue' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Assign Path Modal -->
    <div v-if="assignModal.show" class="modal-overlay" @click="closeAssignModal">
      <div class="modal-content" @click.stop>
        <h2>Assign Workflow Path</h2>
        <p class="modal-subtitle">Request: {{ assignModal.request?.title }}</p>

        <div class="form-group">
          <label>Select Workflow Path *</label>
          <div class="paths-list">
            <div
              v-for="path in workflowPaths"
              :key="path.id"
              :class="['path-option', { selected: assignModal.pathId === path.id }]"
              @click="assignModal.pathId = path.id"
            >
              <div class="path-header">
                <strong>{{ path.name }}</strong>
              </div>
              <p class="path-description">{{ path.description }}</p>
              <div class="path-steps">
                <strong>Steps:</strong>
                <span v-for="(step, index) in path.steps" :key="step.id">
                  {{ step.department?.name }}<span v-if="index < path.steps.length - 1"> → </span>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Comments (Optional)</label>
          <textarea
            v-model="assignModal.comments"
            placeholder="Add any comments about this assignment..."
            rows="3"
          ></textarea>
        </div>

        <div class="modal-actions">
          <button @click="closeAssignModal" class="btn-secondary">Cancel</button>
          <button
            @click="confirmAssign"
            :disabled="!assignModal.pathId || assignModal.isLoading"
            class="btn-primary"
          >
            {{ assignModal.isLoading ? 'Assigning...' : 'Assign Path' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Request Details Modal -->
    <div v-if="detailsModal.show" class="modal-overlay" @click="closeDetailsModal">
      <div class="modal-content" @click.stop>
        <h2>Request More Details</h2>
        <p class="modal-subtitle">Request: {{ detailsModal.request?.title }}</p>

        <div class="form-group">
          <label>What additional information do you need? *</label>
          <textarea
            v-model="detailsModal.comments"
            placeholder="Explain what details are needed from the user..."
            rows="4"
            required
          ></textarea>
        </div>

        <div class="modal-actions">
          <button @click="closeDetailsModal" class="btn-secondary">Cancel</button>
          <button
            @click="confirmRequestDetails"
            :disabled="!detailsModal.comments || detailsModal.isLoading"
            class="btn-primary"
          >
            {{ detailsModal.isLoading ? 'Sending...' : 'Request Details' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="rejectModal.show" class="modal-overlay" @click="closeRejectModal">
      <div class="modal-content" @click.stop>
        <h2>Reject Request</h2>
        <p class="modal-subtitle">Request: {{ rejectModal.request?.title }}</p>

        <div class="alert alert-warning">
          <strong>Warning:</strong> This will permanently reject the request and end the workflow.
        </div>

        <div class="form-group">
          <label>Rejection Reason *</label>
          <textarea
            v-model="rejectModal.reason"
            placeholder="Explain why this request is being rejected..."
            rows="4"
            required
          ></textarea>
        </div>

        <div class="modal-actions">
          <button @click="closeRejectModal" class="btn-secondary">Cancel</button>
          <button
            @click="confirmReject"
            :disabled="!rejectModal.reason || rejectModal.isLoading"
            class="btn-danger"
          >
            {{ rejectModal.isLoading ? 'Rejecting...' : 'Reject Request' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Complete Request Modal -->
    <div v-if="completeModal.show" class="modal-overlay" @click="closeCompleteModal">
      <div class="modal-content" @click.stop>
        <h2>Complete Request</h2>
        <p class="modal-subtitle">Request: {{ completeModal.request?.title }}</p>

        <div class="alert alert-success">
          <strong>Final Approval:</strong> This will mark the request as completed and close the workflow.
        </div>

        <div class="form-group">
          <label>Final Comments (Optional)</label>
          <textarea
            v-model="completeModal.comments"
            placeholder="Add any final comments about the completion..."
            rows="3"
          ></textarea>
        </div>

        <div class="modal-actions">
          <button @click="closeCompleteModal" class="btn-secondary">Cancel</button>
          <button
            @click="confirmComplete"
            :disabled="completeModal.isLoading"
            class="btn-primary"
          >
            {{ completeModal.isLoading ? 'Completing...' : 'Complete Request' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Return to Previous Department Modal -->
    <div v-if="returnToPreviousModal.show" class="modal-overlay" @click="closeReturnToPreviousModal">
      <div class="modal-content" @click.stop>
        <h2>Return to Previous Department</h2>
        <p class="modal-subtitle">Request: {{ returnToPreviousModal.request?.title }}</p>

        <div class="alert alert-warning">
          <strong>Note:</strong> This will send the request back to the previous department for revision.
        </div>

        <div class="form-group">
          <label>Reason for Return *</label>
          <textarea
            v-model="returnToPreviousModal.comments"
            placeholder="Explain what needs to be revised..."
            rows="4"
            required
          ></textarea>
        </div>

        <div class="modal-actions">
          <button @click="closeReturnToPreviousModal" class="btn-secondary">Cancel</button>
          <button
            @click="confirmReturnToPrevious"
            :disabled="!returnToPreviousModal.comments || returnToPreviousModal.isLoading"
            class="btn-primary"
          >
            {{ returnToPreviousModal.isLoading ? 'Returning...' : 'Return to Department' }}
          </button>
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
const workflowPaths = ref([])
const evaluationQuestions = ref([])
const error = ref(null)
const success = ref(null)
const isLoading = ref(true)
const requestEvaluationStatus = ref({})

const assignModal = ref({
  show: false,
  request: null,
  pathId: null,
  comments: '',
  isLoading: false
})

const detailsModal = ref({
  show: false,
  request: null,
  comments: '',
  isLoading: false
})

const rejectModal = ref({
  show: false,
  request: null,
  reason: '',
  isLoading: false
})

const completeModal = ref({
  show: false,
  request: null,
  comments: '',
  isLoading: false
})

const returnToPreviousModal = ref({
  show: false,
  request: null,
  comments: '',
  isLoading: false
})

const evaluationModal = ref({
  show: false,
  request: null,
  answers: {},
  nextAction: null,
  isLoading: false,
  isSaving: false
})

const answeredCount = computed(() => {
  return Object.values(evaluationModal.value.answers).filter(a => a.answer).length
})

const allQuestionsAnswered = computed(() => {
  return answeredCount.value === evaluationQuestions.value.length && evaluationQuestions.value.length > 0
})

const API_URL = 'http://localhost:8000/api'

onMounted(async () => {
  await loadWorkflowPaths()
  await loadRequests()
})

const loadRequests = async () => {
  try {
    isLoading.value = true
    error.value = null

    const response = await axios.get(`${API_URL}/workflow/pending-requests`, {
      headers: {
        Authorization: `Bearer ${authStore.token}`
      }
    })

    requests.value = response.data.requests

    // Load evaluation status for each request
    const evaluationStatusPromises = requests.value.map(async (request) => {
      try {
        const evalResponse = await axios.get(
          `${API_URL}/workflow/requests/${request.id}/evaluation-questions`,
          {
            headers: {
              Authorization: `Bearer ${authStore.token}`
            }
          }
        )
        requestEvaluationStatus.value[request.id] = evalResponse.data.has_evaluated
      } catch (err) {
        console.error(`Failed to load evaluation status for request ${request.id}:`, err)
        requestEvaluationStatus.value[request.id] = false
      }
    })

    await Promise.all(evaluationStatusPromises)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load requests'
  } finally {
    isLoading.value = false
  }
}

const loadWorkflowPaths = async () => {
  try {
    const response = await axios.get(`${API_URL}/workflow/paths`, {
      headers: {
        Authorization: `Bearer ${authStore.token}`
      }
    })

    workflowPaths.value = response.data.paths
  } catch (err) {
    console.error('Failed to load workflow paths:', err)
  }
}

const goBack = () => {
  router.push('/dashboard')
}

const formatStatus = (status) => {
  // Use i18n translation if available, otherwise fallback to formatted string
  return t(`status.${status}`, status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' '))
}

const formatDate = (dateString) => {
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

// Assign Path Modal
const openAssignModal = (request) => {
  assignModal.value.show = true
  assignModal.value.request = request
  assignModal.value.pathId = null
  assignModal.value.comments = ''
}

const closeAssignModal = () => {
  assignModal.value.show = false
  assignModal.value.request = null
  assignModal.value.pathId = null
  assignModal.value.comments = ''
}

const confirmAssign = async () => {
  try {
    assignModal.value.isLoading = true
    error.value = null

    await axios.post(
      `${API_URL}/workflow/requests/${assignModal.value.request.id}/assign-path`,
      {
        workflow_path_id: assignModal.value.pathId,
        comments: assignModal.value.comments
      },
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`
        }
      }
    )

    success.value = 'Request assigned to workflow path successfully'

    // Close modal first
    closeAssignModal()

    // Refresh the requests list to update the UI
    await loadRequests()

    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to assign path'
  } finally {
    assignModal.value.isLoading = false
  }
}

// Request Details Modal
const openDetailsModal = (request) => {
  detailsModal.value.show = true
  detailsModal.value.request = request
  detailsModal.value.comments = ''
}

const closeDetailsModal = () => {
  detailsModal.value.show = false
  detailsModal.value.request = null
  detailsModal.value.comments = ''
}

const confirmRequestDetails = async () => {
  try {
    detailsModal.value.isLoading = true
    error.value = null

    await axios.post(
      `${API_URL}/workflow/requests/${detailsModal.value.request.id}/request-details`,
      {
        comments: detailsModal.value.comments
      },
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`
        }
      }
    )

    success.value = 'More details requested from user successfully'
    closeDetailsModal()
    await loadRequests()

    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to request details'
  } finally {
    detailsModal.value.isLoading = false
  }
}

// Reject Modal
const openRejectModal = (request) => {
  rejectModal.value.show = true
  rejectModal.value.request = request
  rejectModal.value.reason = ''
}

const closeRejectModal = () => {
  rejectModal.value.show = false
  rejectModal.value.request = null
  rejectModal.value.reason = ''
}

const confirmReject = async () => {
  try {
    rejectModal.value.isLoading = true
    error.value = null

    await axios.post(
      `${API_URL}/workflow/requests/${rejectModal.value.request.id}/reject`,
      {
        rejection_reason: rejectModal.value.reason
      },
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`
        }
      }
    )

    success.value = 'Request rejected successfully'

    // Close modal first
    closeRejectModal()

    // Refresh the requests list to update the UI
    await loadRequests()

    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to reject request'
  } finally {
    rejectModal.value.isLoading = false
  }
}

// Complete Request Modal
const openCompleteModal = (request) => {
  completeModal.value.show = true
  completeModal.value.request = request
  completeModal.value.comments = ''
}

const closeCompleteModal = () => {
  completeModal.value.show = false
  completeModal.value.request = null
  completeModal.value.comments = ''
}

const confirmComplete = async () => {
  try {
    completeModal.value.isLoading = true
    error.value = null

    await axios.post(
      `${API_URL}/workflow/requests/${completeModal.value.request.id}/complete`,
      {
        comments: completeModal.value.comments
      },
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`
        }
      }
    )

    success.value = 'Request completed successfully'
    closeCompleteModal()
    await loadRequests()

    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to complete request'
  } finally {
    completeModal.value.isLoading = false
  }
}

// Return to Previous Department Modal
const openReturnToPreviousModal = (request) => {
  returnToPreviousModal.value.show = true
  returnToPreviousModal.value.request = request
  returnToPreviousModal.value.comments = ''
}

const closeReturnToPreviousModal = () => {
  returnToPreviousModal.value.show = false
  returnToPreviousModal.value.request = null
  returnToPreviousModal.value.comments = ''
}

const confirmReturnToPrevious = async () => {
  try {
    returnToPreviousModal.value.isLoading = true
    error.value = null

    await axios.post(
      `${API_URL}/workflow/requests/${returnToPreviousModal.value.request.id}/return-to-previous`,
      {
        comments: returnToPreviousModal.value.comments
      },
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`
        }
      }
    )

    success.value = 'Request returned to previous department successfully'
    closeReturnToPreviousModal()
    await loadRequests()

    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to return request'
  } finally {
    returnToPreviousModal.value.isLoading = false
  }
}

// Evaluation Modal Methods
const checkEvaluationAndOpen = async (request, action) => {
  try {
    // Check if request has been evaluated
    const response = await axios.get(
      `${API_URL}/workflow/requests/${request.id}/evaluation-questions`,
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`
        }
      }
    )

    if (response.data.has_evaluated) {
      // Already evaluated, proceed directly
      openModalForAction(request, action)
    } else {
      // Need evaluation, open evaluation modal first
      await openEvaluationModal(request, action)
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to check evaluation'
  }
}

const openEvaluationModal = async (request, nextAction) => {
  try {
    evaluationModal.value.isLoading = true
    evaluationModal.value.show = true
    evaluationModal.value.request = request
    evaluationModal.value.nextAction = nextAction

    const response = await axios.get(
      `${API_URL}/workflow/requests/${request.id}/evaluation-questions`,
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`
        }
      }
    )

    evaluationQuestions.value = response.data.questions

    // Initialize answers object
    const answers = {}
    response.data.questions.forEach(q => {
      const existing = response.data.evaluations[q.id]
      answers[q.id] = {
        answer: existing?.score ? Math.round((existing.score / q.weight) * 10) : null,
        notes: existing?.notes || ''
      }
    })

    evaluationModal.value.answers = answers
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load evaluation questions'
    closeEvaluationModal()
  } finally {
    evaluationModal.value.isLoading = false
  }
}

const closeEvaluationModal = () => {
  evaluationModal.value = {
    show: false,
    request: null,
    answers: {},
    nextAction: null,
    isLoading: false,
    isSaving: false
  }
  evaluationQuestions.value = []
}

const setAnswer = (questionId, rating) => {
  if (!evaluationModal.value.answers[questionId]) {
    evaluationModal.value.answers[questionId] = { answer: null, notes: '' }
  }
  evaluationModal.value.answers[questionId].answer = rating
}

const submitEvaluationAndProceed = async () => {
  try {
    evaluationModal.value.isSaving = true
    error.value = null

    // Format evaluations for API
    const evaluations = Object.keys(evaluationModal.value.answers).map(questionId => ({
      question_id: parseInt(questionId),
      answer: evaluationModal.value.answers[questionId].answer,
      notes: evaluationModal.value.answers[questionId].notes
    }))

    // Submit evaluation
    await axios.post(
      `${API_URL}/workflow/requests/${evaluationModal.value.request.id}/evaluation`,
      { evaluations },
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`
        }
      }
    )

    success.value = 'Evaluation submitted successfully'

    // Get the request and action before closing modal
    const request = evaluationModal.value.request
    const action = evaluationModal.value.nextAction

    // Update evaluation status
    requestEvaluationStatus.value[request.id] = true

    closeEvaluationModal()

    // Open the appropriate modal for the action if there is one
    if (action) {
      openModalForAction(request, action)
    }

    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to submit evaluation'
  } finally {
    evaluationModal.value.isSaving = false
  }
}

const openModalForAction = (request, action) => {
  switch (action) {
    case 'assign':
      openAssignModal(request)
      break
    case 'details':
      openDetailsModal(request)
      break
    case 'reject':
      openRejectModal(request)
      break
    case 'complete':
      openCompleteModal(request)
      break
    case 'returnPrevious':
      openReturnToPreviousModal(request)
      break
  }
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
  max-width: 1400px;
  margin: 0 auto;
}

.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
  gap: 15px;
}

.btn-back, .btn-refresh {
  padding: 8px 16px;
  background: #f5f5f5;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.2s;
}

.btn-back:hover, .btn-refresh:hover {
  background: #e0e0e0;
}

h1 {
  color: #333;
  font-size: 28px;
  margin: 0;
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

.alert-success {
  background: #e8f5e9;
  color: #2e7d32;
  border: 1px solid #4caf50;
}

.alert-warning {
  background: #fff3cd;
  color: #856404;
  border: 1px solid #ffc107;
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
  color: #666;
}

.requests-grid {
  display: grid;
  gap: 20px;
}

.request-card {
  border: 2px solid #e0e0e0;
  border-radius: 10px;
  padding: 20px;
  background: white;
  transition: all 0.3s;
}

.request-card:hover {
  border-color: #667eea;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.request-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
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

.request-body {
  margin-bottom: 20px;
}

.description {
  color: #666;
  font-size: 14px;
  line-height: 1.6;
  margin-bottom: 15px;
}

.request-meta {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 10px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
  font-size: 13px;
}

.meta-item {
  color: #666;
}

.meta-item strong {
  color: #333;
  display: block;
  margin-bottom: 2px;
}

.request-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.btn-action {
  flex: 1;
  min-width: 140px;
  padding: 10px 16px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-assign {
  background: #4caf50;
  color: white;
}

.btn-assign:hover {
  background: #45a049;
  transform: translateY(-2px);
}

.btn-details {
  background: #2196f3;
  color: white;
}

.btn-details:hover {
  background: #0b7dda;
  transform: translateY(-2px);
}

.btn-reject {
  background: #ff6b6b;
  color: white;
}

.btn-reject:hover {
  background: #ff5252;
  transform: translateY(-2px);
}

.btn-complete {
  background: #4caf50;
  color: white;
}

.btn-complete:hover {
  background: #45a049;
  transform: translateY(-2px);
}

.btn-return-previous {
  background: #ff9800;
  color: white;
}

.btn-return-previous:hover {
  background: #fb8c00;
  transform: translateY(-2px);
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-content {
  background: white;
  border-radius: 15px;
  padding: 30px;
  max-width: 600px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-content h2 {
  color: #333;
  margin-bottom: 10px;
}

.modal-subtitle {
  color: #666;
  font-size: 14px;
  margin-bottom: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  color: #555;
  font-weight: 500;
  font-size: 14px;
}

.form-group textarea {
  width: 100%;
  padding: 12px;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  font-family: inherit;
  font-size: 14px;
  transition: border-color 0.3s;
}

.form-group textarea:focus {
  outline: none;
  border-color: #667eea;
}

.paths-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.path-option {
  padding: 15px;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s;
}

.path-option:hover {
  border-color: #667eea;
  background: #f8f9ff;
}

.path-option.selected {
  border-color: #667eea;
  background: #f0f3ff;
}

.path-header strong {
  color: #333;
  font-size: 15px;
}

.path-description {
  color: #666;
  font-size: 13px;
  margin: 5px 0;
}

.path-steps {
  color: #999;
  font-size: 12px;
  margin-top: 8px;
}

.path-steps strong {
  color: #666;
}

.modal-actions {
  display: flex;
  gap: 10px;
  margin-top: 25px;
}

.btn-primary, .btn-secondary, .btn-danger {
  flex: 1;
  padding: 12px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: #667eea;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #5568d3;
  transform: translateY(-2px);
}

.btn-secondary {
  background: #f5f5f5;
  color: #333;
}

.btn-secondary:hover {
  background: #e0e0e0;
}

.btn-danger {
  background: #ff6b6b;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: #ff5252;
  transform: translateY(-2px);
}

.btn-primary:disabled, .btn-danger:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Evaluation Modal Styles */
.evaluation-modal {
  max-width: 800px;
}

.evaluation-form {
  max-height: 60vh;
  overflow-y: auto;
  padding-right: 10px;
}

.evaluation-question {
  background: #f8f9fa;
  padding: 20px;
  border-radius: 10px;
  margin-bottom: 20px;
  border-left: 4px solid #667eea;
}

.question-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.question-number {
  background: #667eea;
  color: white;
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
}

.question-weight {
  background: #e0e0e0;
  color: #666;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.question-text {
  color: #333;
  font-size: 15px;
  font-weight: 500;
  line-height: 1.6;
  margin: 0 0 15px 0;
}

.answer-section {
  margin-bottom: 15px;
}

.answer-section label {
  display: block;
  margin-bottom: 10px;
  color: #555;
  font-weight: 500;
  font-size: 14px;
}

.rating-scale {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.rating-btn {
  width: 45px;
  height: 45px;
  border: 2px solid #e0e0e0;
  background: white;
  color: #666;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.rating-btn:hover {
  border-color: #667eea;
  background: #f8f9ff;
  transform: scale(1.05);
}

.rating-btn.active {
  background: #667eea;
  color: white;
  border-color: #667eea;
  transform: scale(1.1);
}

.notes-section {
  margin-top: 15px;
}

.notes-section label {
  display: block;
  margin-bottom: 8px;
  color: #555;
  font-weight: 500;
  font-size: 14px;
}

.notes-section textarea {
  width: 100%;
  padding: 10px;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  font-family: inherit;
  font-size: 14px;
  resize: vertical;
  transition: border-color 0.3s;
}

.notes-section textarea:focus {
  outline: none;
  border-color: #667eea;
}

.evaluation-summary {
  background: #e8f5e9;
  color: #2e7d32;
  padding: 15px;
  border-radius: 8px;
  text-align: center;
  font-size: 14px;
  margin-top: 20px;
}

/* Evaluation Status Badges */
.evaluation-warning {
  background: #fff3cd;
  border: 2px solid #ffc107;
  color: #856404;
  padding: 12px 16px;
  border-radius: 8px;
  margin: 15px 0;
  font-weight: 600;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.evaluation-complete {
  background: #d4edda;
  border: 2px solid #28a745;
  color: #155724;
  padding: 12px 16px;
  border-radius: 8px;
  margin: 15px 0;
  font-weight: 600;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Disabled Button Styles */
.btn-action.disabled,
.btn-action:disabled {
  background: #e0e0e0 !important;
  color: #999 !important;
  cursor: not-allowed !important;
  opacity: 0.6;
  border-color: #ccc !important;
}

.btn-action.disabled:hover,
.btn-action:disabled:hover {
  background: #e0e0e0 !important;
  transform: none !important;
  box-shadow: none !important;
}

/* Evaluation Button */
.btn-evaluate {
  background: #6c757d;
  color: white;
  border: 2px solid #5a6268;
}

.btn-evaluate:hover {
  background: #5a6268;
}
</style>
