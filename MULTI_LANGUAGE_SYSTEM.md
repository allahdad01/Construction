# Multi-Language System

## Overview

The Construction SaaS Platform now supports **multi-language** functionality, allowing the system to be displayed in different languages. The system supports **English**, **Dari**, and **Pashto** by default, with the ability to add more languages through the super admin interface.

## 🌍 **Supported Languages**

### **Default Languages**
- **English** (en) - Default language, LTR direction
- **Dari** (da) - Afghan Persian, RTL direction
- **Pashto** (ps) - Afghan Pashto, RTL direction

### **Language Features**
- **Company-specific language**: Each company can set their default language
- **RTL support**: Right-to-left text direction for Arabic/Persian scripts
- **Dynamic translations**: All text is translatable
- **Real-time switching**: Language changes apply immediately
- **Native script support**: Proper display of non-Latin scripts

## 📝 **Language Display Examples**

### **English (LTR)**
```
Dashboard | Employees | Machines | Contracts
Settings | Profile | Logout
```

### **Dari (RTL)**
```
داشبورد | کارمندان | ماشین آلات | قراردادها
تنظیمات | پروفایل | خروج
```

### **Pashto (RTL)**
```
ډاشبورډ | کارمندان | ماشینونه | تړونونه
تنظیمات | پروفایل | وتل
```

## 🏢 **Language Management**

### **For Company Admins**
- **Settings Page**: `/public/settings.php`
- **Language Selection**: Choose from available languages
- **Real-time Preview**: See how language will look
- **Validation**: Ensure proper language configuration

### **For Super Admins**
- **Language Management**: `/public/super-admin/languages/`
- **Add New Languages**: Create new language entries
- **Translation Management**: Manage all translations
- **Import/Export**: Bulk translation operations

## 🗄️ **Database Schema**

### **Languages Table**
```sql
CREATE TABLE languages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    language_code VARCHAR(5) NOT NULL UNIQUE,
    language_name VARCHAR(50) NOT NULL,
    language_name_native VARCHAR(50) NOT NULL,
    direction ENUM('ltr', 'rtl') DEFAULT 'ltr',
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### **Language Translations Table**
```sql
CREATE TABLE language_translations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    language_id INT NOT NULL,
    translation_key VARCHAR(100) NOT NULL,
    translation_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE,
    UNIQUE KEY unique_translation_per_language (language_id, translation_key)
);
```

### **Company Settings Table (Updated)**
```sql
ALTER TABLE company_settings ADD COLUMN default_language_id INT DEFAULT 1;
ALTER TABLE company_settings ADD FOREIGN KEY (default_language_id) REFERENCES languages(id);
```

## 🔧 **Helper Functions**

### **Language Functions**
```php
// Get company's default language
function getCompanyLanguage($company_id = null)

// Get translation for a key
function getTranslation($key, $company_id = null)

// Short translation function
function __($key, $company_id = null)

// Get available languages
function getAvailableLanguages()

// Update company language
function updateCompanyLanguage($company_id, $language_id)

// Get language direction
function getLanguageDirection($company_id = null)

// Check if RTL
function isRTL($company_id = null)

// Get language name
function getLanguageName($language_code)

// Add new language
function addLanguage($language_code, $language_name, $language_name_native, $direction = 'ltr')

// Add translation
function addTranslation($language_id, $translation_key, $translation_value)

// Get translations for language
function getTranslationsForLanguage($language_id)

// Get missing translations
function getMissingTranslations($language_id)
```

## 📊 **Implementation Examples**

### **Sample Company Configurations**

#### **Company 1: ABC Construction (US)**
- **Language**: English (en)
- **Direction**: LTR
- **Example**: "Dashboard | Employees | Settings"

#### **Company 2: XYZ Builders (Afghanistan)**
- **Language**: Dari (da)
- **Direction**: RTL
- **Example**: "داشبورد | کارمندان | تنظیمات"

#### **Company 3: City Construction (Afghanistan)**
- **Language**: Pashto (ps)
- **Direction**: RTL
- **Example**: "ډاشبورډ | کارمندان | تنظیمات"

### **Usage in Code**
```php
// Display translated text
echo __('dashboard'); // Shows "Dashboard", "داشبورد", or "ډاشبورډ"

// Get company language info
$language = getCompanyLanguage();
echo $language['language_code']; // Shows "en", "da", or "ps"

// Check text direction
if (isRTL()) {
    echo 'dir="rtl"';
}

