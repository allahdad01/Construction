# Multi-Currency & Multi-Date System

## Overview

The Construction SaaS Platform now supports **multi-currency** and **multi-date format** functionality, allowing tenant companies to configure their preferred currency and date format settings. This system provides flexibility for international construction companies operating in different regions.

## 🌍 **Multi-Currency Support**

### **Supported Currencies**
- **USD** (US Dollar) - `$`
- **AFN** (Afghan Afghani) - `؋`
- **EUR** (Euro) - `€`
- **GBP** (British Pound) - `£`
- **CAD** (Canadian Dollar) - `C$`
- **AUD** (Australian Dollar) - `A$`

### **Currency Features**
- **Company-specific currency**: Each company can set their default currency
- **Automatic formatting**: Currency symbols positioned correctly based on locale
- **Exchange rate support**: Built-in exchange rates for currency conversion
- **Real-time display**: All financial data displayed in company's currency

### **Currency Display Examples**
```php
// USD: $1,234.56
// AFN: 1,234.56 ؋
// EUR: 1,234.56 €
// GBP: 1,234.56 £
```

## 📅 **Multi-Date Format Support**

### **Supported Date Formats**
- **Gregorian** (YYYY-MM-DD) - Standard international format
- **Shamsi** (YYYY/MM/DD) - Persian calendar format
- **European** (DD/MM/YYYY) - European date format
- **American** (MM/DD/YYYY) - US date format
- **ISO** (YYYY-MM-DD) - ISO standard format

### **Date Format Examples**
```php
// Gregorian: 2023-12-25
// Shamsi: 1402/10/04
// European: 25/12/2023
// American: 12/25/2023
// ISO: 2023-12-25
```

## 🏢 **Company Settings Management**

### **Settings Page** (`/public/settings.php`)

#### **Features**
- **Currency Selection**: Choose from available currencies
- **Date Format Selection**: Select preferred date format
- **Timezone Configuration**: Set company timezone
- **Real-time Preview**: See how settings will look
- **Validation**: Ensure proper settings configuration

#### **Access Control**
- **Company Admin**: Can modify company settings
- **Super Admin**: Can view all company settings
- **Other Users**: Read-only access to settings

## 🗄️ **Database Schema**

### **Currencies Table**
```sql
CREATE TABLE currencies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    currency_code VARCHAR(3) NOT NULL UNIQUE,
    currency_name VARCHAR(50) NOT NULL,
    currency_symbol VARCHAR(5) NOT NULL,
    exchange_rate_to_usd DECIMAL(10,4) DEFAULT 1.0000,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### **Date Formats Table**
```sql
CREATE TABLE date_formats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    format_code VARCHAR(20) NOT NULL UNIQUE,
    format_name VARCHAR(50) NOT NULL,
    format_pattern VARCHAR(20) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### **Company Settings Table**
```sql
CREATE TABLE company_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    default_currency_id INT NOT NULL,
    default_date_format_id INT NOT NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (default_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (default_date_format_id) REFERENCES date_formats(id),
    UNIQUE KEY unique_company_settings (company_id)
);
```

## 🔧 **Helper Functions**

### **Currency Functions**
```php
// Get company's default currency
function getCompanyCurrency($company_id = null)

// Format currency amount
function formatCurrency($amount, $currency_id = null, $company_id = null)

// Get available currencies
function getAvailableCurrencies()

// Convert between currencies
function convertCurrency($amount, $from_currency_id, $to_currency_id)
```

### **Date Functions**
```php
// Get company's default date format
function getCompanyDateFormat($company_id = null)

// Format date according to company settings
function formatDate($date, $company_id = null)

// Convert to Shamsi date
function convertToShamsi($dateObj)

// Convert from Shamsi date
function convertFromShamsi($shamsiDate)

// Get available date formats
function getAvailableDateFormats()
```

### **Settings Functions**
```php
// Update company settings
function updateCompanySettings($company_id, $currency_id, $date_format_id)
```

## 📊 **Implementation Examples**

### **Sample Company Configurations**

#### **Company 1: ABC Construction (US)**
- **Currency**: USD ($)
- **Date Format**: Gregorian (2023-12-25)
- **Timezone**: America/New_York

#### **Company 2: XYZ Builders (Afghanistan)**
- **Currency**: AFN (؋)
- **Date Format**: Shamsi (1402/10/04)
- **Timezone**: Asia/Kabul

#### **Company 3: City Construction (Europe)**
- **Currency**: USD ($)
- **Date Format**: European (25/12/2023)
- **Timezone**: Europe/Paris

#### **Company 4: Metro Builders (Europe)**
- **Currency**: EUR (€)
- **Date Format**: Gregorian (2023-12-25)
- **Timezone**: Europe/London

