# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Snipe-IT is an open-source asset management system built on Laravel 11. It manages IT assets including hardware, software licenses, consumables, components, and accessories with features like check-in/check-out tracking, depreciation, maintenance records, and user assignments.

## Key Technologies

- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: AdminLTE 2, Bootstrap 3, jQuery, Livewire 3
- **Build Tools**: Laravel Mix, Webpack 5
- **Database**: MySQL/MariaDB (primary), SQLite (testing)
- **Authentication**: Laravel Passport (OAuth2), SAML, LDAP, Google Auth
- **Assets**: LESS for styling, compiled to CSS via Laravel Mix

## Development Commands

### Installation & Setup
```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Create environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### Development Workflow
```bash
# Start development server
php artisan serve

# Compile assets for development
npm run dev

# Watch assets for changes
npm run watch

# Build assets for production
npm run prod
```

### Testing
```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Unit/AccessoryTest.php

# Run specific test group
php artisan test --group=ldap

# Exclude specific test group
php artisan test --exclude-group=ldap

# Setup test environment
cp .env.testing.example .env.testing
# Edit .env.testing with appropriate database settings
```

### Code Quality
```bash
# Static analysis (PHPStan)
vendor/bin/phpstan analyse

# Code style checking
vendor/bin/phpcs

# Psalm analysis
vendor/bin/psalm
```

## Architecture Overview

### MVC Structure

**Models** (`app/Models/`): Core entity models including:
- `Asset`: Primary asset tracking (hardware items)
- `AssetModel`: Asset model templates with depreciation
- `User`: Users with role-based permissions
- `License`: Software license management
- `Accessory`, `Component`, `Consumable`: Other trackable items
- `Company`: Multi-tenancy support via company scoping
- `Location`: Hierarchical location tracking
- `Actionlog`: Comprehensive audit trail of all actions

**Controllers** (`app/Http/Controllers/`):
- Standard Laravel resource controllers for each entity
- API controllers in `app/Http/Controllers/Api/` for REST API
- Separate controllers for checkout/checkin operations
- `DashboardController`: Main dashboard aggregation

**Views** (`resources/views/`):
- Blade templates using AdminLTE layout
- Shared partials in `resources/views/partials/`
- Livewire components in `app/Livewire/` for reactive UI

### Key Design Patterns

**Presenter Pattern**: All models use presenters (`app/Presenters/`) to format data for display. Models implement `Presentable` trait and call `$model->present()->propertyName` for formatted output.

**Policy-Based Authorization**: Laravel policies (`app/Policies/`) handle all authorization logic. Check permissions via `$this->authorize()` in controllers.

**Service Layer**: Complex business logic in `app/Services/`:
- `PredefinedKitCheckoutService`: Kit checkout operations
- `Saml`: SAML authentication
- `SnipeTranslator`: Custom translation handling

**Observers**: Model observers (`app/Observers/`) handle side effects like:
- Creating action logs on model changes
- Sending notifications
- Updating denormalized data

### Multi-Tenancy (Company Scoping)

Company scoping is implemented via:
- `CompanyableScope` and `CompanyableChildScope` traits
- Global scopes automatically filter queries by company
- Settings control whether company scoping is enabled
- Some models have hierarchical company relationships (e.g., assets inherit from locations)

### Custom Fields System

- Dynamic custom fields via `CustomField` and `CustomFieldset` models
- Stored as JSON in database tables
- Encryption support for sensitive custom fields via `*Encrypted` validation rules (`app/Rules/`)
- Field types include text, date, checkbox, listbox, etc.

### Notifications System

Extensive notification system (`app/Notifications/`):
- Checkout/checkin notifications for all asset types
- Expiring asset/license alerts
- Expected checkin reminders
- Inventory alerts
- Supports email, Slack, Microsoft Teams, Google Chat

### Import System

Livewire-based importer (`app/Livewire/Importer` and `app/Importer/`):
- CSV import for all entity types
- Field mapping with validation
- Preview before import
- Error handling and reporting

### API

RESTful JSON API (`routes/api.php`, `app/Http/Controllers/Api/`):
- OAuth2 authentication via Laravel Passport
- Comprehensive endpoints for all entities
- SCIM server support for user provisioning (`routes/scim.php`)
- API versioning via route prefixes

### Asset Lifecycle

Assets flow through statuses (deployable, pending, archived, etc.):
1. **Creation**: Asset created with model, status, location
2. **Checkout**: Asset checked out to user/location/asset
3. **Checkin**: Asset returned with optional notes
4. **Maintenance**: Maintenance records tracked separately
5. **Audit**: Periodic audits with next audit due dates
6. **Depreciation**: Automatic depreciation calculation based on asset model

### Action Logging

All significant actions logged to `action_logs` table:
- Entity type and ID
- Action type (checkout, checkin, create, update, delete, etc.)
- User performing action
- Target (assigned user/location/asset)
- Notes and metadata
- Remote IP and action source tracking

## Important Conventions

### Database

- Use migrations for all schema changes (`database/migrations/`)
- Migration naming: `YYYY_MM_DD_HHIISS_descriptive_name.php`
- Always add indexes for foreign keys and frequently queried columns
- Use `deleted_at` for soft deletes (never hard delete)
- Use `created_by` column to track creator (not `user_id`)

### Routes

- Web routes in `routes/web.php` use Blade views
- API routes in `routes/api.php` return JSON
- Route parameters use model name with `_id` suffix: `['parameters' => ['model' => 'model_id']]`
- All authenticated routes wrapped in `auth` middleware group

### Models

- Use `fillable` or `guarded` for mass assignment protection
- Define relationships explicitly (belongsTo, hasMany, etc.)
- Implement soft deletes via `SoftDeletes` trait where appropriate
- Use presenters for formatted output
- Add searchable fields to `$searchable` array for search functionality

### Testing

- Feature tests for HTTP endpoints
- Unit tests for business logic and models
- Use `.env.testing` for test environment configuration
- Tests use SQLite in-memory database by default
- Factory definitions in `database/factories/`
- Use `@group` annotations to organize test suites

### Frontend

- jQuery-based interactions (legacy, gradually moving to Livewire)
- Bootstrap 3 components
- Bootstrap Table for data tables with server-side processing
- Form validation via jQuery Validation plugin
- Assets compiled via Laravel Mix to `public/css/dist/` and `public/js/dist/`
- LESS files in `resources/assets/less/`

### Settings Management

- Application settings stored in `settings` table
- Access via `Setting` model facade-like pattern
- Settings cached for performance
- Settings divided into sections (app, security, labels, ldap, etc.)

## Common Development Patterns

### Adding a New Asset Type

1. Create migration for new table
2. Create model in `app/Models/` extending appropriate base
3. Create policy in `app/Policies/` for authorization
4. Create presenter in `app/Presenters/` for display formatting
5. Create controller in `app/Http/Controllers/` for web CRUD
6. Create API controller in `app/Http/Controllers/Api/` for REST API
7. Add routes to `routes/web.php` and `routes/api.php`
8. Create Blade views in `resources/views/[entity-name]/`
9. Add factory in `database/factories/` for testing
10. Write tests in `tests/Feature/` and `tests/Unit/`

### Adding Custom Validation Rule

1. Create rule class in `app/Rules/`
2. Implement `validate()` method
3. For encrypted field validation, extend existing `*Encrypted` rules
4. Use in form requests or controller validation

### Adding a New Setting

1. Create migration to add column to `settings` table
2. Add getter/setter methods to `app/Models/Setting` if needed
3. Add form field in `resources/views/settings/` blade templates
4. Update `app/Http/Controllers/SettingsController` to save setting

### Debugging

- Laravel Debugbar included in development (`barryvdh/laravel-debugbar`)
- Laravel Telescope available for request inspection (needs manual enabling)
- Check `storage/logs/laravel.log` for application logs
- Use `Log::debug()`, `Log::info()`, etc. for debugging
- Database query logging via Debugbar or Telescope

## File Locations

- **Uploads**: `storage/app/private_uploads/` (private), `public/uploads/` (public)
- **Logs**: `storage/logs/`
- **Cache**: `storage/framework/cache/`
- **Session**: `storage/framework/sessions/` (if file driver)
- **Compiled Views**: `storage/framework/views/`
- **Asset Compilation**: `public/css/dist/`, `public/js/dist/`

## Security Considerations

- Report security vulnerabilities to security@snipeitapp.com (never via GitHub issues)
- All user input sanitized via Laravel validation
- SQL injection protection via Eloquent ORM
- XSS protection via Blade's `{{ }}` escaping
- CSRF protection on all forms via `@csrf` directive
- Password encryption via bcrypt
- Sensitive custom fields can be encrypted at rest
- API uses OAuth2 tokens via Laravel Passport
- Rate limiting on API endpoints
- Two-factor authentication support via Google Authenticator

## Deployment Notes

- Set `APP_ENV=production` and `APP_DEBUG=false` in production
- Run `php artisan config:cache` and `php artisan route:cache` for performance
- Compile assets with `npm run prod`
- Set up queue workers for background jobs
- Configure cron for scheduled tasks: `* * * * * php artisan schedule:run`
- Use Redis/Memcached for cache and sessions in production
- Configure proper file permissions on `storage/` and `bootstrap/cache/`
