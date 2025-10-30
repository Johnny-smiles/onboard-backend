<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            if (!Schema::hasColumn('photos', 'job_name')) {
                $table->string('job_name')->nullable()->after('project_id');
            }

            if (!Schema::hasColumn('photos', 'location')) {
                $table->string('location')->nullable()->after('job_name');
            }

            if (!Schema::hasColumn('photos', 'shot_type')) {
                $table->string('shot_type')->nullable()->after('location');
            }

            if (!Schema::hasColumn('photos', 'notes')) {
                $table->text('notes')->nullable()->after('shot_type');
            }

            if (!Schema::hasColumn('photos', 'review_status')) {
                $table->string('review_status')->default('pending')->after('notes');
            }

            if (!Schema::hasColumn('photos', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('review_status');
            }

            if (!Schema::hasColumn('photos', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('review_notes');
            }

            if (!Schema::hasColumn('photos', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }

            if (Schema::hasColumn('photos', 'client_id')) {
                $table->index(['client_id', 'job_name'], 'photos_client_id_job_name_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex('photos_client_id_job_name_index');

            if (Schema::hasColumn('photos', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }

            if (Schema::hasColumn('photos', 'reviewed_by')) {
                $table->dropColumn('reviewed_by');
            }

            if (Schema::hasColumn('photos', 'review_notes')) {
                $table->dropColumn('review_notes');
            }

            if (Schema::hasColumn('photos', 'review_status')) {
                $table->dropColumn('review_status');
            }

            if (Schema::hasColumn('photos', 'shot_type')) {
                $table->dropColumn('shot_type');
            }

            if (Schema::hasColumn('photos', 'location')) {
                $table->dropColumn('location');
            }

            if (Schema::hasColumn('photos', 'job_name')) {
                $table->dropColumn('job_name');
            }
        });
    }
};
