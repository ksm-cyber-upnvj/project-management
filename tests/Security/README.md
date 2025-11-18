# Security Testing Suite

## Overview
Comprehensive security tests untuk project management application. Tests ini mencakup authentication, authorization, input validation, dan data protection.

---

## 📁 Test Structure

```
tests/Security/
├── AuthenticationSecurityTest.php      # Password hashing, login security, OAuth
├── AuthorizationSecurityTest.php       # RBAC, policies, permissions
├── InputValidationSecurityTest.php     # SQL injection, XSS, mass assignment
├── DataProtectionSecurityTest.php      # Token generation, data encryption
└── README.md                           # This file
```

---

## 🚀 Running Tests

### Run All Security Tests
```bash
php artisan test tests/Security
```

### Run Specific Test File
```bash
# Authentication tests only
php artisan test tests/Security/AuthenticationSecurityTest.php

# Authorization tests only
php artisan test tests/Security/AuthorizationSecurityTest.php

# Input validation tests only
php artisan test tests/Security/InputValidationSecurityTest.php

# Data protection tests only
php artisan test tests/Security/DataProtectionSecurityTest.php
```

### Run in Docker (Sail)
```bash
./vendor/bin/sail test tests/Security
```

### Run with Coverage
```bash
XDEBUG_MODE=coverage php artisan test tests/Security --coverage
```

### Run Specific Test
```bash
# Run only password hashing tests
php artisan test --filter="hashes passwords"

# Run only RBAC tests
php artisan test --filter="Role-Based Access Control"
```

---

## 📋 Test Coverage

### 1. Authentication Security (17 tests)
✅ **Password Storage**
- Hashes passwords before storing
- Uses bcrypt algorithm
- Generates different hashes for same password

✅ **Password Verification**
- Validates correct passwords
- Rejects incorrect passwords
- Case sensitivity enforcement

✅ **Sensitive Data Protection**
- Hides password in serialization
- Hides remember_token
- Protects sensitive attributes

✅ **Panel Access Control**
- Role-based panel access
- Denies access without roles

✅ **Google OAuth Security**
- Stores google_id safely
- Prevents duplicate google_id

✅ **Email Verification**
- Tracks verification status
- Datetime casting

✅ **Remember Token**
- Unique token generation
- Sufficient entropy

---

### 2. Authorization Security (15 tests)
✅ **Role-Based Access Control (RBAC)**
- Assigns roles correctly
- Checks multiple roles
- Prevents unauthorized role assignment

✅ **Ticket View Authorization**
- Super admin can view all
- Creator can view own tickets
- Assigned users can view
- Project members can view
- Denies unauthorized access

✅ **Ticket Update Authorization**
- Super admin can update all
- Creator can update own
- Assigned users can update
- Denies unauthorized updates
- Project members need assignment

✅ **Permission-Based Authorization**
- Checks specific permissions
- Prevents actions without permission
- Allows actions with permission

✅ **Ownership Verification**
- Verifies ticket ownership
- Tracks assignments correctly

✅ **Privilege Escalation Prevention**
- Prevents horizontal escalation
- Super admin bypasses restrictions

---

### 3. Input Validation Security (23 tests)
✅ **SQL Injection Prevention**
- WHERE clause protection
- LIKE query protection
- Parameter binding usage
- ORDER BY safety

✅ **XSS Prevention**
- HTML escaping in titles
- User name sanitization
- JavaScript in comments handling

✅ **Mass Assignment Protection**
- User model protection
- Ticket model fillable check
- ID manipulation prevention

✅ **Data Type Validation**
- Email uniqueness
- Required fields enforcement

✅ **Special Characters**
- Null byte handling
- Integer overflow protection
- Special characters storage
- Unicode support
- Emoji handling

✅ **Path Traversal Prevention**
- Directory traversal blocking

---

### 4. Data Protection Security (19 tests)
✅ **External Access Token Security**
- Unique token generation
- Sufficient token length
- Random password generation
- Active status tracking
- Last accessed tracking
- Token deactivation
- Project association

