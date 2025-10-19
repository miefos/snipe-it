# Custom Routes

This directory contains custom route modifications that are separate from the core Snipe-IT application.

## Files

- **custom.php** - Contains all custom routes for local/organization-specific features

## Purpose

Custom routes are kept separate to:
1. **Avoid merge conflicts** during Snipe-IT version upgrades
2. **Clearly identify** which routes are custom additions
3. **Simplify maintenance** of custom features
4. **Document custom functionality** in one place

## How It Works

The `custom.php` file is loaded by `app/Providers/RouteServiceProvider.php` after all core routes. The file is conditionally loaded (checks if it exists) to ensure the application continues to work even if the file is missing.

## Adding New Custom Routes

To add new custom routes:

1. Edit `routes/web/custom.php`
2. Add your routes within the appropriate middleware groups
3. Document your routes with comments
4. Create corresponding controllers in a clearly marked custom directory or with clear naming

## Upgrading Snipe-IT

When upgrading Snipe-IT:

1. Your custom routes in `custom.php` will not be affected
2. The modification to `RouteServiceProvider.php` is minimal and easy to reapply if needed
3. Custom controllers in `app/Http/Controllers/` should be clearly named (e.g., `MtlDocumentController.php`)

## Current Custom Features

### MTL Document Generation
- **Route:** `GET /users/{userId}/mtl`
- **Controller:** `MtlDocumentController`
- **Purpose:** Generate Latvian MTL (Material Transfer List) documents for asset transfers
- **Added:** 2025-10-19
