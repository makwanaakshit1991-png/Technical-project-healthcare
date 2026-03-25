# 🏥 Smart Health Record System (SHRS)
## Installation & Setup Guide

---

## Quick Start (XAMPP / WAMP / LAMP)

### Step 1 — Copy Files
```
Copy the entire `shrs/` folder into your web root:
  XAMPP → C:\xampp\htdocs\shrs\
  WAMP  → C:\wamp64\www\shrs\
  LAMP  → /var/www/html/shrs/
```

### Step 2 — Import Database
1. Start Apache + MySQL
2. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
3. Click **Import** → Choose file → `shrs/sql/shrs_database.sql`
4. Click **Go**

### Step 3 — Configure Database Connection
Edit `shrs/config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');   // your MySQL username
define('DB_PASS', '');       // your MySQL password
define('DB_NAME', 'shrs_db');
```

### Step 4 — Set Up Test Account Passwords
Open in browser:
```
http://localhost/shrs/setup_passwords.php?confirm=yes
```
**⚠️ Delete `setup_passwords.php` after running!**

### Step 5 — Access the Application
```
http://localhost/shrs/
```

---

## Test Credentials

| Role       | Email                | Password     |
|------------|----------------------|--------------|
| Patient    | patient@shrs.com     | Patient@123  |
| Clinician  | doctor@shrs.com      | Doctor@123   |
| Pharmacist | pharma@shrs.com      | Pharma@123   |
| Insurer    | insurer@shrs.com     | Insurer@123  |
| Admin      | admin@shrs.com       | Admin@123    |

---

## Features

### Security
- bcrypt password hashing (PASSWORD_BCRYPT)
- CSRF tokens on all forms
- MySQLi prepared statements (zero SQL injection)
- Session-based authentication with 30-minute timeout
- Role-Based Access Control (RBAC) on every page

### AI Engine (PHP Rule-Based Simulation)
- **Diabetes Risk** — based on HbA1c, fasting glucose, BMI
- **Hypertension Risk** — based on MAP, BMI, heart rate
- **ICU Deterioration Score** — based on last 5 vital sign readings (SpO2, HR, RR, MAP, Temperature)
- Color-coded risk badges: 🟢 Low | 🟡 Moderate | 🔴 High

### Blockchain Audit Trail
- SHA-256 hash-chained immutable audit log
- Every record create/update, login, consent change logged
- Admin can verify chain integrity with tamper detection

### Roles
- **Patient** — View own records, manage consents, book appointments
- **Clinician** — Create/update records, prescribe, add vitals, view AI alerts
- **Pharmacist** — Dispense prescriptions, allergy checking
- **Insurer** — Review & approve/reject claims (consent-gated)
- **Admin** — Full system access, user management, audit trail

---

## Folder Structure
```
shrs/
├── index.php                  # Login page
├── register.php               # Registration
├── logout.php
├── setup_passwords.php        # Run once, then delete
├── config/db.php              # Database connection
├── auth/                      # Authentication
├── dashboards/                # Role-specific dashboards
├── modules/
│   ├── records/               # Health records CRUD
│   ├── consent/               # Patient consent management
│   ├── appointments/          # Appointment booking
│   ├── prescriptions/         # Prescription management
│   ├── lab_results/           # Lab result upload/view
│   ├── messaging/             # Internal messaging
│   ├── insurance/             # Insurance claims
│   ├── ai_engine/             # AI risk prediction engine
│   └── blockchain/            # Audit trail
├── admin/                     # Admin panel
├── assets/css/style.css       # Custom stylesheet
├── assets/js/main.js          # JavaScript
├── includes/                  # Shared components
└── sql/shrs_database.sql      # Database schema + sample data
```

---

## PHP Requirements
- PHP 8.0+
- MySQLi extension enabled
- MySQL 5.7+ / MariaDB 10.3+
