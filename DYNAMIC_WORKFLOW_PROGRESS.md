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

## 🔄 What's Next - Remaining Phases

### Phase 2: Models & Relationships (Not Started)
**Estimated Time:** 1 hour

**Tasks:**
1. Create FormType model
2. Create WorkflowTemplate model
3. Create WorkflowStep model
4. Create WorkflowStepApprover model
5. Create DepartmentEmployee model
6. Update Idea model relationships
7. Update IdeaApproval model relationships
8. Update User model for employee role

**Files to Create:**
- `app/Models/FormType.php`
- `app/Models/WorkflowTemplate.php`
- `app/Models/WorkflowStep.php`
- `app/Models/WorkflowStepApprover.php`
- `app/Models/DepartmentEmployee.php`

**Files to Update:**
- `app/Models/Idea.php`
- `app/Models/IdeaApproval.php`
- `app/Models/User.php`

---

### Phase 3: Seed Data (Not Started)
**Estimated Time:** 1 hour

**Tasks:**
1. Create RolesSeeder for employee role
2. Create FormTypesSeeder for 2 form types:
   - Budget Request
   - Leave Request
3. Create WorkflowTemplatesSeeder
4. Create sample employees
5. Assign employees to departments

**What Will Be Created:**

**Form 1: Budget Request**
```
Workflow:
Step 1: 3 employees from Department A (Finance)
Step 2: 1 manager from Department B (Operations)
```

**Form 2: Leave Request**
```
Workflow:
Step 1: 2 employees from Department C (HR)
```

**Test Employees:**
- employee1@test.com / 12345 (Dept A - Finance)
- employee2@test.com / 12345 (Dept A - Finance)
- employee3@test.com / 12345 (Dept A - Finance)
- employee4@test.com / 12345 (Dept C - HR)
- employee5@test.com / 12345 (Dept C - HR)

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
| 2. Models & Relationships | ⏸️ TODO | 1 hour | 0% |
| 3. Seed Data | ⏸️ TODO | 1 hour | 0% |
| 4. Workflow Engine | ⏸️ TODO | 2 hours | 0% |
| 5. Backend API | ⏸️ TODO | 1.5 hours | 0% |
| 6. Frontend Updates | ⏸️ TODO | 2-3 hours | 0% |
| **TOTAL** | **IN PROGRESS** | **8.5-9.5 hours** | **~10%** |

---

## 🎯 Next Session Plan

### Recommended Continuation:
**Start with Phase 2 & 3:**
1. Create all models (30 min)
2. Set up relationships (30 min)
3. Create seeders (1 hour)
4. Run seeders and verify data

This will give you:
- Working database with sample data
- 2 form types ready to use
- Test employees assigned

**Then move to Phase 4:**
- Rewrite workflow engine
- Test with seeded data

**Finally Phases 5 & 6:**
- Build APIs
- Create frontends

---

## 💡 What You Can Do Now

The database foundation is ready! You can:
1. Review the DYNAMIC_WORKFLOW_PLAN.md for full feature details
2. Check the migration files to understand the schema
3. Run `php artisan tinker` to explore the new tables
4. Decide when to continue with remaining phases

---

## 🔍 Verification Commands

```bash
# List all tables
php artisan tinker
>>> Schema::getTableNames()

# Check new table structures
>>> Schema::getColumnListing('form_types')
>>> Schema::getColumnListing('workflow_steps')

# Verify migrations
php artisan migrate:status
```

---

## 📝 Important Notes

1. **Backward Compatibility:** Existing ideas will need `form_type_id` assigned
   - We'll handle this in the seeder
   - Can create a "Legacy Ideas" form type for existing data

2. **Employee Role:** Need to add to roles table
   - Will be done in seeder
   - employees can only approve, not submit

3. **Manager vs Employee:**
   - Managers stay in `department_managers` table
   - Employees go in new `department_employees` table
   - Users can be both manager AND employee in different departments

---

## 🚀 Ready to Continue?

When you're ready to continue:
1. Say "continue with Phase 2" - I'll create all models
2. Or "continue with Phase 3" - I'll create seeders
3. Or "implement everything" - I'll do phases 2-6 in sequence

**Estimated time to complete:** 7-8 more hours of implementation

---

**Current Status:** Database foundation complete ✅
**Next Step:** Create models and relationships
**Overall Progress:** ~10% of MVP
