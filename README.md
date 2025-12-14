Ashcol Portal — all-in-one service hub for Ashcol Airconditioning Corporation.

## Overview
Platform for customers, staff, and admins.
- Web: Laravel 11 + Blade + Tailwind CSS
- Mobile: ServiceHub Android app (REST API)
- Landing: Marketing site and contact form
- Auth: Breeze roles (admin, staff, customer)
- Ticketing: CRUD, comments, statuses, priorities
- Dashboards per role
- REST API for mobile integration

### Roles
Customers: create/manage tickets, view status/history, profile.  
Staff: manage assigned tickets, update status/priority, workload view.  
Admins: administration, users/employees/branches, analytics, ticket oversight.

### Local URLs
- Landing: http://localhost/ashcol_portal/public/
- Login: http://localhost/ashcol_portal/public/login
- Dashboard: http://localhost/ashcol_portal/public/dashboard
- Tickets: http://localhost/ashcol_portal/public/tickets

### Default accounts (after seeding)
- Admin: admin@example.com / password
- Staff: staff@example.com / password
- Customer: customer@example.com / password

---

## Quickstart
Requires PHP 8.2+, Composer, Node.js LTS, MySQL (XAMPP/Laragon).

```
composer install
npm install
cp .env.example .env
php artisan key:generate

# set DB in .env (ashcol_portal)
php artisan migrate --seed

# run
php artisan serve
npm run dev
```

If using XAMPP without `artisan serve`, open the /public URLs above.

---

## Architecture
- Models: User(role), Ticket, TicketStatus, TicketComment
- Controllers: TicketController, TicketCommentController, DashboardController, Breeze auth
- Policies: TicketPolicy
- Middleware alias: role -> CheckRole (admin/staff/customer)
- Views: resources/views (tickets, dashboards, landing)
- Landing assets: public/ashcol/styles.css, public/ashcol/script.js, public/ashcol/images

---

## Data model
- users: id, name, email, password, role
- ticket_statuses: id, name, color, is_default
- tickets: id, title, description, customer_id, assigned_staff_id, status_id, priority
- ticket_comments: id, ticket_id, user_id, comment
Priorities: low | medium | high | urgent

---

## Upcoming modules
Workloads: assign/unassign tickets, availability calendar, board (today/upcoming/overdue).  
Employees: roster, link to users, skills, branch.  
Branches: branch CRUD, scope tickets/workloads, default branch for requests.

---

## Roadmap
- Completed: auth with roles; ticketing with comments/status/priority; role dashboards; landing page; auth API; Sanctum tokens.
- Phase 1: map landing request form to tickets.store; contact form email + DB (leads); ticket APIs (list/create/show/update/comments); profile APIs (update profile/password).
- Phase 2: workloads module and APIs.
- Phase 3: employees module (roster, link to users, CRUD, skills).
- Phase 4: branches module (CRUD, scoping, reporting).
- Phase 5: attachments (upload, storage, validation, preview).
- Phase 6: notifications (email, in-app, push, preferences).
- Phase 7: search/reporting (advanced search, analytics, export, audit logs).
- Phase 8: mobile API enhancements (role-based responses, offline sync, real-time, push, workloads, employees, branches).

---

## Developer commands
```
php artisan optimize:clear
php artisan migrate
php artisan migrate:fresh --seed   # dev only
npm run dev
npm run build
```

---

## Contributing
Branch names: feature/<name>. Small commits. Open PRs for review.

## License
Laravel (MIT). See MIT license.
