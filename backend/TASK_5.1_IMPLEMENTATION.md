# Task 5.1 Implementation: Super Admin API Endpoints

## Overview
Implemented all Super Admin API endpoints for managing hospitals (tenants), system-wide settings, audit logs, and feature flags.

## Files Created

### 1. Models
- **`app/Models/SuperAdmin.php`**: Super Admin model implementing JWTSubject for authentication
- **`app/Models/SystemSetting.php`**: System settings model with helper methods for get/set operations

### 2. Controllers
- **`app/Http/Controllers/Api/V1/SuperAdminController.php`**: Main controller with all Super Admin endpoints

### 3. Middleware
- **`app/Http/Middleware/SuperAdminAuthMiddleware.php`**: JWT authentication middleware specifically for Super Admin role validation

### 4. Jobs
- **`app/Jobs/TenantProvisioningJob.php`**: Background job for provisioning new tenants (runs migrations, creates default Hospital Admin)

### 5. Request Validation
- **`app/Http/Requests/Admin/CreateTenantRequest.php`**: Validates tenant creation requests
- **`app/Http/Requests/Admin/UpdateSystemSettingsRequest.php`**: Validates system settings updates
- **`app/Http/Requests/Admin/UpdateFeatureFlagRequest.php`**: Validates feature flag updates

### 6. Tests
- **`tests/Feature/SuperAdmin/SuperAdminApiTest.php`**: Comprehensive test suite for all Super Admin endpoints

## API Endpoints Implemented

### 1. GET /api/v1/admin/hospitals
Lists all tenants with their status (active, suspended, inactive).

**Features:**
- Pagination support
- Filter by status
- Search by name or subdomain
- Includes plan information

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "Hospital Name",
      "subdomain": "subdomain",
      "status": "active",
      "plan_id": "uuid",
      "plan": { ... }
    }
  ],
  "meta": {
    "pagination": { ... }
  }
}
```

### 2. POST /api/v1/admin/hospitals
Creates and provisions a new tenant.

**Request Body:**
```json
{
  "name": "Hospital Name",
  "subdomain": "subdomain",
  "plan_id": "uuid",
  "admin_name": "Admin Name",
  "admin_email": "admin@example.com"
}
```

**Features:**
- Creates tenant record in central database
- Dispatches `TenantProvisioningJob` to:
  - Run tenant migrations
  - Create default Hospital Admin user
  - Send onboarding email (TODO)

### 3. PATCH /api/v1/admin/hospitals/{id}/suspend
Suspends a tenant and invalidates all user tokens.

**Features:**
- Updates tenant status to 'suspended'
- Increments `token_version` for all users in the tenant database
- Immediately invalidates all active JWT tokens

### 4. PATCH /api/v1/admin/hospitals/{id}/activate
Reactivates a suspended tenant.

**Features:**
- Updates tenant status to 'active'
- Allows users to log in again

### 5. GET /api/v1/admin/audit-logs
Retrieves system-wide audit logs from all tenant databases.

**Features:**
- Aggregates logs from all active tenants
- Filter by date range (start_date, end_date)
- Filter by action type
- Includes tenant information with each log entry
- Pagination support

**Query Parameters:**
- `start_date`: Filter logs from this date
- `end_date`: Filter logs until this date
- `action`: Filter by action type (partial match)
- `page`: Page number
- `per_page`: Items per page

### 6. PUT /api/v1/admin/settings
Updates system-wide settings.

**Request Body:**
```json
{
  "mobile_app_version": "1.0.0",
  "api_rate_limit": 100,
  "maintenance_mode": false
}
```

**Features:**
- Updates multiple settings in one request
- Records who updated the settings
- Timestamps each update

### 7. GET /api/v1/admin/feature-flags
Lists all feature flags and their current state.

**Response:**
```json
{
  "success": true,
  "data": {
    "feature_sms_notifications": true,
    "feature_lab_module": false,
    "feature_pharmacy_module": true,
    "feature_appointments": true,
    "feature_mobile_app": true
  }
}
```

### 8. PUT /api/v1/admin/feature-flags/{key}
Toggles a specific feature flag.

**Request Body:**
```json
{
  "enabled": true
}
```

**Features:**
- Updates feature flag state
- Records who made the change
- Timestamps the update

## Authentication & Authorization

### Super Admin Authentication Flow
1. Super Admin logs in (using existing auth endpoints)
2. JWT token is generated with `role: 'super_admin'` claim
3. All Super Admin endpoints require the `super.admin.auth` middleware
4. Middleware validates:
   - JWT signature and expiry
   - Role is 'super_admin'
   - Token version matches Super Admin record
   - Super Admin exists in database

### Middleware Registration
Added to `bootstrap/app.php`:
```php
'super.admin.auth' => \App\Http\Middleware\SuperAdminAuthMiddleware::class,
```

## Routes Configuration

All Super Admin routes are prefixed with `/api/v1/admin` and protected by the `super.admin.auth` middleware. They operate on the central (landlord) database, not tenant databases.

```php
Route::prefix('admin')->middleware(['super.admin.auth'])->group(function () {
    // All Super Admin endpoints
});
```

## Database Schema

### Central Database Tables Used
- `tenants`: Hospital registry
- `plans`: Subscription plans
- `system_settings`: Global configuration
- `super_admins`: Super Admin accounts

### Tenant Database Tables Accessed
- `audit_logs`: For system-wide audit log aggregation
- `users`: For token invalidation during suspension

## Security Features

1. **JWT Authentication**: All endpoints require valid JWT with super_admin role
2. **Token Invalidation**: Suspending a tenant increments token_version for all users
3. **Role Validation**: Middleware ensures only super_admin role can access endpoints
4. **Audit Logging**: All actions are logged (TODO: implement audit logging for Super Admin actions)
5. **Input Validation**: All requests are validated using Form Request classes

## Error Handling

All endpoints return consistent error responses:
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message"
  }
}
```

