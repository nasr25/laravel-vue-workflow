<template>
  <div class="api-example">
    <h2>API Example with Error Logging</h2>

    <button @click="fetchData">Fetch Data from Laravel API</button>
    <button @click="triggerError">Trigger Error (for testing)</button>

    <div v-if="loading">Loading...</div>
    <div v-if="error" class="error">{{ error }}</div>
    <div v-if="data" class="success">{{ data }}</div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { logger } from '@/utils/logger'

const loading = ref(false)
const error = ref<string | null>(null)
const data = ref<any>(null)

const fetchData = async () => {
  loading.value = true
  error.value = null
  data.value = null

  try {
    const response = await fetch('http://localhost:8000/api/example')

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }

    const result = await response.json()
    data.value = result

    logger.info({
      message: 'Data fetched successfully',
      context: { endpoint: '/api/example' }
    })
  } catch (err) {
    error.value = 'Failed to fetch data. Check console for details.'

    logger.error({
      message: 'Failed to fetch data from API',
      error: err,
      context: {
        endpoint: '/api/example',
        timestamp: new Date().toISOString()
      }
    })
  } finally {
    loading.value = false
  }
}

const triggerError = () => {
  try {
    // Intentionally throw an error for testing
    throw new Error('This is a test error!')
  } catch (err) {
    logger.error({
      message: 'Test error triggered',
      error: err,
      context: { source: 'triggerError button' }
    })
  }
}
</script>

<style scoped>
.api-example {
  padding: 20px;
  border: 1px solid #ccc;
  border-radius: 8px;
  margin: 20px 0;
}

button {
  margin: 10px 10px 10px 0;
  padding: 10px 20px;
  background: #42b983;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

button:hover {
  background: #369970;
}

.error {
  color: red;
  padding: 10px;
  background: #ffebee;
  border-radius: 4px;
  margin-top: 10px;
}

.success {
  color: green;
  padding: 10px;
  background: #e8f5e9;
  border-radius: 4px;
  margin-top: 10px;
}
</style>
