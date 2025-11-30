export interface User {
  id: number
  name: string
  email: string
  role_id: number
  role?: Role
  managedDepartments?: Department[]
}

export interface Role {
  id: number
  name: string
  description: string
}

export interface Department {
  id: number
  name: string
  description: string
  approval_order: number
  is_active: boolean
  managers?: User[]
}

export interface Idea {
  id: number
  user_id: number
  name: string
  description: string
  pdf_file_path: string | null
  status: 'draft' | 'pending' | 'approved' | 'rejected' | 'returned'
  current_approval_step: number
  created_at: string
  updated_at: string
  user?: User
  approvals?: IdeaApproval[]
}

export interface IdeaApproval {
  id: number
  idea_id: number
  department_id: number
  manager_id: number | null
  step: number
  status: 'pending' | 'approved' | 'rejected' | 'returned'
  comments: string | null
  reviewed_at: string | null
  created_at: string
  updated_at: string
  department?: Department
  manager?: User
}
