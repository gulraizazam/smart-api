<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        );
        return ! empty($rows);
    }

    private function addIndex(string $table, array|string $columns, ?string $name = null): void
    {
        $name ??= $table . '_' . (is_array($columns) ? implode('_', $columns) : $columns) . '_index';
        if ($this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($columns, $name) {
            $t->index($columns, $name);
        });
    }

    private function addUnique(string $table, array $columns, string $name): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($columns, $name) {
            $t->unique($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! $this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($name) {
            $t->dropIndex($name);
        });
    }

    private function dropUnique(string $table, string $name): void
    {
        if (! $this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($name) {
            $t->dropUnique($name);
        });
    }

    public function up(): void
    {
        $this->addIndex('employee_details', 'reporting_manager_id', 'employee_details_reporting_manager_id_index');

        $this->addIndex('leave_applications', 'leave_type_id', 'leave_applications_leave_type_id_index');
        $this->addIndex('leave_applications', 'reviewed_by', 'leave_applications_reviewed_by_index');
        $this->addIndex('leave_applications', ['user_id', 'start_date', 'end_date'], 'leave_apps_overlap_idx');

        $this->addIndex('recruitment_candidates', 'city_id', 'recruitment_candidates_city_id_index');
        $this->addIndex('recruitment_candidates', 'location_id', 'recruitment_candidates_location_id_index');
        $this->addIndex('recruitment_candidates', 'converted_user_id', 'recruitment_candidates_converted_user_id_index');

        $this->addIndex('recruitment_interviews', 'interviewer_id', 'recruitment_interviews_interviewer_id_index');

        $this->addIndex('departments', ['account_id', 'active'], 'departments_acct_active_idx');
        $this->addIndex('designations', ['account_id', 'active'], 'designations_acct_active_idx');
        $this->addIndex('leave_types', ['account_id', 'active'], 'leave_types_acct_active_idx');

        $this->addUnique('departments', ['name', 'account_id', 'deleted_at'], 'departments_name_acct_unique');
        $this->addUnique('designations', ['name', 'account_id', 'deleted_at'], 'designations_name_acct_unique');
        $this->addUnique('leave_types', ['name', 'account_id', 'deleted_at'], 'leave_types_name_acct_unique');
    }

    public function down(): void
    {
        $this->dropIndex('employee_details', 'employee_details_reporting_manager_id_index');

        $this->dropIndex('leave_applications', 'leave_applications_leave_type_id_index');
        $this->dropIndex('leave_applications', 'leave_applications_reviewed_by_index');
        $this->dropIndex('leave_applications', 'leave_apps_overlap_idx');

        $this->dropIndex('recruitment_candidates', 'recruitment_candidates_city_id_index');
        $this->dropIndex('recruitment_candidates', 'recruitment_candidates_location_id_index');
        $this->dropIndex('recruitment_candidates', 'recruitment_candidates_converted_user_id_index');

        $this->dropIndex('recruitment_interviews', 'recruitment_interviews_interviewer_id_index');

        $this->dropIndex('departments', 'departments_acct_active_idx');
        $this->dropUnique('departments', 'departments_name_acct_unique');

        $this->dropIndex('designations', 'designations_acct_active_idx');
        $this->dropUnique('designations', 'designations_name_acct_unique');

        $this->dropIndex('leave_types', 'leave_types_acct_active_idx');
        $this->dropUnique('leave_types', 'leave_types_name_acct_unique');
    }
};
