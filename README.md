# Laravel Vue Workflow System

A comprehensive idea submission and multi-stage approval workflow system built with Laravel 12 and Vue.js 3.

## 📋 Overview

This application provides a complete workflow management system where:
- **Users** can submit ideas with PDF attachments
- **Managers** review and approve ideas through a 4-stage approval process
- **Admins** manage departments, managers, and configure approval sequences

## ✨ Features

### User Features
- Create and edit ideas (name, description, PDF attachment)
- Submit ideas for approval
- Track approval progress through 4 departments
- View idea history and status
- Dual view mode (Cards/Table) for better visualization

### Manager Features
- Review pending ideas at their approval step
- Approve, reject, or return ideas to users for revision
- Add comments during approval actions
- View all ideas across all departments
- Streamlined approval workflow (2 clicks: confirm → done)

### Admin Features
- Create manager accounts
- Assign managers to departments
- View department overview
- **Dynamic approval sequence management** - reorder departments (e.g., B → A → C → D)
- Real-time pending ideas count
- Comprehensive department and manager management

## 🛠️ Tech Stack

### Backend
- **Laravel 12** (PHP 8.3)
- **MySQL/SQLite** database
- **Laravel Sanctum** for API authentication
- **RESTful API** architecture
- Custom workflow engine for sequential approvals

### Frontend
- **Vue.js 3** (Composition API)
- **TypeScript**
- **Pinia** for state management
- **Vue Router** for navigation
- **Axios** for HTTP requests
- **Bootstrap 5.3.3** for UI components
- **Bootstrap Icons** for visual elements
- **Vite** for build tooling

## 📦 Prerequisites

Before you begin, ensure you have the following installed:
- **PHP** >= 8.3
- **Composer** >= 2.0
- **Node.js** >= 18.x
- **npm** >= 9.x
- **MySQL** >= 8.0 (or use SQLite)
- **Git** (for version control)

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/nasr25/laravel-vue-workflow.git
cd laravel-vue-workflow
```

### 2. Backend Setup (Laravel)

```bash
# Navigate to backend directory
cd backend

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure your database in .env file
# For MySQL:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=workflow_db
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

# Or for SQLite (simpler for development):
# DB_CONNECTION=sqlite
# DB_DATABASE=/absolute/path/to/backend/database/database.sqlite
# Comment out other DB_ variables

# If using MySQL, create the database:
mysql -u root -p -e "CREATE DATABASE workflow_db;"

# If using SQLite, create the database file:
touch database/database.sqlite

# Run migrations
php artisan migrate

# Seed the database (creates roles, departments, test users)
php artisan db:seed

# Create storage symlink for file uploads
php artisan storage:link

# Set proper permissions (Linux/Mac)
chmod -R 775 storage bootstrap/cache
```

### 3. Frontend Setup (Vue.js)

```bash
# Navigate to frontend directory
cd ../frontend

# Install Node.js dependencies
npm install

