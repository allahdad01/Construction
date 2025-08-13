# Contract Timesheet System

## Overview

The Contract Timesheet System is a comprehensive solution for tracking work hours, calculating earnings, managing payments, and monitoring contract progress for construction companies. It provides real-time calculations, visual progress tracking, and detailed financial reporting.

## Features

### 📊 **Comprehensive Timesheet Display**
- **Daily Work Hours**: Track hours worked per day by each employee
- **Real-time Calculations**: Automatic calculation of earnings based on contract type
- **Progress Tracking**: Visual progress bars showing contract completion
- **Monthly Charts**: Interactive charts showing hours and revenue trends

### 💰 **Financial Management**
- **Earnings Calculation**: Automatic calculation based on contract type (Hourly/Daily/Monthly)
- **Payment Tracking**: Record and track all payments made against contracts
- **Remaining Amount**: Real-time calculation of outstanding amounts
- **Payment History**: Complete payment history with status tracking

### 📈 **Contract Types Support**

#### **Hourly Contracts**
- Rate: $X per hour
- Calculation: Hours worked × Hourly rate
- Example: 8 hours × $150/hr = $1,200

#### **Daily Contracts**
- Rate: $X per day
- Calculation: Hours worked × (Daily rate ÷ Working hours per day)
- Example: 9 hours × ($1,200 ÷ 9) = $1,200

#### **Monthly Contracts**
- Rate: $X per month
- Calculation: Hours worked × (Monthly rate ÷ Total required hours)
- Example: 270 hours × ($15,000 ÷ 270) = $15,000

### 🔧 **User Roles & Permissions**

#### **Super Admin**
- View all contracts across all companies
- Access to all timesheet data
- System-wide reporting

#### **Company Admin**
- Manage contracts for their company
- Add/edit work hours and payments
- View comprehensive timesheets
- Generate reports

#### **Driver/Driver Assistant**
- View their own work hours
- Add work hours for contracts they're assigned to
- View payment status and remaining amounts

## System Components

### 1. **Timesheet Page** (`/public/contracts/timesheet.php`)

#### **Contract Information Section**
- Contract details (code, project, machine, employee)
- Contract type and rate information
- Working hours per day and total required hours

#### **Statistics Cards**
- **Total Hours Worked**: Sum of all hours logged
- **Total Amount Earned**: Calculated earnings based on contract type
- **Amount Paid**: Sum of all completed payments
- **Remaining Amount**: Outstanding balance to be paid

#### **Progress Bar**
- Visual representation of contract completion
- Shows percentage of required hours completed
- Current month statistics

#### **Daily Timesheet Table**
- Date and day of week
- Employee information
- Hours worked with rate calculation
- Daily amount earned
- Notes and actions

#### **Payments Section**
- Payment history with dates and amounts
- Payment method and status
- Reference numbers and notes
- Total paid amount

#### **Monthly Chart**
- Interactive chart showing hours and revenue by month
- Dual-axis chart with hours (bars) and revenue (line)
- Responsive design with hover tooltips

### 2. **Add Work Hours** (`/public/contracts/add-hours.php`)

#### **Features**
- Date selection (cannot exceed current date)
- Employee selection (active employees only)
- Hours input (0.5 to 24 hours)
- Real-time amount calculation
- Notes field for work details
- Validation to prevent duplicate entries

#### **Validation Rules**
- Maximum 24 hours per day
- Minimum 0.5 hours per entry
- One entry per employee per day
- Date cannot be in the future

### 3. **Add Payment** (`/public/contracts/add-payment.php`)

#### **Features**
- Payment date selection
- Amount input with maximum validation
- Payment method selection
- Reference number tracking
- Status management (completed/pending/failed)
- Notes for payment details

#### **Validation Rules**
- Payment cannot exceed remaining amount
- Amount must be greater than 0
- Payment method is required
- Automatic payment code generation

## Database Schema

### **Working Hours Table**
```sql
CREATE TABLE working_hours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    contract_id INT NOT NULL,
    machine_id INT NOT NULL,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    hours_worked DECIMAL(4,1) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (contract_id) REFERENCES contracts(id),
    FOREIGN KEY (machine_id) REFERENCES machines(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id)
);
```

