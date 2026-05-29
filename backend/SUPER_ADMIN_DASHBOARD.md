# Super Admin Dashboard

## Overview

The Super Admin Dashboard is a Vue.js 3 + Inertia.js web interface for managing the Hospital Health Management System platform. It provides centralized control over all hospitals, system settings, and audit logs.

## Features

### 1. Hospital Management Dashboard
- **URL**: `/admin/dashboard`
- **Features**:
  - View all registered hospitals with status badges (active, suspended, inactive)
  - Search hospitals by name or subdomain
  - Filter hospitals by status
  - Suspend/activate hospitals with confirmation modals
  - Pagination support

### 2. Create Hospital Form
- **URL**: `/admin/hospitals/create`
- **Features**:
  - Create new hospital tenants
  - Assign subscription plans
  - Configure hospital admin credentials
  - Automatic tenant provisioning
  - Form validation with error messages

### 3. Audit Logs Viewer
- **URL**: `/admin/audit-logs`
- **Features**:
  - View system-wide audit logs from all tenants
  - Filter by date range (start date, end date)
  - Filter by hospital name
  - Filter by action type
  - Export logs to CSV
  - Pagination support

### 4. System Settings
- **URL**: `/admin/settings`
- **Features**:
  - Toggle feature flags (SMS notifications, Lab module, Pharmacy module, Appointments, Mobile app)
  - Configure mobile app version enforcement
  - Set API rate limits
  - Real-time updates with success/error feedback

## Authentication

### Login
- **URL**: `/admin/login`
- Session-based authentication for web dashboard
- JWT authentication for API endpoints

### Logout
- **Endpoint**: `POST /admin/logout`
- Clears session and redirects to login page

## Technical Implementation

### Frontend Stack
- **Vue.js 3**: Reactive UI framework
- **Inertia.js**: Server-side routing with SPA experience
- **Axios**: HTTP client for API calls
- **Vite**: Build tool and dev server

### Backend Stack
- **Laravel**: PHP framework
- **Controllers**:
  - `SuperAdminWebController`: Handles Inertia page rendering
  - `SuperAdminAuthController`: Handles web authentication
- **Middleware**:
  - `SuperAdminAuthMiddleware`: Validates session for web routes, JWT for API routes

### File Structure
```
backend/
├── app/Http/Controllers/
│   ├── SuperAdminWebController.php
│   └── SuperAdminAuthController.php
├── resources/js/
│   ├── Layouts/
│   │   └── SuperAdminLayout.vue
│   └── Pages/SuperAdmin/
│       ├── Dashboard.vue
│       ├── CreateHospital.vue
│       ├── AuditLogs.vue
│       ├── Settings.vue
│       └── Login.vue
└── routes/
    └── web.php
```

## API Integration

The dashboard integrates with existing API endpoints:

### Hospital Management
- `GET /api/v1/admin/hospitals` - List hospitals
- `POST /api/v1/admin/hospitals` - Create hospital
- `PATCH /api/v1/admin/hospitals/{id}/suspend` - Suspend hospital
- `PATCH /api/v1/admin/hospitals/{id}/activate` - Activate hospital

### Audit Logs
- `GET /api/v1/admin/audit-logs` - Fetch audit logs with filters

### System Settings
- `GET /api/v1/admin/feature-flags` - Get feature flags
- `PUT /api/v1/admin/feature-flags/{key}` - Update feature flag
- `PUT /api/v1/admin/settings` - Update system settings

## Development

### Running the Dev Server
```bash
cd backend
npm run dev
```

### Building for Production
```bash
cd backend
npm run build
```

## Security Considerations

1. **Session-based auth** for web routes prevents CSRF attacks
2. **JWT validation** for API endpoints ensures stateless authentication
3. **Middleware protection** on all Super Admin routes
4. **Confirmation modals** for destructive actions (suspend/activate)
5. **Input validation** on all forms

## Future Enhancements

- [ ] Super Admin user management (create/edit/delete super admins)
- [ ] Advanced analytics dashboard
- [ ] Real-time notifications via WebSocket
- [ ] Bulk operations on hospitals
- [ ] Advanced audit log filtering and search
- [ ] System health monitoring dashboard
