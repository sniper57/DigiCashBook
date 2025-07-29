I created this simple Cash Management App to quickly monitor your IN and OUT expenses.
Created in Simple PHP Codes and MYSQL.
PSP: I did not use any framework since this is part of my learning drive towards PHP coding, so don't judge :)

You can freely Fork my work. its Free!

<img width="1365" height="594" alt="image" src="https://github.com/user-attachments/assets/c8dd9d92-c7a9-4d65-a4d9-417235d09353" />

# 📱 DigiCashBook

**DigiCashBook** is a modern, mobile-first, multi-user bookkeeping web application designed for individuals, entrepreneurs, and small businesses who want a fast, simple way to track cash flow, transactions, and financial reports. Inspired by leading digital cashbook tools—but fully customizable, open-source, and PHP/MySQL powered.

---

## ✨ Key Features

- **Responsive Design:** Works beautifully on desktop, tablet, and mobile
- **User Management:** Multi-user with role-based access (Admin, Manager, User)
- **Books:** Create and manage multiple cashbooks, each with their own transactions
- **Bulk Import/Export:** Excel-based import and export for rapid data migration
- **Transaction Attachments:** Upload receipts/images/PDFs (with image resize/optimization)
- **Book Sharing:** Securely share books with other users (viewer/editor roles)
- **Reports & Dashboard:** Modern dashboard with cashflow, expense charts, and summaries
- **Audit Logs:** Track all key actions for compliance and troubleshooting
- **Secure Auth:** Registration, login, password reset with email verification (PHPMailer)
- **Search & Filters:** Instantly search and filter books, transactions, ledgers

---

## 📸 Screenshots

### Dashboard (Mobile & Desktop)
<img width="1365" height="594" alt="image" src="https://github.com/user-attachments/assets/c8dd9d92-c7a9-4d65-a4d9-417235d09353" />
<img width="233" height="477" alt="image" src="https://github.com/user-attachments/assets/1785187f-f925-4ebd-b3ff-b7f5058a6a4b" />


### Book List (Mobile Card Style)
<img width="1364" height="595" alt="image" src="https://github.com/user-attachments/assets/7c47c3f7-e262-4ad0-a870-8424cb1de383" />
<img width="239" height="481" alt="image" src="https://github.com/user-attachments/assets/3a6f8558-f2fa-4fd8-b642-4bc6419682e2" />


### Transaction Entry & Attachments
<img width="1352" height="600" alt="image" src="https://github.com/user-attachments/assets/a0b385c6-e471-4b6d-8a4a-67fbd7d94cd7" />
<img width="1365" height="598" alt="image" src="https://github.com/user-attachments/assets/43cd10f8-d156-480b-b221-c30500eef76c" />


### Import/Export via Excel
<img width="1363" height="593" alt="image" src="https://github.com/user-attachments/assets/11d65e52-1cb0-4758-9161-d79a9354b815" />


---

## 🚀 Getting Started

### 1. Requirements

- PHP 7.4+ (recommended PHP 8.1+)
- MySQL 5.7+ / MariaDB
- Composer (for dependency management)
- GD/Imagick, Zip, and OpenSSL PHP extensions
- Web server (Apache, nginx, or XAMPP/LAMP)

### 2. Installation

**A. Clone the repository**

```bash
git clone https://github.com/sniper57/digicashbook.git
cd digicashbook
```

**B. Install dependencies via Composer**

```bash
composer install
```

**C. Setup your database**
- Import ```schema.sql``` from the repo (contains all tables)
- Edit ```db_connect.php``` with your MySQL credentials

**D. Configure Email (PHPMailer)**
- Update SMTP settings in ```mail_config.php``` or ```includes/mail.php```

**E. Set upload folders’ permissions**
- Make sure ```/uploads``` is writable by the web server

**F. Access in your browser**
- Go to ```http://localhost/digicashbook``` (or your deployment URL)

## 📖 Usage
- **Books:** Add cashbooks for different businesses or wallets
- **Transactions:** Add cash-in/out, attach receipts, and categorize
- **Ledger:** See detailed logs and running balances
- **Reports:** View analytics and download/export data
- **Import/Export:** Use Excel template for fast migration or backups
- **User Management:** Admins can add/edit users, assign roles, or reset passwords
- **Book Sharing:** Use the "Share" button to collaborate securely


## 🔒 Security Best Practices
- Passwords are stored hashed (bcrypt)
- All file uploads are sanitized and restricted by type and size
- Audit logs track all admin/user actions
- Roles control access to sensitive pages

## 📱 Mobile-First UI
### All key pages are designed for single-handed use on smartphones:

- Floating add buttons
- Card-based data display
- Touch-friendly filters and modals

## 🛠️ Customization & Extensibility
- All UI built with Bootstrap 4/5 + custom CSS
- Modular PHP codebase for easy modification
- Add your own modules: eg. budgeting, invoicing, recurring transactions, etc.

## 🙌 Credits
- **PHPMailer** for robust email support
- **PhpSpreadsheet** for Excel import/export
- **Intervention Image** for file/image processing
- **Font Awesome** for icons

## 📝 License
Open-source under the MIT License.

## 🤝 Contributing
- Pull requests and suggestions welcome!
- Please submit issues and feature requests on the GitHub Issues page.

### 📧 Contact
- Created by **Magis Technologies**
- Questions or need support? Open an issue or email **magis.technologies.inc@gmail.com**

