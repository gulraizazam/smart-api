<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Fix-forward for schema drift: the create_products_table migration was edited
// post-deploy (commits dfc078d79 / d7f18141b, 2025-01-12) to add a `slug`
// column. Environments migrated before that edit never got the column, so
// Product::createRecord's slug uniqueness check throws 1054. This migration is
// idempotent — it no-ops on fresh installs where the column already exists.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'slug')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
        });

        $taken = [];
        DB::table('products')
            ->select('id', 'name')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $row) use (&$taken): void {
                $base = Str::slug((string) $row->name) ?: 'product-' . $row->id;
                $candidate = $base;
                $counter = 1;
                while (in_array($candidate, $taken, true)) {
                    $candidate = $base . '-' . $counter;
                    $counter++;
                }
                $taken[] = $candidate;

                DB::table('products')->where('id', $row->id)->update(['slug' => $candidate]);
            });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'slug')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
