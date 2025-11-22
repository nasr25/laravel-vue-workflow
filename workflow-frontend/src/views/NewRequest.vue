<template>
  <div class="page-container">
    <div class="page-card">
      <div class="header">
        <button @click="goBack" class="btn-back">← Back</button>
        <h1>New Request</h1>
      </div>

      <div v-if="error" class="alert alert-error">
        {{ error }}
      </div>

      <div v-if="success" class="alert alert-success">
        {{ success }}
      </div>

      <form @submit.prevent="handleSubmit">
        <div class="form-group">
          <label for="title">Request Title *</label>
          <input
            type="text"
            id="title"
            v-model="form.title"
            placeholder="Enter a clear title for your request"
            required
            maxlength="255"
          />
        </div>

        <div class="form-group">
          <label for="description">Description *</label>
          <textarea
            id="description"
            v-model="form.description"
            placeholder="Provide detailed information about your request"
            rows="6"
            required
          ></textarea>
        </div>

        <div class="form-group">
          <label for="file">Attachments (Optional)</label>
          <input
            type="file"
            id="file"
            @change="handleFileChange"
            accept="*/*"
            ref="fileInput"
          />
          <p class="help-text">Maximum file size: 10MB</p>
        </div>

        <div v-if="uploadedFiles.length > 0" class="uploaded-files">
          <h3>Uploaded Files:</h3>
          <div
            v-for="file in uploadedFiles"
            :key="file.id"
            class="file-item"
          >
            <span>{{ file.file_name }}</span>
            <button
              type="button"
              @click="removeFile(file.id)"
              class="btn-remove"
            >
              Remove
            </button>
          </div>
        </div>

        <div class="form-actions">
          <button
            type="button"
            @click="saveDraft"
            :disabled="isLoading || !form.title || !form.description"
            class="btn-secondary"
          >
            {{ isLoading ? 'Saving...' : 'Save Draft' }}
          </button>
          <button
            type="submit"
            :disabled="isLoading || !form.title || !form.description"
            class="btn-primary"
          >
            {{ isLoading ? 'Submitting...' : 'Submit Request' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import axios from 'axios'

const router = useRouter()
const authStore = useAuthStore()

const form = ref({
  title: '',
  description: ''
})

const error = ref(null)
const success = ref(null)
const isLoading = ref(false)
const uploadedFiles = ref([])
const fileInput = ref(null)
const currentRequestId = ref(null)

const API_URL = 'http://localhost:8000/api'

onMounted(() => {
  // Ensure only users with 'user' role can access this page
  if (authStore.user?.role !== 'user') {
    error.value = 'Only users can submit requests'
    setTimeout(() => {
      router.push('/dashboard')
    }, 2000)
  }
})

const goBack = () => {
  router.push('/dashboard')
}

const handleFileChange = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  if (file.size > 10 * 1024 * 1024) {
    error.value = 'File size must be less than 10MB'
    return
  }

  // Create draft request first if not exists
  if (!currentRequestId.value) {
    await createDraftRequest()
  }

  if (currentRequestId.value) {
    await uploadFile(file)
  }
}

const createDraftRequest = async () => {
  if (!form.value.title || !form.value.description) {
    error.value = 'Please fill in title and description first'
    return
  }

  try {
    const response = await axios.post(
      `${API_URL}/requests`,
      {
        title: form.value.title,
        description: form.value.description
      },
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`
        }
      }
    )

    currentRequestId.value = response.data.request.id
    return true
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to create draft request'
    return false
  }
}

const uploadFile = async (file) => {
  const formData = new FormData()
  formData.append('file', file)

  try {
    isLoading.value = true
    const response = await axios.post(
      `${API_URL}/requests/${currentRequestId.value}/attachments`,
      formData,
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`,
          'Content-Type': 'multipart/form-data'
        }
      }
    )

    uploadedFiles.value.push(response.data.attachment)
    fileInput.value.value = ''
    success.value = 'File uploaded successfully'
    setTimeout(() => (success.value = null), 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to upload file'
  } finally {
    isLoading.value = false
  }
}

