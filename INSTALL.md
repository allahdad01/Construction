# Construction Company SaaS Platform - Installation Guide

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB 10.2+)
- Web server (Apache/Nginx)
- Composer (optional, for dependency management)

## Installation Steps

### 1. Clone or Download the Project

```bash
# If using git
git clone <repository-url>
cd construction-saas

# Or download and extract the ZIP file
```

### 2. Set Up the Database

1. Create a new MySQL database:
```sql
CREATE DATABASE construction_saas;
```

2. Import the database schema:
```bash
mysql -u your_username -p construction_saas < database/schema.sql
```

### 3. Configure Database Connection

Edit `config/database.php` and update the database credentials:

```php
private $host = 'localhost';
private $db_name = 'construction_saas';
private $username = 'your_username';
private $password = 'your_password';
```

### 4. Configure Web Server

#### Apache Configuration

Create a `.htaccess` file in the `public` directory:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/construction-saas/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 5. Set File Permissions

```bash
# Set proper permissions for uploads (if needed)
chmod 755 public/
chmod 644 config/*.php
```

### 6. Access the Application

1. Open your web browser
2. Navigate to `http://your-domain.com` or `http://localhost/construction-saas/public`
3. You will be redirected to the login page

### 7. Default Login Credentials

- **Username:** admin
- **Password:** password

**Important:** Change the default password immediately after first login!

## Features Overview

### Employee Management
- Add drivers and driver assistants
- Automatic salary calculation (30-day month system)
- Daily rate calculation: Monthly Salary ÷ 30
- Termination handling with pro-rated salary

### Machine Management
- Track construction equipment
- Machine status monitoring (available, in use, maintenance, retired)
- Machine assignment to projects

### Contract & Project Management
- Three contract types: Hourly, Daily, Monthly
- Working hours tracking
- Progress calculation and monitoring
- Revenue tracking

### Parking & Area Management
- Machine parking space rental
- Area rental for containers and equipment
- Pro-rated billing based on usage days
- 30-day month calculation system

### Financial Management
- Expense tracking and categorization
- Salary payment management
- Revenue calculation
- Financial reporting

## Calculation Methods

### Salary Calculation
```
Daily Rate = Monthly Salary ÷ 30
Final Salary = Daily Rate × Actual Working Days

Example:
- Monthly Salary: $15,000
- Daily Rate: $15,000 ÷ 30 = $500
- If terminated on 15th day: $500 × 15 = $7,500
```

### Parking/Rental Calculation
```
Daily Rate = Monthly Rate ÷ 30
Final Payment = Daily Rate × Actual Usage Days

Example:
- Monthly Rate: $15,000
- Daily Rate: $15,000 ÷ 30 = $500
- If used for 10 days: $500 × 10 = $5,000
```

### Contract Working Hours
- **Hourly:** Track actual hours worked
- **Daily:** 9 hours per day standard, accumulate until complete days
- **Monthly:** 30 days × 9 hours = 270 hours total requirement

## Security Considerations

1. **Change Default Password:** Immediately change the admin password
2. **Database Security:** Use strong database passwords
3. **HTTPS:** Enable SSL/TLS for production use
4. **File Permissions:** Ensure proper file permissions
5. **Regular Backups:** Set up automated database backups

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify database credentials in `config/database.php`
   - Ensure MySQL service is running
   - Check database exists

2. **404 Errors**
   - Verify `.htaccess` file exists in `public` directory
   - Check Apache mod_rewrite is enabled
   - Ensure web server points to `public` directory

3. **Permission Errors**
   - Check file permissions (755 for directories, 644 for files)
   - Ensure web server user has read access

4. **Session Issues**
   - Check PHP session configuration
   - Verify session directory is writable

### Log Files

Check your web server error logs for detailed error messages:
- Apache: `/var/log/apache2/error.log`
- Nginx: `/var/log/nginx/error.log`
- PHP: `/var/log/php_errors.log`

## Support

For technical support or feature requests, please contact the development team.

## License

This software is proprietary and confidential. Unauthorized copying, distribution, or use is strictly prohibited.