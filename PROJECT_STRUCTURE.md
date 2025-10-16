# Live Streaming Booking System - Project Structure

## Overview
This document outlines the cleaned and organized structure of the Live Streaming Booking System.

## Directory Structure

```
ใช้หลัก วันที่ 300768 สำเนา/
├── config/                          # Configuration files
│   ├── config.php                   # Centralized configuration management
│   ├── database.php                 # Database connection with singleton pattern
│   └── database.example.php         # Database configuration example
├── includes/                        # Core application includes
│   ├── bootstrap.php                # Application initialization
│   ├── functions.php                # Helper functions and utilities
│   ├── layout.php                   # Main site layout
│   └── admin_layout.php              # Admin panel layout
├── models/                          # Database models (MVC pattern)
│   ├── BaseModel.php                # Base model class with common functionality
│   ├── index.php                    # Model loader and factory
│   ├── User.php                     # User management model
│   ├── Booking.php                  # Booking management model
│   ├── Payment.php                  # Payment processing model
│   ├── Package.php                  # Package management model
│   └── PackageItem.php              # Package items model
├── pages/                           # Page routing and controllers
│   ├── index.php                    # Main router
│   ├── web/                         # Web pages (public-facing)
│   │   ├── booking.php
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── payment.php
│   │   ├── profile.php
│   │   └── register.php
│   ├── api/                         # API endpoints
│   │   ├── availability.php
│   │   ├── bookings.php
│   │   ├── bookings_me.php
│   │   ├── bookings_update.php
│   │   ├── health.php
│   │   ├── packages.php
│   │   ├── payments.php
│   │   └── payments_update.php
│   └── admin/                       # Admin panel pages
│       ├── booking_detail.php
│       ├── bookings.php
│       ├── customers.php
│       ├── dashboard.php
│       ├── packages.php
│       ├── payment_detail.php
│       ├── payments.php
│       └── reports.php
├── scripts/                         # Utility scripts and automation
│   ├── backup_ledger.php
│   ├── cleanup_ledger.php
│   ├── db_import.sh
│   ├── start.sh
│   └── tests/                       # Test scripts
│       ├── admin_package_smoke.php
│       ├── booking_concurrency_smoke.php
│       ├── availability_cache_smoke.php
│       ├── booking_concurrency_stress.php
│       └── login_payment_smoke.php
├── assets/                          # Static assets
│   ├── css/
│   ├── js/
│   └── images/
├── database/                        # Database files
│   ├── create_database.sql
│   └── migrations/
├── docs/                            # Documentation
│   ├── api.md
│   ├── architecture.md
│   ├── backlog.md
│   ├── brownfield-architecture.md
│   ├── prd.md
│   ├── postman_collection.json
│   ├── qa/
│   ├── stories/
│   └── diagrams/
├── tests/                           # PHPUnit tests
│   ├── Unit/
│   └── bootstrap.php
├── vendor/                          # Composer dependencies
├── logs/                            # Application logs
├── cache/                           # Cache files
├── uploads/                         # User uploads
│   ├── package-items/
│   └── slips/
└── backups/                         # System backups
```

## Key Improvements Made

### 1. Configuration Management
- **Centralized Config**: Created `config/config.php` with Config class for consistent configuration access
- **Environment Variables**: All configuration now uses environment variables with sensible defaults
- **Database Connection**: Updated to use singleton pattern with proper error handling

### 2. Model Organization
- **BaseModel**: Created base model class with common CRUD operations
- **Model Factory**: Implemented model factory pattern for consistent model instantiation
- **Inheritance**: All models now extend BaseModel for consistent functionality
- **Index File**: Created `models/index.php` for centralized model loading

### 3. Pages Structure
- **Separation**: Separated web pages from API endpoints
- **Router**: Created main router (`pages/index.php`) for clean URL routing
- **Organization**:
  - `pages/web/` - Public-facing web pages
  - `pages/api/` - REST API endpoints
  - `pages/admin/` - Admin panel pages

### 4. Function Library
- **Bootstrap**: Created `includes/bootstrap.php` for application initialization
- **Helper Functions**: Organized all helper functions in `includes/functions.php`
- **Security**: Added CSRF protection, input sanitization, and authentication helpers

### 5. File Organization
- **Consistent Naming**: Standardized file naming conventions
- **Logical Grouping**: Grouped related files together
- **Clean Root**: Moved many root files to appropriate subdirectories

## Configuration

### Environment Variables
The application now supports these environment variables:

```bash
# Application
APP_ENV=development

# Database
DB_HOST=localhost
DB_NAME=live_streaming_booking
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

# Email
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password

# Booking Settings
BOOKING_DEFAULT_PICKUP_TIME=09:00
BOOKING_DEFAULT_RETURN_TIME=18:00
BOOKING_EARLY_PICKUP_THRESHOLD=12:00
BOOKING_LATE_RETURN_THRESHOLD=18:00
BOOKING_HOLIDAYS=

# Caching
AVAILABILITY_CACHE_TTL=120
LEDGER_RETENTION_DAYS=30
```

## Usage Examples

### Accessing Configuration
```php
$config = Config::getInstance();
$site_name = $config->get('site.name');
$db_host = $config->get('database.host');
```

### Using Models
```php
// Initialize models
init_models($db);

// Get model instance
$userModel = model('User');
$bookingModel = model('Booking');

// Use model methods
$users = $userModel->getAllCustomers();
$bookings = $bookingModel->getUserBookings($user_id);
```

### Database Connection
```php
// Get database connection
$conn = get_db_connection();

// Use with models
$booking = new Booking($conn);
```

## Security Features

1. **CSRF Protection**: Token generation and verification
2. **Input Sanitization**: Consistent input sanitization functions
3. **Session Security**: Secure session configuration with SameSite cookies
4. **Password Hashing**: Bcrypt password hashing
5. **SQL Injection Prevention**: Prepared statements throughout

## Caching System

- **File-based Cache**: Simple file caching for availability data
- **Cache Invalidation**: Automatic cache invalidation on booking changes
- **TTL Support**: Configurable cache TTL

## API Structure

The API follows RESTful conventions:

- `GET /api/availability` - Check package availability
- `GET /api/bookings/me` - Get user's bookings
- `POST /api/bookings` - Create new booking
- `PUT /api/bookings/update` - Update booking
- `GET /api/packages` - Get available packages
- `POST /api/payments` - Process payment
- `GET /api/health` - Health check endpoint

## Development Guidelines

1. **Follow MVC Pattern**: Keep business logic in models
2. **Use Configuration Class**: Access all settings via Config class
3. **Prepared Statements**: Always use prepared statements for database queries
4. **Input Validation**: Validate and sanitize all user inputs
5. **Error Handling**: Use try-catch blocks and log errors appropriately
6. **Code Organization**: Place files in appropriate directories based on function

## Future Enhancements

1. **Dependency Injection**: Implement proper DI container
2. **Middleware System**: Add middleware for authentication, logging, etc.
3. **API Versioning**: Implement API versioning
4. **Testing Framework**: Expand test coverage
5. **Documentation**: Generate API documentation automatically
6. **Caching Layer**: Implement more sophisticated caching (Redis/Memcached)
7. **Queue System**: Add background job processing
8. **Monitoring**: Add application monitoring and logging

## Maintenance

- **Regular Backups**: Use provided backup scripts
- **Log Rotation**: Implement log rotation for large log files
- **Cache Cleanup**: Regular cache cleanup using provided scripts
- **Database Maintenance**: Regular database optimization and backups

This cleaned structure provides a solid foundation for continued development and maintenance of the Live Streaming Booking System.
