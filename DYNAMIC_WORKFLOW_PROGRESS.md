# Dynamic Workflow Implementation - Progress Report

**Date:** November 3, 2025
**Feature:** Multi-Form, Dynamic Workflow with Employee Approvals
**Implementation:** Option 2 (MVP) - In Progress

---

## ✅ Phase 1: Database Foundation - COMPLETED

### Migrations Created and Applied

#### 1. **form_types** table ✓
Stores different types of forms (Budget Request, Leave Request, etc.)
```
- id
- name
- description
- icon
- has_file_upload
- file_types_allowed (json)
- max_file_size_mb
- is_active
- timestamps
```

#### 2. **workflow_templates** table ✓
Defines workflows for each form type
```
- id
- form_type_id (FK)
- name
- description
- is_active
- timestamps
```

#### 3. **workflow_steps** table ✓
Individual steps in a workflow
```
- id
- workflow_template_id (FK)
- step_order (1, 2, 3...)
- step_name
- approver_type (employee, manager, either)
- department_id (FK)
- required_approvals_count
- approval_mode (all, any_count)
- can_skip
- timeout_hours
- timestamps
```

#### 4. **workflow_step_approvers** table ✓
Assigns specific users to workflow steps
```
- id
- workflow_step_id (FK)
- user_id (FK)
- role (employee, manager)
- timestamps
- unique(workflow_step_id, user_id)
```

#### 5. **department_employees** table ✓
Employee assignments to departments
```
- id
- department_id (FK)
- user_id (FK)
- permission (viewer, approver)
- timestamps
- unique(department_id, user_id)
```

#### 6. **ideas** table - UPDATED ✓
Added multi-form support
```
+ form_type_id (FK) - Which form type
+ workflow_template_id (FK) - Which workflow to use
+ form_data (json) - Custom form field data (for future)
```

#### 7. **idea_approvals** table - UPDATED ✓
Added employee support
```
~ manager_id → approver_id (renamed)
+ approver_type (employee, manager)
+ workflow_step_id (FK)
+ approvals_received (count)
+ approvals_required (target count)
```

---

## 📊 Database Schema Summary

### Current State
- ✅ 7 new/updated tables
- ✅ All foreign keys configured
- ✅ Unique constraints in place
- ✅ Enums for type safety
- ✅ JSON fields for flexibility

### Migration Files
All created in `/home/nasser/my-app/backend/database/migrations/`
- `2025_11_03_184552_create_form_types_table.php`
- `2025_11_03_184607_create_workflow_templates_table.php`
- `2025_11_03_184607_create_workflow_steps_table.php`
- `2025_11_03_184607_create_workflow_step_approvers_table.php`
- `2025_11_03_184608_create_department_employees_table.php`
- `2025_11_03_184608_add_form_support_to_ideas_table.php`
- `2025_11_03_184608_add_employee_support_to_idea_approvals_table.php`

**Status:** ✅ All applied successfully

---

## ✅ Phase 2: Models & Relationships - COMPLETED

### Models Created
All models have been created with proper relationships and helper methods:

1. **FormType.php** ✓
   - Relationships: workflowTemplates(), activeWorkflowTemplate(), ideas()
   - Scope: active()

2. **WorkflowTemplate.php** ✓
   - Relationships: formType(), steps(), ideas()
   - Scope: active()
   - Attribute: totalSteps

3. **WorkflowStep.php** ✓
   - Relationships: workflowTemplate(), department(), approvers(), users(), ideaApprovals()
   - Helper methods: requiresEmployeeApproval(), requiresManagerApproval(), requiresAllApprovals()

4. **WorkflowStepApprover.php** ✓
   - Relationships: workflowStep(), user()
   - Helper methods: isEmployee(), isManager()

5. **DepartmentEmployee.php** ✓
   - Relationships: department(), user()
   - Helper methods: canApprove(), isViewer()
   - Scopes: approvers(), viewers()

### Existing Models Updated

