# Construction SaaS Platform

A comprehensive multi-tenant SaaS platform for construction companies with employee management, machine tracking, contract management, parking rentals, and more.

## 🚀 Features

### Multi-Tenant Architecture
- **Company Isolation**: Each company manages their own data
- **Role-Based Access**: Super Admin, Company Admin, Drivers, Assistants, etc.
- **Subscription Management**: Different plans with feature limits

### Employee Management
- **Driver & Assistant Management**: Complete employee profiles
- **Salary Calculation**: Daily rate = Monthly salary / 30
- **Leave Management**: Track leave days and working days
- **Attendance Tracking**: Daily check-in/check-out records

### Machine Management
- **Equipment Tracking**: Complete machine inventory
- **Machine Types**: Various construction equipment
- **Status Tracking**: Available, in-use, maintenance

### Contract Management
- **Contract Types**: Hourly, Daily, Monthly contracts
- **Work Hours Tracking**: Daily work hours recording
- **Timesheet System**: Comprehensive timesheet with earnings calculation
- **Payment Tracking**: Track payments and remaining amounts

### Parking & Area Rentals
- **Parking Spaces**: Manage parking space inventory
- **Area Rentals**: Storage and workspace rentals
- **Pro-rated Billing**: Based on 30-day month calculation
- **Payment Tracking**: Track rental payments

### Multi-Currency Support
- **Multiple Currencies**: USD, AFN, EUR, GBP, CAD, AUD
- **Exchange Rates**: Real-time currency conversion
- **Company Settings**: Each company can set default currency

### Multi-Date Format Support
- **Date Formats**: Gregorian, Shamsi, European, American, ISO
- **Company Settings**: Each company can set default date format
- **Flexible Display**: Dates displayed according to company preference

### Multi-Language Support
- **Languages**: English, Dari, Pashto
- **RTL Support**: Right-to-left language support
- **Dynamic Translations**: All text is translatable
- **Language Management**: Add new languages via admin panel

### Expense Management
- **Expense Categories**: Fuel, maintenance, salary, rent, utilities, etc.
- **Payment Methods**: Cash, bank transfer, check, credit card
- **Expense Tracking**: Complete expense history

### Reports & Analytics
- **Dashboard**: Real-time statistics and charts
- **Financial Reports**: Revenue, expenses, payments
- **Employee Reports**: Attendance, salary, performance
- **Contract Reports**: Work hours, earnings, progress

## 📋 Prerequisites

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher (or MariaDB 10.2+)
- **Web Server**: Apache or Nginx
- **Extensions**: PDO, PDO_MySQL, JSON, MBString

## 🛠️ Installation

### 1. Clone the Repository
```bash
git clone https://github.com/allahdad01/Construction.git
cd Construction
```

### 2. Database Setup
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE construction_saas;"

# Import schema
mysql -u root -p construction_saas < database/schema.sql

# Import sample data
mysql -u root -p construction_saas < database/sample_data.sql
```

### 3. Configuration
```bash
# Copy configuration file
cp config/config.example.php config/config.php

# Edit database settings
nano config/config.php
```

### 4. Web Server Configuration

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

#### Nginx
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 5. Permissions
```bash
# Set upload directory permissions
chmod -R 755 public/uploads
chown -R www-data:www-data public/uploads
```

### 6. Run Deployment Script
```bash
# Access via browser
http://your-domain.com/deploy.php

# Or via command line
php deploy.php
```

## 🔧 Configuration

### Environment Variables
```php
// Database Configuration
DB_HOST=localhost
DB_NAME=construction_saas
DB_USER=root
DB_PASSWORD=your_password
DB_PORT=3306