### **Contract Payments Table**
```sql
CREATE TABLE contract_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    contract_id INT NOT NULL,
    payment_code VARCHAR(20) NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('bank_transfer', 'credit_card', 'cash', 'check', 'paypal') NOT NULL,
    reference_number VARCHAR(100),
    status ENUM('completed', 'pending', 'failed') DEFAULT 'completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (contract_id) REFERENCES contracts(id)
);
```

## Calculations

### **Hourly Contract**
```
Daily Amount = Hours Worked × Hourly Rate
Total Earned = Sum of all daily amounts
```

### **Daily Contract**
```
Hourly Rate = Daily Rate ÷ Working Hours per Day
Daily Amount = Hours Worked × Hourly Rate
Total Earned = Sum of all daily amounts
```

### **Monthly Contract**
```
Hourly Rate = Monthly Rate ÷ Total Required Hours
Daily Amount = Hours Worked × Hourly Rate
Total Earned = Sum of all daily amounts
```

### **Payment Tracking**
```
Remaining Amount = Total Earned - Total Paid
Progress Percentage = (Total Hours Worked ÷ Total Required Hours) × 100
```

## Usage Examples

### **Scenario 1: Hourly Contract**
- Contract: $150/hour
- Day 1: 8 hours = $1,200
- Day 2: 7.5 hours = $1,125
- Total: 15.5 hours = $2,325 earned

### **Scenario 2: Daily Contract**
- Contract: $1,200/day (9 hours standard)
- Day 1: 9 hours = $1,200
- Day 2: 8 hours = $1,066.67
- Total: 17 hours = $2,266.67 earned

### **Scenario 3: Monthly Contract**
- Contract: $15,000/month (270 hours required)
- Day 1: 9 hours = $500
- Day 2: 8 hours = $444.44
- Total: 17 hours = $944.44 earned

## Security Features

### **Multi-tenant Isolation**
- All data filtered by `company_id`
- Users can only access their company's data
- Role-based access control

### **Data Validation**
- Input sanitization and validation
- SQL injection prevention with prepared statements
- XSS protection with `htmlspecialchars`

### **Access Control**
- Authentication required for all pages
- Role-based permissions
- Company-specific data isolation

## Reporting Features

### **Real-time Statistics**
- Current month hours and earnings
- Contract progress percentages
- Payment status tracking
- Remaining amount calculations

### **Visual Analytics**
- Monthly hours and revenue charts
- Progress bars for contract completion
- Color-coded status indicators
- Interactive data tables

### **Export Capabilities**
- Detailed timesheet reports
- Payment history reports
- Contract summary reports
- Financial analysis reports

## Integration Points

### **Employee Management**
- Links to employee profiles
- Attendance tracking integration
- Salary calculation integration

### **Machine Management**
- Machine utilization tracking
- Maintenance scheduling
- Cost allocation

### **Project Management**
- Project progress tracking
- Budget monitoring
- Timeline management

## Best Practices

### **Data Entry**
- Enter hours daily for accurate tracking
- Include detailed notes for work performed
- Verify calculations before submission
- Use consistent time formats

### **Payment Management**
- Record payments promptly
- Include reference numbers for tracking
- Update payment status regularly
- Maintain detailed payment notes

### **Reporting**
- Review timesheets weekly
- Monitor contract progress regularly
- Track payment status monthly
- Generate quarterly reports

## Troubleshooting

### **Common Issues**

#### **Calculation Errors**
- Verify contract type and rate
- Check working hours per day setting
- Ensure proper rate calculations

#### **Duplicate Entries**
- Check for existing entries on same date
- Verify employee assignment
- Review data entry process

#### **Payment Validation**
- Ensure payment doesn't exceed remaining amount
- Verify payment method selection
- Check reference number format

### **Support**
- Contact system administrator for technical issues
- Review user manual for detailed instructions
- Check system logs for error details
- Validate data before reporting issues

## Future Enhancements

### **Planned Features**
- Mobile app for field workers
- GPS tracking for work locations
- Photo documentation of work
- Automated payment processing
- Advanced reporting dashboard
- Integration with accounting systems

### **API Development**
- RESTful API for external integrations
- Webhook support for real-time updates
- Third-party system integration
- Mobile app backend services

---

This timesheet system provides a comprehensive solution for construction companies to track work hours, manage payments, and monitor contract progress with real-time calculations and detailed reporting capabilities.