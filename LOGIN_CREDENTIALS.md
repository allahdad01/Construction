# Login Credentials Guide

## 🔐 System Login Credentials

### **Super Admin Access**
- **Email**: `superadmin@construction.com`
- **Password**: `password`
- **Role**: `super_admin`
- **Access**: Full system access, can manage all companies and users

### **Company Admin Users**

#### **ABC Construction Ltd. (Enterprise)**
- **Email**: `admin@abc-construction.com`
- **Password**: `password`
- **Role**: `company_admin`
- **Company**: ABC Construction Ltd.

#### **XYZ Builders Inc. (Professional)**
- **Email**: `admin@xyz-builders.com`
- **Password**: `password`
- **Role**: `company_admin`
- **Company**: XYZ Builders Inc.

#### **City Construction Co. (Basic - Trial)**
- **Email**: `admin@city-construction.com`
- **Password**: `password`
- **Role**: `company_admin`
- **Company**: City Construction Co.

#### **Metro Builders (Suspended)**
- **Email**: `admin@metro-builders.com`
- **Password**: `password`
- **Role**: `company_admin`
- **Company**: Metro Builders (Account Suspended)

### **Employee Users**

#### **Drivers**
- **Email**: `driver1@abc-construction.com`
- **Password**: `password`
- **Role**: `driver`

- **Email**: `driver2@abc-construction.com`
- **Password**: `password`
- **Role**: `driver`

- **Email**: `driver3@xyz-builders.com`
- **Password**: `password`
- **Role**: `driver`

- **Email**: `driver4@city-construction.com`
- **Password**: `password`
- **Role**: `driver`

#### **Driver Assistants**
- **Email**: `assistant1@abc-construction.com`
- **Password**: `password`
- **Role**: `driver_assistant`

- **Email**: `assistant2@xyz-builders.com`
- **Password**: `password`
- **Role**: `driver_assistant`

#### **Parking Users**
- **Email**: `parking1@abc-construction.com`
- **Password**: `password`
- **Role**: `parking_user`

- **Email**: `parking2@xyz-builders.com`
- **Password**: `password`
- **Role**: `parking_user`

#### **Area Renters**
- **Email**: `area1@abc-construction.com`
- **Password**: `password`
- **Role**: `area_renter`

## 🔧 Password Hash Information

All users use the same password hash: `$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi`

This corresponds to the password: **`password`**

## 📋 User Status

- **Active Users**: All users except Metro Builders admin
- **Suspended Users**: Metro Builders admin (`admin@metro-builders.com`)
- **Super Admin**: System-wide access, no company association
- **Company Users**: Associated with specific companies

## 🚀 Quick Start

1. **For System Administration**: Use `superadmin@construction.com`
2. **For Company Management**: Use any company admin email
3. **For Employee Access**: Use any driver/assistant email
4. **Password**: Always use `password`

## ⚠️ Security Note

These are **sample credentials** for development/testing purposes. In production:
- Change all passwords immediately
- Use strong, unique passwords
- Enable two-factor authentication
- Regularly rotate passwords