### **Usage in Code**
```php
// Display currency in company's format
echo formatCurrency($amount, null, getCurrentCompanyId());

// Display date in company's format
echo formatDate($date, getCurrentCompanyId());

// Get company currency info
$currency = getCompanyCurrency();
echo $currency['currency_symbol']; // Shows $, ؋, €, etc.

// Get company date format info
$dateFormat = getCompanyDateFormat();
echo $dateFormat['format_name']; // Shows format name
```

## 🎯 **System Integration**

### **Affected Modules**
- **Employee Management**: Salaries displayed in company currency
- **Contract Management**: Rates and payments in company currency
- **Timesheet System**: Hours and earnings in company currency
- **Parking Management**: Rental rates in company currency
- **Expense Tracking**: All expenses in company currency
- **Payment Tracking**: All payments in company currency

### **Date Display Integration**
- **Contract dates**: Start/end dates in company format
- **Payment dates**: Payment dates in company format
- **Timesheet dates**: Work dates in company format
- **Expense dates**: Expense dates in company format
- **Salary dates**: Payment dates in company format

## 🔒 **Security Features**

### **Multi-tenant Isolation**
- Each company has independent currency/date settings
- Settings are isolated by `company_id`
- No cross-company data leakage

### **Data Validation**
- Currency selection validation
- Date format validation
- Timezone validation
- Input sanitization

### **Access Control**
- Only company admins can modify settings
- Super admins can view all settings
- Regular users see settings in read-only mode

## 📈 **Business Benefits**

### **International Operations**
- Support for multiple currencies
- Local date format preferences
- Timezone support for global teams

### **Compliance**
- Local currency requirements
- Regional date format standards
- Tax reporting compliance

### **User Experience**
- Familiar currency symbols
- Local date formats
- Reduced confusion for users

## 🚀 **Configuration Guide**

### **For Company Admins**

#### **Setting Up Currency**
1. Navigate to **Settings** page
2. Select desired currency from dropdown
3. Preview currency formatting
4. Save settings

#### **Setting Up Date Format**
1. Navigate to **Settings** page
2. Select desired date format
3. Preview date formatting
4. Save settings

#### **Timezone Configuration**
1. Navigate to **Settings** page
2. Select appropriate timezone
3. Save settings

### **For Super Admins**

#### **Managing Currencies**
- Add new currencies to system
- Update exchange rates
- Activate/deactivate currencies

#### **Managing Date Formats**
- Add new date formats
- Configure format patterns
- Activate/deactivate formats

## 🔄 **Migration & Updates**

### **Database Migration**
```sql
-- Add currency columns to existing tables
ALTER TABLE employees ADD COLUMN currency_id INT DEFAULT 1;
ALTER TABLE contracts ADD COLUMN currency_id INT DEFAULT 1;
-- ... (other tables)

-- Add foreign key constraints
ALTER TABLE employees ADD FOREIGN KEY (currency_id) REFERENCES currencies(id);
ALTER TABLE contracts ADD FOREIGN KEY (currency_id) REFERENCES currencies(id);
-- ... (other tables)
```

### **Data Migration**
```sql
-- Update existing data with default currency
UPDATE employees SET currency_id = 1 WHERE currency_id IS NULL;
UPDATE contracts SET currency_id = 1 WHERE currency_id IS NULL;
-- ... (other tables)
```

## 🛠️ **Troubleshooting**

### **Common Issues**

#### **Currency Not Displaying**
- Check if company has currency settings
- Verify currency is active in system
- Check database connection

#### **Date Format Issues**
- Verify date format is active
- Check date conversion functions
- Validate date input format

#### **Settings Not Saving**
- Check user permissions
- Verify database constraints
- Check for validation errors

### **Debug Information**
```php
// Debug currency settings
$currency = getCompanyCurrency();
var_dump($currency);

// Debug date format settings
$dateFormat = getCompanyDateFormat();
var_dump($dateFormat);

// Test currency formatting
echo formatCurrency(1234.56);

// Test date formatting
echo formatDate('2023-12-25');
```

## 🔮 **Future Enhancements**

### **Planned Features**
- **Real-time exchange rates**: API integration for live rates
- **Multiple currencies per company**: Support for multiple currencies
- **Advanced date formats**: More calendar systems
- **Currency conversion**: Automatic conversion between currencies
- **Regional settings**: Language and locale support

### **API Integration**
- **Exchange rate APIs**: Real-time currency conversion
- **Calendar APIs**: Advanced date calculations
- **Timezone APIs**: Automatic timezone detection

---

This multi-currency and multi-date system provides construction companies with the flexibility to operate according to their local preferences while maintaining a unified platform experience.