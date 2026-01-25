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

**🚀 New: Local Development Setup**
- Run locally with `php artisan serve`
- Use Railway database (remote)
- Local SMTP with MailHog or log driver
- See `SETUP_SUMMARY.md` for complete setup guide

### Roles
Customers: create/manage tickets, view status/history, profile.  
Staff: manage assigned tickets, update status/priority, workload view.  
Admins: administration, users/employees/branches, analytics, ticket oversight.

### Local URLs (Development)
- Application: http://localhost:8000 (when using `php artisan serve`)
- MailHog UI: http://localhost:8025 (if using MailHog)

### Production URL
- Railway: https://your-app.railway.app

### Default accounts (after seeding)
- Admin: admin@example.com / password
- Staff: staff@example.com / password
- Customer: customer@example.com / password

---

## Quickstart (Local Development)

**Prerequisites**: PHP 8.2+, Composer, Node.js

**Quick Setup** (Recommended):
```powershell
# Run interactive setup script
.\local-setup.ps1

# Start all development servers
.\start-dev.ps1
```

**Manual Setup**:
```bash
# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure .env with:
# - Railway database credentials
# - Local mail settings (MailHog or log)

# Run migrations
php artisan migrate --seed

# Start servers (3 separate terminals)
php artisan serve          # Terminal 1
npm run dev               # Terminal 2
mailhog                   # Terminal 3 (optional)
```

**📚 Complete Setup Documentation**:
- **[SETUP_SUMMARY.md](SETUP_SUMMARY.md)** - Overview of configuration
- **[SETUP_CHECKLIST.md](SETUP_CHECKLIST.md)** - Step-by-step checklist
- **[LOCAL_SETUP.md](LOCAL_SETUP.md)** - Detailed setup guide
- **[MAIL_SETUP_LOCAL.md](MAIL_SETUP_LOCAL.md)** - Mail configuration options
- **[RAILWAY_VS_LOCAL.md](RAILWAY_VS_LOCAL.md)** - Environment comparison
- **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** - Quick command reference

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
```bash
# Start development
.\start-dev.ps1              # Start all servers (Windows)
php artisan serve            # Start Laravel only

# Database
php artisan migrate          # Run migrations
php artisan db:seed          # Seed database
php artisan migrate:fresh --seed  # Fresh install

# Frontend
npm run dev                  # Development with HMR
npm run build               # Production build

# Cache
php artisan optimize:clear   # Clear all caches
php artisan config:clear     # Clear config cache

# Testing
php artisan test             # Run tests
php artisan tinker          # Interactive shell

# Mail (if using MailHog)
mailhog                     # Start local mail server
# Access at http://localhost:8025

# Logs
Get-Content storage/logs/laravel.log -Tail 50 -Wait  # Watch logs
```

---

## Contributing
Branch names: feature/<name>. Small commits. Open PRs for review.

## License
Laravel (MIT). See MIT license.
