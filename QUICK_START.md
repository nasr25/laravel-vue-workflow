# 🚀 Quick Start Guide - Idea Workflow System

## ✅ System is Ready!

Your Laravel API + Vue.js idea submission and 4-stage approval workflow is complete and running!

**Servers Running:**
- ✅ Laravel API: http://localhost:8000
- ✅ Vue.js Frontend: http://localhost:5173

---

## 🎯 Quick Setup (5 Minutes)

### Step 1: Create Admin Account

```bash
cd /home/nasser/my-app/backend
php artisan tinker
```

In tinker, run:
```php
$adminRole = App\Models\Role::where('name', 'admin')->first();
$admin = App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@test.com',
    'password' => bcrypt('admin123'),
    'role_id' => $adminRole->id
]);
echo "Admin created! Email: admin@test.com Password: admin123\n";
exit
```

### Step 2: Login as Admin

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@test.com","password":"admin123"}'
```

**Save the token from the response!**

### Step 3: Create 4 Departments

```bash
# Replace YOUR_ADMIN_TOKEN with the token from Step 2

# Department 1 - Finance
curl -X POST http://localhost:8000/api/admin/departments \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Finance","description":"Financial review and approval","approval_order":1}'

# Department 2 - Technical
curl -X POST http://localhost:8000/api/admin/departments \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Technical","description":"Technical feasibility review","approval_order":2}'

# Department 3 - Legal
curl -X POST http://localhost:8000/api/admin/departments \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Legal","description":"Legal compliance review","approval_order":3}'

# Department 4 - Executive
curl -X POST http://localhost:8000/api/admin/departments \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Executive","description":"Executive final approval","approval_order":4}'
```

### Step 4: Create 4 Managers

```bash
# Manager 1 - Finance
curl -X POST http://localhost:8000/api/admin/managers \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Finance Manager","email":"finance@test.com","password":"manager123"}'

# Manager 2 - Technical
curl -X POST http://localhost:8000/api/admin/managers \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Technical Manager","email":"tech@test.com","password":"manager123"}'

# Manager 3 - Legal
curl -X POST http://localhost:8000/api/admin/managers \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Legal Manager","email":"legal@test.com","password":"manager123"}'

# Manager 4 - Executive
curl -X POST http://localhost:8000/api/admin/managers \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Executive Manager","email":"exec@test.com","password":"manager123"}'
```

### Step 5: Assign Managers to Departments

```bash
# Assign Finance Manager (user_id: 2) to Finance Department (department_id: 1)
curl -X POST http://localhost:8000/api/admin/managers/assign \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"user_id":2,"department_id":1}'

# Assign Technical Manager (user_id: 3) to Technical Department (department_id: 2)
curl -X POST http://localhost:8000/api/admin/managers/assign \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"user_id":3,"department_id":2}'

# Assign Legal Manager (user_id: 4) to Legal Department (department_id: 3)
curl -X POST http://localhost:8000/api/admin/managers/assign \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"user_id":4,"department_id":3}'

# Assign Executive Manager (user_id: 5) to Executive Department (department_id: 4)
curl -X POST http://localhost:8000/api/admin/managers/assign \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"user_id":5,"department_id":4}'
```

---

## 🧪 Test the Workflow

### 1. Register a Regular User

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name":"Test User",
    "email":"user@test.com",
    "password":"user123",
    "password_confirmation":"user123"
  }'
```

### 2. Login as User

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@test.com","password":"user123"}'
```

**Save the user token!**

### 3. Create an Idea (with PDF)

```bash
# Create a test PDF first
echo "Test PDF Content" > /tmp/test-idea.pdf

# Submit the idea
curl -X POST http://localhost:8000/api/ideas \
  -H "Authorization: Bearer USER_TOKEN" \
  -F "name=Revolutionary AI Idea" \
  -F "description=This is an innovative idea that will change everything" \
  -F "pdf_file=@/tmp/test-idea.pdf"
```

### 4. Submit Idea for Approval

```bash
# Idea ID will be 1
curl -X POST http://localhost:8000/api/ideas/1/submit \
  -H "Authorization: Bearer USER_TOKEN"
```

### 5. Manager Reviews (Department 1)

```bash
# Login as Finance Manager
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"finance@test.com","password":"manager123"}'

# Get pending ideas
curl -X GET http://localhost:8000/api/approvals/pending \
  -H "Authorization: Bearer MANAGER_TOKEN"

# Approve the idea
curl -X POST http://localhost:8000/api/approvals/1/approve \
  -H "Authorization: Bearer MANAGER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"comments":"Finance approved!"}'
```

### 6. Continue with Other Departments

Repeat step 5 for:
- Technical Manager (tech@test.com)
- Legal Manager (legal@test.com)
- Executive Manager (exec@test.com)

After all 4 approvals, the idea status will be `approved`!

---

## 📚 Available Test Accounts

After setup, you'll have:

| Email | Password | Role |
|-------|----------|------|
| admin@test.com | admin123 | Admin |
| finance@test.com | manager123 | Manager (Finance) |
| tech@test.com | manager123 | Manager (Technical) |
| legal@test.com | manager123 | Manager (Legal) |
| exec@test.com | manager123 | Manager (Executive) |
| user@test.com | user123 | User |

---

## 🎨 What Each Role Can Do

### Regular User
- Submit ideas with PDF attachments
- View their own ideas
- Edit ideas (when draft or returned)
- Resubmit after editing

### Manager
- View pending ideas for their department
- Approve ideas at their step
- Reject ideas with comments
- Return ideas to users for edits
- View all ideas in system

### Admin
- Create/edit/delete departments
- Create manager accounts
- Assign managers to departments
- Full system oversight

---

## 🔄 Complete Workflow Example

```
1. User creates idea → Status: draft
2. User submits idea → Status: pending, Step: 1
3. Finance Manager approves → Step: 2
4. Technical Manager approves → Step: 3
5. Legal Manager RETURNS → Status: returned, Step: 0
6. User edits and resubmits → Status: pending, Step: 1
7. Finance approves → Step: 2
8. Technical approves → Step: 3
9. Legal approves → Step: 4
10. Executive approves → Status: approved, Step: 5 ✓
```

---

## 📂 Important Files

- **Full Documentation**: `/home/nasser/my-app/WORKFLOW_GUIDE.md`
- **API Routes**: `/home/nasser/my-app/backend/routes/api.php`
- **Workflow Service**: `/home/nasser/my-app/backend/app/Services/IdeaWorkflowService.php`
- **Error Logs**: `/home/nasser/my-app/backend/storage/logs/laravel.log`
- **Uploaded PDFs**: `/home/nasser/my-app/backend/storage/app/public/ideas/`

---

## 🐛 Troubleshooting

**Can't login?**
```bash
# Check if user exists
cd backend
php artisan tinker
App\Models\User::where('email', 'admin@test.com')->first()
```

**Department creation fails?**
- Ensure approval_order is unique (1, 2, 3, or 4)
- Check you're using admin token

**File upload fails?**
```bash
php artisan storage:link
```

**See all routes:**
```bash
php artisan route:list
```

---

## 🎉 You're All Set!

The system is fully functional. Now you can:

1. Build Vue.js frontend components
2. Add email notifications
3. Create dashboards
4. Add PDF preview
5. Customize the workflow

Check **WORKFLOW_GUIDE.md** for complete API documentation and advanced features!
