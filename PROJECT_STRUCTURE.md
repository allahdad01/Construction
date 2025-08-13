# Construction Company SaaS Platform - Project Structure

## Directory Structure

```
construction-saas/
├── config/                     # Configuration files
│   ├── config.php             # Main application configuration
│   └── database.php           # Database connection settings
├── database/                   # Database files
│   └── schema.sql             # Complete database schema
├── includes/                   # Shared include files
│   ├── header.php             # Common header with navigation
│   └── footer.php             # Common footer with scripts
├── public/                     # Public web directory (Document Root)
│   ├── index.php              # Main dashboard
│   ├── login.php              # Login page
│   ├── logout.php             # Logout handler
│   ├── .htaccess              # Apache rewrite rules
│   ├── employees/             # Employee management
│   │   ├── index.php          # Employee listing
│   │   ├── add.php            # Add employee
│   │   ├── edit.php           # Edit employee
│   │   ├── view.php           # View employee details
│   │   └── terminate.php      # Terminate employee
│   ├── machines/              # Machine management
│   │   ├── index.php          # Machine listing
│   │   ├── add.php            # Add machine
│   │   ├── edit.php           # Edit machine
│   │   ├── view.php           # View machine details
│   │   └── retire.php         # Retire machine
│   ├── projects/              # Project management
│   │   ├── index.php          # Project listing
│   │   ├── add.php            # Add project
│   │   ├── edit.php           # Edit project
│   │   └── view.php           # View project details
│   ├── contracts/             # Contract management
│   │   ├── index.php          # Contract listing
│   │   ├── add.php            # Add contract
│   │   ├── edit.php           # Edit contract
│   │   ├── view.php           # View contract details
│   │   ├── working-hours.php  # Working hours tracking
│   │   └── complete.php       # Complete contract
│   ├── parking/               # Parking space management
│   │   ├── index.php          # Parking space listing
│   │   ├── add.php            # Add parking space
│   │   ├── edit.php           # Edit parking space
│   │   ├── view.php           # View parking space
│   │   ├── rentals.php        # Parking rentals
│   │   └── add-rental.php     # Add parking rental
│   ├── rental-areas/          # Area rental management
│   │   ├── index.php          # Area listing
│   │   ├── add.php            # Add rental area
│   │   ├── edit.php           # Edit rental area
│   │   ├── view.php           # View rental area
│   │   ├── rentals.php        # Area rentals
│   │   └── add-rental.php     # Add area rental
│   ├── expenses/              # Expense management
│   │   ├── index.php          # Expense listing
│   │   ├── add.php            # Add expense
│   │   ├── edit.php           # Edit expense
│   │   ├── view.php           # View expense details
│   │   └── delete.php         # Delete expense
│   ├── salary-payments/       # Salary payment management
│   │   ├── index.php          # Salary payment listing
│   │   ├── add.php            # Add salary payment
│   │   ├── edit.php           # Edit salary payment
│   │   ├── view.php           # View salary payment
│   │   └── calculate.php      # Salary calculation
│   └── reports/               # Reporting system
│       ├── index.php          # Reports dashboard
│       ├── financial.php      # Financial reports
│       ├── employee.php       # Employee reports
│       ├── machine.php        # Machine reports
│       └── contract.php       # Contract reports
├── assets/                     # Static assets (CSS, JS, images)
│   ├── css/                   # Stylesheets
│   ├── js/                    # JavaScript files
│   └── images/                # Images and icons
├── uploads/                    # File uploads directory
├── logs/                       # Application logs
├── vendor/                     # Third-party dependencies (if using Composer)
├── tests/                      # Unit tests (if implemented)
├── docs/                       # Documentation
├── README.md                   # Project overview
├── INSTALL.md                  # Installation guide
├── PROJECT_STRUCTURE.md        # This file
├── setup.php                   # Setup script
└── .gitignore                  # Git ignore file
```

## Key Features by Module

### 1. Employee Management (`public/employees/`)
- **Add/Edit Employees**: Drivers and driver assistants
- **Salary Calculation**: 30-day month system with daily rate calculation
- **Termination Handling**: Pro-rated salary calculation
- **Status Management**: Active, inactive, terminated

### 2. Machine Management (`public/machines/`)
- **Machine Inventory**: Track construction equipment
- **Status Tracking**: Available, in use, maintenance, retired
- **Machine Details**: Type, model, capacity, fuel type
- **Value Tracking**: Purchase cost and current value

