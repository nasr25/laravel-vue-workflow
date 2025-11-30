# Advanced Features Documentation

This document provides comprehensive information about the advanced features implemented in the Laravel Vue Workflow System.

## Table of Contents
1. [Manager Permission System](#manager-permission-system)
2. [Email Notifications](#email-notifications)
3. [Automated Reminder System](#automated-reminder-system)
4. [Dynamic Workflow](#dynamic-workflow)
5. [Department Management](#department-management)
6. [Setup Instructions](#setup-instructions)

---

## Manager Permission System

### Overview
The manager permission system allows fine-grained control over what managers can do in each department. Each manager-department assignment can have one of two permission levels:

- **Approver** (✓ Can Approve): Full permissions to approve, reject, or return ideas
- **Viewer** (👁️ View Only): Can only view ideas but cannot take any actions

### How It Works

#### Database Structure
```sql
-- department_managers table
user_id         INT
department_id   INT
permission      ENUM('viewer', 'approver') DEFAULT 'approver'
```

#### Use Cases
- **Training**: Assign new managers as "Viewer" so they can observe the approval process
- **Temporary Access**: Give temporary viewing access to managers from other departments
- **Delegation**: Allow senior managers full approval rights while junior managers observe
- **Cross-Department Visibility**: Let managers view ideas in departments they don't manage

### Admin Usage

#### Assigning a Manager with Permission
1. Navigate to Admin Dashboard → Manage Managers
2. Find the manager in the list
3. Select a department from the dropdown
4. Choose permission level: "✓ Can Approve" or "👁️ View Only"
5. Click "Assign"

#### Changing Permission
1. In the manager's department badges, look for the pencil icon ✏️
2. Click the pencil icon to toggle between Approver and Viewer
3. Confirm the change

#### Visual Indicators
- **Green badge**: Manager has "Can Approve" permission
- **Gray badge**: Manager has "View Only" permission

### Manager Experience

When managers log in, they will see:
- Department badges in their dashboard header showing their permission level
- A notice if they have any "View Only" permissions
- Only ideas from departments where they have "Approver" permission will show action buttons
- Ideas from "Viewer" departments will be visible in the "All Ideas" tab but without action buttons

### API Endpoints

#### Assign Manager with Permission
```http
POST /api/admin/managers/assign
Content-Type: application/json

{
  "user_id": 123,
  "department_id": 1,
  "permission": "approver" // or "viewer"
}
```

#### Update Manager Permission
```http
POST /api/admin/managers/update-permission
Content-Type: application/json

{
  "user_id": 123,
  "department_id": 1,
  "permission": "viewer" // or "approver"
}
```

---

## Email Notifications

### Overview
The system automatically sends professional, beautifully formatted HTML emails to users whenever their idea status changes.

### Email Types

#### 1. Idea Approved
**Triggered when**: A manager approves an idea at any stage
**Sent to**: The idea's owner (user who submitted it)
**Subject**: "Your Idea '[Idea Name]' was Approved"

**Contains**:
- Green "✓ Approved" badge
- Department name that approved it
- Idea details (name, description)
- Manager's comments (if provided)
- Current status and next steps

#### 2. Idea Rejected
**Triggered when**: A manager rejects an idea
**Sent to**: The idea's owner
**Subject**: "Your Idea '[Idea Name]' was Rejected"

**Contains**:
- Red "✗ Rejected" badge
- Department name that rejected it
- Idea details
- Manager's required comments explaining rejection
- Note that the approval process has ended

#### 3. Idea Returned for Revision
**Triggered when**: A manager returns an idea to the user for editing
**Sent to**: The idea's owner
**Subject**: "Your Idea '[Idea Name]' was Returned for Revision"

**Contains**:
- Orange "⟲ Returned" badge
- Department name that returned it
- Idea details
- Manager's required comments explaining needed changes
- "Edit Your Idea" button linking to the editing page

### Email Configuration

#### Testing with Log Driver (Default)
By default, emails are written to `storage/logs/laravel.log` instead of being sent:

```env
MAIL_MAILER=log
```

To view test emails, check the log file:
```bash
tail -f storage/logs/laravel.log
```

#### Production Email Setup

##### Using Gmail
1. Create an App Password in your Google Account:
   - Go to Google Account → Security → 2-Step Verification → App Passwords
   - Generate a new app password for "Mail"

2. Update `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Workflow System"
```

##### Using Mailtrap (for testing)
Perfect for development and testing:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="Workflow System"
```

##### Using SendGrid
1. Create a SendGrid account and get your API key
2. Update `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="Workflow System"
```

### Email Templates

Email templates are located in:
- `resources/views/emails/idea-status-changed.blade.php`
- `resources/views/emails/manager-reminder.blade.php`

Templates use modern HTML/CSS with:
- Gradient headers
- Responsive design
- Status badges with colors
- Professional typography
- Call-to-action buttons

### Testing Emails

#### Manual Testing
```php
// In tinker (php artisan tinker)
use App\Mail\IdeaStatusChanged;
use App\Models\Idea;
use Illuminate\Support\Facades\Mail;

$idea = Idea::find(1);
Mail::to('test@example.com')->send(
    new IdeaStatusChanged($idea, 'approved', 'Department A', 'Great work!')
);
```

#### Automated Testing
The system automatically sends emails during normal workflow operations. Just ensure your email configuration is correct and take actions on ideas.

---

## Automated Reminder System

### Overview
The system automatically sends reminder emails to managers who have pending approvals waiting for more than 24 hours.

### How It Works

#### Reminder Logic
1. Every hour, the scheduler checks for pending approvals
2. Identifies approvals where:
   - Status is "pending"
   - Idea arrived at the department more than 24 hours ago
   - No reminder was sent in the last 24 hours (or never sent)
3. Sends reminders only to managers with "approver" permission
4. Records the reminder timestamp to avoid spam

#### Scheduler Configuration
The scheduler is configured in `bootstrap/app.php`:

```php
->withSchedule(function (Schedule $schedule): void {
    $schedule->command('reminders:send-manager')->hourly();
})
```

### Setting Up the Scheduler

#### Production (Linux/cron)
Add this to your crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

To edit crontab:
```bash
crontab -e
```

#### Development (Local Testing)
Run the scheduler manually in a separate terminal:
```bash
php artisan schedule:work
```

Or run the reminder command directly:
```bash
php artisan reminders:send-manager
```

### Reminder Email Content

The reminder email includes:
- **Urgency indicator**: Shows how many hours the idea has been waiting
- **Idea details**: Name, description, and submitter
- **Department name**: Which department needs to review
- **Direct link**: Button to review the idea
- **Professional formatting**: Modern design matching other system emails

### Database Fields

#### idea_approvals table
```sql
arrived_at          TIMESTAMP    -- When idea arrived at this department
reminder_sent_at    TIMESTAMP    -- Last time reminder was sent
```

### Command Details

#### Artisan Command
```bash
php artisan reminders:send-manager
```

**Location**: `app/Console/Commands/SendManagerReminders.php`

**What it does**:
1. Finds approvals older than 24 hours without recent reminders
2. Gets managers with "approver" permission for those departments
3. Sends personalized reminder emails
4. Updates reminder_sent_at timestamp
5. Logs all actions

**Output Example**:
```
Checking for pending approvals requiring reminders...
Reminder sent to manager@example.com for idea: New Product Feature
Reminder sent to manager2@example.com for idea: Process Improvement
Total reminders sent: 2
```

### Customization

#### Change Reminder Threshold
Edit `app/Console/Commands/SendManagerReminders.php`:
```php
// Change from 24 hours to 48 hours
$threshold = Carbon::now()->subHours(48);
```

#### Change Reminder Frequency
Edit `bootstrap/app.php`:
```php
// Every 2 hours instead of hourly
$schedule->command('reminders:send-manager')->cron('0 */2 * * *');

// Daily at 9 AM
$schedule->command('reminders:send-manager')->dailyAt('09:00');

// Every 6 hours
$schedule->command('reminders:send-manager')->everySixHours();
```

### Monitoring

#### View Reminder Logs
```bash
tail -f storage/logs/laravel.log | grep "Reminder sent"
```

#### Check Pending Approvals
```bash
php artisan tinker
```
```php
use App\Models\IdeaApproval;
use Carbon\Carbon;

// Approvals older than 24 hours
$threshold = Carbon::now()->subHours(24);
IdeaApproval::where('status', 'pending')
    ->where('arrived_at', '<=', $threshold)
    ->count();
```

---

## Dynamic Workflow

### Overview
The workflow system automatically adapts when departments are disabled, ensuring ideas flow smoothly through only active departments.

### How It Works

#### Traditional Fixed Workflow (Old)
```
Idea → Dept A (Step 1) → Dept B (Step 2) → Dept C (Step 3) → Dept D (Step 4) → Approved
```
Problem: If Dept B is disabled, system breaks.

#### Dynamic Workflow (New)
```
Active departments: A, C, D (B is disabled)
Idea → Dept A (Step 1) → Dept C (Step 3) → Dept D (Step 4) → Approved
```
The system automatically skips disabled departments!

### Implementation Details

#### On Idea Submission
```php
// IdeaWorkflowService::submitIdea()
$departments = Department::where('is_active', true)
    ->orderBy('approval_order')
    ->get();

// Only creates approval records for active departments
// Sets arrived_at for the first active department
```

#### On Approval
```php
// IdeaWorkflowService::approveIdea()
$activeDepartments = Department::where('is_active', true)
    ->orderBy('approval_order')
    ->get();

// Finds next active department after current step
$nextDepartment = $activeDepartments->first(function ($dept) use ($currentStep) {
    return $dept->approval_order > $currentStep;
});

// If found, moves to next department
// If not found, marks idea as fully approved
```

### Examples

#### Example 1: All Departments Active
```
Departments: A(1), B(2), C(3), D(4) - all active
Workflow: 1 → 2 → 3 → 4 → Approved
```

#### Example 2: Middle Department Disabled
```
Departments: A(1), B(2-disabled), C(3), D(4)
Workflow: 1 → 3 → 4 → Approved
```

#### Example 3: Multiple Departments Disabled
```
Departments: A(1), B(2-disabled), C(3-disabled), D(4)
Workflow: 1 → 4 → Approved
```

#### Example 4: Only One Department Active
```
Departments: A(1), B(2-disabled), C(3-disabled), D(4-disabled)
Workflow: 1 → Approved
```

### Admin Controls

Admins can enable/disable departments:
1. Navigate to Admin Dashboard → Departments tab
2. View current status badges (Active/Inactive)
3. Click department to edit
4. Toggle `is_active` status

**Important**: The system handles disabled departments automatically. No need to update existing pending ideas - they will automatically skip disabled departments.

### Database Structure

#### departments table
```sql
id              INT
name            VARCHAR
description     TEXT
approval_order  INT         -- 1, 2, 3, 4
is_active       BOOLEAN     -- true or false
```

---

## Department Management

### Overview
Admins have complete control over departments including creation, editing, reordering, and activation status.

### Features

#### 1. View All Departments
**Location**: Admin Dashboard → Departments tab

Shows:
- Department name and description
- Current approval order (step number)
- Active/Inactive status
- Assigned managers

#### 2. Reorder Departments
**Use case**: Change which department reviews ideas first, second, third, fourth

**How to**:
1. Navigate to Departments tab
2. Use ⬆️ ⬇️ arrow buttons to move departments up or down
3. The step numbers update automatically
4. Click "Save New Order" to apply changes

**Warning**: System warns if there are pending ideas that may be affected

#### 3. Create Department
**Endpoint**: `POST /api/admin/departments`

```json
{
  "name": "Quality Assurance",
  "description": "Reviews technical quality",
  "approval_order": 2,
  "is_active": true
}
```

**Validation**:
- `name`: Required, max 255 characters
- `description`: Optional
- `approval_order`: Required, integer 1-4, must be unique
- `is_active`: Boolean, defaults to true

#### 4. Update Department
**Endpoint**: `PUT /api/admin/departments/{id}`

```json
{
  "name": "Updated Name",
  "description": "Updated description",
  "is_active": false
}
```

#### 5. Delete Department
**Endpoint**: `DELETE /api/admin/departments/{id}`

**Caution**: This will remove all manager assignments and approval records for this department.

#### 6. Reorder Departments
**Endpoint**: `POST /api/admin/departments/reorder`

```json
{
  "departments": [
    {"id": 1, "approval_order": 1},
    {"id": 2, "approval_order": 2},
    {"id": 3, "approval_order": 3},
    {"id": 4, "approval_order": 4}
  ]
}
```

**Validation**:
- Must provide exactly 4 departments
- Orders must be 1, 2, 3, 4 (unique, no gaps)
- Transaction-based (all or nothing)

### Manager Assignment

#### Assign Manager to Department
```json
POST /api/admin/managers/assign
{
  "user_id": 123,
  "department_id": 1,
  "permission": "approver"
}
```

#### Remove Manager from Department
```json
POST /api/admin/managers/remove
{
  "user_id": 123,
  "department_id": 1
}
```

### Best Practices

1. **Always have at least one active department**: System requires at least one active department for ideas to flow
2. **Assign managers before activating**: Make sure departments have managers with "approver" permission
3. **Test workflow after changes**: Submit a test idea to verify the workflow works as expected
4. **Communicate changes**: Notify managers when department order or status changes

---

## Setup Instructions

### Initial Setup

#### 1. Run Migrations
```bash
php artisan migrate
```

This creates:
- `permission` field in department_managers table
- `arrived_at` and `reminder_sent_at` fields in idea_approvals table

#### 2. Configure Email
Edit `.env` file with your email provider settings:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Workflow System"
```

#### 3. Set Up Scheduler

**Production (Linux)**:
```bash
crontab -e
```
Add:
```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

**Development**:
```bash
php artisan schedule:work
```

#### 4. Test the System

##### Test Emails
```bash
php artisan tinker
```
```php
use App\Mail\IdeaStatusChanged;
use Illuminate\Support\Facades\Mail;
use App\Models\Idea;

$idea = Idea::first();
Mail::to('test@example.com')->send(
    new IdeaStatusChanged($idea, 'approved', 'Department A', 'Test comment')
);
```

##### Test Reminders
```bash
php artisan reminders:send-manager
```

##### Test Dynamic Workflow
1. Create a test idea
2. Disable one department
3. Submit the idea
4. Verify it skips the disabled department

### Verification Checklist

- [ ] Migrations ran successfully
- [ ] Email configuration working (test email received)
- [ ] Scheduler running (check with `php artisan schedule:list`)
- [ ] Reminders sending correctly
- [ ] Manager permissions displaying in UI
- [ ] Dynamic workflow skipping disabled departments
- [ ] All frontend updates working (Admin & Manager dashboards)

### Troubleshooting

#### Emails Not Sending
1. Check `storage/logs/laravel.log` for errors
2. Verify `.env` email settings
3. Test SMTP connection:
```bash
php artisan tinker
```
```php
Mail::raw('Test email', function ($message) {
    $message->to('test@example.com')->subject('Test');
});
```

#### Reminders Not Sending
1. Verify scheduler is running
2. Check if ideas have `arrived_at` set
3. Run command manually: `php artisan reminders:send-manager`
4. Check logs: `tail -f storage/logs/laravel.log`

#### Permission Not Working
1. Clear cache: `php artisan cache:clear`
2. Verify database has `permission` column
3. Check that managers have proper permission assigned
4. Verify frontend is reading `pivot.permission`

### Maintenance

#### Regular Tasks
- Monitor email delivery rates
- Check scheduler logs weekly
- Review pending approvals monthly
- Update email templates as needed
- Audit manager permissions quarterly

#### Performance
- Archive old ideas (older than 1 year)
- Clean up old approval records
- Monitor database size
- Index frequently queried fields

---

## API Reference

### Manager Permission Endpoints

```
POST   /api/admin/managers/assign              - Assign manager with permission
POST   /api/admin/managers/update-permission   - Update manager permission
POST   /api/admin/managers/remove              - Remove manager from department
```

### Department Endpoints

```
GET    /api/admin/departments                  - Get all departments
POST   /api/admin/departments                  - Create department
PUT    /api/admin/departments/{id}             - Update department
DELETE /api/admin/departments/{id}             - Delete department
POST   /api/admin/departments/reorder          - Reorder departments
```

### Statistics Endpoints

```
GET    /api/admin/pending-ideas-count          - Get count of pending ideas
```

---

## Support

For issues, questions, or feature requests:
1. Check this documentation first
2. Review Laravel logs: `storage/logs/laravel.log`
3. Test in development environment
4. Check database migrations are up to date
5. Verify all `.env` settings

---

## Version History

### Version 2.0 (Current)
- Manager permission system (viewer/approver)
- Email notifications for idea status changes
- Automated reminder system (24-hour threshold)
- Dynamic workflow with disabled department support
- Enhanced department management
- Comprehensive admin controls

### Version 1.0
- Basic workflow system
- User, Manager, Admin roles
- 4-step approval process
- Idea CRUD operations
