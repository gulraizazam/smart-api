# Appointments Module Optimization Documentation

## Overview
This document outlines the optimization performed on the Appointments module, including Consultations and Treatments. The optimization follows Laravel best practices and implements a clean, maintainable architecture.

## Optimization Date
January 20, 2026

## Phase 1: Consultations & Treatments Optimization

### Architecture Changes

#### 1. Service Layer Implementation
Business logic has been moved from controllers to dedicated service classes:

- **`App\Services\Appointment\AppointmentService`** - Core appointment business logic
- **`App\Services\Appointment\ConsultancyService`** - Consultancy-specific operations
- **`App\Services\Appointment\TreatmentService`** - Treatment-specific operations

**Key Features:**
- Centralized business logic
- Transaction management
- Proper exception handling
- Reusable methods across the application
- Dependency injection support

#### 2. Helper Classes
**`App\Helpers\AppointmentHelper`** - Utility functions for appointments

**Methods:**
- `prepareSMSContent()` - SMS template processing with eager loading
- `getNodeServices()` - Cached service tree retrieval
- `getCancelledStatus()` - Cached cancelled status lookup
- `formatScheduleData()` - Schedule data formatting
- `isChildExists()` - Check for related records
- `clearAppointmentCache()` - Cache invalidation
- `getAppointmentTypes()` - Cached appointment types
- `getAppointmentStatuses()` - Cached appointment statuses
- `validateScheduleConflict()` - Schedule conflict detection
- `prepareAppointmentData()` - Data preparation for create/update

**Caching Strategy:**
- 1-hour TTL for lookup data
- Cache tags for efficient invalidation
- Automatic cache clearing on data changes

#### 3. Exception Handling
**`App\Exceptions\AppointmentException`** - Custom exception class

**Methods:**
- `notFound()` - Appointment not found (404)
- `cannotDelete()` - Deletion prevented (422)
- `invalidStatus()` - Invalid status (422)
- `scheduleConflict()` - Schedule conflict (422)
- `unauthorized()` - Unauthorized access (403)
- And more...

#### 4. Form Request Validation
Validation logic extracted to dedicated Form Request classes:

- **`StoreAppointmentRequest`** - Create appointment validation
- **`UpdateAppointmentRequest`** - Update appointment validation
- **`UpdateAppointmentStatusRequest`** - Status update validation
- **`ScheduleAppointmentRequest`** - Schedule validation

**Benefits:**
- Centralized validation rules
- Automatic validation before controller methods
- Custom error messages
- Cleaner controller code

#### 5. Model Optimization
**`App\Models\Appointments`** - Enhanced with:

**Query Scopes:**
```php
- withRelations() - Eager load common relationships
- scheduled() - Filter scheduled appointments
- nonScheduled() - Filter non-scheduled appointments
- byAccount($account_id) - Filter by account
- byType($appointment_type_id) - Filter by type
- byStatus($appointment_status_id) - Filter by status
- byLocation($location_id) - Filter by location
- byDoctor($doctor_id) - Filter by doctor
- byPatient($patient_id) - Filter by patient
- excludeCancelled($account_id) - Exclude cancelled appointments
- today() - Today's appointments
- upcoming() - Future appointments
- dateRange($start, $end) - Date range filter
```

**Casts:**
```php
- scheduled_date => date
- first_scheduled_date => date
- arrived_at => datetime
- converted_at => datetime
- send_message => boolean
- active => boolean
```

**Optimizations:**
- Eager loading to prevent N+1 queries
- Query scopes for reusable filters
- Proper type casting
- Cached lookups
- Delegated methods to helper classes

#### 6. API Controllers
New RESTful API controllers following Laravel conventions:

**`App\Http\Controllers\Api\AppointmentsController`**
- `index()` - List appointments with filters
- `store()` - Create appointment
- `show($id)` - Get appointment details
- `update($id)` - Update appointment
- `destroy($id)` - Delete appointment
- `updateStatus($id)` - Update appointment status
- `schedule()` - Schedule appointment
- `scheduled()` - Get scheduled appointments
- `nonScheduled()` - Get non-scheduled appointments
- `statistics()` - Get appointment statistics