# The API URL is already configured to http://localhost:8000/api
# If you need to change it, edit src/services/api.ts
```

### 4. Running the Application

You'll need **two terminal windows**:

**Terminal 1 - Backend Server:**
```bash
cd backend
php artisan serve
# Laravel API will run on http://localhost:8000
```

**Terminal 2 - Frontend Dev Server:**
```bash
cd frontend
npm run dev
# Vue.js app will run on http://localhost:5173
```

### 5. Access the Application

Open your browser and navigate to: **http://localhost:5173**

## 👥 Default Test Accounts

The database seeder creates the following test accounts:

### Admin Account
- **Email:** admin@test.com
- **Password:** 12345
- **Access:** Full system administration

### Manager Accounts
- **Manager A:** managera@test.com / 12345 (Department A - Step 1)
- **Manager B:** managerb@test.com / 12345 (Department B - Step 2)
- **Manager C:** managerc@test.com / 12345 (Department C - Step 3)
- **Manager D:** managerd@test.com / 12345 (Department D - Step 4)

### Regular User Account
- **Email:** user@test.com
- **Password:** 12345
- **Access:** Submit and track ideas

## 🏗️ System Architecture

### Database Schema

**Key Tables:**
- `users` - User accounts (admin, manager, user roles)
- `roles` - Role definitions (admin, manager, user)
- `departments` - Approval departments (A, B, C, D)
- `ideas` - User-submitted ideas
- `idea_approvals` - Approval records for each step
- `department_managers` - Manager-to-department assignments

### Approval Workflow

1. **User submits idea** → Status: `draft`
2. **User clicks "Submit for Approval"** → Status: `pending`, creates 4 approval records
3. **Department A Manager reviews** → Approve/Reject/Return
4. **Department B Manager reviews** → Only if A approved
5. **Department C Manager reviews** → Only if B approved
6. **Department D Manager reviews** → Only if C approved
7. **All 4 approvals complete** → Status: `approved`

**Business Rules:**
- Ideas must be approved sequentially (A → B → C → D)
- Managers only see ideas at their current step
- Rejection at any step → Status: `rejected`
- Return to user → Status: `returned`, user can edit and resubmit

### API Endpoints

#### Authentication
- `POST /api/register` - User registration
- `POST /api/login` - User login
- `POST /api/logout` - User logout
- `GET /api/me` - Get authenticated user

#### Ideas (User)
- `GET /api/ideas/my-ideas` - Get user's ideas
- `GET /api/ideas/{id}` - Get single idea
- `POST /api/ideas` - Create new idea (with PDF upload)
- `PUT /api/ideas/{id}` - Update idea (with PDF upload)
- `POST /api/ideas/{id}/submit` - Submit idea for approval
- `DELETE /api/ideas/{id}` - Delete idea

#### Approvals (Manager)
- `GET /api/approvals/pending` - Get ideas pending at manager's step
- `GET /api/approvals/all-ideas` - Get all ideas in system
- `POST /api/approvals/{id}/approve` - Approve idea
- `POST /api/approvals/{id}/reject` - Reject idea (requires comments)
- `POST /api/approvals/{id}/return` - Return idea to user (requires comments)

#### Admin
- `GET /api/admin/departments` - Get all departments
- `POST /api/admin/departments/reorder` - Reorder approval sequence
- `GET /api/admin/managers` - Get all managers
- `POST /api/admin/managers` - Create new manager
- `POST /api/admin/managers/assign` - Assign manager to department
- `POST /api/admin/managers/remove` - Remove manager from department
- `GET /api/admin/pending-ideas-count` - Get count of pending ideas

## 🔒 Security Features

### Input Validation
- Min/max length validation on all text fields
- Email format validation
- Password minimum 6 characters
- HTML tag stripping with `strip_tags()`
- File upload validation (PDF only, max 10MB)

### File Upload Security
- MIME type checking (`application/pdf`)
- File extension validation (`.pdf` only)
- Size limit enforcement (10MB max)
- Secure storage in `storage/app/public/ideas/`

### XSS Prevention
- Vue.js auto-escaping in templates
- No use of `v-html` directive
- HTML tag stripping on backend

### SQL Injection Protection
- Eloquent ORM with parameterized queries
- Laravel's built-in protection

### Authentication
- Laravel Sanctum token-based authentication
- Bearer token in Authorization header
- Token stored in localStorage (frontend)
- Logout invalidates token

## 📱 Responsive Design

The application is fully responsive and works on:
- **Desktop** (1920px+)
- **Laptop** (1024px - 1920px)
- **Tablet** (768px - 1024px)
- **Mobile** (320px - 768px)

## 📂 Project Structure

```
laravel-vue-workflow/
├── backend/                    # Laravel API
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   └── API/
│   │   │   │       ├── AdminController.php
│   │   │   │       ├── ApprovalController.php
│   │   │   │       ├── AuthController.php
│   │   │   │       └── IdeaController.php
│   │   │   └── Middleware/
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Role.php
│   │   │   ├── Department.php
│   │   │   ├── Idea.php
│   │   │   ├── IdeaApproval.php
│   │   │   └── DepartmentManager.php
│   │   └── Services/
│   │       └── IdeaWorkflowService.php
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/
│   │   └── api.php
│   └── storage/
│       └── app/
│           └── public/
│               └── ideas/      # PDF uploads stored here
├── frontend/                   # Vue.js SPA
│   ├── src/
│   │   ├── router/
│   │   │   └── index.ts
│   │   ├── services/
│   │   │   └── api.ts          # Axios API service
│   │   ├── stores/
│   │   │   └── auth.ts         # Pinia auth store
│   │   ├── views/
│   │   │   ├── LoginView.vue
│   │   │   ├── UserDashboard.vue
│   │   │   ├── ManagerDashboard.vue
│   │   │   └── AdminDashboard.vue
│   │   ├── App.vue
│   │   └── main.ts
│   ├── package.json
│   └── vite.config.ts
└── README.md
```

## 📖 Documentation

Additional documentation files in the project:

- **SECURITY.md** - Comprehensive security implementation details
- **BOOTSTRAP_UPGRADE.md** - Bootstrap 5 integration guide
- **LATEST_FIXES.md** - Recent bug fixes and improvements
- **ADMIN_AND_UX_IMPROVEMENTS.md** - Admin dashboard and UX enhancements

## 🧪 Testing the Application

### Complete Workflow Test

1. **Login as User** (user@test.com / 12345)
2. Create a new idea with PDF attachment
3. Submit the idea for approval
4. **Logout and Login as Manager A** (managera@test.com / 12345)
5. Review and approve the idea
6. **Logout and Login as Manager B** (managerb@test.com / 12345)
7. Review and approve the idea
8. Continue with Managers C and D
9. **Login as User** - Verify idea is now `approved`

### Testing Admin Features

1. **Login as Admin** (admin@test.com / 12345)
2. Navigate to Admin Dashboard
3. Create a new manager account
4. Assign manager to a department
5. Go to Departments tab
6. Reorder the approval sequence using arrows
7. Save the new order
8. Test that new ideas follow the new sequence

## 🔧 Troubleshooting

### Common Issues

**1. Database Connection Error**
```bash
# Check your .env file has correct database credentials
# Verify MySQL is running (if using MySQL)
sudo systemctl status mysql
```

**2. Storage Symlink Not Working**
```bash
# Recreate the symlink
cd backend
php artisan storage:link
```

**3. File Upload Fails**
```bash
# Check storage permissions
chmod -R 775 storage
```

**4. CORS Errors**
```bash
# Ensure backend is running on port 8000
# And frontend is on port 5173
```

**5. npm install fails**
```bash
# Clear npm cache
npm cache clean --force
rm -rf node_modules package-lock.json
npm install
```

## 🚢 Production Deployment

### Backend (Laravel)

```bash
# Set environment to production
APP_ENV=production
APP_DEBUG=false

