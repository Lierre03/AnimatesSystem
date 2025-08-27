# 🏦 Cashier Role & Billing Management System

## Overview
The system now includes a dedicated **Cashier** staff role with access to billing management only. Cashiers can process payments, generate bills from RFID tags, and manage customer transactions.

## 🎯 Cashier Role Features

### **Access Control**
- **Role**: `staff` with `staff_role: cashier`
- **Access**: Billing Management only
- **Restricted**: Cannot access admin functions, customer management, or other staff areas

### **Billing Management Capabilities**
1. **RFID Tag Billing**
   - Scan RFID tags to generate bills automatically
   - Manual RFID entry for backup
   - Real-time pet and service information display

2. **Payment Processing**
   - Cash payments
   - Credit/Debit card payments
   - Online payment reference recording (GCash, Maya, Bank Transfer)
   - Discount application (Senior/PWD, Loyalty, First-time customer)

3. **Bill Management**
   - Generate bills from pending services
   - Print bills
   - Save drafts
   - Process payments

4. **Financial Overview**
   - Daily revenue tracking
   - Pending bills monitoring
   - Payment method analytics
   - Processing status tracking

## 🚀 Getting Started

### **1. Create Cashier Account**
Run the cashier creation script:
```bash
# Navigate to drafts folder
cd drafts

# Run the script (adjust path as needed)
php create_cashier.php
```

**Default Cashier Credentials:**
- **Email**: `cashier@animates.ph`
- **Password**: `Cashier@1234`

### **2. Login as Cashier**
1. Go to `admin_staff_auth.html`
2. Select "Staff" tab
3. Enter cashier credentials
4. System will redirect to billing management

### **3. Access Billing Management**
- **Direct URL**: `billing_management.html`
- **Navigation**: Only accessible to admin and cashier roles
- **Auto-redirect**: Cashiers are automatically redirected here after login

## 💳 Payment Processing

### **Online Payment Handling**
Since the system doesn't process actual online payments, cashiers must:

1. **Select "Online Payment"** as payment method
2. **Enter Reference Number** from customer's payment app
3. **Select Payment Platform** (GCash, Maya, Bank Transfer, etc.)
4. **Verify Payment** with customer before processing

### **Payment Flow**
```
RFID Scan → Bill Generation → Payment Method Selection → 
Reference Entry (if online) → Payment Processing → Receipt Generation
```

## 🔒 Security Features

### **Role-Based Access Control**
- Cashiers can only access billing functions
- No access to user management or system settings
- Session validation on every page load

### **Authentication**
- JWT token-based authentication
- Automatic session timeout
- Secure password hashing (bcrypt)

## 📱 RFID Integration

### **Automatic Scanning**
- Simulated RFID scanning every 10 seconds (demo mode)
- Real RFID device integration ready
- Manual entry fallback

### **Data Display**
- Pet information (name, breed, owner)
- Service timeline (check-in, bathing, grooming)
- Service breakdown with pricing
- Size-based price modifiers

## 🎨 User Interface

### **Design Theme**
- **Colors**: Gold/White palette matching Animates PH branding
- **Layout**: Clean, uncluttered design for easy navigation
- **Responsive**: Works on desktop, tablet, and mobile devices

### **Key Sections**
1. **Dashboard Overview** - Revenue and pending bills
2. **RFID Billing** - Main billing interface
3. **Pending Bills** - List of completed services awaiting payment
4. **Payment Processing** - Transaction history and analytics

## 🔧 Technical Implementation

### **Files Modified/Created**
- `html/billing_management.html` - Redesigned billing interface
- `js/billing_management.js` - New billing management logic
- `drafts/create_cashier.php` - Cashier account creation script

### **API Integration**
- **Base URL**: `http://localhost/animates/api/`
- **Authentication**: JWT token validation
- **Endpoints**: Ready for backend integration

### **Database Schema**
- **Users table**: `staff_role` column for role differentiation
- **Billing table**: Ready for transaction recording
- **RFID table**: Pet and service tracking

## 📊 Sample Data

### **Demo RFID Tags**
- `A1B2C3D4` - Buddy (Golden Retriever)
- `B2C3D4E5` - Whiskers (Persian Cat)
- `D4E5F6G7` - Luna (Shih Tzu)

### **Service Pricing**
- Basic Bath: ₱300 (with size modifiers)
- Full Grooming: ₱500 (with size modifiers)
- Nail Trimming: ₱100
- Premium Services: ₱600+

## 🚨 Important Notes

### **No Real Payment Processing**
- System records payment references only
- No integration with GCash, Maya, or banking APIs
- Cashiers must verify payments manually

### **RFID Simulation**
- Current implementation uses simulated RFID scanning
- Ready for real RFID device integration
- Manual entry always available as backup

### **Data Persistence**
- Sample data is currently hardcoded
- Ready for database integration
- All functions work with mock data

## 🔮 Future Enhancements

### **Planned Features**
1. **Real RFID Integration** - Hardware device support
2. **Payment Gateway Integration** - Actual online payment processing
3. **Receipt Generation** - PDF/Email receipt system
4. **Inventory Management** - Product and service catalog
5. **Customer Portal Integration** - Online bill viewing

### **Backend Development**
1. **Database Integration** - Real data persistence
2. **API Endpoints** - Full CRUD operations
3. **Reporting System** - Financial analytics and exports
4. **Audit Logging** - Transaction history and tracking

## 📞 Support

For technical support or questions about the cashier role and billing management system, please contact the development team.

---

**Version**: 1.0  
**Last Updated**: January 2025  
**Compatibility**: PHP 7.4+, Modern Browsers