1. **Idea.php** ✓
   - Added fields: form_type_id, workflow_template_id, form_data
   - New relationships: formType(), workflowTemplate()

2. **IdeaApproval.php** ✓
   - Renamed: manager_id → approver_id
   - Added fields: approver_type, workflow_step_id, approvals_received, approvals_required
   - New relationship: workflowStep()
   - Updated relationship: approver() (replaces manager())
   - Helper methods: isEmployeeApproval(), isManagerApproval(), needsMoreApprovals(), hasAllApprovals()

3. **User.php** ✓
   - New method: isEmployee()
   - New relationships: employeeDepartments(), departmentEmployeeRecords(), workflowStepApprovers(), assignedWorkflowSteps(), approvalsMade()

4. **Department.php** ✓
   - New relationships: employees(), departmentEmployees(), workflowSteps()

**Status:** ✅ All models created and updated

---

## ✅ Phase 3: Seed Data - COMPLETED

### Migration Created
- `2025_11_03_185703_add_employee_role_to_roles_table.php` ✓
  - Added 'employee' role to roles table

### Seeder Created
- `DynamicWorkflowSeeder.php` ✓

### Data Created

**✅ 2 Form Types:**
1. Budget Request
   - Has file upload, accepts: pdf, xlsx, docx
   - Max file size: 10MB

2. Leave Request
   - No file upload required
   - Max file size: 5MB

**✅ 2 Workflow Templates:**
1. Budget Approval Workflow (for Budget Request)
2. Leave Approval Workflow (for Leave Request)

**✅ 3 Workflow Steps:**

**Budget Request Workflow:**
- Step 1: Finance Team Review (Dept A)
  - Approver type: employee
  - Required: 2 out of 3 employees
  - Timeout: 48 hours

- Step 2: Operations Manager Approval (Dept B)
  - Approver type: manager
  - Required: 1 manager
  - Timeout: 72 hours

**Leave Request Workflow:**
- Step 1: HR Employee Review (Dept C)
  - Approver type: employee
  - Required: 1 out of 2 employees
  - Timeout: 24 hours

**✅ 5 Employee Accounts:**
- employee1@test.com / 12345 (Finance - Dept A)
- employee2@test.com / 12345 (Finance - Dept A)
- employee3@test.com / 12345 (Finance - Dept A)
- employee4@test.com / 12345 (HR - Dept C)
- employee5@test.com / 12345 (HR - Dept C)

**✅ Department Assignments:**
- 3 employees assigned to Department A (Finance)
- 2 employees assigned to Department C (HR)

**✅ Workflow Step Assignments:**
- Budget Step 1: 3 employees from Dept A
- Leave Step 1: 2 employees from Dept C

**Status:** ✅ All seed data created and verified

---

## 🔄 What's Next - Remaining Phases

---

### Phase 4: Workflow Engine (Not Started)
**Estimated Time:** 2 hours

**Tasks:**
1. Rewrite IdeaWorkflowService for dynamic workflows
2. Implement approval counting logic
3. Handle "any X out of Y" approval mode
4. Create submission logic based on form type
5. Handle workflow progression

**Key Logic:**
- When user submits: Load workflow template for form type
- Create approval records for each workflow step
- Track approvals_received vs approvals_required
- Move to next step when required approvals met

---

### Phase 5: Backend API (Not Started)
**Estimated Time:** 1.5 hours

**Tasks:**
1. Create EmployeeController
2. Update IdeaController for multi-form
3. Add form type endpoints
4. Add employee approval endpoints

**New Endpoints:**
- `GET /api/forms/types` - Get available form types
- `GET /api/employee/pending` - Get pending for employee
- `POST /api/employee/approve/{id}` - Employee approve
- `POST /api/employee/reject/{id}` - Employee reject

---

### Phase 6: Frontend Updates (Not Started)
**Estimated Time:** 2-3 hours

