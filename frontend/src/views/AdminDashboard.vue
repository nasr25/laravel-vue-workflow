<template>
  <div class="admin-dashboard">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
      <div class="container-fluid px-3 px-md-4">
        <a class="navbar-brand fw-bold">
          <i class="bi bi-shield-fill-check me-2"></i>
          Admin Dashboard
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
      <!-- Tabs -->
      <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
          <button
            class="nav-link"
            :class="{ active: activeTab === 'managers' }"
            @click="activeTab = 'managers'; loadManagers()"
          >
            <i class="bi bi-people-fill me-2"></i>
            Manage Managers
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button
            class="nav-link"
            :class="{ active: activeTab === 'departments' }"
            @click="activeTab = 'departments'; loadDepartments()"
          >
            <i class="bi bi-building me-2"></i>
            Departments
          </button>
        </li>
      </ul>

      <!-- Managers Tab -->
      <div v-if="activeTab === 'managers'">
        <div class="row">
          <!-- Create Manager Form -->
          <div class="col-12 col-lg-5 mb-4">
            <div class="card shadow-sm border-0">
              <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                  <i class="bi bi-person-plus-fill me-2"></i>
                  Create New Manager
                </h5>
              </div>
              <div class="card-body p-4">
                <form @submit.prevent="createNewManager">
                  <div class="mb-3">
                    <label for="managerName" class="form-label fw-semibold">
                      <i class="bi bi-person-fill me-1"></i>
                      Full Name *
                    </label>
                    <input
                      v-model="newManager.name"
                      type="text"
                      class="form-control"
                      id="managerName"
                      required
                      placeholder="Enter manager's full name"
                      maxlength="255"
                    />
                  </div>

                  <div class="mb-3">
                    <label for="managerEmail" class="form-label fw-semibold">
                      <i class="bi bi-envelope-fill me-1"></i>
                      Email Address *
                    </label>
                    <input
                      v-model="newManager.email"
                      type="email"
                      class="form-control"
                      id="managerEmail"
                      required
                      placeholder="manager@example.com"
                    />
                  </div>

                  <div class="mb-3">
                    <label for="managerPassword" class="form-label fw-semibold">
                      <i class="bi bi-lock-fill me-1"></i>
                      Password *
                    </label>
                    <input
                      v-model="newManager.password"
                      type="password"
                      class="form-control"
                      id="managerPassword"
                      required
                      placeholder="Minimum 6 characters"
                      minlength="6"
                    />
                  </div>

                  <div class="mb-3">
                    <label for="managerDepartment" class="form-label fw-semibold">
                      <i class="bi bi-building me-1"></i>
                      Assign to Department (Optional)
                    </label>
                    <select
                      v-model="newManager.departmentId"
                      class="form-select"
                      id="managerDepartment"
                    >
                      <option :value="null">-- Select Department --</option>
                      <option
                        v-for="dept in departments"
                        :key="dept.id"
                        :value="dept.id"
                      >
                        {{ dept.name }} (Step {{ dept.approval_order }})
                      </option>
                    </select>
                    <div class="form-text">You can assign departments later</div>
                  </div>

                  <button
                    type="submit"
                    class="btn btn-success w-100"
                    :disabled="loading"
                  >
                    <span v-if="loading">
                      <span class="spinner-border spinner-border-sm me-2"></span>
                      Creating...
                    </span>
                    <span v-else>
                      <i class="bi bi-person-plus-fill me-2"></i>
                      Create Manager
                    </span>
                  </button>
                </form>
              </div>
            </div>
          </div>

          <!-- Managers List -->
          <div class="col-12 col-lg-7">
            <div class="card shadow-sm border-0">
              <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                  <i class="bi bi-people-fill me-2"></i>
                  All Managers
                </h5>
              </div>
              <div class="card-body p-3 p-md-4">
                <!-- Loading State -->
                <div v-if="loading" class="text-center py-5">
                  <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                  <p class="text-muted mt-3">Loading managers...</p>
                </div>

                <!-- Managers List -->
                <div v-else-if="managers.length > 0">
                  <div
                    v-for="manager in managers"
                    :key="manager.id"
                    class="manager-card border rounded p-3 mb-3"
                  >
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <div>
                        <h6 class="mb-1">
                          <i class="bi bi-person-badge-fill text-primary me-2"></i>
                          {{ manager.name }}
                        </h6>
                        <small class="text-muted">
                          <i class="bi bi-envelope me-1"></i>
                          {{ manager.email }}
                        </small>
                      </div>
                    </div>

                    <!-- Current Departments -->
                    <div v-if="manager.managedDepartments && manager.managedDepartments.length > 0" class="mb-2">
                      <small class="fw-semibold d-block mb-1">Manages:</small>
                      <div class="d-flex flex-wrap gap-1">
                        <span
                          v-for="dept in manager.managedDepartments"
                          :key="dept.id"
                          class="badge bg-info"
                        >
                          {{ dept.name }}
                          <i
                            class="bi bi-x-circle ms-1"
                            style="cursor: pointer"
                            @click="removeFromDepartment(manager.id, dept.id)"
                            title="Remove from this department"
                          ></i>
                        </span>
                      </div>
                    </div>
                    <div v-else>
                      <small class="text-muted">Not assigned to any department</small>
                    </div>

                    <!-- Assign to Department -->
                    <div class="mt-2">
                      <div class="input-group input-group-sm">
                        <select
                          v-model="assignDepartment[manager.id]"
                          class="form-select form-select-sm"
                        >
                          <option :value="null">-- Assign to Department --</option>
                          <option
                            v-for="dept in getAvailableDepartments(manager)"
                            :key="dept.id"
                            :value="dept.id"
                          >
                            {{ dept.name }} (Step {{ dept.approval_order }})
                          </option>
                        </select>
                        <button
                          class="btn btn-outline-primary btn-sm"
                          type="button"
                          :disabled="!assignDepartment[manager.id]"
                          @click="assignToDepartment(manager.id, assignDepartment[manager.id])"
                        >
                          <i class="bi bi-plus-circle me-1"></i>
                          Assign
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-5">
                  <i class="bi bi-people display-1 text-muted"></i>
                  <p class="text-muted mt-3">No managers found. Create one to get started!</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Departments Tab -->
      <div v-if="activeTab === 'departments'">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
              <i class="bi bi-building me-2"></i>
              Approval Sequence Management
            </h5>
            <button
              v-if="!loading && orderChanged"
              @click="saveOrder"
              class="btn btn-light btn-sm"
              :disabled="savingOrder"
            >
              <span v-if="savingOrder">
                <span class="spinner-border spinner-border-sm me-2"></span>
                Saving...
              </span>
              <span v-else>
                <i class="bi bi-save-fill me-1"></i>
                Save New Order
              </span>
            </button>
          </div>
          <div class="card-body p-3 p-md-4">
            <!-- Warning about pending ideas -->
            <div v-if="pendingIdeasCount > 0 && orderChanged" class="alert alert-warning mb-4" role="alert">
              <i class="bi bi-exclamation-triangle-fill me-2"></i>
              <strong>Warning:</strong> There are {{ pendingIdeasCount }} idea(s) currently in the approval process.
              Changing the order will affect how they flow through departments. Ideas currently at Step {{ editableDepartments.find(d => d.approval_order === 2)?.approval_order }} will move to {{ editableDepartments.find(d => d.approval_order === 2)?.name }}.
            </div>

            <div v-if="loading" class="text-center py-5">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
              <p class="text-muted mt-3">Loading departments...</p>
            </div>

            <div v-else>
              <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong>Drag or use arrows to reorder the approval sequence.</strong>
                The order determines which department reviews ideas first, second, third, and fourth.
              </div>

              <!-- Departments List (Reorderable) -->
              <div class="departments-list">
                <div
                  v-for="(dept, index) in editableDepartments"
                  :key="dept.id"
                  class="department-item card mb-3 border"
                  :class="{ 'border-primary': orderChanged }"
                >
                  <div class="card-body p-3">
                    <div class="row align-items-center">
                      <!-- Order Badge -->
                      <div class="col-auto">
                        <div class="order-badge">
                          <span class="badge bg-primary" style="font-size: 1.2rem; padding: 0.5rem 0.75rem;">
                            {{ dept.approval_order }}
                          </span>
                          <div class="text-muted small mt-1">Step</div>
                        </div>
                      </div>

                      <!-- Department Info -->
                      <div class="col">
                        <h6 class="mb-1">
                          <i class="bi bi-building-fill text-primary me-2"></i>
                          {{ dept.name }}
                        </h6>
                        <p class="text-muted small mb-1">{{ dept.description }}</p>
                        <div>
                          <span :class="['badge', 'me-2', dept.is_active ? 'bg-success' : 'bg-danger']">
                            {{ dept.is_active ? 'Active' : 'Inactive' }}
                          </span>
                          <span v-if="getDepartmentManagers(dept.id).length > 0">
                            <span
                              v-for="manager in getDepartmentManagers(dept.id)"
                              :key="manager.id"
                              class="badge bg-info me-1"
                            >
                              {{ manager.name }}
                            </span>
                          </span>
                          <span v-else class="text-muted small">No managers</span>
                        </div>
                      </div>

                      <!-- Reorder Buttons -->
                      <div class="col-auto">
                        <div class="btn-group-vertical" role="group">
                          <button
                            type="button"
                            class="btn btn-sm btn-outline-primary"
                            :disabled="index === 0"
                            @click="moveUp(index)"
                            title="Move up"
                          >
                            <i class="bi bi-arrow-up"></i>
                          </button>
                          <button
                            type="button"
                            class="btn btn-sm btn-outline-primary"
                            :disabled="index === editableDepartments.length - 1"
                            @click="moveDown(index)"
                            title="Move down"
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Reset Button -->
              <div v-if="orderChanged" class="text-center mt-3">
                <button @click="resetOrder" class="btn btn-outline-secondary">
                  <i class="bi bi-arrow-counterclockwise me-1"></i>
                  Reset to Original Order
                </button>
              </div>
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

