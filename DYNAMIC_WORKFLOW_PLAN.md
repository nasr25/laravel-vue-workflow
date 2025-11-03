# Dynamic Workflow System - Implementation Plan

## Overview
Transform the current single-workflow system into a multi-form, dynamic workflow system with employee-level approvals.

---

## Current System vs. New System

### Current System
- ✗ One type of form (generic "idea")
- ✗ Fixed workflow (Dept A → B → C → D → E)
- ✗ Only managers can approve
- ✗ Same workflow for all submissions

### New System
- ✓ Multiple form types (Budget Request, Leave Request, etc.)
- ✓ Custom workflow per form type
- ✓ Employees AND managers can approve
- ✓ Flexible approval rules (e.g., "any 3 out of 5 employees")
- ✓ Mixed steps (employees → manager → employees)

---

## Example Workflows

### Form 1: Budget Request
```
Step 1: 3 employees from Department A (Finance)
Step 2: 1 manager from Department B (Operations)
→ Approved/Rejected
```

### Form 2: Leave Request
```
Step 1: 2 employees from Department C (HR)
→ Approved/Rejected
```

### Form 3: Project Proposal
```
Step 1: Any 2 out of 5 employees from Department A (Technical Review)
Step 2: Manager from Department A (Technical Lead)
Step 3: Manager from Department B (Business Lead)
Step 4: All 3 employees from Department C (Legal Review)
→ Approved/Rejected
```

---

## Database Schema Design

### New Tables

#### 1. `form_types` - Define types of forms
```sql
- id
- name (e.g., "Budget Request", "Leave Request")
- description
- has_file_upload (boolean)
- file_types_allowed (json: ["pdf", "docx"])
- max_file_size_mb
- is_active
- created_at, updated_at
```

#### 2. `form_fields` - Custom fields per form type (optional)
```sql
- id
- form_type_id
- field_name
- field_type (text, number, date, dropdown, etc.)
- is_required
- options (json, for dropdowns)
- order
```

#### 3. `workflow_templates` - Workflow definition per form
```sql
- id
- form_type_id
- name (e.g., "Standard Budget Approval")
- description
- is_active
- created_at, updated_at
```

#### 4. `workflow_steps` - Individual steps in workflow
```sql
- id
- workflow_template_id
- step_order (1, 2, 3...)
- step_name (e.g., "Finance Review", "Manager Approval")
- approver_type (employee, manager, either)
- department_id
- required_approvals_count (how many approvals needed)
- total_approvers_count (total available approvers)
- approval_mode (all, any_count, percentage)
  - all: All must approve
  - any_count: Any X out of Y must approve
  - percentage: X% must approve
- can_skip (boolean)
- timeout_hours (optional deadline)
- created_at, updated_at
```

#### 5. `workflow_step_approvers` - Specific users assigned to steps
```sql
- id
- workflow_step_id
- user_id
- role (employee, manager)
- created_at, updated_at
```

#### 6. `department_employees` - Employee assignments (like department_managers)
```sql
- id
- department_id
- user_id
- permission (viewer, approver)
- created_at, updated_at
```

### Modified Tables

#### 7. Update `users` table - Add employee role
```sql
- Add to roles: 'employee'
```

#### 8. Update `ideas` table - Link to form type
```sql
- Add: form_type_id (foreign key)
- Add: workflow_template_id (foreign key)
- Add: form_data (json, for custom field values)
```

#### 9. Update `idea_approvals` table - Support employees
```sql
- Rename: manager_id → approver_id
- Add: approver_type (employee, manager)
- Add: workflow_step_id (foreign key)
- Modify: Make it support multiple approvers per step
```

---

## Key Features to Implement

### 1. Admin: Form Type Management
**Location:** Admin Dashboard → Form Types

**Actions:**
- Create new form type (name, description, file settings)
- Add custom fields to form (optional)
- Activate/deactivate form types
- View all submissions per form type

### 2. Admin: Workflow Builder
**Location:** Admin Dashboard → Workflows