✅ **Password Storage Security**
- Hashed password storage
- No plain text exposure
- Strong hashing algorithm

✅ **Token Uniqueness**
- Enforces unique tokens
- Different tokens per generation

✅ **Sensitive Data Exposure Prevention**
- Hides remember_token
- Exposes only necessary attributes

✅ **Data Integrity**
- Referential integrity
- Ownership integrity

✅ **Timestamp Security**
- Auto-tracks creation
- Auto-tracks updates
- Prevents manual manipulation

✅ **Random Token Generation**
- Cryptographically secure
- Unpredictable patterns

✅ **Email Verification**
- Status tracking
- Verification checking

---

## 🔍 Test Details

### What's Being Tested?

#### Security Threats Covered:
1. **Authentication Attacks**
   - Brute force (password hashing)
   - Credential stuffing (unique checks)
   - Session hijacking (token security)

2. **Authorization Bypass**
   - Horizontal privilege escalation
   - Vertical privilege escalation
   - Policy violations

3. **Injection Attacks**
   - SQL injection
   - XSS (Cross-Site Scripting)
   - LDAP injection (N/A)

4. **Data Exposure**
   - Sensitive data in responses
   - Token leakage
   - Password exposure

5. **Input Validation**
   - Mass assignment
   - Type juggling
   - Null byte injection

---

## ✅ Expected Test Results

All tests should **PASS** ✅

**Total Tests:** 74 tests
**Expected Duration:** ~30-60 seconds

### Example Output:
```
PASS  Tests\Security\AuthenticationSecurityTest
✓ hashes passwords before storing in database                          0.15s
✓ uses bcrypt hashing algorithm                                        0.02s
✓ generates different hashes for same password                         0.04s
...

PASS  Tests\Security\AuthorizationSecurityTest
✓ assigns roles to users correctly                                     0.10s
✓ allows super_admin to view any ticket                                0.12s
...

Tests:    74 passed (74 assertions)
Duration: 45.23s
```

---

## 🐛 Troubleshooting

### Database Connection Errors
```bash
# Check database connection
php artisan db:show

# Run migrations if needed
php artisan migrate:fresh --seed
```

### Permission Errors (Spatie)
```bash
# Install Spatie permissions
composer require spatie/laravel-permission

# Publish config
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Run migrations
php artisan migrate
```

### Missing Factories
```bash
# All required factories exist:
# - UserFactory
# - ProjectFactory
# - TicketFactory
# - TicketStatusFactory
# - ExternalAccessFactory
```

---

## 📊 Code Quality Checks

### Run with Code Coverage
```bash
XDEBUG_MODE=coverage php artisan test tests/Security --coverage --min=80
```

### Static Analysis
```bash
./vendor/bin/phpstan analyse tests/Security
```

### Code Style
```bash
./vendor/bin/pint tests/Security
```

---

## 🔐 Security Best Practices Enforced

1. ✅ Passwords always hashed (bcrypt/argon2)
2. ✅ Sensitive data never exposed in API
3. ✅ Role-based access control enforced
4. ✅ SQL injection prevented (Eloquent ORM)
5. ✅ XSS prevented (escaping)
6. ✅ Mass assignment protected
7. ✅ Tokens cryptographically secure
8. ✅ Authorization policies enforced

---

## 📝 Adding New Security Tests

Follow the existing pattern:

```php
<?php

use App\Models\User;

describe('New Security Feature', function () {
    describe('Feature Category', function () {
        it('describes what it tests', function () {
            // Arrange
            $user = User::factory()->create();

            // Act
            $result = $user->someSecureMethod();

            // Assert
            expect($result)->toBeTrue();
        });
    });
});
```

---

## 🎯 Integration with CI/CD

Add to `.github/workflows/tests.yml`:

```yaml
- name: Run Security Tests
  run: php artisan test tests/Security --stop-on-failure
```

---

## 📚 References

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [Pest PHP Documentation](https://pestphp.com/)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/)

---

## ✨ Maintenance

**Last Updated:** 2025-11-18
**Test Coverage:** 74 tests
**Status:** All tests passing ✅

Run tests regularly to ensure security compliance!