// Get language name
echo getLanguageName('da'); // Shows "دری (Dari)"
```

## 🎯 **System Integration**

### **Affected Modules**
- **Navigation**: All menu items translated
- **Forms**: All form labels and buttons
- **Tables**: All table headers and data
- **Messages**: All success/error messages
- **Settings**: All setting labels and descriptions

### **RTL Support Integration**
- **HTML Direction**: `dir="rtl"` attribute
- **CSS Styling**: RTL-specific styles
- **Text Alignment**: Right-aligned text
- **Input Fields**: Right-aligned input text
- **Buttons**: RTL button group direction

## 🔒 **Security Features**

### **Multi-tenant Isolation**
- Each company has independent language settings
- Settings are isolated by `company_id`
- No cross-company data leakage

### **Data Validation**
- Language code validation
- Translation key validation
- Input sanitization
- XSS protection

### **Access Control**
- Only company admins can modify language settings
- Super admins can manage all languages
- Regular users see translations in read-only mode

## 📈 **Business Benefits**

### **International Operations**
- Support for multiple languages
- Local language preferences
- RTL script support for Middle Eastern languages

### **User Experience**
- Familiar language interface
- Native script support
- Reduced language barriers
- Improved accessibility

### **Compliance**
- Local language requirements
- Regional accessibility standards
- Cultural sensitivity

## 🚀 **Configuration Guide**

### **For Company Admins**

#### **Setting Up Language**
1. Navigate to **Settings** page
2. Select desired language from dropdown
3. Preview language formatting
4. Save settings

#### **Language Preview**
- See how interface will look
- Test RTL/LTR direction
- Verify native script display

### **For Super Admins**

#### **Adding New Languages**
1. Navigate to **Language Management**
2. Click **Add New Language**
3. Enter language details:
   - Language code (ISO 639-1)
   - Language name (English)
   - Native language name
   - Text direction (LTR/RTL)
4. Save language

#### **Managing Translations**
1. Navigate to **Translation Management**
2. Select language to edit
3. Add/edit translations
4. Import/export translations

## 🔄 **Translation Management**

### **Translation Keys**
```php
// Common translation keys
'dashboard' => 'Dashboard'
'employees' => 'Employees'
'contracts' => 'Contracts'
'settings' => 'Settings'
'add' => 'Add'
'edit' => 'Edit'
'delete' => 'Delete'
'save' => 'Save'
'cancel' => 'Cancel'
'search' => 'Search'
'status' => 'Status'
'active' => 'Active'
'inactive' => 'Inactive'
'success' => 'Success'
'error' => 'Error'
'warning' => 'Warning'
```

### **Translation Examples**

#### **English**
```php
'dashboard' => 'Dashboard'
'employees' => 'Employees'
'contracts' => 'Contracts'
'settings' => 'Settings'
```

#### **Dari**
```php
'dashboard' => 'داشبورد'
'employees' => 'کارمندان'
'contracts' => 'قراردادها'
'settings' => 'تنظیمات'
```

#### **Pashto**
```php
'dashboard' => 'ډاشبورډ'
'employees' => 'کارمندان'
'contracts' => 'تړونونه'
'settings' => 'تنظیمات'
```

## 🛠️ **RTL Support**

### **HTML Structure**
```html
<html lang="da" dir="rtl">
<head>
    <!-- RTL CSS -->
    <style>
        [dir="rtl"] .sidebar { text-align: right; }
        [dir="rtl"] .main-content { direction: rtl; text-align: right; }
        [dir="rtl"] .nav-link { text-align: right; }
        [dir="rtl"] .table th, [dir="rtl"] .table td { text-align: right; }
        [dir="rtl"] .form-control { text-align: right; }
    </style>
</head>
```

### **CSS Classes**
```css
/* RTL Support */
[dir="rtl"] .sidebar { text-align: right; }
[dir="rtl"] .main-content { direction: rtl; text-align: right; }
[dir="rtl"] .nav-link { text-align: right; }
[dir="rtl"] .dropdown-menu { text-align: right; }
[dir="rtl"] .btn-group { direction: rtl; }
[dir="rtl"] .table th, [dir="rtl"] .table td { text-align: right; }
[dir="rtl"] .form-control { text-align: right; }
```

## 📊 **Language Statistics**

### **Translation Coverage**
- **English**: 100% (default language)
- **Dari**: 100% (complete translations)
- **Pashto**: 100% (complete translations)

### **System Integration**
- **Navigation**: 100% translated
- **Forms**: 100% translated
- **Messages**: 100% translated
- **Settings**: 100% translated

## 🔮 **Future Enhancements**

### **Planned Features**
- **More languages**: French, Spanish, Arabic, etc.
- **Language detection**: Automatic language detection
- **Translation memory**: Reuse existing translations
- **Machine translation**: AI-powered translation
- **Regional settings**: Language + locale support

### **API Integration**
- **Translation APIs**: Google Translate, DeepL
- **Language detection APIs**: Automatic detection
- **Spell check APIs**: Language-specific spell checking

## 🛠️ **Troubleshooting**

### **Common Issues**

#### **Language Not Displaying**
- Check if company has language settings
- Verify language is active in system
- Check database connection

#### **RTL Not Working**
- Verify language direction is set to 'rtl'
- Check CSS is loading properly
- Validate HTML structure

#### **Translations Missing**
- Check if translation exists in database
- Verify translation key is correct
- Check language ID is valid

### **Debug Information**
```php
// Debug language settings
$language = getCompanyLanguage();
var_dump($language);

// Debug translation
echo getTranslation('dashboard');

// Debug RTL
echo isRTL() ? 'RTL' : 'LTR';

// Test translation function
echo __('dashboard');
```

## 📖 **Best Practices**

### **Translation Guidelines**
1. **Use consistent keys**: Follow naming conventions
2. **Keep translations short**: Avoid long text in UI
3. **Test RTL languages**: Verify layout in RTL
4. **Use placeholders**: For dynamic content
5. **Maintain context**: Ensure translations fit context

### **RTL Guidelines**
1. **Test thoroughly**: Verify all elements in RTL
2. **Use proper CSS**: RTL-specific styling
3. **Check icons**: Ensure icons work in RTL
4. **Test forms**: Verify form layout in RTL
5. **Check tables**: Ensure table alignment in RTL

---

This multi-language system provides construction companies with the flexibility to operate in their preferred language while maintaining a unified platform experience across different regions and cultures.