**Actions:**
- Create workflow template
- Add steps to workflow:
  - Choose department
  - Choose approver type (employees/manager/either)
  - Set approval count (e.g., "3 employees" or "any 2 out of 5")
  - Set approval mode (all, any count, percentage)
- Reorder steps (drag & drop)
- Assign workflow to form type
- Preview workflow diagram

### 3. Admin: Employee Management
**Location:** Admin Dashboard → Departments → Employees

**Actions:**
- Assign employees to departments
- Set permissions (viewer/approver)
- View employee workload
- Bulk assign employees

### 4. User: Multi-Form Submission
**Location:** User Dashboard

**Changes:**
- Dropdown to select form type
- Dynamic form fields based on form type
- Submit based on selected form's workflow
- Track multiple submission types separately

### 5. Employee Dashboard (NEW)
**Location:** /employee

**Features:**
- View pending approvals assigned to me
- Approve/reject submissions
- Add comments
- View approval progress
- Filter by form type

### 6. Manager Dashboard
**Updates:**
- Filter ideas by form type
- See employee approvals before their turn
- View workflow progress for each submission

---

## Approval Logic Examples

### Example 1: "Any 3 out of 5 employees"
```
Department A has 5 employees assigned to this step
Approval Mode: any_count
Required Approvals: 3
Total Approvers: 5

→ When 3 employees approve, step passes
→ If 3 reject, step fails
→ Remaining 2 employees' votes don't matter
```

### Example 2: "All employees must approve"
```
Department C has 2 employees assigned
Approval Mode: all
Required Approvals: 2
Total Approvers: 2

→ Both must approve for step to pass
→ If 1 rejects, step fails immediately
```

### Example 3: "Manager approval"
```
Department B has 1 manager
Approver Type: manager
Required Approvals: 1

→ Manager must approve
→ Standard behavior (current system)
```

---

## Implementation Phases

### Phase 1: Foundation (Database & Backend)
**Estimated Time:** 2-3 hours

1. Create migrations for new tables
2. Create models (FormType, WorkflowTemplate, WorkflowStep, etc.)
3. Update existing models (Idea, User, IdeaApproval)
4. Create seeder for sample data

**Deliverables:**
- All database tables created
- Models with relationships
- Sample form types and workflows

### Phase 2: Admin Interface
**Estimated Time:** 3-4 hours

1. Form Type CRUD
2. Workflow Builder UI
3. Employee Management
4. Department-Employee assignments

**Deliverables:**
- Admin can create form types
- Admin can build workflows visually
- Admin can assign employees

### Phase 3: Workflow Engine
**Estimated Time:** 2-3 hours

1. Update IdeaWorkflowService for dynamic workflows
2. Implement approval counting logic
3. Handle parallel vs sequential steps
4. Step completion detection

**Deliverables:**
- Dynamic workflow execution
- Proper approval counting
- Workflow progression

### Phase 4: User Interfaces
**Estimated Time:** 2-3 hours

1. Multi-form submission UI
2. Employee Dashboard (new)
3. Update Manager Dashboard
4. Update Idea Details page

**Deliverables:**
- Users can select and submit different forms
- Employees have their own dashboard
- All dashboards show form-specific data

### Phase 5: Testing & Refinement
**Estimated Time:** 1-2 hours

1. Test complex workflows
2. Test edge cases
3. Fix bugs
4. Documentation

---

## API Endpoints (New)

### Form Types
- `GET /api/admin/form-types` - List all form types
- `POST /api/admin/form-types` - Create form type
- `PUT /api/admin/form-types/{id}` - Update form type
- `DELETE /api/admin/form-types/{id}` - Delete form type

### Workflows
- `GET /api/admin/workflows` - List workflows
- `POST /api/admin/workflows` - Create workflow
- `GET /api/admin/workflows/{id}` - Get workflow details
- `PUT /api/admin/workflows/{id}` - Update workflow
- `DELETE /api/admin/workflows/{id}` - Delete workflow

