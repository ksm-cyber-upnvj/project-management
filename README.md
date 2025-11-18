# Cytrack - Project Management System
### by KSM Cyber Security UPNVJ

![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-1.jpeg)
![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-4.jpeg)
![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-5.jpeg)

A Laravel Filament 3 application for managing projects with ticket management and status tracking.

## Features

- Project management with ticket prefix configuration
- Role-based access control (using Filament Shield)
- Team member management with role assignments
- Customizable ticket statuses with color coding
- Ticket management with assignees and due dates
- Unique ticket identifiers
- Epic management for organizing tickets into larger initiatives
- Comment system for tickets to facilitate team discussions
- Kanban board view for visualizing ticket progress
- Assign ticket to multi users
- User contributions chart
- Timeline view
- Export ticket data to CSV
- Leaderboard for team member performance
- External dashboard view (Client Portal)

## Requirements

- PHP > 8.2+
- Laravel 12
- MySQL 8.0+ / PostgreSQL 12+
- Composer

![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-2.jpeg)
![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-6.jpeg)
![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-7.jpeg)
![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-8.jpeg)
![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-9.jpeg)


## Installation

1. Clone the repository:
   ```
   git clone https://github.com/your-organization/cytrack-project-management
   cd cytrack-project-management
   ```

2. Install dependencies:
   ```
   composer install
   npm install
   ```

3. Set up environment:
   ```
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure database in `.env` file:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=cytrack_project_management
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. Run migrations:
   ```
   php artisan migrate
   ```

6. Create storage link for file uploads
   ```
   php artisan storage:link
   ```

7. Create a Filament admin user:
   ```
   php artisan make:filament-user
   ```
8. Activate Role & Permission
   ```
   php artisan shield:setup
   php artisan shield:install
   php artisan shield:super-admin
   ```
9. Compile assets:
   ```
   npm run dev
   ```
10. Start the development server:
   ```
   php artisan serve
   ```

## Usage

1. Access the Filament admin panel at `http://localhost:8000/admin`
2. Log in with the Filament user credentials you created
3. Create a new project with custom ticket prefix
4. Add team members to the project
5. Create and customize ticket statuses
6. Add tickets and assign to team members

## Main Features

### Board View

The Board View offers a familiar kanban-style interface for ticket management:

- Drag-and-drop tickets between status columns
- Customize columns to match your team's process
- Quick-edit functionality for updating tickets directly from the board

### Timeline View

The Timeline feature provides a chronological perspective of your project work:

- Visualize project roadmap with start and end dates
- Track milestone completion across time periods
- Easily identify scheduling conflicts or resource bottlenecks

### Epic Management

Epics help organize related tickets into larger initiatives:

- Group tickets by feature, release, or business objective
- Track progress across multiple tickets
- Set start and end dates for planning purposes
- Visualize which tickets belong to which initiatives

### Ticket Comments

The comment system enhances team collaboration:

- Team members can discuss tickets directly in the application
- All comments are timestamped and attributed to users
- Supports rich text formatting for improved readability
- Enables better context sharing and decision documentation

## Google Login Integration

This application supports Google OAuth login. Here's how to configure it:

### 1. Getting Google OAuth Credentials