### 3. Project Management (`public/projects/`)
- **Project Creation**: Client information and project details
- **Status Tracking**: Planning, active, completed, cancelled
- **Budget Management**: Total budget tracking
- **Timeline Management**: Start and end dates

### 4. Contract Management (`public/contracts/`)
- **Contract Types**: Hourly, daily, monthly billing
- **Working Hours Tracking**: Daily hours recording
- **Progress Calculation**: Automatic completion percentage
- **Revenue Tracking**: Total amount and payments received

### 5. Parking Management (`public/parking/`)
- **Space Management**: Machine parking spaces
- **Rental System**: Client machine parking
- **Pro-rated Billing**: 30-day month calculation
- **Status Tracking**: Available, occupied, maintenance

### 6. Area Rental (`public/rental-areas/`)
- **Area Types**: Storage, workshop, office, other
- **Rental Management**: Client area rentals
- **Pro-rated Billing**: Daily rate calculation
- **Purpose Tracking**: Rental purpose and usage

### 7. Expense Management (`public/expenses/`)
- **Expense Categories**: Fuel, maintenance, salary, rent, utilities, insurance, other
- **Payment Methods**: Cash, bank transfer, check, credit card
- **Date Tracking**: Expense date and payment tracking
- **Reference Numbers**: Payment reference tracking

### 8. Salary Payments (`public/salary-payments/`)
- **Monthly Payments**: Employee salary disbursement
- **Working Days**: Actual working days calculation
- **Payment Methods**: Cash, bank transfer, check
- **Status Tracking**: Pending, paid, cancelled

### 9. Reports (`public/reports/`)
- **Financial Reports**: Revenue, expenses, profit/loss
- **Employee Reports**: Salary, working days, performance
- **Machine Reports**: Utilization, maintenance, value
- **Contract Reports**: Progress, revenue, completion

## Database Schema Overview

### Core Tables
1. **employees** - Driver and assistant information
2. **machines** - Equipment inventory
3. **projects** - Project information
4. **contracts** - Contract details with working hours
5. **working_hours** - Daily hours tracking
6. **parking_spaces** - Parking area management
7. **parking_rentals** - Machine parking rentals
8. **rental_areas** - Area rental spaces
9. **area_rentals** - Area rental agreements
10. **expenses** - Company expenses
11. **salary_payments** - Employee salary records
12. **users** - System user accounts

### Key Relationships
- Contracts link to Projects and Machines
- Working Hours link to Contracts, Machines, and Employees
- Parking Rentals link to Parking Spaces
- Area Rentals link to Rental Areas
- Salary Payments link to Employees

## Calculation Methods

### Salary Calculation
```
Daily Rate = Monthly Salary ÷ 30
Final Salary = Daily Rate × Actual Working Days
```

### Parking/Rental Calculation
```
Daily Rate = Monthly Rate ÷ 30
Final Payment = Daily Rate × Actual Usage Days
```

### Contract Working Hours
- **Hourly**: Track actual hours worked
- **Daily**: 9 hours per day standard
- **Monthly**: 30 days × 9 hours = 270 hours total

## Security Features

1. **Authentication**: Session-based login system
2. **Authorization**: Role-based access control
3. **Input Validation**: Form data sanitization
4. **SQL Injection Prevention**: Prepared statements
5. **XSS Protection**: Output escaping
6. **CSRF Protection**: Form tokens

## Configuration Files

### `config/config.php`
- Application settings
- Helper functions
- Authentication functions
- Currency and date formatting

### `config/database.php`
- Database connection settings
- PDO configuration
- Error handling

## File Permissions

- **Directories**: 755 (rwxr-xr-x)
- **Files**: 644 (rw-r--r--)
- **Uploads**: 755 (rwxr-xr-x)
- **Logs**: 755 (rwxr-xr-x)

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Performance Considerations

1. **Database Indexing**: Optimized queries with proper indexes
2. **Pagination**: Large dataset handling
3. **Caching**: Session-based caching
4. **Asset Optimization**: Minified CSS/JS
5. **Image Optimization**: Compressed images

## Backup Strategy

1. **Database Backups**: Daily automated backups
2. **File Backups**: Weekly file system backups
3. **Configuration Backups**: Version-controlled configs
4. **Log Retention**: 30-day log retention

## Monitoring

1. **Error Logging**: PHP error logs
2. **Access Logging**: Web server access logs
3. **Performance Monitoring**: Response time tracking
4. **Security Monitoring**: Failed login attempts

This structure provides a comprehensive, scalable solution for construction company management with all the requested features implemented according to the specified calculation methods.