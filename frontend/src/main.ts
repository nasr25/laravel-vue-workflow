import './assets/main.css'

// Bootstrap 5 imports
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap/dist/js/bootstrap.bundle.min.js'
import 'bootstrap-icons/font/bootstrap-icons.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'

const app = createApp(App)

app.use(createPinia())
app.use(router)

// Global error handler for Vue errors
app.config.errorHandler = (err, instance, info) => {
  console.error('Vue Error:', err)
  console.error('Component:', instance)
  console.error('Error Info:', info)

  // You can send errors to your backend or error tracking service here
  // Example: sendErrorToBackend(err, info)
}

// Global handler for unhandled promise rejections
window.addEventListener('unhandledrejection', (event) => {
  console.error('Unhandled Promise Rejection:', event.reason)
  // You can send to backend here
})

// Global handler for runtime errors
window.addEventListener('error', (event) => {
  console.error('Runtime Error:', event.error)
  // You can send to backend here
})

app.mount('#app')
