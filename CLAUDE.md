# E-Ride — Backend & Admin Panel (Laravel)

## What this is

The single backend for the E-Ride ride-hailing + parcel-delivery platform (DriveMond-family, v3.2).
It serves three clients:

- **Admin panel** — server-rendered Blade at `http://localhost/e-ride/admin`
- **Customer app** — Flutter, at `c:\Users\Kento Java\AndroidStudioProjects\E-ride_userapp`
  (has its own `CLAUDE.md`), talking to `api/customer/*`
- **Driver app** — separate Flutter repo, not on this machine, talking to `api/driver/*`

Laravel 12 / PHP 8.2, modular via `nwidart/laravel-modules` v12.

## Where things live

**Domain code is in `Modules/`, not `app/`.** `app/` holds only cross-cutting pieces: `Events/`,
`Broadcasting/`, `Listeners/`, `Jobs/`, `Lib/`, `Library/`, `Traits/`, `WebSockets/`, and a handful
of controllers (landing page, blog, payment records, parcel tracking).

Bootstrapping is Laravel 11+ style — **`bootstrap/app.php`, there is no `app/Http/Kernel.php`.**
Middleware groups and aliases are registered there, including the custom ones:

- `admin` → `Modules\AdminModule\Http\Middleware\AdminMiddleware`
- `maintenance_mode` → `App\Http\Middleware\MaintenanceModeMiddleware`

Global constants and helpers are autoloaded as `composer.json` → `autoload.files`, so they are used
as bare globals in controllers and Blade:

| File | Provides |
|---|---|
| `app/Lib/Constant.php` | `PENDING`, `ACCEPTED`, `ONGOING`, `COMPLETED`, `CANCELLED`, `RETURNING`, `RETURNED`, `OUT_FOR_PICKUP`, `SCHEDULED`, `APPROVED`, `DENIED`, `REFUNDED`, `ADMIN_USER_TYPES`, `CUSTOMER`, `DRIVER`, plus date/format constants |
| `app/Lib/Helpers.php` | `translate()`, notification senders, misc helpers |
| `app/Lib/Response.php` | standard API envelope |
| `app/Lib/ReverbPusherHelpers.php` | `areAllBroadcastServicesRunning()` and friends |
| `Modules/ZoneManagement/Lib/Zone.php` | zone lookup helpers |
| `Modules/BusinessManagement/Lib/BusinessSettingType.php` | settings key constants |
| `Modules/Gateways/Library/Constant.php` | payment gateway constants |

Editing a constant here changes behavior everywhere — grep before you touch one.

## Module map (16)

Each module repeats the same internal layout:
`Entities/` (Eloquent models) · `Http/Controllers/{Api,Web}/` · `Repository/` · `Service/` ·
`Routes/{web,api}.php` · `Resources/views/` · `Database/Migrations/` · `Providers/`.

| Module | Owns |
|---|---|
| `AdminModule` | dashboard, heat map, fleet map view, activity log, admin settings, admin notifications, the shared admin layout + sidebar |
| `AuthManagement` | admin login (+ captcha) and app auth endpoints |
| `UserManagement` | `User` and everything around it — customers, drivers, employees, roles, user levels, wallets, withdraw methods/requests, identity verification, referrals, addresses, last locations |
| `TripManagement` | the core: `TripRequest`, fees, times, coordinates, routes, statuses, fare bidding, safety alerts, parcel refunds |
| `ParcelManagement` | parcel categories, weights, parcel/sender/receiver info |
| `VehicleManagement` | vehicles + brand/model/category attributes, new-vehicle and update approval queues |
| `ZoneManagement` | polygon zones (`matanyadaev/laravel-eloquent-spatial`) and per-zone extra fare |
| `FareManagement` | trip fare, parcel fare, surge pricing (zones × time slots × service categories) |
| `PromotionManagement` | banners, coupons, discounts, push notification campaigns |
| `TransactionManagement` | transactions, earning and expense reports |
| `BusinessManagement` | **the settings hub** — every business/config/system setting, landing page builder, languages |
| `ChattingManagement` | channels, conversations, files (customer↔driver, admin↔driver) |
| `ReviewModule` | trip reviews and review exports |
| `BlogManagement` | blog posts, categories, drafts, blog page settings |
| `AiModule` | OpenAI-backed generation (blog titles, descriptions, SEO) |
| `Gateways` | payment gateways — Stripe, Razorpay, Paystack, MercadoPago, Xendit, Iyzico, and more |

## The admin panel (`/admin`)

**Routing convention, and the thing that surprises people:** every module declares its *own*
`Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'])` in its
`Routes/web.php`. There is no single admin route file — to find a screen, start from the sidebar.

`Modules/AdminModule/Resources/views/partials/_sidebar.blade.php` is the panel's table of contents;
it renders inside `Modules/AdminModule/Resources/views/layouts/master.blade.php`.

**Auth and roles.** `web` guard = session. `AdminMiddleware` admits only
`auth()->user()->user_type` of `super-admin` or `admin-employee`, else redirects to
`admin.auth.login`. Login is the one route group *without* the `admin` middleware, in
`Modules/AuthManagement/Routes/web.php`: `admin/auth/login` plus a Gregwar captcha at
`admin/auth/code/captcha/{tmp}`. Employee permissions are per-role via `UserManagement`'s
`Role` / `RoleUser` / `ModuleAccess`.

**Standard resource verbs.** Nearly every admin resource exposes the same set — treat this as the
template when adding one:

