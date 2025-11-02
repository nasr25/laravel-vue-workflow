<template>
  <div class="login-container">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
          <div class="card shadow-lg border-0 rounded-4">
            <div class="card-body p-4 p-md-5">
              <!-- Header -->
              <div class="text-center mb-4">
                <h1 class="text-primary fw-bold mb-2">
                  <i class="bi bi-lightbulb-fill"></i>
                  Idea Workflow System
                </h1>
                <h2 class="h4 text-secondary">Sign In</h2>
              </div>

              <!-- Error Alert -->
              <div v-if="authStore.error" class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                {{ authStore.error }}
                <button type="button" class="btn-close" @click="authStore.error = ''"></button>
              </div>

              <!-- Login Form -->
              <form @submit.prevent="handleLogin" novalidate>
                <div class="mb-3">
                  <label for="email" class="form-label fw-semibold">
                    <i class="bi bi-envelope-fill me-1"></i>
                    Email Address
                  </label>
                  <input
                    v-model="email"
                    type="email"
                    class="form-control form-control-lg"
                    id="email"
                    placeholder="Enter your email"
                    required
                    autocomplete="email"
                  />
                </div>

                <div class="mb-4">
                  <label for="password" class="form-label fw-semibold">
                    <i class="bi bi-lock-fill me-1"></i>
                    Password
                  </label>
                  <input
                    v-model="password"
                    type="password"
                    class="form-control form-control-lg"
                    id="password"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                  />
                </div>

                <button
                  type="submit"
                  :disabled="authStore.loading"
                  class="btn btn-primary btn-lg w-100 mb-3"
                >
                  <span v-if="authStore.loading">
                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    Logging in...
                  </span>
                  <span v-else>
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    Login
                  </span>
                </button>
              </form>

              <!-- Test Accounts -->
              <div class="test-accounts mt-4 pt-4 border-top">
                <h3 class="h6 text-center text-muted mb-3">
                  <i class="bi bi-person-badge-fill me-2"></i>
                  Test Accounts (Password: 12345)
                </h3>
                <div class="row g-2">
                  <div class="col-6">
                    <button @click="quickLogin('admin@test.com')" class="btn btn-outline-primary btn-sm w-100">
                      <i class="bi bi-shield-fill-check me-1"></i>
                      Admin
                    </button>
                  </div>
                  <div class="col-6">
                    <button @click="quickLogin('user@test.com')" class="btn btn-outline-success btn-sm w-100">
                      <i class="bi bi-person-fill me-1"></i>
                      User
                    </button>
                  </div>
                  <div class="col-6">
                    <button @click="quickLogin('managera@test.com')" class="btn btn-outline-info btn-sm w-100">
                      <i class="bi bi-person-badge me-1"></i>
                      Manager A
                    </button>
                  </div>
                  <div class="col-6">
                    <button @click="quickLogin('managerb@test.com')" class="btn btn-outline-info btn-sm w-100">
                      <i class="bi bi-person-badge me-1"></i>
                      Manager B
                    </button>
                  </div>
                  <div class="col-6">
                    <button @click="quickLogin('managerc@test.com')" class="btn btn-outline-info btn-sm w-100">
                      <i class="bi bi-person-badge me-1"></i>
                      Manager C
                    </button>
                  </div>
                  <div class="col-6">
                    <button @click="quickLogin('managerd@test.com')" class="btn btn-outline-info btn-sm w-100">
                      <i class="bi bi-person-badge me-1"></i>
                      Manager D
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
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')

const handleLogin = async () => {
  // Basic client-side validation
  if (!email.value || !password.value) {
    authStore.error = 'Please enter both email and password'
    return
  }

  const success = await authStore.login(email.value, password.value)

  if (success) {
    await authStore.fetchUser()

    // Redirect based on role
    if (authStore.isAdmin) {
      router.push('/admin')
    } else if (authStore.isManager) {
      router.push('/manager')
    } else {
      router.push('/user')
    }
  }
}

const quickLogin = (testEmail: string) => {
  email.value = testEmail
  password.value = '12345'
  handleLogin()
}
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 10px;
}

.card {
  backdrop-filter: blur(10px);
  max-height: 95vh;
  overflow-y: auto;
}

.text-primary {
  color: #667eea !important;
}

.btn-primary {
  background-color: #667eea;
  border-color: #667eea;
}

.btn-primary:hover:not(:disabled) {
  background-color: #5568d3;
  border-color: #5568d3;
}

.btn-outline-primary:hover {
  background-color: #667eea;
  border-color: #667eea;
}

.form-control:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
}

/* Responsive adjustments */
@media (max-width: 576px) {
  .login-container {
    padding: 5px;
  }

  .card {
    max-height: 98vh;
  }

  .card-body {
    padding: 1.5rem 1rem !important;
  }

  h1 {
    font-size: 1.25rem;
  }

  h2 {
    font-size: 1.5rem;
  }

  .btn-sm {
    font-size: 0.7rem;
    padding: 0.3rem 0.4rem;
  }

  .test-accounts h3 {
    font-size: 0.875rem;
  }
}

@media (max-width: 380px) {
  .card-body {
    padding: 1rem 0.75rem !important;
  }

  h1 {
    font-size: 1.1rem;
  }

  h2 {
    font-size: 1.3rem;
  }

  .form-control {
    font-size: 14px;
  }
}
</style>
