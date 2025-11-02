import axios from 'axios'

const API_URL = 'http://localhost:8000/api'

// Create axios instance
const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
})

// Add token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// API methods
export default {
  // Auth
  login(email: string, password: string) {
    return api.post('/login', { email, password })
  },

  register(name: string, email: string, password: string, password_confirmation: string) {
    return api.post('/register', { name, email, password, password_confirmation })
  },

  logout() {
    return api.post('/logout')
  },

  me() {
    return api.get('/me')
  },

  // Ideas (User)
  getMyIdeas() {
    return api.get('/ideas/my-ideas')
  },

  getIdea(id: number) {
    return api.get(`/ideas/${id}`)
  },

  createIdea(data: FormData) {
    return api.post('/ideas', data, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
  },

  updateIdea(id: number, data: FormData) {
    // Laravel method spoofing for PUT with FormData
    data.append('_method', 'PUT')
    return api.post(`/ideas/${id}`, data, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
  },

  submitIdea(id: number) {
    return api.post(`/ideas/${id}/submit`)
  },

  deleteIdea(id: number) {
    return api.delete(`/ideas/${id}`)
  },

  // Approvals (Manager)
  getPendingIdeas() {
    return api.get('/approvals/pending')
  },

  getAllIdeas() {
    return api.get('/approvals/all-ideas')
  },

  approveIdea(ideaId: number, comments?: string) {
    return api.post(`/approvals/${ideaId}/approve`, { comments })
  },

  rejectIdea(ideaId: number, comments: string) {
    return api.post(`/approvals/${ideaId}/reject`, { comments })
  },

  returnIdea(ideaId: number, comments: string) {
    return api.post(`/approvals/${ideaId}/return`, { comments })
  },

  // Admin
  getDepartments() {
    return api.get('/admin/departments')
  },

  getManagers() {
    return api.get('/admin/managers')
  },

  createManager(data: { name: string; email: string; password: string }) {
    return api.post('/admin/managers', data)
  },

  assignManagerToDepartment(managerId: number, departmentId: number) {
    return api.post('/admin/managers/assign', { user_id: managerId, department_id: departmentId })
  },

  removeManagerFromDepartment(managerId: number, departmentId: number) {
    return api.post('/admin/managers/remove', { user_id: managerId, department_id: departmentId })
  },

  updateDepartmentOrder(departments: Array<{ id: number; approval_order: number }>) {
    return api.post('/admin/departments/reorder', { departments })
  },

  getPendingIdeasCount() {
    return api.get('/admin/pending-ideas-count')
  },
}
