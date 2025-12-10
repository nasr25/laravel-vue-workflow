<template>
  <router-view />
</template>

<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from './stores/auth'

const authStore = useAuthStore()

onMounted(() => {
  authStore.initializeAuth()
})
</script>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen,
    Ubuntu, Cantarell, sans-serif;
}

#app {
  width: 100%;
  min-height: 100vh;
}

/* Global Badge Styles */
.badge {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  display: inline-block;
}

.badge-draft {
  background: #f8f9fa;
  color: #6c757d;
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

.badge-need_more_details {
  background: #f8d7da;
  color: #842029;
}

.badge-approved {
  background: #d1e7dd;
  color: #0f5132;
}

.badge-rejected {
  background: #f8d7da;
  color: #842029;
}

.badge-completed {
  background: #d1e7dd;
  color: #0f5132;
}
</style>
