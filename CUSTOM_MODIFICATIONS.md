# Custom Modifications to Snipe-IT

This document tracks custom modifications made to this Snipe-IT installation to help with maintenance and upgrades.

## Custom Files (Safe from Upgrades)

These files are completely custom and won't be affected by Snipe-IT upgrades:

### Routes
- `routes/web/custom.php` - Custom routes file
- `routes/web/CUSTOM_README.md` - Documentation for custom routes

### Controllers
- `app/Http/Controllers/MtlDocumentController.php` - MTL document generation controller

### Templates
- `storage/templates/mtl_template_v1.docx` - MTL document template

## Modified Core Files (Needs Attention During Upgrades)

These are core Snipe-IT files that have been modified. During upgrades, these changes need to be reapplied:

### 1. `app/Providers/RouteServiceProvider.php`
**Change:** Added custom routes loader in `mapWebRoutes()` method

**Lines modified:** Around line 56-59

**Code to preserve:**
```php
// Custom routes - local modifications (to avoid merge conflicts during upgrades)
if (file_exists(base_path('routes/web/custom.php'))) {
    require base_path('routes/web/custom.php');
}
```

**Why:** Loads custom routes without modifying core route files

**Upgrade impact:** MEDIUM - May need to reapply if this method is changed

---

### 2. `resources/views/users/view.blade.php`
**Change:** Added "Ģenerēt MTL lapu" button

**Lines modified:** Around line 245-254

**Code to preserve:**
```blade
@can('view', $user)
  <div class="col-md-12" style="padding-top: 5px;">
    <form action="{{ route('users.mtl', ['userId' => $user->id]) }}" method="GET">
      <button class="btn-block btn btn-sm btn-info btn-social hidden-print" rel="noopener">
          <x-icon type="download" />
          Ģenerēt MTL lapu
      </button>
    </form>
  </div>
@endcan
```

**Why:** Adds MTL document generation button to user profile page

**Upgrade impact:** HIGH - User view template may change frequently

---

## Git Recommendations

### Files to Exclude from Upgrades
Consider adding these to your local `.git/info/exclude` (won't affect others):
```
routes/web/custom.php
app/Http/Controllers/MtlDocumentController.php
storage/templates/mtl_template_v1.docx
```

### Backup Before Upgrading
Before upgrading Snipe-IT, backup these files:
```bash
cp app/Providers/RouteServiceProvider.php app/Providers/RouteServiceProvider.php.backup
cp resources/views/users/view.blade.php resources/views/users/view.blade.php.backup
```

## Upgrade Checklist

When upgrading Snipe-IT:

- [ ] 1. Backup custom files listed above
- [ ] 2. Note the line numbers of modifications in modified core files
- [ ] 3. Perform Snipe-IT upgrade
- [ ] 4. Check if `RouteServiceProvider.php` was changed
  - If yes, reapply the custom routes loader
- [ ] 5. Check if `resources/views/users/view.blade.php` was changed
  - If yes, reapply the MTL button
- [ ] 6. Verify custom routes still work: Visit a user profile and test MTL button
- [ ] 7. Clear cache: `php artisan route:clear && php artisan cache:clear`

## Feature: MTL Document Generation

**Added:** 2025-10-19

**Purpose:** Generate Latvian Material Transfer List documents when transferring assets between users

**Components:**
- Route: `GET /users/{userId}/mtl`
- Controller: `MtlDocumentController::generate()`
- View: Button in user profile page
- Template: Word document template with placeholders

**How it works:**
1. User views another user's profile
2. Clicks "Ģenerēt MTL lapu" button
3. System generates DOCX from template with:
   - Giver (nodod): Currently authenticated user
   - Receiver (pienem): User being viewed
   - Items list (currently placeholder data)
4. Document is saved to user's uploads
5. Document is downloaded to browser

**Future enhancements:**
- Replace placeholder items with actual assigned assets from database
- Add option to select specific assets to include
- Email document to both parties