// Application Configuration
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
SESSION_SECRET=your-secret-key
ENCRYPTION_KEY=your-encryption-key
```

### Company Settings
Each company can configure:
- **Default Currency**: USD, AFN, EUR, GBP, CAD, AUD
- **Date Format**: Gregorian, Shamsi, European, American, ISO
- **Default Language**: English, Dari, Pashto
- **Timezone**: UTC or local timezone

## 👤 Default Login

### Super Admin
- **Email**: `superadmin@construction.com`
- **Password**: `admin123`

### Sample Company Admin
- **Email**: `admin@abc-construction.com`
- **Password**: `admin123`

## 📁 Project Structure

```
construction-saas/
├── config/
│   ├── config.php          # Main configuration
│   └── database.php        # Database connection
├── database/
│   ├── schema.sql          # Database schema
│   └── sample_data.sql     # Sample data
├── includes/
│   ├── header.php          # Common header
│   └── footer.php          # Common footer
├── public/
│   ├── index.php           # Main entry point
│   ├── login.php           # Login page
│   ├── dashboard.php       # Dashboard
│   ├── employees/          # Employee management
│   ├── machines/           # Machine management
│   ├── contracts/          # Contract management
│   ├── parking/            # Parking management
│   ├── area-rentals/       # Area rental management
│   ├── expenses/           # Expense management
│   ├── reports/            # Reports and analytics
│   ├── settings.php        # Company settings
│   └── super-admin/        # Super admin panel
├── deploy.php              # Deployment script
└── README.md               # This file
```

## 🔐 Security Features

- **Password Hashing**: Bcrypt password hashing
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: HTML escaping
- **CSRF Protection**: Token-based protection
- **Session Security**: Secure session handling
- **Input Validation**: Server-side validation
- **File Upload Security**: Restricted file types

## 🌐 Multi-Language Support

### Supported Languages
- **English**: Default language
- **Dari**: Afghan Persian (RTL)
- **Pashto**: Afghan Pashto (RTL)

### Adding New Languages
1. Go to Super Admin → Languages
2. Add new language with code, name, and direction
3. Add translations for all keys
4. Set as default for companies

### RTL Support
- **Right-to-Left**: Full RTL layout support
- **CSS Classes**: Automatic RTL styling
- **Text Direction**: Dynamic text direction

## 💰 Multi-Currency System

### Supported Currencies
- **USD**: US Dollar ($)
- **AFN**: Afghan Afghani (؋)
- **EUR**: Euro (€)
- **GBP**: British Pound (£)
- **CAD**: Canadian Dollar (C$)
- **AUD**: Australian Dollar (A$)

### Currency Features
- **Exchange Rates**: Real-time conversion
- **Company Default**: Each company sets default
- **Display Format**: Proper currency formatting
- **Calculations**: Accurate financial calculations

## 📅 Multi-Date Format System

### Supported Formats
- **Gregorian**: YYYY-MM-DD
- **Shamsi**: YYYY/MM/DD (Persian calendar)
- **European**: DD/MM/YYYY
- **American**: MM/DD/YYYY
- **ISO**: YYYY-MM-DD

### Date Features
- **Company Default**: Each company sets format
- **Display Format**: Consistent date display
- **Input Validation**: Proper date validation
- **Calendar Support**: Multiple calendar systems

## 📊 Timesheet System

### Features
- **Daily Work Hours**: Track hours worked per day
- **Earnings Calculation**: Automatic earnings calculation
- **Payment Tracking**: Track payments and remaining amounts
- **Progress Charts**: Visual progress indicators
- **Export Options**: PDF and Excel export

### Calculations
- **Hourly Rate**: Rate per hour for hourly contracts
- **Daily Rate**: Rate per day for daily contracts
- **Monthly Rate**: Rate per month for monthly contracts
- **Total Earnings**: Sum of all work hours × rate
- **Remaining Amount**: Total earnings - payments made

## 🚀 Deployment

### Traditional Hosting
1. Upload files to web server
2. Create MySQL database
3. Import schema and sample data
4. Configure database connection
5. Set file permissions
6. Run deployment script

### Shared Hosting
1. Upload via FTP/cPanel
2. Create database via phpMyAdmin
3. Import SQL files
4. Update configuration
5. Set permissions
6. Access deploy.php

### VPS/Dedicated Server
1. Install LAMP stack
2. Clone repository
3. Configure virtual host
4. Set up database
5. Configure SSL certificate
6. Run deployment

## 🔧 Troubleshooting

### Common Issues

#### Database Connection Error
```bash
# Check MySQL service
sudo systemctl status mysql

# Check database credentials
mysql -u username -p database_name

# Test connection
php -r "require 'config/database.php'; \$db = new Database(); echo \$db->testConnection() ? 'Connected' : 'Failed';"
```

#### Permission Issues
```bash
# Set correct permissions
chmod -R 755 public/uploads
chown -R www-data:www-data public/uploads

# Check web server user
ps aux | grep apache
```

#### Session Issues
```bash
# Check session directory
ls -la /tmp

# Set session permissions
chmod 755 /tmp
chown www-data:www-data /tmp
```

### Error Logs
```bash
# Apache error log
tail -f /var/log/apache2/error.log

# PHP error log
tail -f /var/log/php/error.log

# Application log
tail -f logs/app.log
```

## 📈 Performance Optimization

### Database Optimization
- **Indexes**: Proper database indexing
- **Queries**: Optimized SQL queries
- **Connection Pooling**: Efficient database connections
- **Caching**: Query result caching

### Application Optimization
- **Code Caching**: OPcache for PHP
- **Asset Compression**: Minified CSS/JS
- **Image Optimization**: Compressed images
- **CDN**: Content delivery network

### Server Optimization
- **Gzip Compression**: Enable compression
- **Browser Caching**: Set cache headers
- **SSL/TLS**: Secure connections
- **Load Balancing**: Multiple servers

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

For support and questions:
- **Email**: support@construction-saas.com
- **Documentation**: [Wiki](https://github.com/allahdad01/Construction/wiki)
- **Issues**: [GitHub Issues](https://github.com/allahdad01/Construction/issues)

## 🎯 Roadmap

### Upcoming Features
- **Mobile App**: iOS and Android apps
- **API Access**: RESTful API for integrations
- **Advanced Analytics**: Business intelligence
- **Multi-location**: Multiple office support
- **Inventory Management**: Material tracking
- **Project Management**: Advanced project features
- **Client Portal**: Customer access portal
- **Automated Billing**: Recurring payments
- **SMS Notifications**: Text message alerts
- **Email Templates**: Customizable emails

### Version History
- **v1.0.0**: Initial release with core features
- **v1.1.0**: Multi-currency and multi-date support
- **v1.2.0**: Multi-language support with RTL
- **v1.3.0**: Comprehensive timesheet system
- **v1.4.0**: Enhanced reporting and analytics

---

**Built with ❤️ for Construction Companies Worldwide**