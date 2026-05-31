<?php

use Illuminate\Database\Migrations\Migration;
  use Illuminate\Database\Schema\Blueprint;
  use Illuminate\Support\Facades\Schema;
  use Illuminate\Support\Facades\DB;

  return new class extends Migration {
      public function up(): void {
          // Local is already on the new schema — no-op there.
          if (!Schema::hasColumn('oauth_clients', 'personal_access_client')) {
              return;
          }

          Schema::table('oauth_clients', function (Blueprint $t) {
              if (!Schema::hasColumn('oauth_clients', 'owner_type'))    $t->string('owner_type')->nullable()->after('id');
              if (!Schema::hasColumn('oauth_clients', 'owner_id'))      $t->unsignedBigInteger('owner_id')->nullable()->after('owner_type');
              if (!Schema::hasColumn('oauth_clients', 'redirect_uris')) $t->text('redirect_uris')->nullable()->after('provider');
              if (!Schema::hasColumn('oauth_clients', 'grant_types'))   $t->text('grant_types')->nullable()->after('redirect_uris');
          });

          DB::statement("
              UPDATE oauth_clients
              SET
                owner_id      = user_id,
                owner_type    = CASE WHEN user_id IS NOT NULL THEN 'App\\\\Models\\\\User' ELSE NULL END,
                redirect_uris = COALESCE(redirect, ''),
                grant_types   = TRIM(BOTH ',' FROM CONCAT(
                  CASE WHEN personal_access_client = 1 THEN 'personal_access,' ELSE '' END,
                  CASE WHEN password_client = 1        THEN 'password,refresh_token,' ELSE '' END,
                  'authorization_code,refresh_token'
                ))
          ");

          Schema::table('oauth_clients', function (Blueprint $t) {
              $t->dropColumn(['user_id', 'redirect', 'personal_access_client', 'password_client']);
          });
      }

      public function down(): void {
          // One-way migration; reverse manually if needed.
      }
  };