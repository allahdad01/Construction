# Construction Company Multi-Tenant SaaS Platform

A comprehensive SaaS platform for construction companies to manage employees, machines, contracts, parking, area rentals, and financial operations with multi-tenant architecture.

## 🏗️ **Platform Overview**

This multi-tenant SaaS platform allows construction companies to manage their operations efficiently while providing different user roles and access levels. The system supports company isolation, subscription management, and role-based access control.

## 🎯 **Key Features**

### **Multi-Tenant Architecture**
- **Company Isolation**: Each company has its own data and users
- **Subscription Management**: Different plans (Basic, Professional, Enterprise)
- **Trial Periods**: 14-day trial for new companies
- **Usage Limits**: Configurable limits per subscription plan

### **User Roles & Access**

#### **1. Super Admin**
- Manage all companies and subscriptions
- System-wide settings and configuration
- Subscription plan management
- Payment tracking and revenue analytics
- Company suspension/activation

#### **2. Company Admin**
- Manage company employees (drivers, driver assistants)
- Machine inventory management
- Contract and project management
- Parking space and area rental management
- Expense tracking and salary payments
- User management within company

#### **3. Employees (Drivers & Driver Assistants)**
- View salary information and payment history
- Track working days and leave days
- View attendance records
- Access to personal dashboard

#### **4. Renters (Parking, Area, Container)**
- View rental agreements and payment history
- Track payment status and amounts
- Access to personal dashboard

## 💰 **Salary & Payment Calculations**

### **Employee Salary System**
- **Daily Rate Calculation**: `Monthly Salary ÷ 30 days`
- **Working Days**: Track actual days worked vs. leave days
- **Leave Management**: Pause work days during leave periods
- **Pro-rated Salary**: Calculate based on actual working days

**Example:**
- Monthly Salary: $15,000
- Daily Rate: $15,000 ÷ 30 = $500
- If employee works 15 days: $500 × 15 = $7,500

### **Rental & Parking Calculations**
- **Daily Rate**: `Monthly Rate ÷ 30 days`
- **Pro-rated Billing**: Based on actual usage days
- **Payment Tracking**: Monitor paid vs. pending amounts

**Example:**
- Monthly Parking Rate: $15,000
- Daily Rate: $15,000 ÷ 30 = $500
- If used for 10 days: $500 × 10 = $5,000

## 🚧 **Contract Management**

### **Three Contract Types**

#### **1. Hourly Contracts**
- Track actual hours worked daily
- Real-time progress monitoring
- Flexible hour tracking

#### **2. Daily Contracts**
- Standard 9-hour working day
- Track deviations and make-up hours
- Accumulate until complete days
- Calculate based on complete days worked

#### **3. Monthly Contracts**
- Fixed monthly amount (e.g., $15,000)
- Required hours: 30 days × 9 hours = 270 hours
- Full payment upon completing required hours
- Progress tracking against target hours

## 🏢 **Company Management**

### **Subscription Plans**

| Plan | Price | Employees | Machines | Projects | Features |
|------|-------|-----------|----------|----------|----------|
| Basic | $99/month | 25 | 50 | 25 | Employee & Machine Management |
| Professional | $199/month | 100 | 200 | 100 | + Contracts & Parking |
| Enterprise | $399/month | 500 | 1000 | 500 | + Area Rental & API Access |

### **Company Features**
- **Trial Period**: 14 days for new companies
- **Usage Limits**: Based on subscription plan
- **Status Management**: Active, Trial, Suspended, Cancelled
- **Payment Tracking**: Monitor subscription payments

## 👥 **User Management**

### **Employee Types**
- **Drivers**: Vehicle operators with salary tracking
- **Driver Assistants**: Support staff with similar benefits
- **Leave Management**: Track leave days and work day pauses
- **Attendance Tracking**: Daily check-in/check-out system

### **Renter Types**
- **Parking Users**: Machine parking space renters
- **Area Renters**: Space rental for containers/equipment
- **Container Renters**: Container storage space users

## 📊 **Dashboard Features**

### **Super Admin Dashboard**
- Total companies and active subscriptions
- Monthly revenue and payment tracking
- Subscription plan statistics
- Recent companies and payments

### **Company Admin Dashboard**
- Active employees and machines
- Active contracts and monthly expenses
- Quick access to all management modules
- Company subscription status

### **Employee Dashboard**
- Monthly salary and daily rate
- Current month working/leave days
- Salary payment status
- Quick access to attendance and leave

