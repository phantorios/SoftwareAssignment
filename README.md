# ASML VESPA Software Assignment — Equipment Import System (Laravel)

This project imports SAP equipment export files (`EQUIPMENTS_*.txt`) into a MariaDB database using Laravel.  
It validates files before importing, prevents corrupt data from entering the database, and provides a web interface to search and view equipment records.

The import runs automatically on a schedule (console.php) and can also be run manually (php artisan equipment:parse).

---

# Technical Requirements

- PHP 8.4
- Composer
- Node.js 22 and npm
- MariaDB
- Git
- Linux or WSL (recommended).

---

# Installation

## 1. Clone repository

```bash
git clone <repository-url>
cd SoftwareAssignment

```

## 2. Install PHP Dependencies

```bash
composer install
```

## 3. Copy .env file

```bash
cp .env.example .env
```

## 4. Generate app key

```bash
php artisan key:generate
```

## 5. Configure database

```SQL
CREATE DATABASE software_assignment;
```

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=software_assignment
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## 6. Run the application

```bash
php artisan serve
```

open in browser

```bash
http://127.0.0.1:8000/equipments
```

Equipment File Import

Import folder

Place equipment files inside:
```code
storage/app/import/EQUIPMENTS_*.txt
```

After successful import

The file is automatically moved to:

```code
storage/app/import/processed/
```

Manual Import

To manually run the import:

```bash
php artisan equipment:parse
```

This command will:

1. Find the newest equipment file
2. Validate file structure
3. Import records into database
4. Move processed file to processed folder

Validation for corrupt/smaller files:
1. check if file exist or not
2. check if file contain header or not
3. check if file contain anything after header or not
4. check if file contains lines multiples of 3
5. check pattern for each lines (adhere to regex pattern)

If validation fails:

1. Import is aborted
2. Database remains unchanged
3. File is not moved

Scheduled Import

The import runs automatically every day at 04.55 am. This is configured in routes/console.php.

Running scheduler

```bash
php artisan schedule:work
```

Running tests

```bash
php artisan test
```
