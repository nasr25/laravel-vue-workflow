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

  returnIdea(ideaId: number, comments: string, returnToDepartmentId?: number) {
    return api.post(`/approvals/${ideaId}/return`, {
      comments,
      return_to_department_id: returnToDepartmentId
    })
  },

  getReturnDepartments(ideaId: number) {
    return api.get(`/approvals/${ideaId}/return-departments`)
  },

  // Form Types
  getFormTypes() {
    return api.get('/form-types')
  },

  getFormType(id: number) {
    return api.get(`/form-types/${id}`)
  },

  // Employee Approvals
  getEmployeePendingIdeas() {
    return api.get('/employee/pending')
  },

  approveIdeaAsEmployee(ideaId: number, comments?: string) {
    return api.post(`/employee/${ideaId}/approve`, { comments })
  },

  rejectIdeaAsEmployee(ideaId: number, comments: string) {
    return api.post(`/employee/${ideaId}/reject`, { comments })
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

  assignManagerToDepartment(managerId: number, departmentId: number, permission: 'viewer' | 'approver' = 'approver') {
    return api.post('/admin/managers/assign', { user_id: managerId, department_id: departmentId, permission })
  },

  updateManagerPermission(managerId: number, departmentId: number, permission: string) {
    return api.post('/admin/managers/update-permission', { user_id: managerId, department_id: departmentId, permission })
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

  createDepartment(data: { name: string; description: string; is_active: boolean; approval_order?: number }) {
    return api.post('/admin/departments', data)
  },

  updateDepartment(id: number, data: { name: string; description: string; approval_order: number; is_active: boolean }) {
    return api.put(`/admin/departments/${id}`, data)
  },

  deleteDepartment(id: number) {
    return api.delete(`/admin/departments/${id}`)
  },

  // User Management
  getAllUsers() {
    return api.get('/admin/users')
  },

  updateUser(id: number, data: { name: string; email: string; password?: string }) {
    return api.put(`/admin/users/${id}`, data)
  },

  deleteUser(id: number) {
    return api.delete(`/admin/users/${id}`)
  },

  // Employee Management
  getEmployees() {
    return api.get('/admin/employees')
  },

  createEmployee(data: { name: string; email: string; password: string }) {
    return api.post('/admin/employees', data)
  },

  assignEmployeeToDepartment(userId: number, departmentId: number, permission: 'viewer' | 'approver') {
    return api.post('/admin/employees/assign', { user_id: userId, department_id: departmentId, permission })
  },

  removeEmployeeFromDepartment(userId: number, departmentId: number) {
    return api.post('/admin/employees/remove', { user_id: userId, department_id: departmentId })
  },

  // Form Type Management (Admin)
  getAdminFormTypes() {
    return api.get('/admin/form-types')
  },

  createFormType(data: {
    name: string;
    description?: string;
    icon?: string;
    has_file_upload?: boolean;
    file_types_allowed?: string[];
    max_file_size_mb?: number;
    is_active?: boolean;
  }) {
    return api.post('/admin/form-types', data)
  },

  updateFormType(id: number, data: {
    name?: string;
    description?: string;
    icon?: string;
    has_file_upload?: boolean;
    file_types_allowed?: string[];
    max_file_size_mb?: number;
    is_active?: boolean;
  }) {
    return api.put(`/admin/form-types/${id}`, data)
  },

  deleteFormType(id: number) {
    return api.delete(`/admin/form-types/${id}`)
  },

  // Workflow Template Management
  getWorkflowTemplates() {
    return api.get('/admin/workflows')
  },

  createWorkflowTemplate(data: {
    form_type_id: number;
    name: string;
    description?: string;
    is_active?: boolean;
  }) {
    return api.post('/admin/workflows', data)
  },

  updateWorkflowTemplate(id: number, data: {
    name?: string;
    description?: string;
    is_active?: boolean;
  }) {
    return api.put(`/admin/workflows/${id}`, data)
  },

  deleteWorkflowTemplate(id: number) {
    return api.delete(`/admin/workflows/${id}`)
  },

  // Workflow Step Management
  createWorkflowStep(data: {
    workflow_template_id: number;
    step_name: string;
    approver_type: 'employee' | 'manager' | 'either';
    department_id: number;
    required_approvals_count: number;
    approval_mode: 'all' | 'any_count';
    can_skip?: boolean;
    timeout_hours?: number;
    approver_ids?: number[];
  }) {
    return api.post('/admin/workflow-steps', data)
  },

  updateWorkflowStep(id: number, data: {
    step_name?: string;
    approver_type?: 'employee' | 'manager' | 'either';
    department_id?: number;
    required_approvals_count?: number;
    approval_mode?: 'all' | 'any_count';
    can_skip?: boolean;
    timeout_hours?: number;
  }) {
    return api.put(`/admin/workflow-steps/${id}`, data)
  },

  deleteWorkflowStep(id: number) {
    return api.delete(`/admin/workflow-steps/${id}`)
  },

  addWorkflowStepApprover(workflowStepId: number, userId: number, role: 'employee' | 'manager') {
    return api.post('/admin/workflow-steps/add-approver', {
      workflow_step_id: workflowStepId,
      user_id: userId,
      role
    })
  },

  removeWorkflowStepApprover(workflowStepId: number, userId: number) {
    return api.post('/admin/workflow-steps/remove-approver', {
      workflow_step_id: workflowStepId,
      user_id: userId
    })
  },
}
