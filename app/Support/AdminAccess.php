<?php

namespace App\Support;

use App\Models\Staff;
use Illuminate\Support\Facades\Route;

/**
 * Who is signed in to the admin panel and what they may do.
 *
 *  - Owner: signs in with ADMIN_USER / ADMIN_PASSWORD (.env). Can do everything.
 *  - Staff: own username + password, can only use the tasks ticked for them.
 *
 * Every admin page is matched to one task by its route name (see routePermission()).
 * A page that is not listed there is owner-only, so a new page can never leak to staff by mistake.
 */
class AdminAccess
{
    const OPEN  = 'open';   // any signed-in person
    const OWNER = 'owner';  // owner only

    private static array $cache = [];

    /** The tasks the owner can tick for a staff member: key => [label, what it allows] */
    public static function permissions(): array
    {
        return [
            'dashboard'     => ['Dashboard',               'See the dashboard with sales numbers'],
            'orders_view'   => ['Orders: view',            'See orders and notifications'],
            'orders_manage' => ['Orders: change status',   'Confirm, cancel or deliver orders'],
            'orders_create' => ['Orders: create',          'Create an order for a customer and reorder an old order'],
            'claims_view'   => ['Claims: view',            'See exchange / wrong-product claims, their photos and videos'],
            'claims_manage' => ['Claims: manage',          'Open claims, approve or reject, add evidence and shipments, make replacement orders'],
            'claims_inspect' => ['Claims: inspect returned goods', 'Decide what happens to returned goods (back to stock, supplier, scrap) and inspect warranty products'],
            'aftersales_approve' => ['Warranty: accept / reject / choose solution', 'Accept or reject a warranty claim and choose repair or replacement'],
            'courier'       => ['Courier',                 'Send orders to Steadfast, tracking, returns'],
            'payments'      => ['Payments',                'Record payments, verify bKash payments'],
            'invoices'      => ['Invoices',                'Create and print invoices'],
            'products'      => ['Products',                'Products, categories, brands and reviews'],
            'customers'     => ['Customers',               'Customer list and history'],
            'coupons'       => ['Coupons',                 'Create and edit coupons'],
            'reports'       => ['Reports',                 'Sales, profit and order reports'],
            'accounting'    => ['Accounting',              'Income, expense, accounts and ledger'],
        ];
    }

    /** Which task a route needs. First match wins. */
    public static function routePermission(string $routeName): string
    {
        $map = [
            'admin.logout'          => self::OPEN,
            'admin.dashboard'       => 'dashboard',
            'admin.orders.status'   => 'orders_manage',
            'admin.orders.charges'  => 'orders_manage',
            // Phase 4: these must stay ABOVE 'admin.orders.*' (first match wins)
            'admin.orders.create'   => 'orders_create',
            'admin.orders.create.*' => 'orders_create',
            'admin.orders.store'    => 'orders_create',
            // Phase 5: claims. Specific routes first, the catch-all "admin.claims.*" last (first match wins)
            'admin.claims.index'    => 'claims_view',
            'admin.claims.show'     => 'claims_view',
            'admin.claims.evidence.show' => 'claims_view',
            'admin.claims.inspect'  => 'claims_inspect',
            // Phase 6: warranty. Only people with "aftersales_approve" decide a warranty claim (the owner always can)
            'admin.claims.warranty.accept'  => 'aftersales_approve',
            'admin.claims.warranty.reject'  => 'aftersales_approve',
            'admin.claims.warranty.resolve' => 'aftersales_approve',
            'admin.claims.warranty.inspect' => 'claims_inspect',
            'admin.claims.refund'   => 'payments',        // giving money back also needs the Payments task
            'admin.claims.order'    => 'orders_create',   // a replacement / exchange order is an order
            'admin.claims.*'        => 'claims_manage',
            'admin.bkash.verify'    => 'payments',
            'admin.orders.*'        => 'orders_view',
            'admin.notifications.*' => 'orders_view',
            'admin.courier.*'       => 'courier',
            'admin.payments.*'      => 'payments',
            'admin.invoices.*'      => 'invoices',
            'admin.products.*'      => 'products',
            'admin.categories.*'    => 'products',
            'admin.brands.*'        => 'products',
            'admin.reviews.*'       => 'products',
            'admin.customers.*'     => 'customers',
            'admin.coupons.*'       => 'coupons',
            'admin.reports.*'       => 'reports',
            'admin.accounting.*'    => 'accounting',
            'admin.settings.*'      => self::OWNER,
            'admin.menu.*'          => self::OWNER,
            'admin.staff.*'         => self::OWNER,
        ];

        foreach ($map as $pattern => $permission) {
            if (fnmatch($pattern, $routeName)) {
                return $permission;
            }
        }

        return self::OWNER;
    }

    public static function allows(string $routeName): bool
    {
        $permission = self::routePermission($routeName);

        return $permission === self::OPEN || self::can($permission);
    }

    public static function signedIn(): bool
    {
        return (bool) session('admin_ok');
    }

    public static function isOwner(): bool
    {
        return self::signedIn() && !session('admin_staff_id');
    }

    /** The signed-in staff member, or null for the owner / nobody */
    public static function staff(): ?Staff
    {
        $id = session('admin_staff_id');
        if (!$id) {
            return null;
        }

        if (!array_key_exists($id, self::$cache)) {
            self::$cache[$id] = Staff::tableReady() ? Staff::find($id) : null;
        }

        return self::$cache[$id];
    }

    /** "Owner" or the staff member's name: this is what is written next to a courier order */
    public static function name(): string
    {
        $staff = self::staff();

        return $staff ? $staff->name : 'Owner';
    }

    public static function can(?string $permission): bool
    {
        if (!self::signedIn()) {
            return false;
        }
        if (self::isOwner() || $permission === null || $permission === self::OPEN) {
            return true;
        }
        if ($permission === self::OWNER) {
            return false;
        }

        $staff = self::staff();

        return $staff && $staff->is_active && $staff->can($permission);
    }

    /** First page this person may open (used after login and instead of an empty dashboard) */
    public static function firstAllowedUrl(): ?string
    {
        $pages = [
            'dashboard'   => 'admin.dashboard',
            'orders_view' => 'admin.orders.index',
            'claims_view' => 'admin.claims.index',
            'courier'     => 'admin.courier.steadfast',
            'payments'    => 'admin.payments.index',
            'invoices'    => 'admin.invoices.index',
            'products'    => 'admin.products.index',
            'customers'   => 'admin.customers.index',
            'coupons'     => 'admin.coupons.index',
            'reports'     => 'admin.reports.daily',
            'accounting'  => 'admin.accounting.dashboard',
        ];

        foreach ($pages as $permission => $route) {
            if (self::can($permission) && Route::has($route)) {
                return route($route);
            }
        }

        return null;
    }
}
