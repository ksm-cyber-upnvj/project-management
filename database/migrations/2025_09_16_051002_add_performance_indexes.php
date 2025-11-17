<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Index for project board queries
            if (!$this->indexExists('tickets', 'idx_tickets_project_status')) {
                $table->index(['project_id', 'ticket_status_id'], 'idx_tickets_project_status');
            }
            if (!$this->indexExists('tickets', 'idx_tickets_status_created')) {
                $table->index(['ticket_status_id', 'created_at'], 'idx_tickets_status_created');
            }
            if (!$this->indexExists('tickets', 'idx_tickets_project_created')) {
                $table->index(['project_id', 'created_at'], 'idx_tickets_project_created');
            }
            if (!$this->indexExists('tickets', 'idx_tickets_project_updated')) {
                $table->index(['project_id', 'updated_at'], 'idx_tickets_project_updated');
            }
            if (!$this->indexExists('tickets', 'idx_tickets_due_date')) {
                $table->index(['due_date'], 'idx_tickets_due_date');
            }
            if (!$this->indexExists('tickets', 'idx_tickets_priority')) {
                $table->index(['priority_id'], 'idx_tickets_priority');
            }
            if (!$this->indexExists('tickets', 'idx_tickets_created_by')) {
                $table->index(['created_by'], 'idx_tickets_created_by');
            }
        });

        Schema::table('ticket_statuses', function (Blueprint $table) {
            // Index for project board status queries
            if (!$this->indexExists('ticket_statuses', 'idx_ticket_statuses_project_sort')) {
                $table->index(['project_id', 'sort_order'], 'idx_ticket_statuses_project_sort');
            }
            if (!$this->indexExists('ticket_statuses', 'idx_ticket_statuses_project_completed')) {
                $table->index(['project_id', 'is_completed'], 'idx_ticket_statuses_project_completed');
            }
        });

        Schema::table('ticket_users', function (Blueprint $table) {
            // Index for assignee queries
            if (!$this->indexExists('ticket_users', 'idx_ticket_users_ticket_user')) {
                $table->index(['ticket_id', 'user_id'], 'idx_ticket_users_ticket_user');
            }
            if (!$this->indexExists('ticket_users', 'idx_ticket_users_user')) {
                $table->index(['user_id'], 'idx_ticket_users_user');
            }
        });

        Schema::table('project_members', function (Blueprint $table) {
            // Index for project member queries
            if (!$this->indexExists('project_members', 'idx_project_members_project_user')) {
                $table->index(['project_id', 'user_id'], 'idx_project_members_project_user');
            }
            if (!$this->indexExists('project_members', 'idx_project_members_user')) {
                $table->index(['user_id'], 'idx_project_members_user');
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            // Index for project queries
            if (!$this->indexExists('projects', 'idx_projects_pinned')) {
                $table->index(['pinned_date'], 'idx_projects_pinned');
            }
            if (!$this->indexExists('projects', 'idx_projects_dates')) {
                $table->index(['start_date', 'end_date'], 'idx_projects_dates');
            }
        });

        Schema::table('ticket_priorities', function (Blueprint $table) {
            // Index for priority queries
            if (!$this->indexExists('ticket_priorities', 'idx_ticket_priorities_name')) {
                $table->index(['name'], 'idx_ticket_priorities_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // When using migrate:fresh (common in testing), tables are dropped entirely
        // so we don't need to drop indexes. This prevents foreign key constraint errors.
        // Only attempt to drop indexes if we're doing a regular rollback.

        try {
            Schema::table('tickets', function (Blueprint $table) {
                $indexes = ['idx_tickets_project_status', 'idx_tickets_status_created',
                           'idx_tickets_project_created', 'idx_tickets_project_updated',
                           'idx_tickets_due_date', 'idx_tickets_priority', 'idx_tickets_created_by'];
                foreach ($indexes as $index) {
                    if ($this->indexExists('tickets', $index)) {
                        try {
                            $table->dropIndex($index);
                        } catch (\Exception $e) {
                            // Ignore if index can't be dropped due to foreign key constraints
                            // This happens during migrate:fresh which will drop the table anyway
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            // Table might not exist during fresh migration
        }

        try {
            Schema::table('ticket_statuses', function (Blueprint $table) {
                $indexes = ['idx_ticket_statuses_project_sort', 'idx_ticket_statuses_project_completed'];
                foreach ($indexes as $index) {
                    if ($this->indexExists('ticket_statuses', $index)) {
                        try {
                            $table->dropIndex($index);
                        } catch (\Exception $e) {
                            // Ignore errors
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            // Table might not exist
        }

        try {
            Schema::table('ticket_users', function (Blueprint $table) {
                $indexes = ['idx_ticket_users_ticket_user', 'idx_ticket_users_user'];
                foreach ($indexes as $index) {
                    if ($this->indexExists('ticket_users', $index)) {
                        try {
                            $table->dropIndex($index);
                        } catch (\Exception $e) {
                            // Ignore errors
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            // Table might not exist
        }

        try {
            Schema::table('project_members', function (Blueprint $table) {
                $indexes = ['idx_project_members_project_user', 'idx_project_members_user'];
                foreach ($indexes as $index) {
                    if ($this->indexExists('project_members', $index)) {
                        try {
                            $table->dropIndex($index);
                        } catch (\Exception $e) {
                            // Ignore errors
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            // Table might not exist
        }

        try {
            Schema::table('projects', function (Blueprint $table) {
                $indexes = ['idx_projects_pinned', 'idx_projects_dates'];
                foreach ($indexes as $index) {
                    if ($this->indexExists('projects', $index)) {
                        try {
                            $table->dropIndex($index);
                        } catch (\Exception $e) {
                            // Ignore errors
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            // Table might not exist
        }

        try {
            Schema::table('ticket_priorities', function (Blueprint $table) {
                if ($this->indexExists('ticket_priorities', 'idx_ticket_priorities_name')) {
                    try {
                        $table->dropIndex('idx_ticket_priorities_name');
                    } catch (\Exception $e) {
                        // Ignore errors
                    }
                }
            });
        } catch (\Exception $e) {
            // Table might not exist
        }
    }

    /**
     * Check if index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};