```
index · create · store · edit/{id} · update/{id} · delete/{id} · status
log · export · trash|trashed · restore/{id} · permanent-delete/{id}
```

Soft deletes plus a trash view are the norm; mutations write `AdminModule`'s `ActivityLog`
(surfaced at `admin.log` and each module's `.log` route); exports use `maatwebsite/excel` +
`rap2hpoutre/fast-excel`; invoices and PDFs use dompdf/mpdf.

**Sidebar → module**, for orientation:

| Sidebar section | Routes declared in |
|---|---|
| Dashboard / Heat Map / Fleet View | `AdminModule` |
| Zone Management | `ZoneManagement` |
| Trip Management (trips by status, parcel refunds, safety alerts) | `TripManagement` |
| Promotion Management (banners, coupons, discounts, send notification) | `PromotionManagement` |
| User Management (customers, drivers, levels, withdrawals, employees, wallet bonus, newsletter) | `UserManagement` |
| Parcel Management | `ParcelManagement` |
| Vehicles Management | `VehicleManagement` |
| Fare Management (trip fare, parcel fare, surge pricing) | `FareManagement` |
| Transactions & Reports | `TransactionManagement` |
| Help & Support (chatting) | `AdminModule` + `ChattingManagement` |
| Blog Management | `BlogManagement` (+ `AiModule` for generation) |
| Business Management (business setup, pages & media, configurations, system settings, languages) | `BusinessManagement` |

All admin-facing strings go through `translate()`; language files are editable from
Business Management → System Settings → Languages, which also offers auto-translate.

## API (mobile apps)

Routes are in each module's `Routes/api.php`, prefixed `api/customer/...` or `api/driver/...`,
guarded by `['auth:api', 'maintenance_mode']`.

- **Auth**: `api` guard = **Laravel Passport**. Tokens are issued by `Modules/AuthManagement`
  (`api/customer/auth/*`).
- **`zoneId` request header**: the apps send it on every call and much of the system is scoped by it
  (fare lookup, surge, vehicle categories, driver matching). When an endpoint returns empty results,
  check the zone before the query.
- **Trip lifecycle** is owned by
  `Modules/TripManagement/Http/Controllers/Api/Customer/TripRequestController.php` and its driver
  counterpart in `.../Api/Driver/`: `get-estimated-fare → create → bidding-list / ignore-bidding →
  trip-action → update-status/{id} → final-fare → payment`, with `ride-resume-status` for recovering
  an in-flight trip.
  Note: `Modules/TripManagement/Routes/api.php` imports these two classes under the aliases
  `NewCustomerTripController` and `NewDriverTripController` — those names exist only in that file,
  there is no class by either name.
- **App configuration** is
  `Modules/BusinessManagement/Http/Controllers/Api/Customer/ConfigController` —
  `api/customer/configuration` plus the `config/*` helpers (zone lookup, place autocomplete,
  geocode, routes, payment methods, cancellation and safety reason lists). Google Maps is proxied
  server-side using the key set in the admin panel, so map failures are usually an admin
  configuration problem rather than app code.

**Admin settings reach the apps only through these config endpoints.** A fare edited under Fare
Management changes `get-estimated-fare` on the next call; a toggle under Business Setup changes what
`api/customer/configuration` returns.

## Realtime

**Laravel Reverb** (`BROADCAST_DRIVER=reverb`, port 6001), Pusher wire-protocol compatible — the
Flutter clients connect with `dart_pusher_channels`.

Three pieces must exist for any broadcast:

1. an event in `app/Events/` (~27 exist: `CustomerTripRequestEvent`, `DriverTripAcceptedEvent`,
   `DriverTripStartedEvent`, `DriverTripCompletedEvent`, `DriverPaymentReceivedEvent`, chat and
   coupon events, and more)
2. an authorization class in `app/Broadcasting/`
3. a registration in `routes/channels.php`

Miss the third and the client silently never receives the event. Push notifications — the
out-of-app path for the same events — go through `kreait/firebase-php`; SMS through Twilio and the
other gateways configured in the admin panel.

## Local development

- Served by **XAMPP** from `C:\xampp\htdocs\e-ride`, so the app URL includes the subdirectory:
  `http://localhost/e-ride`. The root `.htaccess` and `index.php` handle that. Do not assume a bare
  `php artisan serve` on `http://localhost:8000` — it drops the `/e-ride` prefix that `.env`
  (`APP_URL`) and the mobile apps expect.
- MySQL database `e-ride`, user `root`. `CACHE_DRIVER=file`, `SESSION_DRIVER=file`,
  `QUEUE_DRIVER=sync` (jobs run inline — there is no worker to start).
- Reverb must run separately for realtime: `php artisan reverb:start`.
- After touching config, routes, or the `composer.json` autoload files: `php artisan optimize:clear`
  (and `composer dump-autoload` for the latter).
- `modules_statuses.json` enables and disables modules.
- **`.env` in this working copy holds live third-party credentials** (payment gateways, Firebase,
  Google Maps, purchase code). Never commit it, paste it, or send it to an external service.

## Conventions

- Controllers depend on **service interfaces**, services on **repository interfaces**; the bindings
  live in each module's `Providers/RepositoryServiceProvider.php`. Add both the interface and the
  binding when you add a repository or service.
- `translate()` for every admin-facing string.
- Soft deletes + trash/restore + `ActivityLog` for admin resources.
- Status values come from `app/Lib/Constant.php` — never a string literal — and they must stay in
  sync with the Flutter apps' `AppConstants`.