**`App\Http\Controllers\Api\ConsultancyController`**
- `index()` - List consultancies
- `store()` - Create consultancy
- `update($id)` - Update consultancy
- `scheduled()` - Get scheduled consultancies
- `nonScheduled()` - Get non-scheduled consultancies
- `statistics()` - Get consultancy statistics

**`App\Http\Controllers\Api\TreatmentController`**
- `index()` - List treatments
- `store()` - Create treatment
- `update($id)` - Update treatment
- `scheduled()` - Get scheduled treatments
- `nonScheduled()` - Get non-scheduled treatments
- `statistics()` - Get treatment statistics
- `availableResources()` - Get available resources
- `servicesByLocation()` - Get services by location

### API Routes

#### Appointments Routes
```
GET     /api/appointments                    - List appointments
POST    /api/appointments                    - Create appointment
GET     /api/appointments/{id}               - Get appointment
PUT     /api/appointments/{id}               - Update appointment
DELETE  /api/appointments/{id}               - Delete appointment
PUT     /api/appointments/{id}/status        - Update status
POST    /api/appointments/schedule           - Schedule appointment
GET     /api/appointments/scheduled/list     - Scheduled appointments
GET     /api/appointments/non-scheduled/list - Non-scheduled appointments
GET     /api/appointments/statistics/data    - Statistics
```

#### Consultancy Routes
```
GET     /api/consultancy                     - List consultancies
POST    /api/consultancy                     - Create consultancy
PUT     /api/consultancy/{id}                - Update consultancy
GET     /api/consultancy/scheduled/list      - Scheduled consultancies
GET     /api/consultancy/non-scheduled/list  - Non-scheduled consultancies
GET     /api/consultancy/statistics/data     - Statistics
```

#### Treatment Routes
```
GET     /api/treatment                       - List treatments
POST    /api/treatment                       - Create treatment
PUT     /api/treatment/{id}                  - Update treatment
GET     /api/treatment/scheduled/list        - Scheduled treatments
GET     /api/treatment/non-scheduled/list    - Non-scheduled treatments
GET     /api/treatment/statistics/data       - Statistics
GET     /api/treatment/resources/available   - Available resources
GET     /api/treatment/services/by-location  - Services by location
```

### Web Routes
Web routes have been simplified to only include view routes:
- `GET /admin/consultancy` - Consultancy index view
- `GET /admin/treatment` - Treatment index view

All data operations now use API endpoints.

### Database Optimizations

#### Eager Loading
Relationships are now eager loaded to prevent N+1 queries:
```php
$appointments = Appointments::with([
    'appointment_type',
    'appointment_status',
    'service',
    'location.city',
    'doctor',
    'patient',
    'lead'
])->get();
```

#### Query Scopes
Reusable query scopes reduce code duplication:
```php
$appointments = Appointments::byAccount($account_id)
    ->scheduled()
    ->excludeCancelled($account_id)
    ->withRelations()
    ->get();
```

#### Caching Strategy
- Lookup data cached for 1 hour
- Statistics cached for 5 minutes
- Cache automatically cleared on data changes
- Cache tags for efficient invalidation

### Performance Improvements

1. **Reduced Database Queries**
   - Eager loading eliminates N+1 queries
   - Cached lookups reduce repetitive queries
   - Query scopes optimize filtering

2. **Improved Response Times**
   - Caching reduces database load
   - Optimized queries with proper indexing
   - Efficient data retrieval

3. **Better Memory Usage**
   - Pagination support
   - Selective field loading
   - Efficient data structures

### Security Enhancements

1. **Authorization**
   - Gate-based permission checks
   - Proper exception handling
   - Unauthorized access prevention

2. **Validation**
   - Form Request validation
   - Type-safe operations
   - Input sanitization

3. **Data Integrity**
   - Transaction management
   - Proper error handling
   - Audit trail logging

### Code Quality Improvements

1. **Separation of Concerns**
   - Controllers handle HTTP requests
   - Services handle business logic
   - Models handle data access
   - Helpers provide utilities

2. **Reusability**
   - Service classes can be used anywhere
   - Helper functions are globally available
   - Query scopes reduce duplication

