# HelpKonnect — Full-Stack Build Prompt

Act as a **Senior Full-Stack Engineer and UI/UX Designer**. Build a production-ready platform called **HelpKonnect** for connecting Tanzanian residents and electricians with HelpKonnect support and services.

## Tech Stack

Use only:

* Frontend: **HTML5, CSS3, Vanilla JavaScript**
* Backend: **PHP 8.2+ / REST API**
* Database: **MySQL 8+**
* PHP PDO
* Fetch API / AJAX
* SVG icons

Do NOT use React, Vue, Angular, Bootstrap, Tailwind, Laravel, Node.js, Firebase, or other frameworks.

---

## Architecture

Use a separated architecture:

```text
Public Client
     ↓
REST API Gateway
     ↓
PHP Backend
     ↓
MySQL Database
     ↑
Private Admin Dashboard
```

The **client website is public**.

The **admin dashboard is completely separate and private**, with secure admin authentication and role-based access.

Neither client nor admin should directly access MySQL. Everything must go through the REST API.

---

## Client Features

### 1. Home

Modern landing page explaining HelpKonnect with:

* Services
* How it works
* Apply Now
* Request Drawing
* Assistance
* Contact Support
* Announcements

### 2. Applications / Orders

Allow users to submit:

* New electricity/application request
* Electrical service request
* Technical assistance
* Other requests

Collect:

* Full name
* Phone
* Email
* Location
* Applicant type
* Service/request type
* Description
* Supporting documents/images

Generate a unique reference number such as:

`NK-2026-00001`

Allow users to track application status:

`Submitted → Under Review → Processing → Completed`

### 3. Drawing Requests

Create a dedicated drawing request system.

Users can submit:

* Personal information
* Location
* Property details
* Electrical requirements
* Description
* Site images/documents

Admins can review requests, communicate with users and upload completed drawings.

### 4. Assistance Center

Create a searchable resource center containing:

* PDFs
* Guides
* FAQs
* Electrical information
* Application instructions
* Important notices
* Technical information

Admins can upload, edit, categorize, publish and delete resources.

### 5. Direct Contact

Provide:

* Internal chat with admin
* Phone contact
* WhatsApp contact
* Gmail/email contact

Admin contact information must be configurable from the admin dashboard.

### 6. User Account

Include:

* Register
* Login
* Logout
* Forgot/reset password
* Profile
* My applications
* My drawing requests
* Notifications
* Messages

---

# Admin Dashboard

Create a **separate professional admin dashboard**.

Dashboard should display real database data:

* Total applications
* New applications
* Pending applications
* Drawing requests
* Completed requests
* Open chats
* Users
* Documents

Admins can:

* Manage applications
* Update application status
* Manage drawing requests
* Upload completed drawings
* Manage users
* Manage chats
* Upload/manage PDFs
* Manage FAQs
* Manage announcements
* Manage contact information
* View activity logs

---

# Auto Refresh

The admin dashboard must automatically fetch new client activity through REST APIs without refreshing the entire page.

Use AJAX/Fetch polling, for example every **15 seconds**:

* New applications
* New drawing requests
* New messages
* Notifications
* Dashboard statistics

Show a small **Last Updated** indicator.

---

# REST API

Create a versioned API:

```text
/api/v1/
```

Include endpoints for:

```text
/auth
/users
/applications
/drawings
/conversations
/messages
/documents
/faqs
/announcements
/notifications
/admin/dashboard
/settings
```

Use consistent JSON responses and proper HTTP status codes.

---

# Database

Create a properly related MySQL database including tables such as:

```text
users
roles
applications
application_status_history
application_attachments
drawing_requests
drawing_files
conversations
messages
documents
document_categories
faqs
announcements
notifications
admin_logs
system_settings
```

Use foreign keys, indexes, timestamps and transactions where necessary.

---

# Security

Implement:

* `password_hash()` / `password_verify()`
* PDO prepared statements
* Authentication and authorization
* Role-based admin permissions
* CSRF protection
* XSS protection
* Input validation
* Secure file upload validation
* Session security
* Rate limiting for sensitive endpoints
* Audit/activity logs

Never expose database credentials or sensitive API information to the client.

---

# UI/UX

Create a **premium modern interface**, not a generic template.

Use:

* Modern CSS Grid
* Advanced responsive layouts
* Professional electrical/technology visual identity
* Clean typography
* Beautiful cards
* Rounded components
* Soft shadows
* Modern dashboard
* Smooth micro-interactions
* Page transitions
* Loading skeletons
* Toast notifications
* Modal/slide-over interfaces
* Responsive mobile design
* Dark/light mode

Use a professional palette based around **deep navy, electric blue, cyan, white and neutral gray**.

---

# Important Rules

**NO placeholders.
NO fake data.
NO demo features.
NO "Coming Soon".
NO non-functional buttons.**

Every feature must be fully connected:

```text
UI → REST API → PHP → MySQL → API Response → UI
```

Use **Tanzanian context**, Tanzanian locations and **TZS** whenever monetary values are required.

Build the project with clean folder structure, reusable components, secure APIs and production-quality code.

The final result must be a **fully functional HelpKonnect full-stack platform**, not just a frontend mockup.
