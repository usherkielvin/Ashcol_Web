<p align="center"><strong>Ashcol Portal</strong><br/>All-in-One Service Hub - Complete business management system for Ashcol Airconditioning Corporation.</p>

---

## 1) Overview

**Ashcol Portal** is the comprehensive backend and web platform that powers the all-in-one Service Hub ecosystem, serving **customers**, **employees (staff)**, and **administrators** with a complete suite of service management tools.

### System Components
- **Web Portal:** Monolith built with Laravel 11 + Blade + Tailwind CSS
- **Mobile App:** ServiceHub Android app (connects via REST API)
- **Public Landing:** Marketing site + contact form
- **Authentication:** Breeze with role-based access (Admin, Staff, Customer)
- **Ticketing System:** Full CRUD with comments, statuses, priorities
- **Role-Based Dashboards:** Customized views for each user type
- **API Layer:** RESTful API for mobile app integration

### User Roles & Access

#### 👤 **Customers**
- Create and manage service ticketss
- View ticket status and history
- Access customer dashboard
- Manage profile

#### 👷 **Employees (Staff)**
- View and manage assigned tickets
- Update ticket status and priorities
- Access workload management
- Employee dashboard with assignments

#### 👨‍💼 **Administrators**
- Full system administration
- User and employee management
- Branch management
- Analytics and reporting
- Ticket assignment and oversight

Live dev URLs (local/XAMPP):
- Landing: `http://localhost/ashcol_portal/public/`
- Login: `http://localhost/ashcol_portal/public/login`
- Dashboard: `http://localhost/ashcol_portal/public/dashboard`
- Tickets: `http://localhost/ashcol_portal/public/tickets`

Default accounts (after seeding):
- Admin: `admin@example.com` / `password`
- Staff: `staff@example.com` / `password`
- Customer: `customer@example.com` / `password`

---

## 2) Quickstart

Requirements: PHP 8.2+, Composer, Node.js LTS, MySQL (XAMPP/Laragon)

```
composer install
npm install
cp .env.example .env
php artisan key:generate

# configure DB in .env (ashcol_portal)
php artisan migrate --seed

# run
php artisan serve
npm run dev
```

If using XAMPP without `artisan serve`, access via `/public/` as shown above.

---

## 3) Architecture

- Models: `User(role)`, `Ticket`, `TicketStatus`, `TicketComment`
- Controllers: `TicketController`, `TicketCommentController`, `DashboardController`, Breeze auth controllers
- Policies: `TicketPolicy` (view/create/update/delete)
- Middleware alias: `role` → `CheckRole` (admin/staff/customer)
- Views: `resources/views` (tickets, dashboards, landing)
- Landing assets: `public/ashcol/styles.css`, `public/ashcol/script.js`, images in `public/ashcol/`

---

## 4) Data Model (current)

- users: `id, name, email, password, role`
- ticket_statuses: `id, name, color, is_default`
- tickets: `id, title, description, customer_id, assigned_staff_id, status_id, priority`
- ticket_comments: `id, ticket_id, user_id, comment`

Priorities: `low | medium | high | urgent`

---

## 5) Workloads / Employees / Branches (to be implemented)

The next domain features are planned as separate modules:

### A) Workloads (Scheduling & Assignment)
- Entities: `workloads(id, ticket_id, staff_id, start_at, end_at, status)`
- Features:
  - Assign/unassign tickets to staff
  - Staff availability calendar (basic)
  - Workload board: Today / Upcoming / Overdue

### B) Employees (HR-lite)
- Entities: `employees(id, user_id, position, skills(json), branch_id, active)`
- Features:
  - Staff roster with filters (skills/branch)
  - Link `employees.user_id` to `users.id`

### C) Branches (Geography)
- Entities: `branches(id, name, address, phone, region, active)`
- Features:
  - Branch management CRUD
  - Scope tickets and workloads by branch
  - Landing “Request Service” default branch selection

---

## 6) Roadmap / TODO

### ✅ Completed
- [x] Breeze auth + roles (admin/staff/customer)
- [x] Ticketing: CRUD, comments, statuses, priorities
- [x] Role dashboards (admin/staff/customer)
- [x] Public landing page (ported design)
- [x] API endpoints for authentication (login, logout, user profile)
- [x] Sanctum token-based authentication

### 🚀 Phase 1: Core Features (In Progress)
- [ ] Request Service → create Ticket (map landing form to `tickets.store`)
- [ ] Contact form → email + DB (leads table)
- [ ] API endpoints for ticket management
  - [ ] `GET /api/v1/tickets` - List tickets
  - [ ] `POST /api/v1/tickets` - Create ticket
  - [ ] `GET /api/v1/tickets/{id}` - Get ticket details
  - [ ] `PUT /api/v1/tickets/{id}` - Update ticket
  - [ ] `POST /api/v1/tickets/{id}/comments` - Add comment
  - [ ] `GET /api/v1/tickets/{id}/comments` - Get comments
- [ ] Profile API endpoints
  - [ ] `PUT /api/v1/profile` - Update profile
  - [ ] `PUT /api/v1/profile/password` - Change password

### 🎯 Phase 2: Workload Management
- [ ] Workloads module (assignments, schedule view)
  - [ ] Assign/unassign tickets to staff
  - [ ] Staff availability calendar (basic)
  - [ ] Workload board: Today / Upcoming / Overdue
  - [ ] API endpoints for workload management

### 👥 Phase 3: Employee Management
- [ ] Employees module (roster, skills, link to users)
  - [ ] Staff roster with filters (skills/branch)
  - [ ] Link `employees.user_id` to `users.id`
  - [ ] Employee CRUD operations
  - [ ] Skills management

### 🏢 Phase 4: Branch Management
- [ ] Branches module (scope tickets/workloads)
  - [ ] Branch management CRUD
  - [ ] Scope tickets and workloads by branch
  - [ ] Landing "Request Service" default branch selection
  - [ ] Branch-based filtering and reporting

### 📎 Phase 5: File Management
- [ ] Attachments (ticket and comment uploads)
  - [ ] File upload functionality
  - [ ] File storage and retrieval
  - [ ] File type validation
  - [ ] Image preview

### 🔔 Phase 6: Notifications
- [ ] Notifications (email on create/assign/status change)
  - [ ] Email notifications for ticket events
  - [ ] In-app notification system
  - [ ] Push notifications (for mobile app integration)
  - [ ] Notification preferences

### 🔍 Phase 7: Search & Reporting
- [ ] Advanced search functionality
- [ ] Reporting and analytics dashboard
- [ ] Export capabilities (CSV, PDF)
- [ ] Audit logs

### 📱 Phase 8: Mobile API Enhancements (ServiceHub App)
- [ ] Enhanced mobile API endpoints for all user roles
- [ ] Role-based API responses (customer/employee/admin)
- [ ] Offline sync support
- [ ] Real-time updates (WebSockets)
- [ ] Mobile-optimized responses
- [ ] Push notification endpoints
- [ ] Workload management APIs
- [ ] Employee management APIs (admin only)
- [ ] Branch management APIs (admin only)

---

## 7) Developer Commands

```
# caches
php artisan optimize:clear

# DB lifecycle
php artisan migrate
php artisan migrate:fresh --seed   # dev only

# assets
npm run dev
npm run build
```

---

## 8) Contributing

Branch naming: `feature/<name>` • Small, descriptive commits • Open PRs for review.

---

## 9) License

This project uses Laravel (MIT). See [MIT license](https://opensource.org/licenses/MIT).
