# 🎨 Frontend Guide - Vue.js Idea Workflow System

## ✅ Frontend is Complete and Running!

Your Vue.js frontend is fully built and running at **http://localhost:5173**

---

## 🔑 Test Accounts (All passwords: 12345)

| Email | Password | Role | Description |
|-------|----------|------|-------------|
| admin@test.com | 12345 | Admin | Can manage departments and managers |
| user@test.com | 12345 | User | Can submit ideas |
| managera@test.com | 12345 | Manager A | Reviews Department A (Step 1) |
| managerb@test.com | 12345 | Manager B | Reviews Department B (Step 2) |
| managerc@test.com | 12345 | Manager C | Reviews Department C (Step 3) |
| managerd@test.com | 12345 | Manager D | Reviews Department D (Step 4) |

---

## 🚀 Quick Test Workflow

### 1. Open the App
Visit: **http://localhost:5173**

You'll see the login page with quick-login buttons for all test accounts!

### 2. Test as Regular User

**Login:** Click "User" button (or enter user@test.com / 12345)

**You can:**
- Submit new ideas with name, description, and PDF
- See all your ideas with their status
- Edit ideas (only when draft or returned)
- Submit ideas for approval
- Delete draft ideas
- Track approval progress through all 4 departments

**Try this:**
1. Click "Create Draft" to save an idea
2. Click "Submit for Approval" to start the workflow
3. Watch as it goes to Department A for review

### 3. Test as Manager A

**Logout** (top-right button) → **Login** as Manager A

**You can:**
- See all ideas pending your review
- View idea details and previous approvals
- Approve, Reject, or Return ideas
- Add comments to your decision
- View all ideas in the system

**Try this:**
1. You'll see the idea from User in "Pending Approvals"
2. Read the description
3. Add comments (optional for approval)
4. Click "✓ Approve"
5. The idea moves to Department B!

### 4. Test Full Workflow

Continue logging in as each manager:

**Manager B** → Approve → Goes to Step 3
**Manager C** → Try "↩ Return for Edit" with comments
→ User can edit and resubmit
**Manager D** → Final approval → Status becomes "Approved" ✓

---

## 📱 Frontend Features

### Login Page
- Clean, modern design
- Quick-login buttons for testing
- Form validation
- Error messages
- Auto-redirects based on role

### User Dashboard
- **Create Ideas**: Name, description, PDF upload
- **My Ideas List**: See all submitted ideas
- **Status Badges**: Draft, Pending, Approved, Rejected, Returned
- **Progress Tracking**: See which department is reviewing
- **Edit/Resubmit**: Available for draft or returned ideas
- **Delete**: Only for draft ideas
- **Approval History**: See all department reviews

### Manager Dashboard
- **Two Tabs**: Pending Approvals | All Ideas
- **Department Info**: Shows which departments you manage
- **Pending Queue**: Only ideas at your step
- **Idea Details**: Full description, PDF link, submitter
- **Previous Approvals**: See what other departments said
- **Three Actions**:
  - ✓ Approve → Move to next step
  - ✗ Reject → End workflow (requires comments)
  - ↩ Return → Send back to user (requires comments)
- **All Ideas View**: Table of all ideas in system

---

## 🎯 User Flows

### User Flow: Submit an Idea

```
1. Login as user@test.com
2. Fill in idea form:
   - Name: "AI-Powered Chatbot"
   - Description: "Implement AI chatbot for customer support"
   - PDF: (optional) Upload requirements doc
3. Click "Create Draft"
4. Idea saved! Status: Draft
5. Click "Submit for Approval"
6. Status changes to: Pending
7. Current Step: 1/4
8. Wait for managers to review
```

### Manager Flow: Review an Idea

```
1. Login as managera@test.com
2. See "Pending Approvals (1)"
3. Read idea details
4. Check previous approvals (none for step 1)
5. Decide:
   Option A: Approve
   - Add comments: "Looks good!"
   - Click "✓ Approve"
   - Idea goes to Manager B

   Option B: Reject
   - Add comments: "Not aligned with strategy"
   - Click "✗ Reject"
   - Workflow ends, status: Rejected

   Option C: Return
   - Add comments: "Please add budget details"
   - Click "↩ Return for Edit"
   - User can edit and resubmit
```

---

## 🔄 Workflow States

### Idea Status Colors

- **🟡 Draft** - Yellow - Being edited by user
- **🔵 Pending** - Blue - Awaiting approvals
- **🟢 Approved** - Green - All 4 departments approved
- **🔴 Rejected** - Red - Rejected by a department
- **🟠 Returned** - Orange - Sent back for edits

### Department Progress

```
Draft (Step 0)
   ↓
Step 1: Department A → Manager A reviews
   ↓
Step 2: Department B → Manager B reviews
   ↓
Step 3: Department C → Manager C reviews
   ↓
Step 4: Department D → Manager D reviews
   ↓
Approved! (Step 5)
```

---

## 🛠️ Frontend Architecture