const router = useRouter()
const authStore = useAuthStore()

const activeTab = ref<'managers' | 'departments'>('managers')
const managers = ref<any[]>([])
const departments = ref<any[]>([])
const loading = ref(false)

const newManager = ref({
  name: '',
  email: '',
  password: '',
  departmentId: null as number | null
})

const assignDepartment = reactive<Record<number, number | null>>({})

// Department reordering state
const editableDepartments = ref<any[]>([])
const originalDepartments = ref<any[]>([])
const savingOrder = ref(false)
const pendingIdeasCount = ref(0)

const orderChanged = computed(() => {
  if (editableDepartments.value.length === 0 || originalDepartments.value.length === 0) {
    return false
  }
  return JSON.stringify(editableDepartments.value.map(d => ({ id: d.id, order: d.approval_order }))) !==
         JSON.stringify(originalDepartments.value.map(d => ({ id: d.id, order: d.approval_order })))
})

onMounted(async () => {
  await authStore.fetchUser()
  loadManagers()
  loadDepartments()
})

async function loadManagers() {
  loading.value = true
  try {
    const response = await api.getManagers()
    if (response.data.success) {
      managers.value = response.data.managers
    }
  } catch (error) {
    console.error('Failed to load managers:', error)
    alert('Failed to load managers')
  } finally {
    loading.value = false
  }
}

