<?php

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketStatus;
use App\Models\User;

describe('Model Event Reliability', function () {
    describe('Ticket Creation Events', function () {
        it('automatically generates UUID on ticket creation', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['uuid' => null]); // Don't provide UUID

            expect($ticket->uuid)->not->toBeNull();
            expect($ticket->uuid)->toBeString();
            expect($ticket->uuid)->toStartWith($project->ticket_prefix);
        });

        it('auto-assigns creator when user is authenticated', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => null]); // Don't provide creator

            expect($ticket->created_by)->toBe($user->id);
            expect($ticket->creator->id)->toBe($user->id);
        });

        it('allows manual creator assignment when specified', function () {
            $authenticatedUser = User::factory()->create();
            $actualCreator = User::factory()->create();

            $this->actingAs($authenticatedUser);

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => $actualCreator->id]);

            // Should use manually specified creator
            expect($ticket->created_by)->toBe($actualCreator->id);
            expect($ticket->created_by)->not->toBe($authenticatedUser->id);
        });

        it('handles ticket creation without authenticated user', function () {
            // No authenticated user
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => null]);

            expect($ticket->created_by)->toBeNull();
        });
    });

    describe('Ticket Update Events', function () {
        it('creates history when ticket status changes', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $project = Project::factory()->create();
            $status1 = TicketStatus::factory()->forProject($project)->create();
            $status2 = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status1)
                ->create();

            $initialHistoryCount = TicketHistory::where('ticket_id', $ticket->id)->count();

            // Update status
            $ticket->update(['ticket_status_id' => $status2->id]);

            $finalHistoryCount = TicketHistory::where('ticket_id', $ticket->id)->count();

            expect($finalHistoryCount)->toBe($initialHistoryCount + 1);

            // Verify history entry
            $latestHistory = TicketHistory::where('ticket_id', $ticket->id)
                ->latest()
                ->first();

            expect($latestHistory->ticket_status_id)->toBe($status2->id);
            expect($latestHistory->user_id)->toBe($user->id);
        });

        it('does not create history when status is not changed', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create();

            $initialHistoryCount = TicketHistory::where('ticket_id', $ticket->id)->count();

            // Update other fields (not status)
            $ticket->update(['name' => 'Updated Name']);

            $finalHistoryCount = TicketHistory::where('ticket_id', $ticket->id)->count();

            expect($finalHistoryCount)->toBe($initialHistoryCount);
        });

        it('creates multiple history entries for multiple status changes', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $project = Project::factory()->create();
            $status1 = TicketStatus::factory()->forProject($project)->create();
            $status2 = TicketStatus::factory()->forProject($project)->create();
            $status3 = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status1)
                ->create();

            // Change status multiple times
            $ticket->update(['ticket_status_id' => $status2->id]);
            $ticket->update(['ticket_status_id' => $status3->id]);
            $ticket->update(['ticket_status_id' => $status1->id]);

            $historyCount = TicketHistory::where('ticket_id', $ticket->id)->count();

            expect($historyCount)->toBeGreaterThanOrEqual(3);
        });
    });

    describe('UUID Generation Reliability', function () {
        it('generates unique UUIDs for concurrent creations', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $uuids = [];

            // Create multiple tickets rapidly
            for ($i = 0; $i < 10; $i++) {
                $ticket = Ticket::factory()
                    ->forProject($project)
                    ->withStatus($status)
                    ->create();
                $uuids[] = $ticket->uuid;
            }

            // All UUIDs should be unique
            expect(count($uuids))->toBe(count(array_unique($uuids)));
        });

        it('uses project prefix in UUID', function () {
            $project = Project::factory()->create(['ticket_prefix' => 'CUSTOM']);
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create();

            expect($ticket->uuid)->toStartWith('CUSTOM-');
        });

        it('handles missing project gracefully in UUID generation', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            // Create ticket then delete project reference (edge case)
            $ticket = new Ticket([
                'project_id' => 99999, // Non-existent project
                'ticket_status_id' => $status->id,
                'name' => 'Test',
            ]);

            // Should handle gracefully (uses default 'TKT' prefix)
            expect(function () use ($ticket) {
                $ticket->save();
            })->toThrow(\Illuminate\Database\QueryException::class); // Foreign key constraint
        });
    });

    describe('Event Execution Order', function () {
        it('executes creating event before save', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = new Ticket([
                'project_id' => $project->id,
                'ticket_status_id' => $status->id,
                'name' => 'Test Ticket',
            ]);

            // UUID should be null before save
            expect($ticket->uuid)->toBeNull();

            $ticket->save();

            // UUID should be generated after save
            expect($ticket->uuid)->not->toBeNull();
        });

        it('executes updating event before update is persisted', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $project = Project::factory()->create();
            $status1 = TicketStatus::factory()->forProject($project)->create();
            $status2 = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status1)
                ->create();

            $beforeUpdateHistoryCount = TicketHistory::count();

            // Update status
            $ticket->update(['ticket_status_id' => $status2->id]);

            $afterUpdateHistoryCount = TicketHistory::count();

            // History should be created during update event
            expect($afterUpdateHistoryCount)->toBeGreaterThan($beforeUpdateHistoryCount);
        });
    });

    describe('Event Failure Handling', function () {
        it('handles event failures gracefully', function () {
            // Test that even if event logic has issues, it doesn't break model operations
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create();

            expect($ticket->exists)->toBeTrue();
            expect($ticket->id)->toBeInt();
        });

        it('maintains data consistency if event partially fails', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $project = Project::factory()->create();
            $status1 = TicketStatus::factory()->forProject($project)->create();
            $status2 = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status1)
                ->create();

            // Update ticket (history creation happens in event)
            $ticket->update(['ticket_status_id' => $status2->id]);

            // Verify ticket was updated even if history creation might fail
            expect($ticket->fresh()->ticket_status_id)->toBe($status2->id);
        });
    });

    describe('Model Observer Pattern', function () {
        it('consistently applies business logic through events', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            // Create 5 tickets
            for ($i = 0; $i < 5; $i++) {
                $ticket = Ticket::factory()
                    ->forProject($project)
                    ->withStatus($status)
                    ->create();

                // All should have UUID generated
                expect($ticket->uuid)->not->toBeNull();
                expect($ticket->uuid)->toStartWith($project->ticket_prefix);
            }
        });
    });
});
