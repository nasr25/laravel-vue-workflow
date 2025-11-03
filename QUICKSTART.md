# Quick Start Guide - After System Restart

## 🚀 Start the Application (2 Steps)

### Step 1: Start Backend Server
```bash
cd /home/nasser/my-app/backend
php artisan serve
```
✅ Server running at: http://localhost:8000

### Step 2: Start Frontend Server (Open New Terminal)
```bash
cd /home/nasser/my-app/frontend
npm run dev
```
✅ Server running at: http://localhost:5173

---

## 🌐 Access the Application

Open your browser: **http://localhost:5173**

---

## 👤 Login Credentials

### Test as Admin
```
Email: admin@test.com
Password: 12345
```

### Test as Manager
```
Manager A: managera@test.com / 12345
Manager B: managerb@test.com / 12345
Manager C: managerc@test.com / 12345
Manager D: managerd@test.com / 12345
```

### Test as User
```
Email: user@test.com
Password: 12345
```

---

## 📝 What's New (Latest Updates)

### ✨ Return to Previous Department
- Managers can now return ideas to any previous department
- Click "Return for Edit" → Choose department from dropdown
- Example: Return from Department E → Department C

### ✨ Idea Tracking Details
- Click "View Details" on any idea
- See full approval timeline
- View manager comments and actions

### ✨ Dynamic Approval Count
- Now shows "5/5" instead of hardcoded "4/4"
- Automatically adjusts based on number of departments

---

## 🔍 Testing the Full Workflow

1. Login as **user@test.com** / 12345
2. Create a new idea with PDF
3. Submit for approval
4. Logout, login as **managerc@test.com** (Department C)
5. Approve the idea
6. Logout, login as **managera@test.com** (Department A)
7. Approve or Return to Department C
8. Continue through all departments

---

## 📚 Full Documentation

See **LATEST_PROGRESS.md** for:
- Complete feature list
- All API endpoints
- Troubleshooting guide
- Database schema
- Recent changes

---

## 💾 Your Data is Safe

- ✅ All code backed up to GitHub
- ✅ Database: `backend/database/database.sqlite`
- ✅ Uploads: `backend/storage/app/public/ideas/`

**GitHub:** https://github.com/nasr25/laravel-vue-workflow

---

## ⚠️ If Something Doesn't Work

### Backend Issues
```bash
cd /home/nasser/my-app/backend
php artisan config:clear
php artisan cache:clear
php artisan serve
```

### Frontend Issues
```bash
cd /home/nasser/my-app/frontend
npm install
npm run dev
```

### Database Issues (Last Resort - Deletes All Data)
```bash
cd /home/nasser/my-app/backend
php artisan migrate:fresh --seed
```

---

**Need Help?** Check LATEST_PROGRESS.md or README.md for detailed information.
