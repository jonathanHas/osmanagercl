# Test Pages Registry

All test/debug pages in the project. These are development aids that can be removed when no longer needed.

**Test Hub**: `/tests/hub` — central dashboard linking to all test pages.

## Labels & Printing

| Route | Name | Purpose | Files to Remove |
|-------|------|---------|-----------------|
| `/labels/barcode-scan-test` | `labels.barcode-scan-test` | Live camera barcode scanning | `resources/views/labels/barcode-scan-test.blade.php`, route in `web.php` |
| `/labels/camera-test` | `labels.camera-test` | v1: Raw ZPL via Gemini photo upload | `resources/views/labels/camera-test.blade.php`, `LabelAreaController@cameraTest`, `@uploadPhoto` |
| `/labels/camera-test2` | `labels.camera-test2` | v2: JSON-based Gemini translation | `resources/views/labels/camera-test2.blade.php`, `LabelAreaController@cameraTest2`, `@uploadPhoto2` |
| `/labels/zpl-debug` | `labels.zpl-debug` | ZPL renderer diagnostics | `resources/views/labels/zpl-debug.blade.php`, route in `web.php` |
| `/labels/test-print` (POST) | `labels.test-print` | Zebra printer connectivity test | `LabelAreaController@testPrint`, route in `web.php` |
| `/zebra-labels/create` | `zebra-labels.create` | Upload & print ZebraDesigner .prn exports | `app/Http/Controllers/ZebraLabelController.php`, `resources/views/zebra-labels/`, `app/Models/ZebraLabel.php`, migration, routes in `web.php` |

## Roles & Authentication

| Route | Name | Purpose | Files to Remove |
|-------|------|---------|-----------------|
| `/roles-test` | `roles.test` | Role-based access control testing | `app/Http/Controllers/RoleTestController.php`, `resources/views/roles/test.blade.php`, routes |
| `/roles-test/admin-only` | `roles.admin-only` | Admin-only access test | See RoleTestController |
| `/roles-test/manager-only` | `roles.manager-only` | Manager-only access test | See RoleTestController |
| `/roles-test/sales-reports` | `roles.sales-reports` | Sales reports access test | See RoleTestController |
| `/tests/authentication-test` | `tests.authentication-test` | Auth system testing | `app/Http/Controllers/AuthenticationTestController.php`, `resources/views/tests/authentication-test.blade.php` |

## Products & Suppliers

| Route | Name | Purpose | Files to Remove |
|-------|------|---------|-----------------|
| `/products/independent-test` | `products.independent-test` | Independent delivery integration | `app/Http/Controllers/IndependentTestController.php`, `resources/views/products/independent-test.blade.php` |
| `/tests/specific-product-test` | `tests.specific-product-test` | Product lookup debugging | `app/Http/Controllers/SpecificProductTestController.php`, `resources/views/tests/specific-product-test.blade.php` |
| `/tests/customer-price/{code}` | `tests.customer-price` | Scraper price comparison | `TestScraperController@testCustomerPrice`, `resources/views/tests/customer-price-debug.blade.php` |

## Language & Search

| Route | Name | Purpose | Files to Remove |
|-------|------|---------|-----------------|
| `/tests/language-debug` | `tests.language-debug` | Language control testing | `app/Http/Controllers/LanguageDebugController.php`, `resources/views/tests/language-debug.blade.php` |
| `/tests/language-flag-test` | `tests.language-flag-test` | Language flag display | `app/Http/Controllers/LanguageFlagTestController.php`, `resources/views/tests/language-flag-test.blade.php` |
| `/tests/english-search-test` | `tests.english-search-test` | English search functionality | `app/Http/Controllers/EnglishSearchTestController.php`, `resources/views/tests/english-search-test.blade.php` |

## Scraper & Integrations

| Route | Name | Purpose | Files to Remove |
|-------|------|---------|-----------------|
| `/tests/guzzle` | `tests.guzzle` | Server-side Udea login + Guzzle | `TestScraperController@guzzleLogin`, `resources/views/tests/guzzle.blade.php` |
| `/tests/client` | `tests.client` | Client-side fetch testing | `TestScraperController@clientFetch`, `resources/views/tests/client.blade.php` |
| `/tests/dashboard` | `tests.dashboard` | Udea scraper dashboard | `TestScraperController@dashboard`, `resources/views/tests/dashboard.blade.php` |

## UI Components

| Route | Name | Purpose | Files to Remove |
|-------|------|---------|-----------------|
| `/tests/phase2-components` | `tests.phase2-components` | Phase 2 UI component testing | `resources/views/test-phase2.blade.php`, route in `web.php` |
| `/tests/tab-group` | `tests.tab-group` | Tab group component testing | `resources/views/test-tab-group.blade.php`, route in `web.php` |

## Debug Routes

| Route | Name | Purpose | Files to Remove |
|-------|------|---------|-----------------|
| `/debug/suppliers` | (none) | Supplier table debugging | `resources/views/debug/suppliers.blade.php`, closure in `web.php` |
| `/debug/product-suppliers` | (none) | Product-supplier data debugging | `resources/views/debug/product-suppliers.blade.php`, closure in `web.php` |

## Orphaned Views (no route)

| File | Notes |
|------|-------|
| `resources/views/products/supplier-test.blade.php` | No route points to this file — safe to delete |

## Test Hub

| Route | Name | Purpose | Files to Remove |
|-------|------|---------|-----------------|
| `/tests/hub` | `tests.hub` | Central test page index | `resources/views/tests/hub.blade.php`, route in `web.php` |