async function loadDepartments() {
  loading.value = true
  try {
    const response = await api.getDepartments()
    if (response.data.success) {
      departments.value = response.data.departments
      // Sort by approval_order for consistent ordering
      const sorted = [...response.data.departments].sort((a, b) => a.approval_order - b.approval_order)
      editableDepartments.value = JSON.parse(JSON.stringify(sorted))
      originalDepartments.value = JSON.parse(JSON.stringify(sorted))
    }
    await loadPendingIdeasCount()
  } catch (error) {
    console.error('Failed to load departments:', error)
  } finally {
    loading.value = false
  }
}

async function loadPendingIdeasCount() {
  try {
    const response = await api.getPendingIdeasCount()
    if (response.data.success) {
      pendingIdeasCount.value = response.data.count
    }
  } catch (error) {
    console.error('Failed to load pending ideas count:', error)
  }
}

async function createNewManager() {
  if (!confirm('Create this manager account?')) return

  loading.value = true
  try {
    // Create manager
    const response = await api.createManager({
      name: newManager.value.name.trim(),
      email: newManager.value.email.trim(),
      password: newManager.value.password
    })

    if (response.data.success) {
      const managerId = response.data.manager.id

      // If department selected, assign immediately
      if (newManager.value.departmentId) {
        await api.assignManagerToDepartment(managerId, newManager.value.departmentId)
      }

      // Reset form
      newManager.value = { name: '', email: '', password: '', departmentId: null }

      // Reload managers
      await loadManagers()
    }
  } catch (error: any) {
    alert('Error: ' + (error.response?.data?.message || 'Failed to create manager'))
  } finally {
    loading.value = false
  }
}

