<?php

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Policies\TicketPolicy;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    // Reset cached roles and permissions
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    // Create roles
    Role::create(['name' => 'super_admin']);
    Role::create(['name' => 'developer']);
    Role::create(['name' => 'manager']);

    // Create permissions
    Permission::create(['name' => 'view_any_ticket']);
    Permission::create(['name' => 'create_ticket']);
    Permission::create(['name' => 'delete_ticket']);
    Permission::create(['name' => 'delete_any_ticket']);
    Permission::create(['name' => 'force_delete_ticket']);
    Permission::create(['name' => 'force_delete_any_ticket']);
    Permission::create(['name' => 'restore_ticket']);
    Permission::create(['name' => 'restore_any_ticket']);
    Permission::create(['name' => 'replicate_ticket']);
    Permission::create(['name' => 'reorder_ticket']);
});

describe('Authorization Security', function () {
    describe('Role-Based Access Control (RBAC)', function () {
        it('assigns roles to users correctly', function () {
            $user = User::factory()->create();
            $user->assignRole('super_admin');

            expect($user->hasRole('super_admin'))->toBeTrue();
            expect($user->hasRole('developer'))->toBeFalse();
        });

        it('checks multiple roles with hasAnyRole', function () {
            $user = User::factory()->create();
            $user->assignRole('developer');

            expect($user->hasAnyRole(['super_admin', 'developer']))->toBeTrue();
            expect($user->hasAnyRole(['super_admin', 'manager']))->toBeFalse();
        });

        it('prevents unauthorized role assignment', function () {
            $user = User::factory()->create();

            // Should not have any role by default
            expect($user->roles()->count())->toBe(0);
        });
    });

    describe('Ticket View Authorization', function () {
        it('allows super_admin to view any ticket', function () {
            $admin = User::factory()->create();
            $admin->assignRole('super_admin');

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            $policy = new TicketPolicy();

            expect($policy->view($admin, $ticket))->toBeTrue();
        });

        it('allows ticket creator to view their ticket', function () {
            $creator = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => $creator->id]);

            $policy = new TicketPolicy();

            expect($policy->view($creator, $ticket))->toBeTrue();
        });

        it('allows assigned users to view ticket', function () {
            $assignedUser = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $ticket->assignUser($assignedUser);

            $policy = new TicketPolicy();

            expect($policy->view($assignedUser, $ticket))->toBeTrue();
        });

        it('allows project members to view tickets in their project', function () {
            $member = User::factory()->create();

            $project = Project::factory()->create();
            $project->members()->attach($member);

            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            $policy = new TicketPolicy();

            expect($policy->view($member, $ticket))->toBeTrue();
        });

        it('denies access to unauthorized users', function () {
            $unauthorizedUser = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            $policy = new TicketPolicy();

            expect($policy->view($unauthorizedUser, $ticket))->toBeFalse();
        });
    });

    describe('Ticket Update Authorization', function () {
        it('allows super_admin to update any ticket', function () {
            $admin = User::factory()->create();
            $admin->assignRole('super_admin');

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            $policy = new TicketPolicy();

            expect($policy->update($admin, $ticket))->toBeTrue();
        });

        it('allows ticket creator to update their ticket', function () {
            $creator = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => $creator->id]);

            $policy = new TicketPolicy();

            expect($policy->update($creator, $ticket))->toBeTrue();
        });

        it('allows assigned users to update ticket', function () {
            $assignedUser = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $ticket->assignUser($assignedUser);

            $policy = new TicketPolicy();

            expect($policy->update($assignedUser, $ticket))->toBeTrue();
        });

        it('denies update to unauthorized users', function () {
            $unauthorizedUser = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            $policy = new TicketPolicy();

            expect($policy->update($unauthorizedUser, $ticket))->toBeFalse();
        });

        it('denies update to project members who are not assigned', function () {
            $member = User::factory()->create();

            $project = Project::factory()->create();
            $project->members()->attach($member);

            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            $policy = new TicketPolicy();

            // Member can view but not update unless assigned
            expect($policy->view($member, $ticket))->toBeTrue();
            expect($policy->update($member, $ticket))->toBeFalse();
        });
    });

    describe('Permission-Based Authorization', function () {
        it('checks specific permissions for actions', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('create_ticket');

            expect($user->can('create_ticket'))->toBeTrue();
            expect($user->can('delete_ticket'))->toBeFalse();
        });

        it('prevents actions without required permission', function () {
            $user = User::factory()->create();
            // No permissions assigned

            $policy = new TicketPolicy();

            expect($policy->create($user))->toBeFalse();
            expect($policy->deleteAny($user))->toBeFalse();
        });

        it('allows actions with granted permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('delete_ticket');

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            $policy = new TicketPolicy();

            expect($policy->delete($user, $ticket))->toBeTrue();
        });
    });

    describe('Ownership Verification', function () {
        it('verifies ticket ownership correctly', function () {
            $owner = User::factory()->create();
            $other = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => $owner->id]);

            expect($ticket->created_by)->toBe($owner->id);
            expect($ticket->created_by)->not->toBe($other->id);
        });

        it('tracks ticket assignment correctly', function () {
            $assignedUser = User::factory()->create();
            $otherUser = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            $ticket->assignUser($assignedUser);

            expect($assignedUser->isAssignedToTicket($ticket))->toBeTrue();
            expect($otherUser->isAssignedToTicket($ticket))->toBeFalse();
        });
    });

    describe('Privilege Escalation Prevention', function () {
        it('prevents horizontal privilege escalation', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => $user2->id]);

            $policy = new TicketPolicy();

            // User1 cannot update User2's ticket
            expect($policy->update($user1, $ticket))->toBeFalse();
        });

        it('super_admin role bypasses standard restrictions', function () {
            $admin = User::factory()->create();
            $admin->assignRole('super_admin');

            $regularUser = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => $regularUser->id]);

            $policy = new TicketPolicy();

            // Admin can update any ticket
            expect($policy->update($admin, $ticket))->toBeTrue();
            expect($policy->view($admin, $ticket))->toBeTrue();
        });
    });
});
