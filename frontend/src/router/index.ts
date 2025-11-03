import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LoginView from '../views/LoginView.vue'
import UserDashboard from '../views/UserDashboard.vue'
import EmployeeDashboard from '../views/EmployeeDashboard.vue'
import ManagerDashboard from '../views/ManagerDashboard.vue'
import AdminDashboard from '../views/AdminDashboard.vue'
import IdeaDetails from '../views/IdeaDetails.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/login',
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guest: true },
    },
    {
      path: '/user',
      name: 'user',
      component: UserDashboard,
      meta: { requiresAuth: true, role: 'user' },
    },
    {
      path: '/idea/:id',
      name: 'ideaDetails',
      component: IdeaDetails,
      meta: { requiresAuth: true },
    },
    {
      path: '/employee',
      name: 'employee',
      component: EmployeeDashboard,
      meta: { requiresAuth: true, role: 'employee' },
    },
    {
      path: '/manager',
      name: 'manager',
      component: ManagerDashboard,
      meta: { requiresAuth: true, role: 'manager' },
    },
    {
      path: '/admin',
      name: 'admin',
      component: AdminDashboard,
      meta: { requiresAuth: true, role: 'admin' },
    },
  ],
})

// Navigation guards
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Fetch user if token exists but user not loaded
  if (authStore.token && !authStore.user) {
    await authStore.fetchUser()
  }

  // Guest routes (login)
  if (to.meta.guest && authStore.isAuthenticated) {
    // Already logged in, redirect to appropriate dashboard
    if (authStore.isAdmin) return next('/admin')
    if (authStore.isManager) return next('/manager')
    if (authStore.isEmployee) return next('/employee')
    return next('/user')
  }

  // Protected routes
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return next('/login')
  }

  // Role-based access
  if (to.meta.role) {
    const role = to.meta.role as string
    if (role === 'admin' && !authStore.isAdmin) {
      return next('/login')
    }
    if (role === 'manager' && !authStore.isManager) {
      return next('/login')
    }
    if (role === 'employee' && !authStore.isEmployee) {
      return next('/login')
    }
    if (role === 'user' && !authStore.isUser) {
      return next('/login')
    }
  }

  next()
})

export default router