3. **Maintainability**
   - Clear code organization
   - Consistent naming conventions
   - Comprehensive documentation
   - Type hints and return types

4. **Testability**
   - Service layer can be unit tested
   - Controllers can be integration tested
   - Mocking support through dependency injection

### Migration Guide

#### For Frontend Developers

**Old API Calls:**
```javascript
// Old
GET /api/appointments/load/scheduled-appointments

// New
GET /api/appointments/scheduled/list
```

**New API Response Format:**
```json
{
    "status": 200,
    "message": "Appointments retrieved successfully.",
    "data": {
        "current_page": 1,
        "data": [...],
        "total": 100
    }
}
```

#### For Backend Developers

**Old Controller Usage:**
```php
// Old - Business logic in controller
public function store(Request $request) {
    $appointment = new Appointments();
    $appointment->fill($request->all());
    $appointment->save();
    // ... more logic
}
```

**New Service Usage:**
```php
// New - Use service class
public function store(StoreAppointmentRequest $request) {
    $appointment = $this->appointmentService->createAppointment(
        $request->validated()
    );
    return ApiHelper::apiResponse(200, 'Success', $appointment);
}
```

### Backward Compatibility

Legacy routes are maintained with deprecation notices:
- All old API endpoints still work
- Marked as "To be deprecated"
- Will be removed in future version
- Migrate to new endpoints recommended

### Testing Recommendations

1. **Unit Tests**
   - Test service methods
   - Test helper functions
   - Test model scopes

2. **Integration Tests**
   - Test API endpoints
   - Test authentication/authorization
   - Test data validation

3. **Performance Tests**
   - Test query performance
   - Test cache effectiveness
   - Test response times

### Future Enhancements (Phase 2)

1. **Complete API Migration**
   - Remove all web-based CRUD operations
   - Full API-based implementation
   - Remove legacy routes

2. **Additional Optimizations**
   - Invoice management optimization
   - SMS integration optimization
   - Reporting optimization

3. **Advanced Features**
   - Real-time notifications
   - Advanced scheduling algorithms
   - AI-based conflict resolution

### File Structure

```
app/
├── Exceptions/
│   └── AppointmentException.php
├── Helpers/
│   └── AppointmentHelper.php
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   └── AppointmentsController.php (Legacy - to be refactored)
│   │   └── Api/
│   │       ├── AppointmentsController.php (New)
│   │       ├── ConsultancyController.php (New)
│   │       └── TreatmentController.php (New)
│   └── Requests/
│       └── Appointment/
│           ├── StoreAppointmentRequest.php
│           ├── UpdateAppointmentRequest.php
│           ├── UpdateAppointmentStatusRequest.php
│           └── ScheduleAppointmentRequest.php
├── Models/
│   └── Appointments.php (Optimized)
└── Services/
    └── Appointment/
        ├── AppointmentService.php
        ├── ConsultancyService.php
        └── TreatmentService.php

routes/
└── api.php (Updated with new routes)

docs/
└── appointments-module-optimization.md (This file)
```

### Best Practices Applied

1. **Laravel Conventions**
   - RESTful API design
   - Resource controllers
   - Form Request validation
   - Eloquent relationships

2. **SOLID Principles**
   - Single Responsibility
   - Open/Closed
   - Dependency Inversion

3. **Design Patterns**
   - Service Layer Pattern
   - Repository Pattern (via Eloquent)
   - Factory Pattern (for exceptions)

4. **Code Standards**
   - PSR-12 coding style
   - Type hints
   - DocBlocks
   - Meaningful names

### Support and Maintenance

For questions or issues:
1. Check this documentation
2. Review the code comments
3. Check the API response messages
4. Contact the development team

### Changelog

**Version 1.0.0 - January 20, 2026**
- Initial optimization of Consultations and Treatments
- Service layer implementation
- Helper classes creation
- Exception handling
- Form Request validation
- Model optimization with scopes
- API controllers creation
- Route optimization
- Documentation creation

---

**Note:** This is Phase 1 of the appointments module optimization. Phase 2 will include complete migration to API-based implementation and removal of all legacy code.
