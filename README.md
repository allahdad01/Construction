# Construction Company SaaS Platform

A comprehensive SaaS platform for construction companies to manage:
- Drivers and Driver Assistants
- Machines and Equipment
- Contracts and Projects
- Parking Management
- Area Rental Management
- Salary Calculations
- Expense Management

## Features

### Employee Management
- Driver and Driver Assistant profiles
- Salary calculation based on working days (30-day month system)
- Attendance tracking
- Salary disbursement management

### Machine Management
- Machine inventory and tracking
- Machine assignment to projects
- Working hours tracking
- Maintenance scheduling

### Contract & Project Management
- Contract creation with different billing types (Hourly/Daily/Monthly)
- Project assignment and tracking
- Working hours calculation
- Revenue tracking

### Parking & Area Management
- Machine parking space rental
- Area rental for containers and equipment
- Pro-rated billing based on usage days
- Space availability tracking

### Financial Management
- Expense tracking and categorization
- Revenue calculation
- Salary disbursement
- Financial reporting

## Installation

1. Clone the repository
2. Import the database schema from `database/schema.sql`
3. Configure database connection in `config/database.php`
4. Set up web server to point to the `public` directory
5. Access the application through your web browser

## Database Structure

The application uses MySQL with the following main tables:
- `employees` - Driver and assistant information
- `machines` - Equipment inventory
- `contracts` - Project contracts
- `parking_spaces` - Parking area management
- `rental_areas` - Area rental management
- `expenses` - Company expenses
- `salary_payments` - Employee salary records
- `working_hours` - Time tracking for machines and employees

## Salary Calculation

- Monthly salary is divided by 30 days (company standard)
- Daily rate = Monthly salary / 30
- Final salary = Daily rate × Actual working days

## Parking/Rental Calculation

- Monthly rate is divided by 30 days
- Daily rate = Monthly rate / 30
- Final payment = Daily rate × Actual usage days