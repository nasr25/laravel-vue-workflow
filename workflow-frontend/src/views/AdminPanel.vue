<template>
  <div class="page-container">
    <div class="page-card">
      <div class="header">
        <button @click="goBack" class="btn-back">← Back</button>
        <h1>⚙️ Admin Panel</h1>
        <button @click="refresh" class="btn-refresh">🔄 Refresh</button>
      </div>

      <p class="subtitle">Manage departments, users, and assignments</p>

      <div v-if="error" class="alert alert-error">{{ error }}</div>
      <div v-if="success" class="alert alert-success">{{ success }}</div>

      <div class="tabs">
        <button :class="['tab', { active: activeTab === 'departments' }]" @click="activeTab = 'departments'">
          🏢 Departments
        </button>
        <button :class="['tab', { active: activeTab === 'users' }]" @click="activeTab = 'users'">
          👥 Users
        </button>
        <button :class="['tab', { active: activeTab === 'assignments' }]" @click="activeTab = 'assignments'">
          🔗 Assignments
        </button>
        <button :class="['tab', { active: activeTab === 'evaluations' }]" @click="activeTab = 'evaluations'">
          📋 Evaluation Questions
        </button>
      </div>

      <div v-if="activeTab === 'departments'" class="tab-content">
        <div class="section-header">
          <h2>Departments ({{ departments.length }})</h2>
          <button @click="openDepartmentModal()" class="btn-primary">➕ Add Department</button>
        </div>
        <div v-if="isLoading" class="loading">Loading...</div>
        <div v-else class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Code</th>
                <th>Members</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="dept in departments" :key="dept.id">
                <td>
                  <strong>{{ dept.name }}</strong>
                  <span v-if="dept.is_department_a" class="badge badge-primary">Dept A</span>
                </td>
                <td><code>{{ dept.code }}</code></td>
                <td>{{ dept.users?.length || 0 }}</td>
                <td>
                  <span :class="['badge', dept.is_active ? 'badge-success' : 'badge-inactive']">
                    {{ dept.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="actions">
                  <button @click="openDepartmentModal(dept)" class="btn-icon">✏️</button>
                  <button @click="deleteDepartment(dept)" class="btn-icon btn-danger">🗑️</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="activeTab === 'users'" class="tab-content">
        <div class="section-header">
          <h2>Users ({{ users.length }})</h2>
          <button @click="openUserModal()" class="btn-primary">➕ Add User</button>
        </div>
        <div v-if="isLoading" class="loading">Loading...</div>
        <div v-else class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Departments</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in users" :key="user.id">
                <td><strong>{{ user.name }}</strong></td>
                <td><code>{{ user.email }}</code></td>
                <td><span :class="['badge', `badge-${user.role}`]">{{ user.role.toUpperCase() }}</span></td>
                <td>
                  <span v-if="user.departments && user.departments.length > 0" class="dept-badges">
                    <span v-for="dept in user.departments" :key="dept.id" class="badge badge-dept">
                      {{ dept.code }}
                    </span>
                  </span>
                  <span v-else class="text-muted">None</span>
                </td>
                <td class="actions">
                  <button @click="openUserModal(user)" class="btn-icon">✏️</button>
                  <button @click="deleteUser(user)" :disabled="user.id === authStore.user?.id" class="btn-icon btn-danger">
                    🗑️
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="activeTab === 'assignments'" class="tab-content">
        <div class="section-header">
          <h2>Department Assignments</h2>
          <button @click="openAssignmentModal()" class="btn-primary">➕ Assign User</button>
        </div>
        <div v-if="isLoading" class="loading">Loading...</div>
        <div v-else class="assignments-grid">
          <div v-for="dept in departments" :key="dept.id" class="assignment-card">
            <div class="assignment-header">
              <h3>{{ dept.name }}</h3>
              <span class="badge badge-info">{{ dept.users?.length || 0 }}</span>
            </div>
            <div v-if="!dept.users || dept.users.length === 0" class="empty-message">No members</div>
            <div v-else class="members-list">
              <div v-for="user in dept.users" :key="user.id" class="member-item">
                <div class="member-info">
                  <strong>{{ user.name }}</strong>
                  <code>{{ user.email }}</code>
                  <span :class="['badge', `badge-${user.pivot.role}`]">{{ user.pivot.role }}</span>
                </div>
                <div class="member-actions">
                  <button @click="openEditRoleModal(user, dept)" class="btn-icon-small">🔄</button>
                  <button @click="removeUserFromDept(user, dept)" class="btn-icon-small btn-danger">✖️</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Evaluation Questions Tab -->
      <div v-if="activeTab === 'evaluations'" class="tab-content">
        <div class="section-header">
          <h2>Evaluation Questions</h2>
          <div class="weight-display">
            <span class="weight-label">Total Weight:</span>
            <span :class="['weight-value', totalWeight === 100 ? 'complete' : 'incomplete']">
              {{ totalWeight }}%
            </span>
            <span class="weight-remaining">({{ 100 - totalWeight }}% remaining)</span>
          </div>
          <button @click="openQuestionModal()" class="btn-primary">➕ Add Question</button>
        </div>

        <div v-if="totalWeight !== 100" class="alert alert-warning">
          ⚠️ Total weight must equal 100% before evaluation questions can be used.
        </div>

        <div v-if="isLoading" class="loading">Loading...</div>
        <div v-else-if="evaluationQuestions.length === 0" class="empty-state">
          <p>No evaluation questions yet. Add your first question above.</p>
        </div>
        <div v-else class="questions-list">
          <div v-for="question in evaluationQuestions" :key="question.id" class="question-card">
            <div class="question-header">
              <span class="question-order">#{{ question.order }}</span>
              <span :class="['question-status', question.is_active ? 'active' : 'inactive']">
                {{ question.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
            <div class="question-body">
              <p class="question-text">{{ question.question }}</p>
              <div class="question-meta">
                <span class="weight-badge">Weight: {{ question.weight }}%</span>
                <span class="question-date">Created: {{ formatDate(question.created_at) }}</span>
              </div>
            </div>
            <div class="question-actions">
              <button @click="openQuestionModal(question)" class="btn-icon">✏️ Edit</button>
              <button
                @click="toggleQuestionStatus(question)"
                class="btn-icon"
                :title="question.is_active ? 'Deactivate' : 'Activate'"
              >
                {{ question.is_active ? '⏸️' : '▶️' }}
              </button>
              <button @click="deleteQuestion(question)" class="btn-icon btn-danger">🗑️ Delete</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Question Modal -->
    <div v-if="questionModal.show" class="modal-overlay" @click="closeQuestionModal">
      <div class="modal-content" @click.stop>
        <h2>{{ questionModal.isEdit ? 'Edit' : 'Add' }} Evaluation Question</h2>
        <div class="form-group">
          <label>Question *</label>
          <textarea v-model="questionModal.form.question" rows="3" required placeholder="Enter the evaluation question..."></textarea>
        </div>
        <div class="form-group">
          <label>Weight (%) *</label>
          <input v-model.number="questionModal.form.weight" type="number" min="0" max="100" step="0.01" required />
          <small>Current total: {{ totalWeight }}% | Available: {{ questionModal.availableWeight }}%</small>
        </div>
        <div class="form-group">
          <label>Display Order</label>
          <input v-model.number="questionModal.form.order" type="number" min="0" />
        </div>
        <div class="form-group">
          <label><input v-model="questionModal.form.is_active" type="checkbox" /> Active</label>
        </div>
        <div class="modal-actions">
          <button @click="closeQuestionModal" class="btn-secondary">Cancel</button>
          <button @click="saveQuestion" :disabled="questionModal.isLoading" class="btn-primary">
            {{ questionModal.isLoading ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="departmentModal.show" class="modal-overlay" @click="closeDepartmentModal">
      <div class="modal-content" @click.stop>
        <h2>{{ departmentModal.isEdit ? 'Edit' : 'Add' }} Department</h2>
        <div class="form-group">
          <label>Name *</label>
          <input v-model="departmentModal.form.name" type="text" required />
        </div>
        <div class="form-group">
          <label>Code *</label>
          <input v-model="departmentModal.form.code" type="text" required />
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea v-model="departmentModal.form.description" rows="3"></textarea>
        </div>
        <div class="form-group-inline">
          <label><input v-model="departmentModal.form.is_active" type="checkbox" /> Active</label>
          <label><input v-model="departmentModal.form.is_department_a" type="checkbox" /> Is Dept A</label>
        </div>
        <div class="modal-actions">
          <button @click="closeDepartmentModal" class="btn-secondary">Cancel</button>
          <button @click="saveDepartment" :disabled="departmentModal.isLoading" class="btn-primary">
            {{ departmentModal.isLoading ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="userModal.show" class="modal-overlay" @click="closeUserModal">
      <div class="modal-content" @click.stop>
        <h2>{{ userModal.isEdit ? 'Edit' : 'Add' }} User</h2>
        <div class="form-group">
          <label>Name *</label>
          <input v-model="userModal.form.name" type="text" required />
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input v-model="userModal.form.email" type="email" required />
        </div>
        <div class="form-group">
          <label>Password {{ userModal.isEdit ? '(leave blank to keep)' : '*' }}</label>
          <input v-model="userModal.form.password" type="password" :required="!userModal.isEdit" />
        </div>
        <div class="form-group">
          <label>Role *</label>
          <select v-model="userModal.form.role" required>
            <option value="admin">Admin</option>
            <option value="manager">Manager</option>
            <option value="employee">Employee</option>
            <option value="user">User</option>
          </select>
        </div>
        <div class="form-group">
          <label><input v-model="userModal.form.is_active" type="checkbox" /> Active</label>
        </div>
        <div class="modal-actions">
          <button @click="closeUserModal" class="btn-secondary">Cancel</button>
          <button @click="saveUser" :disabled="userModal.isLoading" class="btn-primary">
            {{ userModal.isLoading ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="assignmentModal.show" class="modal-overlay" @click="closeAssignmentModal">
      <div class="modal-content" @click.stop>
        <h2>Assign User to Department</h2>
        <div class="form-group">
          <label>User *</label>
          <select v-model="assignmentModal.form.user_id" required>
            <option value="">Select User</option>
            <option v-for="user in users.filter(u => ['manager', 'employee'].includes(u.role))" :key="user.id" :value="user.id">
              {{ user.name }} ({{ user.email }})
            </option>
          </select>
        </div>
        <div class="form-group">
          <label>Department *</label>
          <select v-model="assignmentModal.form.department_id" required>
            <option value="">Select Department</option>
            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Role in Department *</label>
          <select v-model="assignmentModal.form.role" required>
            <option value="manager">Manager</option>
            <option value="employee">Employee</option>
          </select>
        </div>
        <div class="modal-actions">
          <button @click="closeAssignmentModal" class="btn-secondary">Cancel</button>
          <button @click="saveAssignment" :disabled="assignmentModal.isLoading" class="btn-primary">
            {{ assignmentModal.isLoading ? 'Assigning...' : 'Assign' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="editRoleModal.show" class="modal-overlay" @click="closeEditRoleModal">
      <div class="modal-content" @click.stop>
        <h2>Change User Role</h2>
        <p class="modal-subtitle">
          <strong>User:</strong> {{ editRoleModal.user?.name }}<br />
          <strong>Department:</strong> {{ editRoleModal.department?.name }}
        </p>
        <div class="form-group">
          <label>New Role *</label>
          <select v-model="editRoleModal.newRole" required>
            <option value="manager">Manager</option>
            <option value="employee">Employee</option>
          </select>
        </div>
        <div class="modal-actions">
          <button @click="closeEditRoleModal" class="btn-secondary">Cancel</button>
          <button @click="updateUserRole" :disabled="editRoleModal.isLoading" class="btn-primary">
            {{ editRoleModal.isLoading ? 'Updating...' : 'Update Role' }}
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
import axios from 'axios'

const router = useRouter()
const authStore = useAuthStore()

const activeTab = ref('departments')
const departments = ref([])
const users = ref([])
const evaluationQuestions = ref([])
const error = ref(null)
const success = ref(null)
const isLoading = ref(true)

const API_URL = 'http://localhost:8000/api'

const departmentModal = ref({ show: false, isEdit: false, isLoading: false, form: { name: '', code: '', description: '', is_active: true, is_department_a: false }, editId: null })
const userModal = ref({ show: false, isEdit: false, isLoading: false, form: { name: '', email: '', password: '', role: 'employee', is_active: true }, editId: null })
const assignmentModal = ref({ show: false, isLoading: false, form: { user_id: '', department_id: '', role: 'employee' } })
const editRoleModal = ref({ show: false, isLoading: false, user: null, department: null, newRole: 'employee' })
const questionModal = ref({ show: false, isEdit: false, isLoading: false, form: { question: '', weight: 0, order: 0, is_active: true }, editId: null, availableWeight: 100 })

const totalWeight = computed(() => {
  return evaluationQuestions.value
    .filter(q => q.is_active)
    .reduce((sum, q) => sum + parseFloat(q.weight || 0), 0)
})

onMounted(async () => {
  if (authStore.user?.role !== 'admin') {
    error.value = 'Access denied. Admin only.'
    setTimeout(() => router.push('/dashboard'), 2000)
    return
  }
  await loadData()
})

const loadData = async () => {
  isLoading.value = true
  error.value = null
  try {
    const [deptsRes, usersRes, questionsRes] = await Promise.all([
      axios.get(`${API_URL}/admin/departments`, { headers: { Authorization: `Bearer ${authStore.token}` } }),
      axios.get(`${API_URL}/admin/users`, { headers: { Authorization: `Bearer ${authStore.token}` } }),
      axios.get(`${API_URL}/admin/evaluation-questions`, { headers: { Authorization: `Bearer ${authStore.token}` } })
    ])
    departments.value = deptsRes.data.departments
    users.value = usersRes.data.users
    evaluationQuestions.value = questionsRes.data.questions
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load data'
  } finally {
    isLoading.value = false
  }
}

const refresh = async () => {
  await loadData()
  success.value = 'Refreshed'
  setTimeout(() => (success.value = null), 3000)
}

const goBack = () => router.push('/dashboard')

const openDepartmentModal = (dept = null) => {
  departmentModal.value = dept ? {
    show: true, isEdit: true, isLoading: false,
    form: { name: dept.name, code: dept.code, description: dept.description || '', is_active: dept.is_active, is_department_a: dept.is_department_a || false },
    editId: dept.id
  } : {
    show: true, isEdit: false, isLoading: false,
    form: { name: '', code: '', description: '', is_active: true, is_department_a: false },
    editId: null
  }
}

const closeDepartmentModal = () => { departmentModal.value.show = false }

const saveDepartment = async () => {
  try {
    departmentModal.value.isLoading = true
    error.value = null
    const url = departmentModal.value.isEdit ? `${API_URL}/admin/departments/${departmentModal.value.editId}` : `${API_URL}/admin/departments`
    const method = departmentModal.value.isEdit ? 'put' : 'post'
    await axios[method](url, departmentModal.value.form, { headers: { Authorization: `Bearer ${authStore.token}` } })
    success.value = `Department ${departmentModal.value.isEdit ? 'updated' : 'created'}`
    closeDepartmentModal()
    await loadData()
    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save department'
  } finally {
    departmentModal.value.isLoading = false
  }
}

const deleteDepartment = async (dept) => {
  if (!confirm(`Delete ${dept.name}?`)) return
  try {
    await axios.delete(`${API_URL}/admin/departments/${dept.id}`, { headers: { Authorization: `Bearer ${authStore.token}` } })
    success.value = 'Department deleted'
    await loadData()
    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to delete'
  }
}

const openUserModal = (user = null) => {
  userModal.value = user ? {
    show: true, isEdit: true, isLoading: false,
    form: { name: user.name, email: user.email, password: '', role: user.role, is_active: user.is_active },
    editId: user.id
  } : {
    show: true, isEdit: false, isLoading: false,
    form: { name: '', email: '', password: '', role: 'employee', is_active: true },
    editId: null
  }
}

const closeUserModal = () => { userModal.value.show = false }

const saveUser = async () => {
  try {
    userModal.value.isLoading = true
    error.value = null
    const url = userModal.value.isEdit ? `${API_URL}/admin/users/${userModal.value.editId}` : `${API_URL}/admin/users`
    const method = userModal.value.isEdit ? 'put' : 'post'
    await axios[method](url, userModal.value.form, { headers: { Authorization: `Bearer ${authStore.token}` } })
    success.value = `User ${userModal.value.isEdit ? 'updated' : 'created'}`
    closeUserModal()
    await loadData()
    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save user'
  } finally {
    userModal.value.isLoading = false
  }
}

const deleteUser = async (user) => {
  if (user.id === authStore.user?.id) {
    error.value = 'Cannot delete own account'
    return
  }
  if (!confirm(`Delete ${user.name}?`)) return
  try {
    await axios.delete(`${API_URL}/admin/users/${user.id}`, { headers: { Authorization: `Bearer ${authStore.token}` } })
    success.value = 'User deleted'
    await loadData()
    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to delete'
  }
}

const openAssignmentModal = () => {
  assignmentModal.value = { show: true, isLoading: false, form: { user_id: '', department_id: '', role: 'employee' } }
}

const closeAssignmentModal = () => { assignmentModal.value.show = false }

const saveAssignment = async () => {
  try {
    assignmentModal.value.isLoading = true
    error.value = null
    await axios.post(`${API_URL}/admin/assign-user-department`, assignmentModal.value.form, { headers: { Authorization: `Bearer ${authStore.token}` } })
    success.value = 'User assigned'
    closeAssignmentModal()
    await loadData()
    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to assign'
  } finally {
    assignmentModal.value.isLoading = false
  }
}

const openEditRoleModal = (user, dept) => {
  editRoleModal.value = { show: true, isLoading: false, user, department: dept, newRole: user.pivot.role }
}

const closeEditRoleModal = () => { editRoleModal.value.show = false }

const updateUserRole = async () => {
  try {
    editRoleModal.value.isLoading = true
    error.value = null
    await axios.put(`${API_URL}/admin/update-user-department-role`, {
      user_id: editRoleModal.value.user.id,
      department_id: editRoleModal.value.department.id,
      role: editRoleModal.value.newRole
    }, { headers: { Authorization: `Bearer ${authStore.token}` } })
    success.value = 'Role updated'
    closeEditRoleModal()
    await loadData()
    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to update'
  } finally {
    editRoleModal.value.isLoading = false
  }
}

const removeUserFromDept = async (user, dept) => {
  if (!confirm(`Remove ${user.name} from ${dept.name}?`)) return
  try {
    error.value = null
    await axios.post(`${API_URL}/admin/remove-user-department`, {
      user_id: user.id,
      department_id: dept.id
    }, { headers: { Authorization: `Bearer ${authStore.token}` } })
    success.value = 'User removed'
    await loadData()
    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to remove'
  }
}

// ===== EVALUATION QUESTIONS =====
const openQuestionModal = (question = null) => {
  if (question) {
    questionModal.value = {
      show: true,
      isEdit: true,
      isLoading: false,
      form: {
        question: question.question,
        weight: parseFloat(question.weight),
        order: question.order,
        is_active: question.is_active
      },
      editId: question.id,
      availableWeight: 100 - totalWeight.value + parseFloat(question.weight)
    }
  } else {
    questionModal.value = {
      show: true,
      isEdit: false,
      isLoading: false,
      form: { question: '', weight: 0, order: evaluationQuestions.value.length, is_active: true },
      editId: null,
      availableWeight: 100 - totalWeight.value
    }
  }
}

const closeQuestionModal = () => {
  questionModal.value = { show: false, isEdit: false, isLoading: false, form: { question: '', weight: 0, order: 0, is_active: true }, editId: null, availableWeight: 100 }
}

const saveQuestion = async () => {
  try {
    questionModal.value.isLoading = true
    error.value = null

    if (questionModal.value.isEdit) {
      await axios.put(`${API_URL}/admin/evaluation-questions/${questionModal.value.editId}`, questionModal.value.form, {
        headers: { Authorization: `Bearer ${authStore.token}` }
      })
      success.value = 'Question updated successfully'
    } else {
      await axios.post(`${API_URL}/admin/evaluation-questions`, questionModal.value.form, {
        headers: { Authorization: `Bearer ${authStore.token}` }
      })
      success.value = 'Question created successfully'
    }

    closeQuestionModal()
    await loadData()
    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save question'
  } finally {
    questionModal.value.isLoading = false
  }
}

const toggleQuestionStatus = async (question) => {
  if (!confirm(`Are you sure you want to ${question.is_active ? 'deactivate' : 'activate'} this question?`)) return

  try {
    await axios.put(`${API_URL}/admin/evaluation-questions/${question.id}`, {
      is_active: !question.is_active
    }, {
      headers: { Authorization: `Bearer ${authStore.token}` }
    })
    success.value = `Question ${question.is_active ? 'deactivated' : 'activated'}`
    await loadData()
    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to update question'
  }
}

const deleteQuestion = async (question) => {
  if (!confirm(`Are you sure you want to delete this question? This cannot be undone.`)) return

  try {
    await axios.delete(`${API_URL}/admin/evaluation-questions/${question.id}`, {
      headers: { Authorization: `Bearer ${authStore.token}` }
    })
    success.value = 'Question deleted successfully'
    await loadData()
    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to delete question'
  }
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
</script>

<style scoped>
.page-container { min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; }
.page-card { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); max-width: 1400px; margin: 0 auto; max-height: 90vh; overflow-y: auto; }
.header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; gap: 15px; }
.btn-back, .btn-refresh { padding: 8px 16px; background: #f5f5f5; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; transition: background 0.2s; }
.btn-back:hover, .btn-refresh:hover { background: #e0e0e0; }
h1 { color: #333; font-size: 28px; margin: 0; }
.subtitle { color: #666; margin-bottom: 30px; font-size: 14px; }
.alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
.alert-error { background: #fee; color: #c33; border: 1px solid #fcc; }
.alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #4caf50; }
.tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; }
.tab { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 500; color: #666; transition: all 0.3s; }
.tab:hover { color: #667eea; }
.tab.active { color: #667eea; border-bottom-color: #667eea; }
.tab-content { animation: fadeIn 0.3s; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.section-header h2 { color: #333; font-size: 20px; margin: 0; }
.btn-primary { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-primary:hover:not(:disabled) { background: #5568d3; transform: translateY(-2px); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.loading { text-align: center; padding: 40px; color: #666; font-size: 16px; }
.table-container { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.data-table thead { background: #f8f9fa; }
.data-table th { padding: 12px; text-align: left; font-weight: 600; color: #555; border-bottom: 2px solid #dee2e6; }
.data-table td { padding: 12px; border-bottom: 1px solid #e9ecef; }
.data-table tbody tr:hover { background: #f8f9ff; }
.data-table code { background: #f0f0f0; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
.badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 8px; }
.badge-primary { background: #667eea; color: white; }
.badge-success { background: #4caf50; color: white; }
.badge-inactive { background: #9e9e9e; color: white; }
.badge-admin { background: #ff6b6b; color: white; }
.badge-manager { background: #4ecdc4; color: white; }
.badge-employee { background: #ffd93d; color: #333; }
.badge-user { background: #95e1d3; color: white; }
.badge-dept { background: #e3f2fd; color: #1976d2; margin-right: 4px; }
.badge-info { background: #2196f3; color: white; }
.dept-badges { display: flex; flex-wrap: wrap; gap: 4px; }
.text-muted { color: #999; font-style: italic; }
.actions { display: flex; gap: 8px; }
.btn-icon { padding: 6px 10px; background: #f5f5f5; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; transition: all 0.2s; }
.btn-icon:hover { background: #e0e0e0; transform: scale(1.1); }
.btn-icon.btn-danger:hover { background: #ffebee; }
.btn-icon:disabled { opacity: 0.5; cursor: not-allowed; }
.assignments-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
.assignment-card { border: 2px solid #e0e0e0; border-radius: 10px; padding: 20px; background: white; transition: all 0.3s; }
.assignment-card:hover { border-color: #667eea; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2); }
.assignment-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #e0e0e0; }
.assignment-header h3 { color: #333; font-size: 16px; margin: 0; }
.empty-message { text-align: center; padding: 20px; color: #999; font-style: italic; }
.members-list { display: flex; flex-direction: column; gap: 10px; }
.member-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 8px; transition: background 0.2s; }
.member-item:hover { background: #f0f3ff; }
.member-info { display: flex; flex-direction: column; gap: 4px; min-width: 0; flex: 1; }
.member-info strong { color: #333; font-size: 13px; }
.member-info code { color: #666; font-size: 11px; }
.member-actions { display: flex; gap: 6px; }
.btn-icon-small { padding: 4px 8px; background: #f5f5f5; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; transition: all 0.2s; }
.btn-icon-small:hover { background: #e0e0e0; transform: scale(1.1); }
.btn-icon-small.btn-danger:hover { background: #ffebee; }
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal-content { background: white; border-radius: 15px; padding: 30px; max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto; }
.modal-content h2 { color: #333; margin-bottom: 20px; }
.modal-subtitle { color: #666; font-size: 14px; margin-bottom: 20px; line-height: 1.6; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; font-size: 14px; }
.form-group input[type="text"], .form-group input[type="email"], .form-group input[type="password"], .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-family: inherit; font-size: 14px; transition: border-color 0.3s; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
.form-group textarea { resize: vertical; }
.form-group-inline { display: flex; gap: 20px; margin-bottom: 20px; }
.form-group-inline label { display: flex; align-items: center; gap: 8px; color: #555; font-weight: 500; font-size: 14px; cursor: pointer; }
.form-group-inline input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
.modal-actions { display: flex; gap: 10px; margin-top: 25px; }
.btn-secondary { flex: 1; padding: 12px; background: #f5f5f5; color: #333; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-secondary:hover { background: #e0e0e0; }

/* Evaluation Questions Styles */
.weight-display {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}

.weight-label {
  color: #666;
  font-weight: 500;
}

.weight-value {
  font-size: 18px;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 8px;
}

.weight-value.complete {
  background: #d1e7dd;
  color: #0f5132;
}

.weight-value.incomplete {
  background: #fff3cd;
  color: #856404;
}

.weight-remaining {
  color: #999;
  font-size: 13px;
}

.questions-list {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.question-card {
  border: 2px solid #e0e0e0;
  border-radius: 12px;
  padding: 20px;
  background: white;
  transition: all 0.3s;
}

.question-card:hover {
  border-color: #667eea;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.question-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.question-order {
  background: #667eea;
  color: white;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.question-status {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.question-status.active {
  background: #d1e7dd;
  color: #0f5132;
}

.question-status.inactive {
  background: #f8d7da;
  color: #842029;
}

.question-body {
  margin-bottom: 15px;
}

.question-text {
  color: #333;
  font-size: 15px;
  line-height: 1.6;
  margin: 0 0 10px 0;
}

.question-meta {
  display: flex;
  gap: 15px;
  align-items: center;
}

.weight-badge {
  background: #667eea;
  color: white;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.question-date {
  color: #999;
  font-size: 12px;
}

.question-actions {
  display: flex;
  gap: 8px;
  padding-top: 15px;
  border-top: 1px solid #e0e0e0;
}

.alert-warning {
  background: #fff3cd;
  color: #856404;
  border: 1px solid #ffc107;
}
</style>
