<template>
  <div class="page-container">
    <div class="page-card">
      <div class="header">
        <button @click="goBack" class="btn-back">← Back</button>
        <h1>Email Templates</h1>
        <button @click="refresh" class="btn-refresh">🔄 Refresh</button>
      </div>

      <p class="subtitle">Manage email notification templates for users, admins, and managers</p>

      <div v-if="error" class="alert alert-error">{{ error }}</div>
      <div v-if="success" class="alert alert-success">{{ success }}</div>

      <!-- Tabs for recipient types -->
      <div class="tabs">
        <button
          :class="['tab', { active: activeRecipientType === 'user' }]"
          @click="filterByRecipientType('user')"
        >
          👤 User Templates ({{ userTemplatesCount }})
        </button>
        <button
          :class="['tab', { active: activeRecipientType === 'admin' }]"
          @click="filterByRecipientType('admin')"
        >
          🔧 Admin Templates ({{ adminTemplatesCount }})
        </button>
        <button
          :class="['tab', { active: activeRecipientType === 'manager' }]"
          @click="filterByRecipientType('manager')"
        >
          👔 Manager Templates ({{ managerTemplatesCount }})
        </button>
      </div>

      <div class="tab-content">
        <div v-if="isLoading" class="loading">Loading templates...</div>
        <div v-else-if="filteredTemplates.length === 0" class="empty-state">
          <p>No templates found for {{ activeRecipientType }} recipients.</p>
        </div>
        <div v-else class="templates-list">
          <div v-for="template in filteredTemplates" :key="template.id" class="template-card">
            <div class="template-header">
              <div class="template-info">
                <h3>{{ template.event_type }}</h3>
                <span :class="['recipient-badge', `badge-${template.recipient_type}`]">
                  {{ template.recipient_type }}
                </span>
                <span :class="['status-badge', template.is_active ? 'active' : 'inactive']">
                  {{ template.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
              <div class="template-actions">
                <button @click="openEditModal(template)" class="btn-icon" title="Edit">✏️</button>
                <button
                  @click="toggleStatus(template)"
                  class="btn-icon"
                  :title="template.is_active ? 'Deactivate' : 'Activate'"
                >
                  {{ template.is_active ? '⏸️' : '▶️' }}
                </button>
                <button @click="openTestModal(template)" class="btn-icon" title="Send Test">📧</button>
              </div>
            </div>

            <div class="template-body">
              <p class="template-description">{{ template.description }}</p>

              <div class="template-content">
                <div class="content-section">
                  <h4>📝 Subject (English)</h4>
                  <p class="subject-preview">{{ template.subject_en }}</p>
                </div>

                <div class="content-section">
                  <h4>📝 Subject (Arabic)</h4>
                  <p class="subject-preview">{{ template.subject_ar }}</p>
                </div>

                <div class="content-section">
                  <h4>📄 Body Preview (English)</h4>
                  <pre class="body-preview">{{ truncateText(template.body_en, 200) }}</pre>
                </div>

                <div class="content-section">
                  <h4>📄 Body Preview (Arabic)</h4>
                  <pre class="body-preview">{{ truncateText(template.body_ar, 200) }}</pre>
                </div>

                <div v-if="template.available_placeholders" class="placeholders">
                  <h4>Available Placeholders:</h4>
                  <div class="placeholder-tags">
                    <span
                      v-for="placeholder in template.available_placeholders"
                      :key="placeholder"
                      class="placeholder-tag"
                    >
                      {{ placeholder }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Template Modal -->
    <div v-if="editModal.show" class="modal-overlay" @click="closeEditModal">
      <div class="modal-content large-modal" @click.stop>
        <h2>Edit Email Template</h2>

        <div class="form-group">
          <label>Event Type</label>
          <input v-model="editModal.form.event_type" type="text" disabled />
        </div>

        <div class="form-group">
          <label>Recipient Type</label>
          <input v-model="editModal.form.recipient_type" type="text" disabled />
        </div>

        <div class="form-group">
          <label>Description</label>
          <textarea v-model="editModal.form.description" rows="2"></textarea>
        </div>

        <div class="form-group">
          <label>Subject (English) *</label>
          <input v-model="editModal.form.subject_en" type="text" required />
        </div>

        <div class="form-group">
          <label>Subject (Arabic) *</label>
          <input v-model="editModal.form.subject_ar" type="text" required dir="rtl" />
        </div>

        <div class="form-group">
          <label>Body (English) *</label>
          <textarea v-model="editModal.form.body_en" rows="8" required></textarea>
        </div>

        <div class="form-group">
          <label>Body (Arabic) *</label>
          <textarea v-model="editModal.form.body_ar" rows="8" required dir="rtl"></textarea>
        </div>

        <div class="form-group">
          <label>
            <input v-model="editModal.form.is_active" type="checkbox" />
            Active
          </label>
        </div>

        <div v-if="editModal.placeholders && editModal.placeholders.length > 0" class="placeholders-help">
          <h4>Available Placeholders:</h4>
          <div class="placeholder-tags">
            <span
              v-for="placeholder in editModal.placeholders"
              :key="placeholder"
              class="placeholder-tag clickable"
              @click="copyPlaceholder(placeholder)"
              :title="'Click to copy ' + placeholder"
            >
              {{ placeholder }}
            </span>
          </div>
        </div>

        <div class="modal-actions">
          <button @click="closeEditModal" class="btn-secondary">Cancel</button>
          <button @click="saveTemplate" :disabled="editModal.isLoading" class="btn-primary">
            {{ editModal.isLoading ? 'Saving...' : 'Save Template' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Test Email Modal -->
    <div v-if="testModal.show" class="modal-overlay" @click="closeTestModal">
      <div class="modal-content" @click.stop>
        <h2>Send Test Email</h2>
        <p class="modal-subtitle">Template: <strong>{{ testModal.template?.event_type }}</strong></p>

        <div class="form-group">
          <label>Recipient Email *</label>
          <input v-model="testModal.form.recipient_email" type="email" required placeholder="test@example.com" />
        </div>

        <div class="form-group">
          <label>Language</label>
          <select v-model="testModal.form.language">
            <option value="en">English</option>
            <option value="ar">Arabic</option>
          </select>
        </div>

        <div class="modal-actions">
          <button @click="closeTestModal" class="btn-secondary">Cancel</button>
          <button @click="sendTestEmail" :disabled="testModal.isLoading" class="btn-primary">
            {{ testModal.isLoading ? 'Sending...' : 'Send Test Email' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const router = useRouter()
const authStore = useAuthStore()

const templates = ref([])
const activeRecipientType = ref('user')
const error = ref(null)
const success = ref(null)
const isLoading = ref(true)

const API_URL = 'http://localhost:8000/api'

const editModal = ref({
  show: false,
  isLoading: false,
  form: {
    event_type: '',
    recipient_type: '',
    subject_en: '',
    subject_ar: '',
    body_en: '',
    body_ar: '',
    description: '',
    is_active: true
  },
  placeholders: [],
  templateId: null
})

const testModal = ref({
  show: false,
  isLoading: false,
  template: null,
  form: {
    recipient_email: '',
    language: 'en'
  }
})

const filteredTemplates = computed(() => {
  return templates.value.filter(t => t.recipient_type === activeRecipientType.value)
})

const userTemplatesCount = computed(() => {
  return templates.value.filter(t => t.recipient_type === 'user').length
})

const adminTemplatesCount = computed(() => {
  return templates.value.filter(t => t.recipient_type === 'admin').length
})

const managerTemplatesCount = computed(() => {
  return templates.value.filter(t => t.recipient_type === 'manager').length
})

onMounted(async () => {
  if (authStore.user?.role !== 'admin') {
    error.value = 'Access denied. Admin only.'
    setTimeout(() => router.push('/dashboard'), 2000)
    return
  }
  await loadTemplates()
})

const loadTemplates = async () => {
  isLoading.value = true
  error.value = null
  try {
    const response = await axios.get(`${API_URL}/email-templates`, {
      headers: { Authorization: `Bearer ${authStore.token}` }
    })
    templates.value = response.data.templates
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load templates'
  } finally {
    isLoading.value = false
  }
}

const filterByRecipientType = (type) => {
  activeRecipientType.value = type
}

const refresh = async () => {
  await loadTemplates()
  success.value = 'Templates refreshed'
  setTimeout(() => (success.value = null), 3000)
}

const goBack = () => router.push('/admin')

const truncateText = (text, maxLength) => {
  if (!text) return ''
  if (text.length <= maxLength) return text
  return text.substring(0, maxLength) + '...'
}

const openEditModal = (template) => {
  editModal.value = {
    show: true,
    isLoading: false,
    form: {
      event_type: template.event_type,
      recipient_type: template.recipient_type,
      subject_en: template.subject_en,
      subject_ar: template.subject_ar,
      body_en: template.body_en,
      body_ar: template.body_ar,
      description: template.description || '',
      is_active: template.is_active
    },
    placeholders: template.available_placeholders || [],
    templateId: template.id
  }
}

const closeEditModal = () => {
  editModal.value = {
    show: false,
    isLoading: false,
    form: {
      event_type: '',
      recipient_type: '',
      subject_en: '',
      subject_ar: '',
      body_en: '',
      body_ar: '',
      description: '',
      is_active: true
    },
    placeholders: [],
    templateId: null
  }
}

const saveTemplate = async () => {
  try {
    editModal.value.isLoading = true
    error.value = null

    await axios.put(
      `${API_URL}/email-templates/${editModal.value.templateId}`,
      editModal.value.form,
      { headers: { Authorization: `Bearer ${authStore.token}` } }
    )

    success.value = 'Template updated successfully'
    closeEditModal()
    await loadTemplates()
    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save template'
  } finally {
    editModal.value.isLoading = false
  }
}

const toggleStatus = async (template) => {
  try {
    error.value = null

    await axios.post(
      `${API_URL}/email-templates/${template.id}/toggle-status`,
      {},
      { headers: { Authorization: `Bearer ${authStore.token}` } }
    )

    success.value = `Template ${template.is_active ? 'deactivated' : 'activated'}`
    await loadTemplates()
    setTimeout(() => (success.value = null), 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to toggle status'
  }
}

const openTestModal = (template) => {
  testModal.value = {
    show: true,
    isLoading: false,
    template,
    form: {
      recipient_email: authStore.user?.email || '',
      language: 'en'
    }
  }
}

const closeTestModal = () => {
  testModal.value = {
    show: false,
    isLoading: false,
    template: null,
    form: {
      recipient_email: '',
      language: 'en'
    }
  }
}

const sendTestEmail = async () => {
  try {
    testModal.value.isLoading = true
    error.value = null

    await axios.post(
      `${API_URL}/email-templates/${testModal.value.template.id}/test`,
      testModal.value.form,
      { headers: { Authorization: `Bearer ${authStore.token}` } }
    )

    success.value = 'Test email sent successfully'
    closeTestModal()
    setTimeout(() => (success.value = null), 5000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to send test email'
  } finally {
    testModal.value.isLoading = false
  }
}

const copyPlaceholder = (placeholder) => {
  navigator.clipboard.writeText(placeholder).then(() => {
    success.value = `Copied ${placeholder} to clipboard`
    setTimeout(() => (success.value = null), 2000)
  }).catch(() => {
    error.value = 'Failed to copy to clipboard'
    setTimeout(() => (error.value = null), 2000)
  })
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
  max-height: 90vh;
  overflow-y: auto;
}

.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
  gap: 15px;
}

.btn-back,
.btn-refresh {
  padding: 8px 16px;
  background: #f5f5f5;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.2s;
}

.btn-back:hover,
.btn-refresh:hover {
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

.tabs {
  display: flex;
  gap: 10px;
  margin-bottom: 30px;
  border-bottom: 2px solid #e0e0e0;
}

.tab {
  padding: 12px 24px;
  background: none;
  border: none;
  border-bottom: 3px solid transparent;
  cursor: pointer;
  font-size: 15px;
  font-weight: 500;
  color: #666;
  transition: all 0.3s;
}

.tab:hover {
  color: #667eea;
}

.tab.active {
  color: #667eea;
  border-bottom-color: #667eea;
}

.tab-content {
  animation: fadeIn 0.3s;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
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
  color: #999;
}

.templates-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.template-card {
  border: 2px solid #e0e0e0;
  border-radius: 12px;
  padding: 25px;
  background: white;
  transition: all 0.3s;
}

.template-card:hover {
  border-color: #667eea;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.template-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid #e0e0e0;
}

.template-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.template-info h3 {
  color: #333;
  font-size: 18px;
  margin: 0;
  font-family: 'Courier New', monospace;
}

.recipient-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  width: fit-content;
}

.badge-user {
  background: #95e1d3;
  color: white;
}

.badge-admin {
  background: #ff6b6b;
  color: white;
}

.badge-manager {
  background: #4ecdc4;
  color: white;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  width: fit-content;
}

.status-badge.active {
  background: #d1e7dd;
  color: #0f5132;
}

.status-badge.inactive {
  background: #f8d7da;
  color: #842029;
}

.template-actions {
  display: flex;
  gap: 8px;
}

.btn-icon {
  padding: 8px 12px;
  background: #f5f5f5;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.2s;
}

.btn-icon:hover {
  background: #667eea;
  color: white;
  transform: scale(1.1);
}

.template-body {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.template-description {
  color: #666;
  font-size: 14px;
  font-style: italic;
  margin: 0;
}

.template-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.content-section {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 8px;
}

.content-section h4 {
  color: #555;
  font-size: 13px;
  margin: 0 0 10px 0;
  font-weight: 600;
}

.subject-preview {
  color: #333;
  font-size: 14px;
  margin: 0;
  font-weight: 500;
}

.body-preview {
  color: #555;
  font-size: 12px;
  line-height: 1.6;
  margin: 0;
  white-space: pre-wrap;
  font-family: 'Courier New', monospace;
}

.placeholders {
  grid-column: 1 / -1;
  background: #fff3cd;
  padding: 15px;
  border-radius: 8px;
  border: 1px solid #ffc107;
}

.placeholders h4 {
  color: #856404;
  font-size: 13px;
  margin: 0 0 10px 0;
  font-weight: 600;
}

.placeholder-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.placeholder-tag {
  background: #667eea;
  color: white;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
  font-family: 'Courier New', monospace;
}

.placeholder-tag.clickable {
  cursor: pointer;
  transition: all 0.2s;
}

.placeholder-tag.clickable:hover {
  background: #5568d3;
  transform: scale(1.05);
}

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

.modal-content.large-modal {
  max-width: 900px;
}

.modal-content h2 {
  color: #333;
  margin-bottom: 20px;
}

.modal-subtitle {
  color: #666;
  font-size: 14px;
  margin-bottom: 20px;
  line-height: 1.6;
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

.form-group input[type="text"],
.form-group input[type="email"],
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 10px 12px;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  font-family: inherit;
  font-size: 14px;
  transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #667eea;
}

.form-group textarea {
  resize: vertical;
  font-family: 'Courier New', monospace;
}

.form-group input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  margin-right: 8px;
}

.placeholders-help {
  background: #f0f3ff;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
}

.placeholders-help h4 {
  color: #333;
  font-size: 13px;
  margin: 0 0 10px 0;
  font-weight: 600;
}

.modal-actions {
  display: flex;
  gap: 10px;
  margin-top: 25px;
}

.btn-secondary {
  flex: 1;
  padding: 12px;
  background: #f5f5f5;
  color: #333;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-secondary:hover {
  background: #e0e0e0;
}

.btn-primary {
  flex: 1;
  padding: 12px;
  background: #667eea;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary:hover:not(:disabled) {
  background: #5568d3;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
