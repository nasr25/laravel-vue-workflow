# Idea Submission & Approval Workflow System

## 📋 Overview

This is a comprehensive Laravel + Vue.js application with a **4-stage approval workflow** for idea submissions.

### Key Features:
- **3 User Roles**: Admin, User, Manager
- **4-Department Approval Workflow**: Ideas go through 4 sequential approval steps
- **PDF File Upload**: Users can attach PDF files to ideas
- **Manager Actions**: Approve, Reject, or Return ideas for editing
- **Complete Audit Trail**: Track every approval action with comments

---

## 🏗️ System Architecture

###Database Schema

```
users (id, name, email, password, role_id)
  └── roles (admin, user, manager)

departments (id, name, description, approval_order [1-4])
  └── department_managers (links managers to departments)

ideas (id, user_id, name, description, pdf_file_path, status, current_approval_step)
  └── idea_approvals (id, idea_id, department_id, manager_id, step, status, comments)
```

### Workflow States

**Idea Status:**
- `draft` - Being edited by user
- `pending` - Submitted, awaiting approvals
- `approved` - All 4 departments approved
- `rejected` - Rejected by a department
- `returned` - Returned to user for edits

**Approval Status:**
- `pending` - Awaiting manager action
- `approved` - Approved by manager
- `rejected` - Rejected by manager
- `returned` - Returned to user

---

## 🔑 User Roles & Capabilities

### 1. **Regular User**
Can:
- Register and login
- Submit ideas with PDF attachments
- Edit ideas (only when draft or returned)
- View their own ideas
- Resubmit ideas after editing

### 2. **Manager**
Can:
- All user capabilities
- View pending ideas for their department(s)
- Approve ideas at their department's step
- Reject ideas with comments
- Return ideas to users for editing
- View all ideas in the system

### 3. **Admin**
Can:
- Create and manage departments (must create exactly 4)
- Create manager accounts
- Assign managers to departments
- Remove managers from departments
- Full system oversight

---

## 🔄 4-Stage Approval Workflow

### How It Works:

1. **User Submits Idea**
   - User creates idea with name, description, and PDF
   - User clicks "Submit"
   - System creates 4 approval records (one for each department)
   - Idea status: `draft` → `pending`
   - Current step: 0 → 1

2. **Department 1 Review**
   - Any manager assigned to Department 1 can review
   - Options: Approve / Reject / Return
   - If approved: Move to step 2
   - If rejected: Idea status → `rejected` (workflow ends)
   - If returned: Idea status → `returned` (user can edit)

3. **Department 2, 3, 4 Review**
   - Same process as Department 1
   - Each approval moves to next step
   - Any rejection or return stops the workflow

4. **Final Approval**
   - After Department 4 approves
   - Idea status: `pending` → `approved`
   - Current step: 4 → 5 (completed)

### Workflow Diagram:

```
User Creates Idea (draft)
         ↓
User Submits Idea
         ↓
[Step 1] Department 1 Review
   ├─ Approve → [Step 2]
   ├─ Reject → REJECTED (end)
   └─ Return → RETURNED (user edits)
         ↓
[Step 2] Department 2 Review
   ├─ Approve → [Step 3]
   ├─ Reject → REJECTED (end)
   └─ Return → RETURNED (user edits)
         ↓
[Step 3] Department 3 Review
   ├─ Approve → [Step 4]
   ├─ Reject → REJECTED (end)
   └─ Return → RETURNED (user edits)
         ↓
[Step 4] Department 4 Review
   ├─ Approve → APPROVED ✓
   ├─ Reject → REJECTED (end)
   └─ Return → RETURNED (user edits)
```

---

## 🚀 API Endpoints

### Authentication

```http
POST /api/register
POST /api/login
POST /api/logout (auth required)
GET  /api/me (auth required)
```

### User - Ideas Management

```http
GET    /api/ideas/my-ideas          # Get all my ideas
GET    /api/ideas/{id}              # Get single idea details
POST   /api/ideas                   # Create new idea (draft)
PUT    /api/ideas/{id}              # Update idea (draft/returned only)
POST   /api/ideas/{id}/submit       # Submit idea for approval
DELETE /api/ideas/{id}              # Delete idea (draft only)
```

### Manager - Approvals

```http
GET    /api/approvals/pending       # Get ideas pending my review
GET    /api/approvals/all-ideas     # Get all ideas
POST   /api/approvals/{id}/approve  # Approve idea
POST   /api/approvals/{id}/reject   # Reject idea (requires comments)
POST   /api/approvals/{id}/return   # Return to user (requires comments)
```

### Admin - Department Management

```http
GET    /api/admin/departments       # Get all departments
POST   /api/admin/departments       # Create department
PUT    /api/admin/departments/{id}  # Update department
DELETE /api/admin/departments/{id}  # Delete department
```

### Admin - Manager Management

```http
GET    /api/admin/managers          # Get all managers
POST   /api/admin/managers          # Create manager account
POST   /api/admin/managers/assign   # Assign manager to department
POST   /api/admin/managers/remove   # Remove manager from department
```

---

## 📝 API Request Examples

### 1. Register User

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

### 2. Login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

Response:
```json
{
  "success": true,
  "user": { "id": 1, "name": "John Doe", "role": {...} },
  "token": "1|abc123..."
}
```

### 3. Create Idea (with PDF)

```bash
curl -X POST http://localhost:8000/api/ideas \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "name=My Innovation Idea" \
  -F "description=This idea will revolutionize..." \
  -F "pdf_file=@/path/to/document.pdf"
```