### Workflow Steps
- `POST /api/admin/workflows/{id}/steps` - Add step
- `PUT /api/admin/workflows/{id}/steps/{stepId}` - Update step
- `DELETE /api/admin/workflows/{id}/steps/{stepId}` - Delete step
- `POST /api/admin/workflows/{id}/steps/reorder` - Reorder steps

### Employee Management
- `GET /api/admin/employees` - List all employees
- `POST /api/admin/employees` - Create employee
- `POST /api/admin/employees/assign` - Assign to department
- `POST /api/admin/employees/remove` - Remove from department

### Submissions
- `GET /api/forms/types` - Get available form types
- `POST /api/submissions` - Submit new form
- `GET /api/submissions/my-submissions` - Get user's submissions

### Employee Approvals
- `GET /api/employee/pending` - Get pending approvals for employee
- `POST /api/employee/approve/{id}` - Approve submission
- `POST /api/employee/reject/{id}` - Reject submission

---

## UI Mockups

### Admin: Workflow Builder
```
┌─────────────────────────────────────────┐
│ Create Workflow: Budget Request         │
├─────────────────────────────────────────┤
│                                         │
│ Step 1: Finance Team Review            │
│ ┌─────────────────────────────────────┐ │
│ │ Department: [Department A ▼]       │ │
│ │ Approver Type: [Employees ▼]       │ │
│ │ Approval Mode: [Any Count ▼]       │ │
│ │ Required Approvals: [3]            │ │
│ │ Total Approvers: [5]               │ │
│ │ [+ Assign Specific Employees]      │ │
│ └─────────────────────────────────────┘ │
│                         ↓                │
│ Step 2: Manager Approval                │
│ ┌─────────────────────────────────────┐ │
│ │ Department: [Department B ▼]       │ │
│ │ Approver Type: [Manager ▼]         │ │
│ │ Required Approvals: [1]            │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ [+ Add Step] [Save Workflow]           │
└─────────────────────────────────────────┘
```

### Employee Dashboard
```
┌─────────────────────────────────────────┐
│ 📋 My Pending Approvals                │
├─────────────────────────────────────────┤
│ Budget Request #45                     │
│ Step 1/2: Finance Review (3/5 approved)│
│ Submitted by: John Doe                 │
│ [View Details] [Approve] [Reject]      │
├─────────────────────────────────────────┤
│ Leave Request #23                      │
│ Step 1/1: HR Review (0/2 approved)    │
│ Submitted by: Jane Smith               │
│ [View Details] [Approve] [Reject]      │
└─────────────────────────────────────────┘
```

---

## Questions to Confirm

1. **Employee Authentication**: Should employees have separate login credentials or use same user system?
   - Recommended: Same user system with "employee" role

2. **Parallel Approvals**: Should employees in same step approve simultaneously or sequentially?
   - Recommended: Simultaneously (all see at once, first X to approve wins)

3. **Approval Count**: "3 employees" means:
   - Option A: Exactly 3 specific employees must approve
   - Option B: Any 3 out of all assigned employees
   - Recommended: Option B with admin ability to assign specific employees

4. **Form Fields**: Should we implement custom form fields or keep simple like current?
   - Simple: Name, Description, File (current)
   - Complex: Dynamic fields per form type
   - Recommended: Start simple, add custom fields later

5. **Notification**: Email/notify employees when submission arrives?
   - Recommended: Yes, add email notifications

---

## Estimated Total Time
- **Full Implementation:** 10-15 hours
- **Can be done in phases** - each phase delivers working features

---

## Next Steps

**Option 1: Implement Everything**
- All phases at once
- 10-15 hours of work
- Complete feature-rich system

**Option 2: MVP First**
- Phase 1 + 3 + 4 (skip fancy workflow builder)
- 5-7 hours of work
- Working system with manual workflow setup

**Option 3: Step by Step**
- One phase at a time
- Review and test after each phase
- More control, slower progress

---

## Which approach would you like?
Please confirm:
1. ✓ Overall approach acceptable?
2. ✓ Which implementation option (1, 2, or 3)?
3. ✓ Any specific requirements or changes?
4. ✓ Should we start with a specific form type example?

---

**Ready to transform your workflow system!** 🚀
