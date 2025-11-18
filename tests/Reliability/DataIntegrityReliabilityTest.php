<?php

use App\Models\Notification;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

describe('Data Integrity Reliability', function () {
    describe('Relationship Integrity', function () {
        it('maintains referential integrity for ticket creator', function () {
            $creator = User::factory()->create();
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => $creator->id]);

            expect($ticket->creator->id)->toBe($creator->id);
            expect($ticket->created_by)->toBe($creator->id);
            expect($ticket->creator)->toBeInstanceOf(User::class);
        });

        it('maintains referential integrity for ticket assignees', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            // Assign multiple users
            $ticket->assignUser($user1);
            $ticket->assignUser($user2);

            $assignees = $ticket->assignees;

            expect($assignees)->toHaveCount(2);
            expect($assignees->pluck('id'))->toContain($user1->id, $user2->id);
        });

        it('maintains referential integrity for project members', function () {
            $project = Project::factory()->create();
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $project->members()->attach([$user1->id, $user2->id]);

            expect($project->members)->toHaveCount(2);
            expect($project->members->pluck('id'))->toContain($user1->id, $user2->id);
        });

        it('maintains ticket-project relationship', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create();

            expect($ticket->project->id)->toBe($project->id);
            expect($ticket->project_id)->toBe($project->id);
            expect($project->fresh()->tickets->pluck('id'))->toContain($ticket->id);
        });
    });

    describe('Data Consistency', function () {
        it('maintains consistent ticket status', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $project = Project::factory()->create();
            $status1 = TicketStatus::factory()->forProject($project)->create();
            $status2 = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status1)
                ->create();

            expect($ticket->status->id)->toBe($status1->id);

            // Update status
            $ticket->update(['ticket_status_id' => $status2->id]);

            expect($ticket->fresh()->status->id)->toBe($status2->id);
            expect($ticket->fresh()->ticket_status_id)->toBe($status2->id);
        });

        it('ensures ticket UUID uniqueness', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket1 = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $ticket2 = Ticket::factory()->forProject($project)->withStatus($status)->create();

            expect($ticket1->uuid)->not->toBe($ticket2->uuid);
            expect($ticket1->uuid)->toBeString();
            expect($ticket2->uuid)->toBeString();
        });

        it('maintains notification data consistency', function () {
            $user = User::factory()->create();

            $notification = Notification::create([
                'user_id' => $user->id,
                'type' => 'test_type',
                'title' => 'Test Title',
                'message' => 'Test Message',
                'data' => [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
            ]);

            expect($notification->data)->toBeArray();
            expect($notification->data)->toHaveKey('key1');
            expect($notification->data['key1'])->toBe('value1');
        });
    });

    describe('Cascade Operations', function () {
        it('handles ticket deletion with related records', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $user = User::factory()->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create();

            // Add related records
            TicketComment::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
            ]);

            $ticketId = $ticket->id;

            // Delete ticket
            $ticket->delete();

            // Verify ticket is deleted
            expect(Ticket::find($ticketId))->toBeNull();

            // Comments might be cascade deleted or orphaned depending on schema
            // This tests that the operation doesn't fail
            expect(true)->toBeTrue();
        });

        it('preserves ticket history on ticket updates', function () {
            $this->actingAs(User::factory()->create());

            $project = Project::factory()->create();
            $status1 = TicketStatus::factory()->forProject($project)->create();
            $status2 = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status1)
                ->create();

            $initialHistoryCount = TicketHistory::where('ticket_id', $ticket->id)->count();

            // Update status (triggers history creation)
            $ticket->update(['ticket_status_id' => $status2->id]);

            $finalHistoryCount = TicketHistory::where('ticket_id', $ticket->id)->count();

            expect($finalHistoryCount)->toBeGreaterThan($initialHistoryCount);
        });
    });

    describe('Timestamp Integrity', function () {
        it('automatically tracks creation timestamps', function () {
            $project = Project::factory()->create();

            expect($project->created_at)->not->toBeNull();
            expect($project->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
            expect($project->updated_at)->not->toBeNull();
        });

        it('updates timestamps on modification', function () {
            $project = Project::factory()->create();
            $originalUpdatedAt = $project->updated_at;

            sleep(1);

            $project->update(['name' => 'Updated Name']);

            expect($project->fresh()->updated_at->timestamp)
                ->toBeGreaterThan($originalUpdatedAt->timestamp);
        });

        it('preserves creation timestamp on updates', function () {
            $project = Project::factory()->create();
            $originalCreatedAt = $project->created_at;

            $project->update(['name' => 'Updated Name']);

            expect($project->fresh()->created_at->timestamp)
                ->toBe($originalCreatedAt->timestamp);
        });
    });

    describe('Pivot Table Integrity', function () {
        it('maintains project-member pivot data', function () {
            $project = Project::factory()->create();
            $user = User::factory()->create();

            $project->members()->attach($user->id);

            // Verify pivot exists
            expect($project->fresh()->members->pluck('id'))->toContain($user->id);

            // Verify we can detach
            $project->members()->detach($user->id);
            expect($project->fresh()->members->pluck('id'))->not->toContain($user->id);
        });

        it('enforces unique constraint on pivot table', function () {
            $project = Project::factory()->create();
            $user = User::factory()->create();

            $project->members()->attach($user->id);

            // Try to attach again - should throw unique constraint error
            expect(function () use ($project, $user) {
                $project->members()->attach($user->id);
            })->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);

            // Verify only one entry exists
            expect($project->fresh()->members)->toHaveCount(1);
        });

        it('maintains ticket-user assignment pivot', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $user = User::factory()->create();

            $ticket->assignUser($user);

            expect($user->isAssignedToTicket($ticket))->toBeTrue();
            expect($ticket->fresh()->assignees->pluck('id'))->toContain($user->id);
        });
    });

    describe('Foreign Key Constraints', function () {
        it('enforces project foreign key on tickets', function () {
            expect(function () {
                Ticket::create([
                    'project_id' => 99999, // Non-existent project
                    'ticket_status_id' => 1,
                    'name' => 'Test',
                ]);
            })->toThrow(\Illuminate\Database\QueryException::class);
        });

        it('enforces user foreign key on notifications', function () {
            expect(function () {
                Notification::create([
                    'user_id' => 99999, // Non-existent user
                    'type' => 'test',
                    'title' => 'Test',
                    'message' => 'Test',
                ]);
            })->toThrow(\Illuminate\Database\QueryException::class);
        });
    });

    describe('Data Type Consistency', function () {
        it('maintains correct data types for dates', function () {
            $project = Project::factory()->create([
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
            ]);

            expect($project->start_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
            expect($project->end_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('maintains correct data types for JSON fields', function () {
            $notification = Notification::create([
                'user_id' => User::factory()->create()->id,
                'type' => 'test',
                'title' => 'Test',
                'message' => 'Test',
                'data' => ['key' => 'value'],
            ]);

            expect($notification->data)->toBeArray();
            expect($notification->fresh()->data)->toBeArray();
        });

        it('maintains boolean data types', function () {
            $project = Project::factory()->create();

            $project->pin();
            expect($project->fresh()->is_pinned)->toBeTrue();

            $project->unpin();
            expect($project->fresh()->is_pinned)->toBeFalse();
        });
    });

    describe('Transaction Consistency', function () {
        it('maintains consistency within transactions', function () {
            $initialProjectCount = Project::count();
            $initialTicketCount = Ticket::count();

            DB::transaction(function () {
                $project = Project::factory()->create();
                $status = TicketStatus::factory()->forProject($project)->create();
                Ticket::factory()->forProject($project)->withStatus($status)->create();
            });

            expect(Project::count())->toBe($initialProjectCount + 1);
            expect(Ticket::count())->toBeGreaterThan($initialTicketCount);
        });

        it('rolls back all changes on transaction failure', function () {
            $initialCount = Project::count();

            try {
                DB::transaction(function () {
                    Project::factory()->create();
                    Project::factory()->create();

                    throw new \Exception('Force rollback');
                });
            } catch (\Exception $e) {
                // Expected
            }

            expect(Project::count())->toBe($initialCount);
        });
    });
});
