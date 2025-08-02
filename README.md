# Construction SaaS Platform

A comprehensive multi-tenant SaaS platform for construction companies with support for multi-currency, multi-date formats, and multi-language functionality.

## 🌟 Features

- **Multi-Tenant Architecture**: Isolated company data and settings
- **Multi-Currency Support**: USD, AFN, EUR, GBP, CAD, AUD
- **Multi-Date Formats**: Gregorian, Shamsi, European, American, ISO
- **Multi-Language Support**: English, Dari, Pashto with RTL support
- **Employee Management**: Drivers, assistants, salary calculations
- **Machine Management**: Company-owned equipment tracking
- **Contract Management**: Hourly, daily, monthly contracts
- **Timesheet System**: Comprehensive work hour tracking
- **Parking Management**: Space rental and billing
- **Expense Tracking**: Company expense management
- **Payment Tracking**: Salary and contract payments
- **Role-Based Access**: Super admin, company admin, employees, renters

## 🚀 Quick Deploy to Render

### Option 1: One-Click Deploy (Recommended)

[![Deploy to Render](https://render.com/images/deploy-to-render/button.svg)](https://render.com/deploy/schema-render?repo=https://github.com/yourusername/construction-saas-platform)

### Option 2: Manual Deploy

1. **Fork this repository** to your GitHub account
2. **Connect to Render**:
   - Go to [Render Dashboard](https://dashboard.render.com)
   - Click "New +" → "Web Service"
   - Connect your GitHub repository
   - Select the repository

3. **Configure the service**:
   - **Name**: `construction-saas-platform`
   - **Environment**: `PHP`
   - **Build Command**: `composer install`
   - **Start Command**: `vendor/bin/heroku-php-apache2 public/`

4. **Add Environment Variables**:
   ```
   PHP_VERSION=8.1.0
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-app-name.onrender.com
   SESSION_SECRET=your-secret-key
   ENCRYPTION_KEY=your-encryption-key
   ```

5. **Create Database**:
   - Go to "New +" → "PostgreSQL" or "MySQL"
   - Name: `construction-saas-db`
   - Connect it to your web service

6. **Deploy**:
   - Click "Create Web Service"
   - Wait for deployment to complete
   - Visit your app URL

## 📋 Prerequisites

- **GitHub Account**: To host your code
- **Render Account**: For hosting and database
- **Domain (Optional)**: For custom domain

## 🛠️ Local Development

### Requirements
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.2+
- Composer
- Web server (Apache/Nginx)

### Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/yourusername/construction-saas-platform.git
   cd construction-saas-platform
   ```

2. **Install dependencies**:
   ```bash
   composer install
   ```

3. **Create database**:
   ```sql
   CREATE DATABASE construction_saas;
   ```

4. **Configure environment**:
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

5. **Run deployment script**:
   ```bash
   php deploy.php
   ```

6. **Start development server**:
   ```bash
   php -S localhost:8000 -t public
   ```

## 🔧 Configuration

### Environment Variables

Create a `.env` file in the root directory:

```env
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app-name.onrender.com

# Database
DB_HOST=your-db-host
DB_PORT=3306
DB_NAME=construction_saas
DB_USER=your-db-user
DB_PASSWORD=your-db-password

# Security
SESSION_SECRET=your-secret-key
ENCRYPTION_KEY=your-encryption-key
```

### Database Setup

The deployment script will automatically:
- Create all required tables
- Insert initial data
- Create super admin user
- Set up sample companies and data

## 👤 Default Login

After deployment, you can log in with:

**Super Admin**:
- Email: `superadmin@construction.com`
- Password: `admin123`

**Sample Company Admin**:
- Email: `admin@abc-construction.com`
- Password: `admin123`

## 📊 Sample Data

The system comes with sample data including:
- 4 sample companies
- Multiple employees and machines
- Sample contracts and timesheets
- Parking spaces and rentals
- Expenses and payments

## 🌍 Multi-Language Support

The platform supports:
- **English** (LTR)
- **Dari** (RTL) - Afghan Persian
- **Pashto** (RTL) - Afghan Pashto

Companies can set their preferred language in settings.

## 💰 Multi-Currency Support

Supported currencies:
- **USD** ($) - US Dollar
- **AFN** (؋) - Afghan Afghani
- **EUR** (€) - Euro
- **GBP** (£) - British Pound
- **CAD** (C$) - Canadian Dollar
- **AUD** (A$) - Australian Dollar

## 📅 Multi-Date Format Support

Supported date formats:
- **Gregorian** (YYYY-MM-DD)
- **Shamsi** (YYYY/MM/DD) - Persian calendar
- **European** (DD/MM/YYYY)
- **American** (MM/DD/YYYY)
- **ISO** (YYYY-MM-DD)

## 🔒 Security Features

- **Multi-tenant isolation**: Company data is completely separated
- **Role-based access control**: Different permissions for different user types
- **SQL injection protection**: Prepared statements throughout
- **XSS protection**: Input sanitization and output escaping
- **CSRF protection**: Session-based security
- **Password hashing**: Secure password storage

## 📈 Business Benefits

- **International Operations**: Multi-currency and multi-language support
- **Compliance**: Local currency and date format requirements
- **User Experience**: Familiar interfaces in local languages
- **Scalability**: Multi-tenant architecture supports unlimited companies
- **Cost Efficiency**: Shared infrastructure with isolated data

## 🚀 Deployment Checklist

Before deploying to production:

- [ ] Update environment variables
- [ ] Set secure session and encryption keys
- [ ] Configure database connection
- [ ] Test deployment script
- [ ] Verify all features work
- [ ] Set up monitoring
- [ ] Configure backups
- [ ] Test user roles and permissions

## 🛠️ Troubleshooting

### Common Issues

**Database Connection Failed**:
- Check database credentials in environment variables
- Verify database is accessible from your deployment
- Check firewall settings

**Deployment Fails**:
- Check PHP version compatibility
- Verify all required extensions are installed
- Check build logs for specific errors

**Language/Currency Not Working**:
- Verify database tables are created
- Check if sample data is inserted
- Verify company settings are configured

### Debug Information

Check the deployment logs in Render dashboard for detailed error messages.

## 📚 Documentation

- [Multi-Currency & Multi-Date System](MULTI_CURRENCY_DATE_SYSTEM.md)
- [Multi-Language System](MULTI_LANGUAGE_SYSTEM.md)
- [Timesheet System](TIMESHEET_SYSTEM.md)

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For support and questions:
- Create an issue on GitHub
- Check the documentation files
- Review the troubleshooting section

---

**Built with ❤️ for construction companies worldwide**