# Optimize application
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set up proper permissions
chmod -R 755 storage bootstrap/cache
```

### Frontend (Vue.js)

```bash
# Build for production
npm run build

# Files will be in frontend/dist/
# Deploy dist/ folder to your web server
```

### Security Checklist for Production

- [ ] Change all default passwords
- [ ] Set strong `APP_KEY` in .env
- [ ] Enable HTTPS (SSL certificate)
- [ ] Configure CORS properly
- [ ] Set `APP_DEBUG=false`
- [ ] Use environment variables for secrets
- [ ] Set up regular database backups
- [ ] Configure file upload size limits
- [ ] Set up monitoring and logging

## 🌐 Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Opera (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is open-source and available under the MIT License.

## 💬 Support

For issues, questions, or contributions:
- **GitHub Issues:** https://github.com/nasr25/laravel-vue-workflow/issues

## 📊 Changelog

### Version 1.0.0 (November 2025)

**Features:**
- ✅ Complete user idea submission system
- ✅ 4-stage sequential approval workflow
- ✅ PDF file upload and storage
- ✅ Bootstrap 5 responsive design
- ✅ Admin dashboard with manager management
- ✅ Dynamic approval sequence reordering
- ✅ Dual view mode (Cards/Table)
- ✅ Progress tracking with percentages
- ✅ Comprehensive security implementation
- ✅ Role-based access control

**UX Improvements:**
- ✅ Reduced manager approval clicks (3 → 2)
- ✅ Silent success (no unnecessary alerts)
- ✅ Real-time status updates
- ✅ Touch-friendly mobile interface

**Security Enhancements:**
- ✅ Input sanitization with strip_tags()
- ✅ File upload validation (MIME + extension)
- ✅ XSS prevention
- ✅ SQL injection protection
- ✅ Token-based authentication

## 🙏 Acknowledgments

- Laravel Framework - https://laravel.com
- Vue.js - https://vuejs.org
- Bootstrap - https://getbootstrap.com
- Vite - https://vitejs.dev

---

**Built with ❤️ using Laravel 12 and Vue.js 3**