### 4. Submit Idea for Approval

```bash
curl -X POST http://localhost:8000/api/ideas/1/submit \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 5. Create Department (Admin)

```bash
curl -X POST http://localhost:8000/api/admin/departments \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Finance Department",
    "description": "Handles financial approvals",
    "approval_order": 1
  }'
```

### 6. Approve Idea (Manager)

```bash
curl -X POST http://localhost:8000/api/approvals/1/approve \
  -H "Authorization: Bearer MANAGER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "comments": "Looks good, approved!"
  }'
```

### 7. Return Idea to User (Manager)

```bash
curl -X POST http://localhost:8000/api/approvals/1/return \
  -H "Authorization: Bearer MANAGER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "comments": "Please add more details about the budget."
  }'
```

---

## 🔧 Setup Instructions

### 1. First Time Setup

```bash
# Navigate to backend
cd backend

# Install dependencies (already done)
# composer install

# Storage link for file uploads
php artisan storage:link
```

### 2. Create Admin Account Manually

```bash
php artisan tinker
```

Then in tinker:
```php
$adminRole = App\Models\Role::where('name', 'admin')->first();
App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => bcrypt('admin123'),
    'role_id' => $adminRole->id
]);
```

### 3. Setup 4 Departments (via Admin)

Use the admin account to create 4 departments with approval_order 1-4:

```bash
# Department 1 - Finance
POST /api/admin/departments
{
  "name": "Finance",
  "description": "Financial review",
  "approval_order": 1
}

# Department 2 - Technical
# Department 3 - Legal
# Department 4 - Executive
```

### 4. Create Manager Accounts (via Admin)

```bash
POST /api/admin/managers
{
  "name": "Finance Manager",
  "email": "finance@example.com",
  "password": "password123"
}
```

### 5. Assign Managers to Departments

```bash
POST /api/admin/managers/assign
{
  "user_id": 2,
  "department_id": 1
}
```

---

## 📂 File Structure

```
backend/
├── app/
│   ├── Http/Controllers/API/
│   │   ├── AuthController.php        # Authentication
│   │   ├── AdminController.php       # Admin features
│   │   ├── IdeaController.php        # User idea submission
│   │   └── ApprovalController.php    # Manager approvals
│   ├── Models/
│   │   ├── User.php                  # User model with role methods
│   │   ├── Role.php                  # Role model
│   │   ├── Department.php            # Department model
│   │   ├── DepartmentManager.php     # Manager assignment
│   │   ├── Idea.php                  # Idea model
│   │   └── IdeaApproval.php          # Approval records
│   └── Services/
│       └── IdeaWorkflowService.php   # Workflow logic
├── database/migrations/
└── routes/api.php                     # All API routes
```

---

## 🧪 Testing the Workflow

### Step-by-Step Test:

1. **Create admin user** (via tinker)
2. **Login as admin** → Get token
3. **Create 4 departments** (approval_order: 1-4)
4. **Create 4 manager accounts**
5. **Assign each manager to a department**
6. **Register as regular user**
7. **Create an idea with PDF**
8. **Submit idea**
9. **Login as manager 1** → Approve
10. **Login as manager 2** → Approve
11. **Login as manager 3** → Return to user
12. **Login as user** → Edit and resubmit
13. **Complete all 4 approvals**
14. **Verify idea status = approved**

---

## 🔐 Security Features

- **Sanctum Authentication**: Token-based API authentication
- **Role-based Access Control**: Enforced at controller level
- **File Upload Validation**: PDF only, 10MB max
- **Authority Verification**: Managers can only review their department's step
- **Ownership Checks**: Users can only edit their own ideas
- **Error Logging**: All actions logged for audit trail

---

## 📊 Workflow Service Methods

The `IdeaWorkflowService` provides these core methods:

- `submitIdea($idea)` - Initialize 4-step approval
- `approveIdea($idea, $managerId, $comments)` - Approve current step
- `rejectIdea($idea, $managerId, $comments)` - Reject idea
- `returnIdea($idea, $managerId, $comments)` - Return for edits
- `getPendingIdeasForManager($managerId)` - Get pending items

---

## 🎯 Next Steps

1. **Frontend Integration**: Build Vue.js components for each role
2. **Notifications**: Add email notifications for status changes
3. **Dashboard**: Create analytics dashboard for admins
4. **PDF Viewer**: Add in-browser PDF preview
5. **History**: Show full approval history timeline

---

## ⚠️ Important Notes

- System MUST have exactly 4 active departments
- Each department MUST have approval_order 1-4 (unique)
- Managers can be assigned to multiple departments
- Multiple managers can be assigned to one department
- Any manager in a department can approve/reject/return
- PDF files stored in `storage/app/public/ideas/`
- All errors logged to `storage/logs/laravel.log`

---

## 🆘 Troubleshooting

**Issue: "System must have 4 active departments"**
- Solution: Create 4 departments with approval_order 1, 2, 3, 4

**Issue: "You do not have authority to review this idea"**
- Solution: Ensure manager is assigned to the department for current step

**Issue: "Cannot edit idea in current status"**
- Solution: Ideas can only be edited when status is 'draft' or 'returned'

**Issue: File upload fails**
- Solution: Run `php artisan storage:link`

---

## 📞 Support

For issues or questions, check:
- Laravel logs: `backend/storage/logs/laravel.log`
- Vue.js console: Browser DevTools
- API responses: Include error details

---

**Built with Laravel 12 + Vue.js 3 + Custom Workflow Engine**
