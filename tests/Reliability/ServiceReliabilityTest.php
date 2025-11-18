<?php

use App\Models\Notification;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\NotificationService;

describe('Service Reliability', function () {
    describe('NotificationService Reliability', function () {
        it('successfully creates notification for comment', function () {
            $service = new NotificationService();

            $user = User::factory()->create();
            $commenter = User::factory()->create();
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => $user->id]);

            $comment = TicketComment::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $commenter->id,
            ]);

            $initialNotificationCount = Notification::count();

            $service->notifyCommentAdded($comment);

            $finalNotificationCount = Notification::count();

            expect($finalNotificationCount)->toBeGreaterThan($initialNotificationCount);
        });

        it('notifies correct users for comment', function () {
            $service = new NotificationService();

            $creator = User::factory()->create();
            $assignee = User::factory()->create();
            $commenter = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => $creator->id]);

            $ticket->assignUser($assignee);

            $comment = TicketComment::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $commenter->id,
            ]);

            $service->notifyCommentAdded($comment);

            // Creator should be notified
            $creatorNotifications = Notification::where('user_id', $creator->id)->count();
            expect($creatorNotifications)->toBeGreaterThan(0);

            // Assignee should be notified
            $assigneeNotifications = Notification::where('user_id', $assignee->id)->count();
            expect($assigneeNotifications)->toBeGreaterThan(0);

            // Commenter should NOT be notified
            $commenterNotifications = Notification::where('user_id', $commenter->id)->count();
            expect($commenterNotifications)->toBe(0);
        });

        it('notifies users based on their roles in ticket', function () {
            $service = new NotificationService();

            $creator = User::factory()->create();
            $commenter = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => $creator->id]);

            // Assign creator as assignee too
            $ticket->assignUser($creator);

            $initialCount = Notification::where('user_id', $creator->id)->count();

            $comment = TicketComment::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $commenter->id,
            ]);

            $service->notifyCommentAdded($comment);

            $finalCount = Notification::where('user_id', $creator->id)->count();

            // Creator should receive notification (de-duplication handled by service)
            expect($finalCount)->toBeGreaterThan($initialCount);
        });
    });

    describe('Notification Mark as Read Reliability', function () {
        it('successfully marks notification as read', function () {
            $service = new NotificationService();
            $user = User::factory()->create();

            $notification = Notification::create([
                'user_id' => $user->id,
                'type' => 'test',
                'title' => 'Test',
                'message' => 'Test message',
            ]);

            expect($notification->read_at)->toBeNull();

            $result = $service->markAsRead($notification->id, $user->id);

            expect($result)->toBeTrue();
            expect($notification->fresh()->read_at)->not->toBeNull();
        });

        it('prevents marking another user notification as read', function () {
            $service = new NotificationService();
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $notification = Notification::create([
                'user_id' => $user1->id,
                'type' => 'test',
                'title' => 'Test',
                'message' => 'Test message',
            ]);

            // User2 tries to mark User1's notification
            $result = $service->markAsRead($notification->id, $user2->id);

            expect($result)->toBeFalse();
            expect($notification->fresh()->read_at)->toBeNull();
        });

        it('marks all unread notifications as read', function () {
            $service = new NotificationService();
            $user = User::factory()->create();

            // Create multiple unread notifications
            Notification::factory()->count(5)->create([
                'user_id' => $user->id,
                'read_at' => null,
            ]);

            $service->markAllAsRead($user->id);

            $unreadCount = Notification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->count();

            expect($unreadCount)->toBe(0);
        });

        it('does not affect already read notifications', function () {
            $service = new NotificationService();
            $user = User::factory()->create();

            // Create read notification
            $readNotification = Notification::create([
                'user_id' => $user->id,
                'type' => 'test',
                'title' => 'Test',
                'message' => 'Test',
                'read_at' => now()->subHours(2),
            ]);

            $originalReadAt = $readNotification->read_at;

            $service->markAllAsRead($user->id);

            // Read_at should not change
            expect($readNotification->fresh()->read_at->timestamp)
                ->toBe($originalReadAt->timestamp);
        });
    });

    describe('Project Assignment Notification Reliability', function () {
        it('creates notification for project assignment', function () {
            $service = new NotificationService();
            $project = Project::factory()->create();
            $assignedUser = User::factory()->create();
            $assignedBy = User::factory()->create();

            $initialCount = Notification::count();

            $service->notifyProjectAssignment($project, $assignedUser, $assignedBy);

            $finalCount = Notification::count();

            expect($finalCount)->toBeGreaterThan($initialCount);

            $notification = Notification::where('user_id', $assignedUser->id)->latest()->first();

            expect($notification->type)->toBe('project_assigned');
            expect($notification->data)->toHaveKey('project_id');
            expect($notification->data['project_id'])->toBe($project->id);
        });

        it('includes correct information in notification', function () {
            $service = new NotificationService();
            $project = Project::factory()->create(['name' => 'Test Project']);
            $assignedUser = User::factory()->create();
            $assignedBy = User::factory()->create(['name' => 'Admin User']);

            $service->notifyProjectAssignment($project, $assignedUser, $assignedBy);

            $notification = Notification::where('user_id', $assignedUser->id)->latest()->first();

            expect($notification->data)->toHaveKeys([
                'project_id',
                'project_name',
                'assigned_by_id',
                'assigned_by_name',
            ]);

            expect($notification->data['project_name'])->toBe('Test Project');
            expect($notification->data['assigned_by_name'])->toBe('Admin User');
        });
    });

    describe('Project Removal Notification Reliability', function () {
        it('creates notification for project removal', function () {
            $service = new NotificationService();
            $project = Project::factory()->create();
            $removedUser = User::factory()->create();
            $removedBy = User::factory()->create();

            $initialCount = Notification::count();

            $service->notifyProjectRemoval($project, $removedUser, $removedBy);

            $finalCount = Notification::count();

            expect($finalCount)->toBeGreaterThan($initialCount);

            $notification = Notification::where('user_id', $removedUser->id)->latest()->first();

            expect($notification->type)->toBe('project_removed');
            expect($notification->data)->toHaveKey('project_id');
        });
    });

    describe('Comment Update Notification Reliability', function () {
        it('creates notification for comment update', function () {
            $service = new NotificationService();

            $creator = User::factory()->create();
            $commenter = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => $creator->id]);

            $comment = TicketComment::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $commenter->id,
            ]);

            $initialCount = Notification::where('user_id', $creator->id)->count();

            $service->notifyCommentUpdated($comment);

            $finalCount = Notification::where('user_id', $creator->id)->count();

            expect($finalCount)->toBeGreaterThan($initialCount);
        });
    });

    describe('Service Error Resilience', function () {
        it('handles null relationships gracefully', function () {
            $service = new NotificationService();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => null]); // No creator

            $commenter = User::factory()->create();
            $comment = TicketComment::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $commenter->id,
            ]);

            // Should not throw error even with null creator
            expect(function () use ($service, $comment) {
                $service->notifyCommentAdded($comment);
            })->not->toThrow(\Exception::class);
        });

        it('handles missing ticket data gracefully', function () {
            $service = new NotificationService();

            $user = User::factory()->create();

            // Create a malformed notification scenario
            expect(function () use ($service, $user) {
                // Try to mark non-existent notification as read
                $result = $service->markAsRead(99999, $user->id);
                expect($result)->toBeFalse();
            })->not->toThrow(\Exception::class);
        });
    });

    describe('Notification Data Consistency', function () {
        it('maintains consistent notification data structure', function () {
            $service = new NotificationService();

            $creator = User::factory()->create();
            $commenter = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => $creator->id]);

            $comment = TicketComment::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $commenter->id,
            ]);

            $service->notifyCommentAdded($comment);

            $notification = Notification::where('user_id', $creator->id)->latest()->first();

            expect($notification->data)->toBeArray();
            expect($notification->data)->toHaveKeys([
                'ticket_id',
                'comment_id',
                'commenter_id',
                'commenter_name',
            ]);
        });
    });
});