### **Renter Dashboard**
- Active parking and area rentals
- Total and pending payments
- Rental history and status
- Payment tracking

## 🗄️ **Database Schema**

### **Core Tables**
- `companies`: Multi-tenant company data
- `users`: System users with role-based access
- `employees`: Company-specific employee records
- `machines`: Equipment inventory per company
- `contracts`: Project contracts with working hours
- `parking_spaces`: Parking space management
- `rental_areas`: Area rental management
- `expenses`: Company expense tracking
- `salary_payments`: Employee salary payments
- `user_payments`: Renter payment tracking

### **Key Features**
- **Company Isolation**: All tables include `company_id`
- **Role-based Access**: User roles determine permissions
- **Audit Trail**: Created/updated timestamps
- **Data Integrity**: Foreign key constraints

## 🔐 **Security Features**

### **Authentication & Authorization**
- Session-based authentication
- Role-based access control
- Company data isolation
- Password hashing with `password_verify()`

### **Data Protection**
- SQL injection prevention with prepared statements
- XSS protection with `htmlspecialchars()`
- Input validation and sanitization
- CSRF protection

## 🚀 **Installation**

### **Prerequisites**
- PHP 7.4+ with PDO extension
- MySQL 5.7+ or MariaDB 10.2+
- Web server (Apache/Nginx)
- Composer (for dependencies)

### **Quick Setup**

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd construction-saas
   ```

2. **Import database schema**
   ```bash
   mysql -u username -p construction_saas < database/schema.sql
   ```

3. **Configure database connection**
   Edit `config/database.php` with your database credentials

4. **Set file permissions**
   ```bash
   chmod 755 public/
   chmod 644 config/
   ```

5. **Access the application**
   Navigate to `http://your-domain.com/public/`

6. **Login with default credentials**
   - **Super Admin**: `superadmin` / `password`
   - **Company Admin**: Create via super admin panel

## 📁 **Project Structure**

```
construction-saas/
├── config/
│   ├── config.php          # Main configuration
│   └── database.php        # Database connection
├── database/
│   └── schema.sql          # Database schema
├── includes/
│   ├── header.php          # Common header
│   └── footer.php          # Common footer
├── public/
│   ├── dashboard.php       # User dashboard
│   ├── login.php          # Authentication
│   ├── logout.php         # Logout
│   ├── super-admin/       # Super admin modules
│   ├── employees/         # Employee management
│   ├── machines/          # Machine management
│   ├── contracts/         # Contract management
│   ├── parking/           # Parking management
│   ├── area-rentals/      # Area rental management
│   ├── expenses/          # Expense management
│   └── salary-payments/   # Salary payment management
├── README.md              # This file
├── INSTALL.md             # Installation guide
└── setup.php              # Setup script
```

## 🔧 **Configuration**

### **Environment Variables**
- Database connection settings
- Application constants
- Subscription plan limits
- Trial period duration

### **System Settings**
- Default working hours per day
- Leave days per year
- Salary calculation days
- Currency and timezone settings

## 📈 **Usage Examples**

### **Adding a New Company**
1. Super admin creates company via admin panel
2. System generates company admin user
3. Company admin logs in and sets up employees
4. Employees can access their dashboards

### **Employee Leave Management**
1. Employee goes on leave → work days pause
2. Employee returns → work days resume
3. System calculates pro-rated salary
4. Leave days deducted from total

### **Contract Progress Tracking**
1. Create contract with working hours requirement
2. Track daily hours worked
3. Monitor progress against target
4. Calculate completion percentage

## 🤝 **Support & Documentation**

### **Additional Documentation**
- `INSTALL.md`: Detailed installation guide
- `PROJECT_STRUCTURE.md`: Architecture overview
- `API_DOCUMENTATION.md`: API endpoints (if applicable)

### **Troubleshooting**
- Check database connection settings
- Verify file permissions
- Review error logs
- Ensure PHP extensions are enabled

## 📄 **License**

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆕 **Version History**

### **v2.0.0 - Multi-Tenant SaaS**
- ✅ Multi-tenant architecture
- ✅ Role-based access control
- ✅ Subscription management
- ✅ Company isolation
- ✅ User-specific dashboards
- ✅ Leave management system
- ✅ Payment tracking
- ✅ Super admin panel

### **v1.0.0 - Basic Platform**
- ✅ Employee management
- ✅ Machine tracking
- ✅ Contract management
- ✅ Basic reporting

---

**Built with ❤️ for Construction Companies**