Common error codes:
- `UNAUTHORIZED`: Invalid or missing authentication
- `TENANT_NOT_FOUND`: Hospital not found
- `ALREADY_SUSPENDED`: Hospital is already suspended
- `ALREADY_ACTIVE`: Hospital is already active
- `CREATION_FAILED`: Failed to create hospital
- `SUSPENSION_FAILED`: Failed to suspend hospital
- `UPDATE_FAILED`: Failed to update settings/feature flags

## Testing

Comprehensive test suite covers:
- ✅ Listing hospitals with pagination
- ✅ Creating new hospitals
- ✅ Suspending hospitals
- ✅ Activating hospitals
- ✅ Updating system settings
- ✅ Getting feature flags
- ✅ Updating feature flags
- ✅ Unauthorized access without token

## Requirements Mapping

This implementation satisfies the following requirements from the spec:

**Requirement 2.1**: Super Admin can list all registered hospitals with their status
**Requirement 2.2**: Super Admin can create new hospitals and provision tenants
**Requirement 2.3**: Super Admin can suspend hospitals and revoke all active sessions
**Requirement 2.4**: Super Admin can view system-wide audit logs with filters
**Requirement 2.5**: Super Admin can configure system-wide settings
**Requirement 2.6**: Super Admin can toggle feature flags
**Requirement 2.7**: Super Admin has full control over the platform

## Future Enhancements

1. **Onboarding Email**: Implement email dispatch in `TenantProvisioningJob`
2. **Super Admin Audit Logging**: Log all Super Admin actions to a central audit log
3. **Bulk Operations**: Add endpoints for bulk tenant operations
4. **Advanced Filtering**: Add more filter options for hospitals list
5. **Tenant Metrics**: Add endpoint to get tenant usage statistics
6. **Feature Flag Validation**: Validate feature flag keys against a whitelist
7. **Rate Limiting**: Implement rate limiting for Super Admin endpoints
8. **Webhook Support**: Add webhooks for tenant lifecycle events

## Notes

- The `TenantProvisioningJob` runs asynchronously via Laravel Queue
- Audit log aggregation may be slow with many tenants (consider optimization)
- Feature flags are stored as system settings with 'feature_' prefix
- Token invalidation during suspension is immediate and affects all users
- Super Admin authentication is separate from tenant user authentication
