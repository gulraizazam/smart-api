<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // Add extra fields to vendor_requests so the full vendor form data is stored
        if (!Schema::hasColumn('vendor_requests', 'contact_person')) {
            Schema::table('vendor_requests', function (Blueprint $table) {
                $table->string('contact_person')->nullable();
                $table->string('email')->nullable();
                $table->string('payment_terms')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->decimal('opening_balance', 14, 2)->default(0);
                $table->text('address')->nullable();
            });
        }

        // Add new permissions under the cashflow_manage parent
        $parent = Permission::where('name', 'cashflow_manage')->first();
        if ($parent) {
            $maxSort = Permission::where('parent_id', $parent->id)->max('sort_order') ?? 0;
            $newPerms = [
                ['name' => 'cashflow_vendor_deliver', 'title' => 'Mark Vendor Purchases Delivered'],
                ['name' => 'cashflow_vendor_request', 'title' => 'Request Vendor'],
            ];
            foreach ($newPerms as $i => $perm) {
                Permission::firstOrCreate(
                    ['name' => $perm['name']],
                    [
                        'title' => $perm['title'],
                        'main_group' => 0,
                        'parent_id' => $parent->id,
                        'status' => 1,
                        'guard_name' => 'web',
                        'sort_order' => $maxSort + $i + 1,
                    ]
                );
            }
            // Clear Spatie permission cache
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::table('vendor_requests', function (Blueprint $table) {
            $table->dropColumn(['contact_person', 'email', 'payment_terms', 'category_id', 'opening_balance', 'address']);
        });

        Permission::whereIn('name', ['cashflow_vendor_deliver', 'cashflow_vendor_request'])->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
