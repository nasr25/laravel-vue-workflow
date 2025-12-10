<template>
  <div class="page-container">
    <div class="page-card">
      <div class="header">
        <button @click="goBack" class="btn-back">← Back</button>
        <h1>Department Workflow</h1>
        <button @click="loadRequests" class="btn-refresh">🔄 Refresh</button>
      </div>

      <p class="subtitle">Manage requests assigned to your department</p>

      <div v-if="error" class="alert alert-error">
        {{ error }}
      </div>

      <div v-if="success" class="alert alert-success">
        {{ success }}
      </div>

      <div v-if="isLoading" class="loading">
        Loading department requests...
      </div>

      <div v-else-if="requests.length === 0" class="empty-state">
        <p>No requests assigned to your department.</p>
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
                <strong>Department:</strong> {{ request.current_department?.name }}
              </div>
              <div class="meta-item">
                <strong>Workflow Path:</strong> {{ request.workflow_path?.name }}
              </div>
              <div v-if="request.current_assignee" class="meta-item">
                <strong>Assigned to:</strong> {{ request.current_assignee?.name }}
              </div>
              <div class="meta-item">
                <strong>Last Updated:</strong> {{ formatDate(request.updated_at) }}
              </div>
            </div>
          </div>

          <div class="request-actions">
            <button v-if="isManager && !request.current_user_id" @click="openAssignModal(request)" class="btn-action btn-assign">
              👤 Assign to Employee
            </button>

            <!-- Employee: Return to Manager -->
            <button
              v-if="isEmployee(request) && request.current_user_id === authStore.user?.id"
              @click="openReturnToManagerModal(request)"
              class="btn-action btn-return-manager"
            >
              ↩️ Return to Manager
            </button>

            <!-- Manager: Return to Dept A (only for unassigned requests) -->
            <button
              v-if="isManager && !request.current_user_id"
              @click="openReturnToDeptAModal(request)"
              class="btn-action btn-return"
            >
              ✓ Return to Dept A
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Assign to Employee Modal -->
    <div v-if="assignModal.show" class="modal-overlay" @click="closeAssignModal">
      <div class="modal-content" @click.stop>
        <h2>Assign to Employee</h2>
        <p class="modal-subtitle">Request: {{ assignModal.request?.title }}</p>

        <div class="form-group">
          <label>Select Employee *</label>
          <div class="employees-list">
            <div
              v-for="employee in employees"
              :key="employee.id"
              :class="['employee-option', { selected: assignModal.employeeId === employee.id }]"
              @click="assignModal.employeeId = employee.id"
            >
              <strong>{{ employee.name }}</strong>
              <span class="employee-email">{{ employee.email }}</span>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Comments (Optional)</label>
          <textarea
            v-model="assignModal.comments"
            placeholder="Add any instructions for the employee..."
            rows="3"
          ></textarea>
        </div>

        <div class="modal-actions">
          <button @click="closeAssignModal" class="btn-secondary">Cancel</button>
          <button
            @click="confirmAssign"
            :disabled="!assignModal.employeeId || assignModal.isLoading"
            class="btn-primary"
          >
            {{ assignModal.isLoading ? 'Assigning...' : 'Assign to Employee' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Return to Manager Modal (Employee) -->
    <div v-if="returnToManagerModal.show" class="modal-overlay" @click="closeReturnToManagerModal">
      <div class="modal-content" @click.stop>
        <h2>Return to Manager</h2>
        <p class="modal-subtitle">Request: {{ returnToManagerModal.request?.title }}</p>

        <div class="alert alert-info">
          <strong>Note:</strong> This will return the request to your department manager for review.
        </div>

        <div class="form-group">
          <label>Work Summary / Comments *</label>
          <textarea
            v-model="returnToManagerModal.comments"
            placeholder="Describe the work completed and any findings..."
            rows="5"
            required
          ></textarea>
        </div>

        <div class="modal-actions">
          <button @click="closeReturnToManagerModal" class="btn-secondary">Cancel</button>
          <button
            @click="confirmReturnToManager"
            :disabled="!returnToManagerModal.comments || returnToManagerModal.isLoading"
            class="btn-primary"
          >
            {{ returnToManagerModal.isLoading ? 'Returning...' : 'Return to Manager' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Return to Department A Modal (Manager) -->
    <div v-if="returnToDeptAModal.show" class="modal-overlay" @click="closeReturnToDeptAModal">
      <div class="modal-content" @click.stop>
        <h2>Return to Department A</h2>
        <p class="modal-subtitle">Request: {{ returnToDeptAModal.request?.title }}</p>

        <div class="alert alert-info">
          <strong>Note:</strong> This will send the request back to Department A for final validation.
        </div>

        <div class="form-group">
          <label>Validation Summary / Comments *</label>
          <textarea
            v-model="returnToDeptAModal.comments"
            placeholder="Confirm work completion and provide validation notes..."
            rows="5"
            required
          ></textarea>
        </div>

        <div class="modal-actions">
          <button @click="closeReturnToDeptAModal" class="btn-secondary">Cancel</button>
          <button
            @click="confirmReturnToDeptA"
            :disabled="!returnToDeptAModal.comments || returnToDeptAModal.isLoading"
            class="btn-primary"
          >
            {{ returnToDeptAModal.isLoading ? 'Returning...' : 'Return to Dept A' }}
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
const { t } = useI18n()
const authStore = useAuthStore()

const requests = ref([])
const employees = ref([])
const error = ref(null)
const success = ref(null)
const isLoading = ref(true)

const assignModal = ref({
  show: false,
  request: null,
  employeeId: null,
  comments: '',
  isLoading: false
})

const returnToManagerModal = ref({
  show: false,
  request: null,
  comments: '',
  isLoading: false
})

const returnToDeptAModal = ref({
  show: false,
  request: null,
  comments: '',
  isLoading: false
})

const API_URL = 'http://localhost:8000/api'

const isManager = computed(() => authStore.user?.role === 'manager')

const isEmployee = (request) => {
  // Check if user is an employee (not manager) in the department
  return authStore.user?.role === 'employee' && request.current_user_id === authStore.user?.id
}

onMounted(async () => {
  await loadRequests()
  if (isManager.value) {
    await loadEmployees()
  }
})

const loadRequests = async () => {
  try {
    isLoading.value = true
    error.value = null

    const response = await axios.get(`${API_URL}/department/requests`, {
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

const loadEmployees = async () => {
  try {
    const response = await axios.get(`${API_URL}/department/employees`, {
      headers: {
        Authorization: `Bearer ${authStore.token}`
      }
    })

    employees.value = response.data.employees
  } catch (err) {
    console.error('Failed to load employees:', err)
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

// Assign to Employee Modal
const openAssignModal = (request) => {
  assignModal.value.show = true
  assignModal.value.request = request
  assignModal.value.employeeId = null
  assignModal.value.comments = ''
}

const closeAssignModal = () => {
  assignModal.value.show = false
  assignModal.value.request = null
  assignModal.value.employeeId = null
  assignModal.value.comments = ''
}

const confirmAssign = async () => {
  try {
    assignModal.value.isLoading = true
    error.value = null

    await axios.post(
      `${API_URL}/department/requests/${assignModal.value.request.id}/assign-employee`,
      {
        employee_id: assignModal.value.employeeId,
        comments: assignModal.value.comments
      },
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`
        }
      }
    )

    success.value = 'Request assigned to employee successfully'
    closeAssignModal()
    await loadRequests()

    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to assign to employee'
  } finally {
    assignModal.value.isLoading = false
  }
}

// Return to Manager Modal (Employee)
const openReturnToManagerModal = (request) => {
  returnToManagerModal.value.show = true
  returnToManagerModal.value.request = request
  returnToManagerModal.value.comments = ''
}

const closeReturnToManagerModal = () => {
  returnToManagerModal.value.show = false
  returnToManagerModal.value.request = null
  returnToManagerModal.value.comments = ''
}

const confirmReturnToManager = async () => {
  try {
    returnToManagerModal.value.isLoading = true
    error.value = null

    await axios.post(
      `${API_URL}/department/requests/${returnToManagerModal.value.request.id}/return-to-manager`,
      {
        comments: returnToManagerModal.value.comments
      },
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`
        }
      }
    )

    success.value = 'Request returned to manager successfully'
    closeReturnToManagerModal()
    await loadRequests()

    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to return request'
  } finally {
    returnToManagerModal.value.isLoading = false
  }
}

// Return to Department A Modal (Manager)
const openReturnToDeptAModal = (request) => {
  returnToDeptAModal.value.show = true
  returnToDeptAModal.value.request = request
  returnToDeptAModal.value.comments = ''
}

const closeReturnToDeptAModal = () => {
  returnToDeptAModal.value.show = false
  returnToDeptAModal.value.request = null
  returnToDeptAModal.value.comments = ''
}

const confirmReturnToDeptA = async () => {
  try {
    returnToDeptAModal.value.isLoading = true
    error.value = null

    await axios.post(
      `${API_URL}/department/requests/${returnToDeptAModal.value.request.id}/return-to-dept-a`,
      {
        comments: returnToDeptAModal.value.comments
      },
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`
        }
      }
    )

    success.value = 'Request returned to Department A successfully'
    closeReturnToDeptAModal()
    await loadRequests()

    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to return request'
  } finally {
    returnToDeptAModal.value.isLoading = false
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

.alert-info {
  background: #e3f2fd;
  color: #1976d2;
  border: 1px solid #90caf9;
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

.badge-in_review {
  background: #cfe2ff;
  color: #084298;
}

/* In Progress status badge */
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
  min-width: 180px;
  padding: 10px 16px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-assign {
  background: #2196f3;
  color: white;
}

.btn-assign:hover {
  background: #0b7dda;
  transform: translateY(-2px);
}

.btn-return {
  background: #4caf50;
  color: white;
}

.btn-return:hover {
  background: #45a049;
  transform: translateY(-2px);
}

.btn-return-manager {
  background: #ff9800;
  color: white;
}

.btn-return-manager:hover {
  background: #f57c00;
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

.employees-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 300px;
  overflow-y: auto;
}

.employee-option {
  padding: 12px;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s;
}

.employee-option:hover {
  border-color: #667eea;
  background: #f8f9ff;
}

.employee-option.selected {
  border-color: #667eea;
  background: #f0f3ff;
}

.employee-option strong {
  color: #333;
  font-size: 14px;
  display: block;
  margin-bottom: 4px;
}

.employee-email {
  color: #999;
  font-size: 12px;
}

.modal-actions {
  display: flex;
  gap: 10px;
  margin-top: 25px;
}

.btn-primary, .btn-secondary {
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

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
