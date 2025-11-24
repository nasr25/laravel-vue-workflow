import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  {
    path: '/',
    redirect: '/login'
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/Login.vue')
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('../views/Dashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/requests',
    name: 'MyRequests',
    component: () => import('../views/MyRequests.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/requests/new',
    name: 'NewRequest',
    component: () => import('../views/NewRequest.vue'),
    meta: { requiresAuth: true, requiresUserRole: true }
  },
  {
    path: '/requests/:id',
    name: 'RequestDetail',
    component: () => import('../views/RequestDetail.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/requests/:id/edit',
    name: 'EditRequest',
    component: () => import('../views/NewRequest.vue'),
    meta: { requiresAuth: true, isEdit: true }
  },
  {
    path: '/workflow/review',
    name: 'WorkflowReview',
    component: () => import('../views/WorkflowReview.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/department/workflow',
    name: 'DepartmentWorkflow',
    component: () => import('../views/DepartmentWorkflow.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/admin',
    name: 'AdminPanel',
    component: () => import('../views/AdminPanel.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next('/login')
  } else if (to.path === '/login' && authStore.isAuthenticated) {
    next('/dashboard')
  } else if (to.meta.requiresUserRole && authStore.user?.role !== 'user') {
    // Only users with 'user' role can access this route
    next('/dashboard')
  } else if (to.meta.requiresAdmin && authStore.user?.role !== 'admin') {
    // Only admins can access this route
    next('/dashboard')
  } else {
    next()
  }
})

export default router