async function assignToDepartment(managerId: number, departmentId: number | null) {
  if (!departmentId) return

  try {
    await api.assignManagerToDepartment(managerId, departmentId)
    assignDepartment[managerId] = null
    await loadManagers()
  } catch (error: any) {
    alert('Error: ' + (error.response?.data?.message || 'Failed to assign manager'))
  }
}

async function removeFromDepartment(managerId: number, departmentId: number) {
  if (!confirm('Remove this manager from the department?')) return

  try {
    await api.removeManagerFromDepartment(managerId, departmentId)
    await loadManagers()
  } catch (error: any) {
    alert('Error: ' + (error.response?.data?.message || 'Failed to remove manager'))
  }
}

function getAvailableDepartments(manager: any) {
  if (!manager.managedDepartments) return departments.value

  const assignedIds = manager.managedDepartments.map((d: any) => d.id)
  return departments.value.filter(dept => !assignedIds.includes(dept.id))
}

function getDepartmentManagers(departmentId: number) {
  return managers.value.filter(manager =>
    manager.managedDepartments?.some((d: any) => d.id === departmentId)
  )
}

// Department reordering functions
function moveUp(index: number) {
  if (index === 0) return

  // Swap departments
  const temp = editableDepartments.value[index]
  editableDepartments.value[index] = editableDepartments.value[index - 1]
  editableDepartments.value[index - 1] = temp

  // Reassign approval_order based on new positions
  editableDepartments.value.forEach((dept, idx) => {
    dept.approval_order = idx + 1
  })
}

function moveDown(index: number) {
  if (index === editableDepartments.value.length - 1) return

  // Swap departments
  const temp = editableDepartments.value[index]
  editableDepartments.value[index] = editableDepartments.value[index + 1]
  editableDepartments.value[index + 1] = temp

  // Reassign approval_order based on new positions
  editableDepartments.value.forEach((dept, idx) => {
    dept.approval_order = idx + 1
  })
}

async function saveOrder() {
  if (!confirm('Save the new approval order? This will affect how future ideas flow through departments.')) return

  savingOrder.value = true
  try {
    const departmentOrder = editableDepartments.value.map(d => ({
      id: d.id,
      approval_order: d.approval_order
    }))

    await api.updateDepartmentOrder(departmentOrder)

    // Update original to match new order
    originalDepartments.value = JSON.parse(JSON.stringify(editableDepartments.value))

    // Reload departments to get fresh data
    await loadDepartments()
  } catch (error: any) {
    alert('Error: ' + (error.response?.data?.message || 'Failed to save order'))
  } finally {
    savingOrder.value = false
  }
}

function resetOrder() {
  editableDepartments.value = JSON.parse(JSON.stringify(originalDepartments.value))
}

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.admin-dashboard {
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

.card-header.bg-success {
  background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
}

.card-header.bg-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.card-header.bg-info {
  background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%) !important;
}

.manager-card {
  transition: transform 0.2s, box-shadow 0.2s;
  background-color: #fafbfc;
}

.manager-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1) !important;
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
}
</style>