**Tasks:**
1. Update UserDashboard for form type selection
2. Create EmployeeDashboard.vue
3. Update router for employee route
4. Update IdeaDetails for employee approvals
5. Add form type filtering

**New Components:**
- `frontend/src/views/EmployeeDashboard.vue`

**Updated Components:**
- `frontend/src/views/UserDashboard.vue`
- `frontend/src/views/IdeaDetails.vue`
- `frontend/src/router/index.ts`
- `frontend/src/services/api.ts`

---

## 📈 Overall Progress

| Phase | Status | Time Estimate | Completed |
|-------|--------|---------------|-----------|
| 1. Database Migrations | ✅ DONE | 1 hour | 100% |
| 2. Models & Relationships | ✅ DONE | 1 hour | 100% |
| 3. Seed Data | ✅ DONE | 1 hour | 100% |
| 4. Workflow Engine | ⏸️ TODO | 2 hours | 0% |
| 5. Backend API | ⏸️ TODO | 1.5 hours | 0% |
| 6. Frontend Updates | ⏸️ TODO | 2-3 hours | 0% |
| **TOTAL** | **IN PROGRESS** | **8.5-9.5 hours** | **~35%** |

---

## 🎯 Next Session Plan

### Recommended Continuation:
**Start with Phase 4 - Workflow Engine:**
The database foundation, models, and seed data are all complete. Next step is to rewrite the workflow engine to support:
1. Dynamic workflow loading from templates
2. Employee approvals (not just managers)
3. Approval counting logic ("2 out of 3" approvals)
4. Automatic progression through workflow steps

**Then move to Phase 5 - Backend API:**
- Create employee approval endpoints
- Add form type selection endpoints
- Update idea submission to use workflows

**Finally Phase 6 - Frontend:**
- Update user dashboard for form type selection
- Create employee dashboard
- Update idea details to show employee approvals

---

## 💡 What You Can Do Now

Phases 1, 2, and 3 are complete! You can:
1. Review the new models in `app/Models/`
2. Test employee login: employee1@test.com / 12345
3. Run `php artisan tinker` to explore the data:
   ```php
   FormType::all()
   WorkflowTemplate::with('steps')->get()
   User::where('email', 'employee1@test.com')->first()->employeeDepartments
   ```
4. Decide when to continue with Phase 4 (Workflow Engine)

---

## 🔍 Verification Commands

```bash
# Check seeded data
php artisan tinker
>>> FormType::all()
>>> WorkflowTemplate::with('steps.approvers.user')->get()
>>> User::where('role_id', Role::where('name', 'employee')->first()->id)->get()

# Verify employee assignments
>>> DepartmentEmployee::with('user', 'department')->get()

# Check workflow step approvers
>>> WorkflowStepApprover::with('user', 'workflowStep')->get()
```

---

## 📝 Important Notes

1. **Backward Compatibility:** Existing ideas still work with old workflow
   - New workflow system is separate
   - Old ideas don't have form_type_id (nullable field)
   - Can migrate old ideas to "Legacy" form type later

2. **Employee Role:** ✅ Added to roles table
   - Employees can only approve, not submit ideas
   - 5 test employees created

3. **Manager vs Employee:**
   - Managers: `department_managers` table (unchanged)
   - Employees: NEW `department_employees` table
   - Users can be both manager AND employee in different departments

4. **Workflow Steps:**
   - Step assignments stored in `workflow_step_approvers` table
   - Each step can require X out of Y approvals
   - Supports both employee and manager approvers

---

## 🚀 Ready to Continue?

When you're ready to continue:
1. **"continue with Phase 4"** - Rewrite workflow engine for dynamic workflows
2. **"implement everything"** - Complete phases 4-6 in sequence

**Estimated time to complete remaining phases:** 5.5-6.5 hours

---

**Current Status:** Phases 1, 2, and 3 complete ✅
**Next Step:** Phase 4 - Rewrite workflow engine
**Overall Progress:** ~35% of MVP
