<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drops all backup/staging tables created during data migrations.
     * These tables have no foreign keys pointing to them and are not
     * referenced anywhere in the application code.
     */
    public function up(): void
    {
        // Large backup tables from cast_crew and film_cast_pivot migrations (~390 MB total)
        Schema::dropIfExists('film_cast_pivot_old');
        Schema::dropIfExists('film_cast_pivot_backup_final');
        Schema::dropIfExists('film_cast_pivot_safety_backup');
        Schema::dropIfExists('cast_crew_backup');
        Schema::dropIfExists('cast_crew_old');
        Schema::dropIfExists('films_backup');

        // Small backup tables from general data migrations
        Schema::dropIfExists('jobs_backup');
        Schema::dropIfExists('migrations_backup');
        Schema::dropIfExists('personal_access_tokens_backup');
        Schema::dropIfExists('post_backup');
        Schema::dropIfExists('rol_backup');
        Schema::dropIfExists('failed_jobs_backup');
        Schema::dropIfExists('user_comments_backup');
        Schema::dropIfExists('user_entries_backup');
        Schema::dropIfExists('user_entry_films_backup');
        Schema::dropIfExists('user_entry_likes_backup');
        Schema::dropIfExists('user_film_actions_backup');
        Schema::dropIfExists('user_friends_backup');
        Schema::dropIfExists('user_profiles_backup');
        Schema::dropIfExists('user_saved_lists_backup');
        Schema::dropIfExists('users_backup');
    }

    /**
     * Backup tables are intentionally not restored on rollback.
     * They were temporary artifacts of data migrations and hold no
     * production data that isn't already in the canonical tables.
     */
    public function down(): void
    {
        // Intentional no-op: backup tables should not be recreated.
    }
};