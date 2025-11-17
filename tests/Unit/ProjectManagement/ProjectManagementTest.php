<?php

use App\Models\Epic;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Carbon\Carbon;

describe('Project Model', function () {

    describe('Pin/Unpin Operations', function () {

        it('can pin a project', function () {
            $project = Project::factory()->create();

            expect($project->is_pinned)->toBeFalse();
            expect($project->pinned_date)->toBeNull();

            $project->pin();

            expect($project->fresh()->is_pinned)->toBeTrue();
            expect($project->fresh()->pinned_date)->not->toBeNull();
        });

        it('can unpin a project', function () {
            $project = Project::factory()->pinned()->create();

            expect($project->is_pinned)->toBeTrue();

            $project->unpin();

            expect($project->fresh()->is_pinned)->toBeFalse();
            expect($project->fresh()->pinned_date)->toBeNull();
        });

        it('returns correct is_pinned attribute', function () {
            $pinnedProject = Project::factory()->pinned()->create();
            $unpinnedProject = Project::factory()->create();

            expect($pinnedProject->is_pinned)->toBeTrue();
            expect($unpinnedProject->is_pinned)->toBeFalse();
        });

        it('can toggle pin status multiple times', function () {
            $project = Project::factory()->create();

            $project->pin();
            expect($project->fresh()->is_pinned)->toBeTrue();

            $project->unpin();
            expect($project->fresh()->is_pinned)->toBeFalse();

            $project->pin();
            expect($project->fresh()->is_pinned)->toBeTrue();
        });
    });

    describe('Date Calculations', function () {

        it('calculates remaining days correctly for future end date', function () {
            $project = Project::factory()->create([
                'end_date' => Carbon::today()->addDays(10),
            ]);

            expect($project->remaining_days)->toBe(10.0);
        });

        it('returns 0 for past end dates', function () {
            $project = Project::factory()->overdue()->create();

            expect($project->remaining_days)->toBe(0);
        });

        it('returns null when end_date is not set', function () {
            $project = Project::factory()->noEndDate()->create();

            expect($project->remaining_days)->toBeNull();
        });

        it('returns 0 for today as end date', function () {
            $project = Project::factory()->create([
                'end_date' => Carbon::today(),
            ]);

            expect($project->remaining_days)->toBe(0.0);
        });

        it('casts start_date to date', function () {
            $project = Project::factory()->create([
                'start_date' => '2024-01-15',
            ]);

            expect($project->start_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('casts end_date to date', function () {
            $project = Project::factory()->create([
                'end_date' => '2024-12-31',
            ]);

            expect($project->end_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });

    describe('Progress Tracking', function () {

        it('returns 0% progress when project has no tickets', function () {
            $project = Project::factory()->create();

            expect($project->progress_percentage)->toBe(0.0);
        });

        it('calculates progress correctly with partial completion', function () {
            $project = Project::factory()->create();
            $completedStatus = TicketStatus::factory()->completed()->forProject($project)->create();
            $pendingStatus = TicketStatus::factory()->forProject($project)->create(['is_completed' => false]);

            // Create 7 completed tickets and 3 pending tickets (70% completion)
            Ticket::factory()->count(7)->forProject($project)->withStatus($completedStatus)->create();
            Ticket::factory()->count(3)->forProject($project)->withStatus($pendingStatus)->create();

            expect($project->fresh()->progress_percentage)->toBe(70.0);
        });

        it('returns 100% when all tickets are completed', function () {
            $project = Project::factory()->create();
            $completedStatus = TicketStatus::factory()->completed()->forProject($project)->create();

            Ticket::factory()->count(5)->forProject($project)->withStatus($completedStatus)->create();

            expect($project->fresh()->progress_percentage)->toBe(100.0);
        });

        it('returns 0% when all tickets are incomplete', function () {
            $project = Project::factory()->create();
            $pendingStatus = TicketStatus::factory()->forProject($project)->create(['is_completed' => false]);

            Ticket::factory()->count(5)->forProject($project)->withStatus($pendingStatus)->create();

            expect($project->fresh()->progress_percentage)->toBe(0.0);
        });

        it('handles progress calculation with single ticket', function () {
            $project = Project::factory()->create();
            $completedStatus = TicketStatus::factory()->completed()->forProject($project)->create();

            Ticket::factory()->forProject($project)->withStatus($completedStatus)->create();

            expect($project->fresh()->progress_percentage)->toBe(100.0);
        });
    });

    describe('Relationships', function () {

        it('has many tickets', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            Ticket::factory()->count(3)->forProject($project)->withStatus($status)->create();

            expect($project->tickets()->count())->toBe(3);
            expect($project->tickets)->toHaveCount(3);
        });

        it('has many ticket statuses', function () {
            $project = Project::factory()->create();

            TicketStatus::factory()->count(4)->forProject($project)->create();

            expect($project->ticketStatuses()->count())->toBe(4);
            expect($project->ticketStatuses)->toHaveCount(4);
        });

        it('has many epics', function () {
            $project = Project::factory()->create();

            Epic::factory()->count(3)->forProject($project)->create();

            expect($project->epics()->count())->toBe(3);
            expect($project->epics)->toHaveCount(3);
        });

        it('has many members through project_members pivot', function () {
            $project = Project::factory()->create();
            $users = User::factory()->count(3)->create();

            $project->members()->attach($users->pluck('id'));

            expect($project->members()->count())->toBe(3);
            expect($project->members)->toHaveCount(3);
        });

        it('has users relationship for Filament compatibility', function () {
            $project = Project::factory()->create();
            $users = User::factory()->count(2)->create();

            $project->users()->attach($users->pluck('id'));

            expect($project->users()->count())->toBe(2);
            expect($project->users)->toHaveCount(2);
        });

        it('members and users relationships return same data', function () {
            $project = Project::factory()->create();
            $users = User::factory()->count(3)->create();

            $project->members()->attach($users->pluck('id'));

            expect($project->members()->count())->toBe($project->users()->count());
        });
    });

    describe('Model Attributes', function () {

        it('has correct fillable attributes', function () {
            $project = new Project();

            expect($project->getFillable())->toContain('name', 'description', 'ticket_prefix', 'start_date', 'end_date', 'pinned_date');
        });

        it('can create project with all fillable attributes', function () {
            $data = [
                'name' => 'Test Project',
                'description' => 'Test Description',
                'ticket_prefix' => 'TST',
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
            ];

            $project = Project::factory()->create($data);

            expect($project->name)->toBe('Test Project');
            expect($project->description)->toBe('Test Description');
            expect($project->ticket_prefix)->toBe('TST');
        });

        it('casts pinned_date to datetime', function () {
            $project = Project::factory()->pinned()->create();

            expect($project->pinned_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });
});

describe('Ticket Model', function () {

    describe('UUID Generation', function () {

        it('automatically generates UUID on ticket creation', function () {
            $project = Project::factory()->create(['ticket_prefix' => 'PRJ']);
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            expect($ticket->uuid)->not->toBeNull();
            expect($ticket->uuid)->toStartWith('PRJ-');
        });

        it('generates UUID with correct project prefix', function () {
            $project = Project::factory()->create(['ticket_prefix' => 'ABC']);
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            expect($ticket->uuid)->toStartWith('ABC-');
        });

        it('generates UUID with different project prefixes', function () {
            $project1 = Project::factory()->create(['ticket_prefix' => 'DEV']);
            $project2 = Project::factory()->create(['ticket_prefix' => 'PROD']);
            $status1 = TicketStatus::factory()->forProject($project1)->create();
            $status2 = TicketStatus::factory()->forProject($project2)->create();

            $ticket1 = Ticket::factory()->forProject($project1)->withStatus($status1)->create();
            $ticket2 = Ticket::factory()->forProject($project2)->withStatus($status2)->create();

            expect($ticket1->uuid)->toStartWith('DEV-');
            expect($ticket2->uuid)->toStartWith('PROD-');
        });

        it('generates unique UUIDs for multiple tickets', function () {
            $project = Project::factory()->create(['ticket_prefix' => 'PRJ']);
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket1 = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $ticket2 = Ticket::factory()->forProject($project)->withStatus($status)->create();

            expect($ticket1->uuid)->not->toBe($ticket2->uuid);
        });

        it('does not override manually set UUID', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->withUuid('CUSTOM-123456')
                ->create();

            expect($ticket->uuid)->toBe('CUSTOM-123456');
        });
    });

    describe('Creator Auto-Assignment', function () {

        it('auto-assigns created_by when user is authenticated', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            expect($ticket->created_by)->toBe($user->id);
        });

        it('does not set created_by when no user is authenticated', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            expect($ticket->created_by)->toBeNull();
        });

        it('does not override manually set created_by', function () {
            $creator = User::factory()->create();
            $authUser = User::factory()->create();
            $this->actingAs($authUser);

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->createdBy($creator)
                ->create();

            expect($ticket->created_by)->toBe($creator->id);
            expect($ticket->created_by)->not->toBe($authUser->id);
        });
    });

    describe('Status History Tracking', function () {

        it('creates history record when ticket status is updated', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $project = Project::factory()->create();
            $status1 = TicketStatus::factory()->forProject($project)->create(['name' => 'To Do']);
            $status2 = TicketStatus::factory()->forProject($project)->create(['name' => 'In Progress']);

            $ticket = Ticket::factory()->forProject($project)->withStatus($status1)->create();

            expect($ticket->histories()->count())->toBe(0);

            $ticket->update(['ticket_status_id' => $status2->id]);

            expect($ticket->histories()->count())->toBe(1);
            $history = $ticket->histories()->first();
            expect($history->ticket_status_id)->toBe($status2->id);
            expect($history->user_id)->toBe($user->id);
        });

        it('does not create history when status is not changed', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            $ticket->update(['name' => 'Updated Name']);

            expect($ticket->histories()->count())->toBe(0);
        });

        it('creates multiple history records for multiple status changes', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $project = Project::factory()->create();
            $status1 = TicketStatus::factory()->forProject($project)->create(['name' => 'To Do']);
            $status2 = TicketStatus::factory()->forProject($project)->create(['name' => 'In Progress']);
            $status3 = TicketStatus::factory()->forProject($project)->create(['name' => 'Done']);

            $ticket = Ticket::factory()->forProject($project)->withStatus($status1)->create();

            $ticket->update(['ticket_status_id' => $status2->id]);
            $ticket->update(['ticket_status_id' => $status3->id]);

            expect($ticket->histories()->count())->toBe(2);
        });

        it('orders histories by created_at descending', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $project = Project::factory()->create();
            $status1 = TicketStatus::factory()->forProject($project)->create();
            $status2 = TicketStatus::factory()->forProject($project)->create();
            $status3 = TicketStatus::factory()->forProject($project)->create();

            $ticket = Ticket::factory()->forProject($project)->withStatus($status1)->create();

            $ticket->update(['ticket_status_id' => $status2->id]);
            sleep(1);
            $ticket->update(['ticket_status_id' => $status3->id]);

            $histories = $ticket->histories;
            expect($histories->first()->ticket_status_id)->toBe($status3->id);
            expect($histories->last()->ticket_status_id)->toBe($status2->id);
        });
    });

    describe('Multi-User Assignment', function () {

        it('can assign user to ticket', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $user = User::factory()->create();

            $ticket->assignUser($user);

            expect($ticket->assignees()->count())->toBe(1);
            expect($ticket->isAssignedTo($user))->toBeTrue();
        });

        it('can unassign user from ticket', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $user = User::factory()->create();

            $ticket->assignUser($user);
            expect($ticket->isAssignedTo($user))->toBeTrue();

            $ticket->unassignUser($user);
            expect($ticket->fresh()->isAssignedTo($user))->toBeFalse();
        });

        it('can assign multiple users to ticket', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $users = User::factory()->count(3)->create();

            $ticket->assignUsers($users->pluck('id')->toArray());

            expect($ticket->assignees()->count())->toBe(3);
        });

        it('assignUser does not create duplicate assignments', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $user = User::factory()->create();

            $ticket->assignUser($user);
            $ticket->assignUser($user);

            expect($ticket->assignees()->count())->toBe(1);
        });

        it('assignUsers replaces existing assignments', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            $user3 = User::factory()->create();

            $ticket->assignUsers([$user1->id, $user2->id]);
            expect($ticket->assignees()->count())->toBe(2);

            $ticket->assignUsers([$user3->id]);
            expect($ticket->fresh()->assignees()->count())->toBe(1);
            expect($ticket->fresh()->isAssignedTo($user3))->toBeTrue();
            expect($ticket->fresh()->isAssignedTo($user1))->toBeFalse();
        });

        it('isAssignedTo returns correct boolean', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $assignedUser = User::factory()->create();
            $unassignedUser = User::factory()->create();

            $ticket->assignUser($assignedUser);

            expect($ticket->isAssignedTo($assignedUser))->toBeTrue();
            expect($ticket->isAssignedTo($unassignedUser))->toBeFalse();
        });
    });

    describe('Relationships', function () {

        it('belongs to project', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            expect($ticket->project)->toBeInstanceOf(Project::class);
            expect($ticket->project->id)->toBe($project->id);
        });

        it('belongs to status', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            expect($ticket->status)->toBeInstanceOf(TicketStatus::class);
            expect($ticket->status->id)->toBe($status->id);
        });

        it('belongs to creator', function () {
            $creator = User::factory()->create();
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->createdBy($creator)
                ->create();

            expect($ticket->creator)->toBeInstanceOf(User::class);
            expect($ticket->creator->id)->toBe($creator->id);
        });

        it('belongs to epic', function () {
            $project = Project::factory()->create();
            $epic = Epic::factory()->forProject($project)->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->forEpic($epic)
                ->create();

            expect($ticket->epic)->toBeInstanceOf(Epic::class);
            expect($ticket->epic->id)->toBe($epic->id);
        });

        it('belongs to priority', function () {
            $priority = TicketPriority::factory()->create();
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create([
                'priority_id' => $priority->id,
            ]);

            expect($ticket->priority)->toBeInstanceOf(TicketPriority::class);
            expect($ticket->priority->id)->toBe($priority->id);
        });

        it('has many assignees through pivot table', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $users = User::factory()->count(3)->create();

            $ticket->assignees()->attach($users->pluck('id'));

            expect($ticket->assignees)->toHaveCount(3);
        });

        it('has many histories', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            TicketHistory::factory()->count(3)->forTicket($ticket)->create();

            expect($ticket->histories)->toHaveCount(3);
        });
    });

    describe('Model Attributes', function () {

        it('has correct fillable attributes', function () {
            $ticket = new Ticket();

            expect($ticket->getFillable())->toContain(
                'project_id',
                'ticket_status_id',
                'priority_id',
                'name',
                'description',
                'start_date',
                'due_date',
                'uuid',
                'epic_id',
                'created_by'
            );
        });

        it('casts start_date to date', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create([
                'start_date' => '2024-01-15',
            ]);

            expect($ticket->start_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('casts due_date to date', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create([
                'due_date' => '2024-12-31',
            ]);

            expect($ticket->due_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });
});

describe('Epic Model', function () {

    describe('Basic Functionality', function () {

        it('can create epic with required attributes', function () {
            $project = Project::factory()->create();

            $epic = Epic::factory()->forProject($project)->create([
                'name' => 'User Authentication Epic',
                'description' => 'Implement complete authentication system',
            ]);

            expect($epic->name)->toBe('User Authentication Epic');
            expect($epic->description)->toBe('Implement complete authentication system');
        });

        it('can update epic attributes', function () {
            $epic = Epic::factory()->create();

            $epic->update(['name' => 'Updated Epic Name']);

            expect($epic->fresh()->name)->toBe('Updated Epic Name');
        });

        it('has correct fillable attributes', function () {
            $epic = new Epic();

            expect($epic->getFillable())->toContain('project_id', 'name', 'description', 'start_date', 'end_date');
        });
    });

    describe('Relationships', function () {

        it('belongs to project', function () {
            $project = Project::factory()->create();
            $epic = Epic::factory()->forProject($project)->create();

            expect($epic->project)->toBeInstanceOf(Project::class);
            expect($epic->project->id)->toBe($project->id);
        });

        it('has many tickets', function () {
            $project = Project::factory()->create();
            $epic = Epic::factory()->forProject($project)->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            Ticket::factory()->count(5)
                ->forProject($project)
                ->withStatus($status)
                ->forEpic($epic)
                ->create();

            expect($epic->tickets)->toHaveCount(5);
        });

        it('epic can have no tickets', function () {
            $epic = Epic::factory()->create();

            expect($epic->tickets)->toHaveCount(0);
        });
    });

    describe('Date Handling', function () {

        it('casts start_date to date', function () {
            $epic = Epic::factory()->create([
                'start_date' => '2024-01-01',
            ]);

            expect($epic->start_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('casts end_date to date', function () {
            $epic = Epic::factory()->create([
                'end_date' => '2024-12-31',
            ]);

            expect($epic->end_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('allows null end_date', function () {
            $epic = Epic::factory()->noEndDate()->create();

            expect($epic->end_date)->toBeNull();
        });
    });
});

describe('TicketStatus Model', function () {

    describe('Completion Flag', function () {

        it('can create status with is_completed true', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->completed()->forProject($project)->create();

            expect($status->is_completed)->toBeTrue();
        });

        it('can create status with is_completed false', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create(['is_completed' => false]);

            expect($status->is_completed)->toBeFalse();
        });

        it('casts is_completed to boolean', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create(['is_completed' => 1]);

            expect($status->is_completed)->toBeTrue();
            expect($status->is_completed)->toBeBool();
        });

        it('can update is_completed flag', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create(['is_completed' => false]);

            $status->update(['is_completed' => true]);

            expect($status->fresh()->is_completed)->toBeTrue();
        });
    });

    describe('Relationships', function () {

        it('belongs to project', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            expect($status->project)->toBeInstanceOf(Project::class);
            expect($status->project->id)->toBe($project->id);
        });

        it('has many tickets', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            Ticket::factory()->count(4)
                ->forProject($project)
                ->withStatus($status)
                ->create();

            expect($status->tickets)->toHaveCount(4);
        });

        it('status can have no tickets', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            expect($status->tickets)->toHaveCount(0);
        });
    });

    describe('Attributes', function () {

        it('has correct fillable attributes', function () {
            $status = new TicketStatus();

            expect($status->getFillable())->toContain('project_id', 'name', 'sort_order', 'color', 'is_completed');
        });

        it('can create status with all attributes', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create([
                'name' => 'In Review',
                'sort_order' => 5,
                'color' => '#ff5733',
                'is_completed' => false,
            ]);

            expect($status->name)->toBe('In Review');
            expect($status->sort_order)->toBe(5);
            expect($status->color)->toBe('#ff5733');
            expect($status->is_completed)->toBeFalse();
        });

        it('can update status attributes', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $status->update([
                'name' => 'Updated Status',
                'color' => '#00ff00',
            ]);

            expect($status->fresh()->name)->toBe('Updated Status');
            expect($status->fresh()->color)->toBe('#00ff00');
        });
    });
});

