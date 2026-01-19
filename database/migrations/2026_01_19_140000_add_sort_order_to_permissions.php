<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

class AddSortOrderToPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add sort_order column to permissions table
        Schema::table('permissions', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('parent_id');
        });

        // Set sort_order based on current ID, but place Treatments right after Consultancy
        $permissions = Permission::orderBy('id')->get();

        $sortOrder = 1;
        foreach ($permissions as $permission) {
            // For Consultancy (appointments) and its children (IDs 65-77)
            if ($permission->id >= 65 && $permission->id < 78) {
                $permission->sort_order = $sortOrder++;
            }
            // For Treatments and its children (IDs 484+)
            // We'll assign these right after consultancy
            else if ($permission->id >= 484 && $permission->id < 514) {
                // Keep track of these, we'll assign them after consultancy
                continue;
            }
            // For all other permissions before consultancy
            else if ($permission->id < 65) {
                $permission->sort_order = $sortOrder++;
            }
            // For all other permissions after consultancy but before treatments
            else if ($permission->id >= 78 && $permission->id < 484) {
                // These will be assigned after treatments
                continue;
            }
            // For all permissions after treatments
            else if ($permission->id >= 514) {
                // These will be assigned at the end
                continue;
            }

            $permission->save();
        }

        // Now assign Treatments permissions right after Consultancy
        $treatmentPermissions = Permission::where('id', '>=', 484)->orderBy('id')->get();
        foreach ($treatmentPermissions as $permission) {
            $permission->sort_order = $sortOrder++;
            $permission->save();
        }

        // Now assign the remaining permissions (Lead Sources and onwards)
        $remainingPermissions = Permission::where('id', '>=', 78)
            ->where('id', '<', 484)
            ->orderBy('id')
            ->get();
        foreach ($remainingPermissions as $permission) {
            $permission->sort_order = $sortOrder++;
            $permission->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
}