1. **Open Google Cloud Console**
   - Visit [Google Cloud Console](https://console.cloud.google.com/)
   - Sign in with your Google account

2. **Create or Select a Project**
   - Create a new project or select an existing one
   - Make sure the project is active

3. **Enable Google+ API**
   - In the sidebar, select "APIs & Services" > "Library"
   - Search for "Google+ API" and click "Enable"

4. **Create OAuth 2.0 Credentials**
   - In the sidebar, select "APIs & Services" > "Credentials"
   - Click "Create Credentials" > "OAuth 2.0 Client IDs"
   - Select "Web application" as the application type
   - Enter application name (example: "Cytrack Project Management")
   - In "Authorized redirect URIs", add:
     ```
     http://localhost:8000/auth/google/callback
     https://yourdomain.com/auth/google/callback
     ```
   - Click "Create"

5. **Copy Client ID and Client Secret**
   - After creation, you will get Client ID and Client Secret
   - Copy both values for application configuration

### 2. Environment Configuration

Add Google OAuth configuration in `.env` file:

```env
GOOGLE_CLIENT_ID=your_google_client_id_here
GOOGLE_CLIENT_SECRET=your_google_client_secret_here
```

Replace `your_google_client_id_here` and `your_google_client_secret_here` with the values you obtained from Google Cloud Console.

### 3. How to Use

1. Open the application login page at `/admin/login`
2. Click the "Continue with Google" button
3. Sign in with your Google account
4. The application will automatically create a new account or log in to an existing account

**Note:** If the Google email is already registered in the system, the application will link the Google account with the existing account.

## Queue & Email Notifications

This application uses a queue system to send email notifications asynchronously. Here's how to configure and run it:

### 1. Email Configuration

Configure email in `.env` file:

```env
# For development (log emails to file)
MAIL_MAILER=log

# For production (SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Cytrack by KSM Cyber Security UPNVJ"
```

### 2. Queue Configuration

Ensure queue configuration in `.env`:

```env
QUEUE_CONNECTION=database
```

### 3. Running Queue Worker

To process email notification queues, run the following commands:

#### Development Mode
```bash
php artisan queue:work
```

#### Production Mode (with automatic restart)
```bash
php artisan queue:work --daemon --tries=3 --timeout=60
```

#### Using Supervisor (Recommended for Production)

1. Install supervisor:
   ```bash
   sudo apt-get install supervisor
   ```

2. Create configuration file `/etc/supervisor/conf.d/laravel-worker.conf`:
   ```ini
   [program:laravel-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /path/to/your/project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   numprocs=8
   redirect_stderr=true
   stdout_logfile=/path/to/your/project/storage/logs/worker.log
   stopwaitsecs=3600
   ```

3. Restart supervisor:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start laravel-worker:*
   ```

### 4. Email Notification Types

The application sends email notifications for:
- **Project Assignment**: When a user is added to a project
- **Comment Notifications**: When there are new comments on tickets
- **Ticket Updates**: When ticket status changes

**Note:** Make sure the queue worker is always running to process email notifications. Without the queue worker, emails will not be sent.

## Testing

Cytrack includes comprehensive automated tests to ensure code quality, security, and reliability.

### Running Tests

Start Docker services first:
```bash
docker compose up -d db app
```

Run all tests:
```bash
docker compose exec -T app php artisan test
```

Run specific test suites:
```bash
# Unit tests only
docker compose exec -T app php artisan test --testsuite=Unit

# Feature tests only
docker compose exec -T app php artisan test --testsuite=Feature
```

### Security Testing

Run security tests to verify authentication, authorization, input validation, and data protection:

```bash
docker compose exec -T app php artisan test tests/Security
```

**Coverage:** 80 tests covering OWASP Top 10 security concerns including:
- Password hashing and authentication security
- Role-based access control (RBAC) enforcement
- SQL injection and XSS prevention
- Mass assignment protection
- Secure session management

### Reliability Testing

Run reliability tests to verify error handling, data integrity, and system stability:

```bash
docker compose exec -T app php artisan test tests/Reliability
```

**Coverage:** 68 tests ensuring system reliability including:
- Database error handling and recovery
- Transaction consistency and rollbacks
- Referential integrity and foreign key constraints
- Model event reliability (UUID generation, history tracking)
- Service layer error resilience

### Performance Testing

Run performance tests to measure system performance under various load conditions using K6:

```bash
# Load test - average and peak load (10-100 concurrent users)
docker compose run --rm k6 run load-test.js

# Admin test - admin panel performance (10-30 concurrent users)
docker compose run --rm k6 run admin-test.js

# Spike test - sudden traffic spike (10-1000 concurrent users)
docker compose run --rm k6 run spike-test.js

# Stress test - gradual load increase (50-400 concurrent users)
docker compose run --rm k6 run stress-test.js
```

**Coverage:** 4 test scenarios measuring:
- Response time under various load conditions (p95, p99)
- Error rates during normal and peak traffic
- System behavior during traffic spikes
- Maximum capacity and breaking point identification
- Homepage, admin panel, and external dashboard performance

**Note:** See `tests/Performance/README.md` for detailed documentation and customization options.

### Additional Options

Run with additional options for better output:
```bash
# With colors for better readability
docker compose exec -T app php artisan test --colors

# Stop on first failure
docker compose exec -T app php artisan test --stop-on-failure

# Verbose output
docker compose exec -T app php artisan test -v
```

### Troubleshooting

If tests fail due to database connection:
```bash
docker compose up -d db
sleep 10
docker compose exec -T app php artisan test
```

**Note:** Tests use a separate database and automatically reset between runs. Your development data won't be affected.

## License

This project is licensed under the GNU General Public License v3.0 or later (GPL-3.0-or-later).

**Key Points:**
- ✅ Free to use, modify, and distribute
- ✅ Source code must remain open
- ✅ Derivative works must also be GPL-licensed
- ✅ Commercial use allowed
- ⚠️ Any modifications or derivative works must be shared under the same GPL license
- ⚠️ Must include copyright notice and license text

For the full license text, see the [LICENSE](LICENSE) file.

---

## Credits & Attribution

This project is a customized version based on [DewaKoding Project Management](https://github.com/SeptiawanAjiP/dewakoding-project-management) by [@SeptiawanAjiP](https://github.com/SeptiawanAjiP).

**Original Project:**
- **Author**: DewaKoding (@SeptiawanAjiP)
- **Repository**: https://github.com/SeptiawanAjiP/dewakoding-project-management
- **License**: GNU General Public License v3.0

**Customizations for KSM Cyber Security UPNVJ:**
- Rebranded to "Cytrack" for KSM Cyber Security UPNVJ
- Customized color scheme to monochrome palette
- Updated copywriting for student-friendly English interface
- Tailored for cyber security work program management

We are grateful to the original author for creating and maintaining this excellent open-source project management system.

---

## Changelog

### Version 1.1.0 (MailHog Integration & Queue Worker)

#### 📧 Email Development Tools
- **MailHog Integration** - Added MailHog service for email testing in development environment
  - Web UI accessible at http://localhost:8025 for viewing test emails
  - SMTP server running on port 1025 for Laravel email delivery
  - No need for real SMTP credentials during development
- **Automated Queue Worker** - Added dedicated queue worker service in Docker Compose
  - Automatic email notification processing in background
  - Auto-restart on failure for reliability
  - Configured with optimal settings (3 retries, 1-hour max time)
  - No manual queue:work command needed

#### 🔧 Configuration Improvements
- **Environment Configuration** - Updated .env.example and .env with MailHog settings
  - Clear separation between development (MailHog) and production (SMTP) configurations
  - Commented production SMTP examples for easy switching
  - Added MAIL_ENCRYPTION configuration for better security control

#### 🐳 Docker Compose Enhancements
- **MailHog Service** - Containerized email testing service
- **Queue Worker Service** - Dedicated container for processing background jobs
- **Service Dependencies** - Proper dependency management between services

---

### Version 1.0.0 (Initial Release - Cytrack by KSM Cyber Security UPNVJ)
*Release Date: January 2025*

#### 🎨 Branding & Design
- **Rebranded to Cytrack** - Project management system for KSM Cyber Security UPNVJ
- **Monochrome Color Scheme** - Professional gray-scale palette following UI/UX best practices
- **Custom Logo Integration** - Cytrack branding across admin panel and landing page
- **Student-Friendly Interface** - Casual, modern design tailored for university organization

#### 📋 Core Project Management Features
- **Project Management** - Complete project lifecycle management with customizable ticket prefixes
- **Ticket Management System** - Create, assign, and track tickets with unique identifiers
- **Epic Management** - Organize related tickets into larger initiatives with progress tracking
- **Customizable Ticket Statuses** - Define workflow stages with color coding
- **Multi-User Assignment** - Assign tickets to multiple team members simultaneously
- **Due Date Tracking** - Set and monitor deadlines for tickets and projects
- **Comment System** - Facilitate team discussions directly on tickets with rich text support

#### 📊 Visualization & Tracking
- **Kanban Board View** - Drag-and-drop interface for visualizing ticket progress
- **Timeline View** - Chronological perspective of project roadmap with Gantt-style visualization
- **User Contributions Chart** - Track individual team member productivity and contributions
- **Leaderboard System** - Gamified performance tracking for team engagement
- **Progress Tracking** - Real-time project and ticket completion monitoring

#### 👥 Team & Access Management
- **Role-Based Access Control (RBAC)** - Powered by Filament Shield
- **Team Member Management** - Add, remove, and manage project team members
- **Role Assignments** - Assign specific roles and permissions to users
- **External Dashboard** - Client portal for stakeholder visibility without admin access

#### 📧 Communication & Notifications
- **Email Notifications** - Automated notifications for project assignments and updates
- **Queue System** - Asynchronous email processing for optimal performance
- **Project Assignment Emails** - Welcome emails when users are added to projects
- **Comment Notifications** - Alerts for new ticket discussions
- **Ticket Update Notifications** - Status change alerts for stakeholders

#### 🔐 Authentication & Security
- **Google OAuth Integration** - Single sign-on with Google accounts
- **Email Verification** - Secure account confirmation process
- **Password Reset** - Self-service password recovery
- **Session Management** - Secure authentication with session handling

#### 📤 Data Management
- **CSV Export** - Export ticket data for external analysis and reporting
- **Data Import/Export** - Bulk operations for efficient data management

#### 🛠️ Technical Features
- **Laravel 12** - Built on latest Laravel framework
- **Filament 3** - Modern admin panel with excellent DX
- **MySQL/PostgreSQL Support** - Flexible database options
- **Docker Support** - Containerized deployment ready
- **SPA Mode** - Single-page application for faster navigation
- **Database Transactions** - Data integrity and rollback support
- **Responsive Design** - Mobile-friendly interface

#### 🌐 Localization & Content
- **English Interface** - Full English language support
- **Student-Friendly Copywriting** - Casual, approachable messaging
- **Work Program Context** - Tailored for cyber security organization workflows

#### 📝 Documentation
- **Comprehensive README** - Detailed installation and configuration guide
- **Google OAuth Setup Guide** - Step-by-step integration instructions
- **Queue & Email Configuration** - Production-ready email setup documentation
- **Supervisor Configuration** - Production deployment best practices

---

**Note:** This version represents the initial customized release for KSM Cyber Security UPNVJ, based on the original DewaKoding Project Management system.