```
frontend/
├── src/
│   ├── views/
│   │   ├── LoginView.vue          # Login page with quick buttons
│   │   ├── UserDashboard.vue      # User idea submission & tracking
│   │   └── ManagerDashboard.vue   # Manager approval interface
│   ├── stores/
│   │   └── auth.ts                # Pinia store for authentication
│   ├── services/
│   │   └── api.ts                 # Axios API service layer
│   ├── types/
│   │   └── index.ts               # TypeScript interfaces
│   ├── router/
│   │   └── index.ts               # Vue Router with guards
│   └── App.vue                     # Root component
```

---

## 🔐 Authentication

**How it works:**
1. Login sends credentials to Laravel API
2. API returns JWT token
3. Token stored in localStorage
4. All API requests include token in header
5. Router guards protect routes based on role
6. Auto-redirect to correct dashboard

**Protected Routes:**
- `/user` - Only for users
- `/manager` - Only for managers
- `/admin` - Only for admins

---

## 📂 File Upload

**PDF Upload Features:**
- Accepts only PDF files
- Max size: 10MB
- Stored in: `backend/storage/app/public/ideas/`
- Accessible via: `http://localhost:8000/storage/ideas/filename.pdf`
- Can be replaced when editing
- Optional (ideas can be submitted without PDF)

---

## 🎨 UI/UX Features

### Login Page
- Beautiful gradient background
- Quick-login buttons for all test accounts
- Responsive design
- Form validation
- Error handling

### Dashboards
- Clean, professional design
- Color-coded status badges
- Intuitive navigation
- Real-time updates
- Responsive layout
- Loading states
- Empty states

### User Dashboard
- Card-based layout
- Form at top for easy access
- Ideas list below
- Edit mode inline
- Progress visualization

### Manager Dashboard
- Tabbed interface
- Pending queue prioritized
- Rich idea details
- Comment system
- Action buttons with confirmation

---

## 🚨 Common Actions

### As User:

**Create Idea:**
```
1. Fill form
2. (Optional) Attach PDF
3. Click "Create Draft"
```

**Submit for Approval:**
```
1. Find your draft idea
2. Click "Submit for Approval"
3. Confirm
4. Status → Pending
```

**Edit Returned Idea:**
```
1. Find returned idea (orange badge)
2. Click "Edit"
3. Update fields
4. Click "Update"
5. Click "Submit for Approval" again
```

### As Manager:

**Approve:**
```
1. Go to "Pending Approvals" tab
2. Read idea
3. (Optional) Add positive comments
4. Click "✓ Approve"
```

**Reject:**
```
1. Add comments (required)
2. Click "✗ Reject"
3. Confirm
```

**Return for Edits:**
```
1. Add comments explaining what to fix (required)
2. Click "↩ Return for Edit"
3. User receives feedback
```

---

## ✨ Advanced Features

1. **Real-time Updates**: Refresh data after actions
2. **Approval History**: See all previous reviews
3. **Department Tracking**: Know which step idea is at
4. **Comment System**: Managers can leave feedback
5. **Role-based UI**: Different interface for each role
6. **Responsive Design**: Works on desktop and mobile
7. **Error Handling**: User-friendly error messages
8. **Loading States**: Visual feedback during operations

---

## 🔧 Technical Details

**Built with:**
- Vue 3 (Composition API)
- TypeScript
- Vue Router
- Pinia (State Management)
- Axios (HTTP Client)
- Vite (Build Tool)

**API Integration:**
- Base URL: http://localhost:8000/api
- Authentication: Bearer Token
- Error handling on all requests
- Interceptors for auth headers

**State Management:**
- Auth store for user/token
- Persistent in localStorage
- Auto-fetch user on page load
- Reactive role checking

---

## 🎯 Test Scenarios

### Scenario 1: Full Approval
1. User submits idea
2. Manager A approves
3. Manager B approves
4. Manager C approves
5. Manager D approves
6. Status: Approved ✓

### Scenario 2: Rejection at Step 2
1. User submits idea
2. Manager A approves
3. Manager B rejects (with comments)
4. Status: Rejected
5. User sees rejection comments

### Scenario 3: Return for Edits
1. User submits idea
2. Manager A returns with comments
3. User edits idea
4. User resubmits
5. Process starts from Step 1 again

### Scenario 4: Multiple Ideas
1. User creates 5 different ideas
2. Submits all 5
3. Managers review in parallel
4. Different outcomes for each

---

## 📊 What You Can Track

### As User:
- How many ideas submitted
- Current status of each
- Which department is reviewing
- Comments from managers
- Full approval history

### As Manager:
- Ideas pending your review
- All ideas in system
- Your department's statistics
- Approval/rejection history

---

## 🎉 You're Ready!

The frontend is fully functional with:
- ✅ Beautiful login page
- ✅ User dashboard for idea submission
- ✅ Manager dashboard for approvals
- ✅ Role-based routing
- ✅ PDF upload support
- ✅ Real-time workflow tracking
- ✅ 6 test accounts ready to use

**Just open http://localhost:5173 and start testing!**

---

## 💡 Tips

1. **Test the full flow**: Login as each role to see different interfaces
2. **Use quick-login buttons**: Faster testing
3. **Try all actions**: Approve, Reject, Return
4. **Check comments**: Managers can provide feedback
5. **Upload PDFs**: Test file handling
6. **Multiple ideas**: Submit several to see the list view

Enjoy your fully functional idea workflow system! 🚀
