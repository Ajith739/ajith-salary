# 💰 MyFinance — Personal Financial Command Center

A full-featured personal financial management web application built with **PHP 8+, MySQL/MariaDB, Vanilla Modern CSS (Dark/Light glassmorphism), Three.js, GSAP, and Chart.js**.

Designed specifically around Indian financial conventions (Indian Rupee formatting `₹1,23,456`, calendar working days with Sundays & 2nd/4th Saturday holidays, prepaid mobile recharge cycles, friend loan tracking, EMI reducing balance calculations, and 12-month projections).

---

## 📸 Core Features

- **📊 Central Dashboard**:
  - Financial Health Score (0–100) with radial SVG gauge and breakdown factors.
  - Cash Flow trend line chart & category spending donut chart.
  - Overview stat cards (Salary, Expenses, Savings, Balance, Friend Loans, EMI).
  - Monthly financial breakdown and quick widgets.
  - Interactive Three.js 3D particle constellation background.

- **💳 Expense Tracker (`expenses.php`)**:
  - Log daily expenses with categories, payment channels (UPI, Cash, Bank, Card), and tags.
  - Recurring subscriptions tracker (WiFi, Netflix, Gym) with billing intervals.
  - Filter by month, year, and category with live count & totals.

- **💵 Income Management (`income.php`)**:
  - Primary employment salary schedule with daily earning rate based on working days.
  - Extra income stream logging (Freelance, Bonuses, Interests, Refunds, Gifts).

- **🤝 Friend Loans Manager (`friends.php`)**:
  - Track money lent to friends and repayments received.
  - Individual friend ledger cards with remaining balances and quick "Repay" / "Settle Up" actions.

- **🎯 Financial Goals & Wishlist (`goals.php`)**:
  - Plan major purchases (iPad, appliances, emergency fund) with visual progress bars.
  - Calculate required daily/monthly savings to achieve goals on target dates.
  - Deposit contributions directly into goals.

- **🧮 EMI & Loan Calculator (`emi.php`)**:
  - Interactive reducing balance loan calculator with real-time sliders (Principal, Rate, Tenure).
  - Generate and view complete month-by-month loan amortization schedules.
  - Track active loans and monitor EMI impact as a percentage of monthly salary.

- **📅 Working Days & Holiday Calendar (`calendar-view.php`)**:
  - Automatically calculates Indian working days (excluding Sundays, 2nd & 4th Saturdays).
  - Multiplies working days by configured daily travel cost.
  - Overlays daily spending tags directly on calendar dates.

- **📈 Deep Analytics (`analytics.php`)**:
  - 12-month historical trajectory of Income vs Expenses vs Net Savings.
  - Category distribution donut chart and payment method distribution bar chart.
  - List of top largest spending items.

- **🔮 12-Month Forecast (`forecast.php`)**:
  - Forward-looking cash balance projections.
  - Emergency fund runway calculator (months of expenses covered).
  - Goal milestones forecast.

- **📑 Reports & Statements (`reports.php`)**:
  - Detailed monthly accounting reports.
  - One-click print / PDF export stylesheet.
  - CSV export for spreadsheets and tax tracking.

- **💎 Net Worth Command Center (`net-worth.php`)**:
  - Aggregates liquid cash, bank balances, UPI accounts, goal reserves, and friend receivables against liabilities.
  - Calculates Debt-to-Asset ratio and net worth.

- **⚙️ Settings (`settings.php`)**:
  - Configure monthly salary, pay day, and daily commute cost.
  - Mobile recharge plan details and due date notifications.
  - Customize working days and holiday rules.
  - Update liquid account balances and manage password security.

- **🔌 RESTful APIs (`public/api/`)**:
  - Full suite of JSON endpoints for dashboard, expenses, income, friends, goals, EMI, calendar, analytics, and settings.

---

## 📁 Project Directory Structure

