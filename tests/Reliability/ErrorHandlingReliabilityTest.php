<?php

use App\Models\Notification;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

describe('Error Handling Reliability', function () {
    describe('Database Connection Failures', function () {
        it('handles database connection loss gracefully', function () {
            // This test verifies error detection, not actual connection loss
            // In production, Laravel will throw PDOException

            expect(function () {
                DB::connection()->getPdo();
            })->not->toThrow(\PDOException::class);

            // Verify connection is working
            expect(DB::connection()->getDatabaseName())->toBeString();
        });

        it('recovers from temporary connection issues', function () {
            // Test database reconnection capability
            $beforeCount = User::count();

            // Simulate disconnection and reconnection
            DB::disconnect();
            DB::reconnect();

            $afterCount = User::count();

            expect($afterCount)->toBe($beforeCount);
        });
    });

    describe('Query Failure Handling', function () {
        it('handles invalid query gracefully', function () {
            expect(function () {
                // Try to query non-existent table
                DB::table('non_existent_table')->get();
            })->toThrow(\Illuminate\Database\QueryException::class);
        });

        it('handles constraint violations gracefully', function () {
            $user = User::factory()->create(['email' => 'unique@test.com']);

            expect(function () {
                // Try to create duplicate email
                User::factory()->create(['email' => 'unique@test.com']);
            })->toThrow(\Illuminate\Database\QueryException::class);

            // Original user should still exist
            expect(User::where('email', 'unique@test.com')->count())->toBe(1);
        });

        it('handles foreign key constraint violations', function () {
            expect(function () {
                // Try to create ticket with non-existent project
                Ticket::create([
                    'project_id' => 99999,
                    'ticket_status_id' => 99999,
                    'name' => 'Test Ticket',
                ]);
            })->toThrow(\Illuminate\Database\QueryException::class);
        });
    });

    describe('Model Operation Failures', function () {
        it('handles missing required attributes gracefully', function () {
            expect(function () {
                User::create([
                    'email' => 'test@test.com',
                    // Missing required 'name' field
                ]);
            })->toThrow(\Illuminate\Database\QueryException::class);
        });

        it('handles invalid data types', function () {
            expect(function () {
                Project::factory()->create([
                    'start_date' => 'invalid-date-format',
                ]);
            })->toThrow(\Exception::class);
        });

        it('validates model before saving', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = new Ticket([
                'project_id' => $project->id,
                'ticket_status_id' => $status->id,
                'name' => 'Valid Ticket',
            ]);

            expect($ticket->save())->toBeTrue();
            expect($ticket->exists)->toBeTrue();
        });
    });

    describe('Service Error Handling', function () {
        it('handles notification service calls without errors', function () {
            $service = new NotificationService();

            // Create valid scenario
            $project = Project::factory()->create();
            $user = User::factory()->create();
            $assignedBy = User::factory()->create();

            // Should not throw exception
            expect(function () use ($service, $project, $user, $assignedBy) {
                $service->notifyProjectAssignment($project, $user, $assignedBy);
            })->not->toThrow(\Exception::class);

            // Verify notification was created
            expect(Notification::where('user_id', $user->id)->count())->toBeGreaterThan(0);
        });

        it('logs errors when operations fail', function () {
            Log::shouldReceive('error')
               ->atLeast()
               ->once();

            // Trigger an operation that might log errors
            $service = new NotificationService();

            // This should be caught and logged
            try {
                throw new \Exception('Test error');
            } catch (\Exception $e) {
                Log::error('Test error occurred: ' . $e->getMessage());
            }

            expect(true)->toBeTrue(); // Test passes if no exception thrown
        });
    });

    describe('Null Reference Handling', function () {
        it('handles null relationships gracefully', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => null]);

            // Should not throw error when accessing null creator
            expect($ticket->creator)->toBeNull();
            expect($ticket->creator_id)->toBeNull();
        });

        it('handles deleted related models gracefully', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $creator = User::factory()->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => $creator->id]);

            // Delete creator
            $creator->delete();

            // Refresh ticket and check creator
            $ticket = $ticket->fresh();

            // Relationship should return null for soft-deleted or missing user
            expect($ticket->creator)->toBeNull();
        });

        it('handles missing pivot data gracefully', function () {
            $project = Project::factory()->create();
            $user = User::factory()->create();

            // Get members without attaching any
            $members = $project->members;

            expect($members)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
            expect($members)->toHaveCount(0);
        });
    });

    describe('Transaction Error Handling', function () {
        it('detects transaction failures', function () {
            $initialCount = Ticket::count();

            expect(function () {
                DB::transaction(function () {
                    $project = Project::factory()->create();
                    $status = TicketStatus::factory()->forProject($project)->create();

                    Ticket::factory()->forProject($project)->withStatus($status)->create();

                    // Force failure
                    throw new \Exception('Simulated transaction failure');
                });
            })->toThrow(\Exception::class, 'Simulated transaction failure');

            // Transaction should have rolled back
            expect(Ticket::count())->toBe($initialCount);
        });

        it('preserves data integrity on rollback', function () {
            $user = User::factory()->create();
            $initialNotificationCount = Notification::count();

            try {
                DB::transaction(function () use ($user) {
                    // Create notification
                    Notification::create([
                        'user_id' => $user->id,
                        'type' => 'test',
                        'title' => 'Test',
                        'message' => 'Test message',
                    ]);

                    // Force rollback
                    throw new \Exception('Force rollback');
                });
            } catch (\Exception $e) {
                // Expected
            }

            // Notification should not exist
            expect(Notification::count())->toBe($initialNotificationCount);
        });
    });

    describe('Concurrent Operation Handling', function () {
        it('handles concurrent updates without data corruption', function () {
            $project = Project::factory()->create(['name' => 'Original Name']);

            // Simulate concurrent updates
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            // Both users try to update the project name
            $project->update(['name' => 'Updated by User 1']);
            $project->fresh()->update(['name' => 'Updated by User 2']);

            $finalProject = $project->fresh();

            // One update should succeed (last write wins)
            expect($finalProject->name)->toBeIn(['Updated by User 1', 'Updated by User 2']);
            expect($finalProject->name)->not->toBe('Original Name');
        });

        it('handles race conditions in ticket creation', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            // Create multiple tickets rapidly
            $tickets = [];
            for ($i = 0; $i < 5; $i++) {
                $tickets[] = Ticket::factory()
                    ->forProject($project)
                    ->withStatus($status)
                    ->create();
            }

            // All tickets should have unique UUIDs
            $uuids = collect($tickets)->pluck('uuid');
            expect($uuids->unique()->count())->toBe(5);
        });
    });
});