const removeFile = async (attachmentId) => {
  if (!confirm('Are you sure you want to remove this file?')) return

  try {
    await axios.delete(
      `${API_URL}/requests/${currentRequestId.value}/attachments/${attachmentId}`,
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`
        }
      }
    )

    uploadedFiles.value = uploadedFiles.value.filter(f => f.id !== attachmentId)
    success.value = 'File removed successfully'
    setTimeout(() => (success.value = null), 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to remove file'
  }
}

const saveDraft = async () => {
  error.value = null
  isLoading.value = true

  try {
    if (currentRequestId.value) {
      // Update existing draft
      await axios.put(
        `${API_URL}/requests/${currentRequestId.value}`,
        {
          title: form.value.title,
          description: form.value.description
        },
        {
          headers: {
            Authorization: `Bearer ${authStore.token}`
          }
        }
      )
      success.value = 'Draft saved successfully'
    } else {
      // Create new draft
      const created = await createDraftRequest()
      if (created) {
        success.value = 'Draft created successfully'
      }
    }

    setTimeout(() => (success.value = null), 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save draft'
  } finally {
    isLoading.value = false
  }
}

const handleSubmit = async () => {
  error.value = null
  isLoading.value = true

  try {
    // Create or update draft first
    if (!currentRequestId.value) {
      const created = await createDraftRequest()
      if (!created) {
        isLoading.value = false
        return
      }
    } else {
      await axios.put(
        `${API_URL}/requests/${currentRequestId.value}`,
        {
          title: form.value.title,
          description: form.value.description
        },
        {
          headers: {
            Authorization: `Bearer ${authStore.token}`
          }
        }
      )
    }

    // Submit the request
    await axios.post(
      `${API_URL}/requests/${currentRequestId.value}/submit`,
      {},
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`
        }
      }
    )

    success.value = 'Request submitted successfully! Redirecting...'
    setTimeout(() => {
      router.push('/requests')
    }, 2000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to submit request'
  } finally {
    isLoading.value = false
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
  max-width: 800px;
  margin: 0 auto;
}

.header {
  display: flex;
  align-items: center;
  margin-bottom: 30px;
  gap: 15px;
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

.alert-success {
  background: #e8f5e9;
  color: #2e7d32;
  border: 1px solid #4caf50;
}

.form-group {
  margin-bottom: 25px;
}

label {
  display: block;
  margin-bottom: 8px;
  color: #555;
  font-weight: 500;
  font-size: 14px;
}

input[type='text'],
textarea {
  width: 100%;
  padding: 12px 15px;
  border: 2px solid #e0e0e0;
  border-radius: 10px;
  font-size: 15px;
  font-family: inherit;
  transition: all 0.3s;
}

input[type='text']:focus,
textarea:focus {
  outline: none;
  border-color: #667eea;
}

input[type='file'] {
  width: 100%;
  padding: 10px;
  border: 2px dashed #e0e0e0;
  border-radius: 10px;
  cursor: pointer;
}

.help-text {
  font-size: 12px;
  color: #999;
  margin-top: 5px;
}

.uploaded-files {
  background: #f5f5f5;
  padding: 15px;
  border-radius: 10px;
  margin-bottom: 20px;
}

.uploaded-files h3 {
  color: #667eea;
  font-size: 14px;
  margin-bottom: 10px;
}

.file-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px;
  background: white;
  border-radius: 5px;
  margin-bottom: 8px;
  font-size: 13px;
}

.btn-remove {
  padding: 4px 12px;
  background: #ff6b6b;
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 12px;
}

.btn-remove:hover {
  background: #ff5252;
}

.form-actions {
  display: flex;
  gap: 15px;
  margin-top: 30px;
}

.btn-primary,
.btn-secondary {
  flex: 1;
  padding: 14px;
  border: none;
  border-radius: 10px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-secondary {
  background: #f5f5f5;
  color: #333;
}

.btn-primary:hover:not(:disabled),
.btn-secondary:hover:not(:disabled) {
  transform: translateY(-2px);
}

.btn-primary:disabled,
.btn-secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