```
finance-app/
├── config/
│   ├── database.php        # Database PDO connection (supports Unix sockets & TCP)
│   └── app.php             # App constants & configuration
├── includes/
│   ├── auth.php            # Session management, authentication, login/register
│   ├── csrf.php            # CSRF token generation and validation
│   ├── functions.php       # Indian currency formatters (formatINR), validation, helpers
│   ├── calendar.php        # Working days & Indian holiday calculation engine
│   ├── finance.php         # Monthly financial engine, EMI math, health score, forecasting
│   ├── header.php          # Shared layout header, sidebar, navigation, theme toggle
│   └── footer.php          # Shared layout footer, modals, toasts, scripts
├── public/
│   ├── index.php           # Entrypoint redirector
│   ├── login.php           # User authentication login
│   ├── register.php        # User registration
│   ├── logout.php          # User logout
│   ├── dashboard.php       # Main financial command center
│   ├── expenses.php        # Expense tracker & recurring bills
│   ├── income.php          # Salary & additional income
│   ├── friends.php         # Friend loan tracking & settlement
│   ├── goals.php           # Wishlist & financial goals with progress
│   ├── emi.php             # EMI calculator & loan tracker
│   ├── calendar-view.php   # Indian working days calendar & travel cost
│   ├── analytics.php       # Financial analytics & category breakdown
│   ├── settings.php        # System configuration & account balances
│   ├── forecast.php        # 12-month forward cashflow projections
│   ├── reports.php         # Monthly statements, printing & CSV export
│   ├── net-worth.php       # Net worth calculation & asset breakdown
│   └── api/
│       ├── dashboard.php   # Dashboard data API
│       ├── expenses.php    # Expenses CRUD API
│       ├── income.php      # Income CRUD API
│       ├── friends.php     # Friends & loans API
│       ├── goals.php       # Goals & contributions API
│       ├── emi.php         # EMI calculation API
│       ├── calendar.php    # Calendar days & expenses API
│       ├── analytics.php   # Analytics chart data API
│       ├── settings.php    # Settings update API
│       └── monthly.php     # Monthly financial refresh API
├── assets/
│   ├── css/
│   │   └── style.css       # Complete dark/light design system with glassmorphism
│   └── js/
│       ├── app.js          # Sidebar, modals, toasts, theme toggle, CSRF fetch
│       ├── dashboard.js    # Dashboard chart and gauge initialization
│       ├── charts.js       # Chart.js helper configurations & INR tooltips
│       ├── three-bg.js     # Three.js 3D ambient particle background
│       └── animations.js   # GSAP staggers & number counter animations
├── database/
│   ├── schema.sql          # Complete MySQL database schema
│   └── seed.sql            # Demo account and initial financial dataset
└── README.md
```

---

## 🚀 Setup & Installation

### Prerequisites
- **PHP 8.0+** (with `pdo_mysql` extension)
- **MySQL / MariaDB** (or XAMPP / MAMP / WAMP)

### 1. Database Setup
Import the database schema and seed data into MySQL:

```bash
# Using MySQL CLI or phpMyAdmin:
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql
```

> **Default Demo Account:**
> - **Email**: `demo@myfinance.app`
> - **Password**: `demo123`

### 2. Configure Database Credentials
Edit `config/database.php` if your MySQL username or password differs from the defaults:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'myfinance');
define('DB_USER', 'root');
define('DB_PASS', '');
```
*(Unix sockets for XAMPP on macOS/Linux are automatically auto-detected)*

### 3. Run Locally

#### Option A: Using PHP Built-in Server (Recommended for development)
From the `finance-app/` directory:
```bash
php -S localhost:8000 -t public
```
Open your browser and navigate to:
**[http://localhost:8000](http://localhost:8000)**

#### Option B: Using XAMPP / Apache
Place the `finance-app` folder inside your `htdocs` folder:
```bash
# Example on macOS:
/Applications/XAMPP/xamppfiles/htdocs/finance-app

# Example on Windows:
C:\xampp\htdocs\finance-app
```
Access via **`http://localhost/finance-app/public`**.

---

## 🔒 Security Features
- **Session Protection**: Strict SameSite cookie flags, HTTPOnly cookies, session regeneration on login.
- **CSRF Token Guard**: Every POST action and AJAX request is protected by cryptographically random tokens.
- **SQL Injection Defense**: 100% prepared PDO statements with parameter binding throughout the application.
- **XSS Protection**: Complete HTML escaping (`e()` and `sanitize()`) on all user outputs.

---

## 📄 License
MIT License. Feel free to customize and expand for personal or organizational use.
