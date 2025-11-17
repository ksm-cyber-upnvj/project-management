<?php

use App\Models\Notification;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;

describe('Notification Model', function () {

    describe('Read/Unread Status Management', function () {

        it('can mark notification as read', function () {
            $notification = Notification::factory()->unread()->create();

            expect($notification->read_at)->toBeNull();
            expect($notification->isUnread())->toBeTrue();

            $result = $notification->markAsRead();

            expect($result)->toBeTrue();
            expect($notification->fresh()->read_at)->not->toBeNull();
            expect($notification->fresh()->isRead())->toBeTrue();
        });

        it('isRead returns true for read notifications', function () {
            $notification = Notification::factory()->read()->create();

            expect($notification->isRead())->toBeTrue();
            expect($notification->isUnread())->toBeFalse();
        });

        it('isUnread returns true for unread notifications', function () {
            $notification = Notification::factory()->unread()->create();

            expect($notification->isUnread())->toBeTrue();
            expect($notification->isRead())->toBeFalse();
        });

        it('can mark as read multiple times without error', function () {
            $notification = Notification::factory()->unread()->create();

            $notification->markAsRead();
            $firstReadAt = $notification->fresh()->read_at;

            sleep(1);
            $notification->fresh()->markAsRead();
            $secondReadAt = $notification->fresh()->read_at;

            expect($firstReadAt)->not->toBeNull();
            expect($secondReadAt)->not->toBeNull();
        });

        it('markAsRead updates timestamp correctly', function () {
            $notification = Notification::factory()->unread()->create();

            $notification->markAsRead();
            $readAt = $notification->fresh()->read_at;

            expect($readAt)->not->toBeNull();
            expect($readAt)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
            
            // Check if read_at is recent (within last minute)
            expect($readAt->diffInSeconds(now()))->toBeLessThan(60);
        });
    });

    describe('Query Scopes', function () {

        it('unread scope filters only unread notifications', function () {
            $user = User::factory()->create();
            
            Notification::factory()->count(3)->unread()->forUser($user)->create();
            Notification::factory()->count(2)->read()->forUser($user)->create();

            $unreadNotifications = Notification::where('user_id', $user->id)->unread()->get();

            expect($unreadNotifications)->toHaveCount(3);
            foreach ($unreadNotifications as $notification) {
                expect($notification->read_at)->toBeNull();
            }
        });

        it('read scope filters only read notifications', function () {
            $user = User::factory()->create();
            
            Notification::factory()->count(2)->read()->forUser($user)->create();
            Notification::factory()->count(3)->unread()->forUser($user)->create();

            $readNotifications = Notification::where('user_id', $user->id)->read()->get();

            expect($readNotifications)->toHaveCount(2);
            foreach ($readNotifications as $notification) {
                expect($notification->read_at)->not->toBeNull();
            }
        });

        it('can combine scopes with other queries', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            
            Notification::factory()->count(2)->unread()->forUser($user1)->ofType('ticket_assigned')->create();
            Notification::factory()->count(1)->unread()->forUser($user1)->ofType('comment_added')->create();
            Notification::factory()->count(1)->read()->forUser($user1)->ofType('ticket_assigned')->create();
            Notification::factory()->count(2)->unread()->forUser($user2)->ofType('ticket_assigned')->create();

            $result = Notification::where('user_id', $user1->id)
                ->where('type', 'ticket_assigned')
                ->unread()
                ->get();

            expect($result)->toHaveCount(2);
        });

        it('scopes return empty collection when no matches', function () {
            $user = User::factory()->create();
            Notification::factory()->count(3)->read()->forUser($user)->create();

            $unreadNotifications = Notification::where('user_id', $user->id)->unread()->get();

            expect($unreadNotifications)->toBeEmpty();
        });
    });

    describe('Relationships', function () {

        it('belongs to user', function () {
            $user = User::factory()->create();
            $notification = Notification::factory()->forUser($user)->create();

            expect($notification->user)->toBeInstanceOf(User::class);
            expect($notification->user->id)->toBe($user->id);
        });

        it('user has many notifications', function () {
            $user = User::factory()->create();
            Notification::factory()->count(5)->forUser($user)->create();

            expect($user->notifications)->toHaveCount(5);
        });

        it('ticket relationship returns correct ticket', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            
            $notification = Notification::factory()->withTicket($ticket)->create();

            // Test the getTicketAttribute method
            $relatedTicket = $notification->ticket;
            
            expect($relatedTicket)->not->toBeNull();
            expect($relatedTicket)->toBeInstanceOf(Ticket::class);
            expect($relatedTicket->id)->toBe($ticket->id);
        });
    });

    describe('Custom Attributes', function () {

        it('ticket attribute extracts ticket from data', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            
            $notification = Notification::factory()->withTicket($ticket)->create();

            $extractedTicket = $notification->ticket;

            expect($extractedTicket)->not->toBeNull();
            expect($extractedTicket->id)->toBe($ticket->id);
        });

        it('ticket_name attribute returns correct ticket name', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create([
                'name' => 'Test Ticket Name'
            ]);
            
            $notification = Notification::factory()->withTicket($ticket)->create();

            expect($notification->ticket_name)->toBe('Test Ticket Name');
        });

        it('project_name attribute returns correct project name', function () {
            $project = Project::factory()->create(['name' => 'Test Project']);
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            
            $notification = Notification::factory()->withTicket($ticket)->create();

            expect($notification->project_name)->toBe('Test Project');
        });

        it('ticket attribute returns null when no ticket_id in data', function () {
            $notification = Notification::factory()->create(['data' => []]);

            expect($notification->ticket)->toBeNull();
        });

        it('ticket_name returns null when ticket not found', function () {
            $notification = Notification::factory()->create(['data' => ['ticket_id' => 99999]]);

            expect($notification->ticket_name)->toBeNull();
        });

        it('project_name returns null when ticket not found', function () {
            $notification = Notification::factory()->create(['data' => ['ticket_id' => 99999]]);

            expect($notification->project_name)->toBeNull();
        });

        it('handles deleted ticket gracefully', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            
            $notification = Notification::factory()->withTicket($ticket)->create();
            $ticketId = $ticket->id;
            
            // Delete the ticket
            $ticket->delete();

            // Should not throw error when accessing ticket attributes
            expect($notification->fresh()->ticket)->toBeNull();
            expect($notification->fresh()->ticket_name)->toBeNull();
            expect($notification->fresh()->project_name)->toBeNull();
        });
    });

    describe('Model Attributes', function () {

        it('has correct fillable attributes', function () {
            $notification = new Notification();

            expect($notification->getFillable())->toContain(
                'user_id',
                'type',
                'title',
                'message',
                'data',
                'read_at'
            );
        });

        it('casts data to array', function () {
            $notification = Notification::factory()->create([
                'data' => ['key' => 'value', 'number' => 123]
            ]);

            expect($notification->data)->toBeArray();
            expect($notification->data)->toHaveKey('key');
            expect($notification->data['key'])->toBe('value');
            expect($notification->data['number'])->toBe(123);
        });

        it('casts read_at to datetime', function () {
            $notification = Notification::factory()->read()->create();

            expect($notification->read_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('can create notification with all attributes', function () {
            $user = User::factory()->create();
            
            $notification = Notification::factory()->create([
                'user_id' => $user->id,
                'type' => 'custom_type',
                'title' => 'Custom Title',
                'message' => 'Custom Message',
                'data' => ['custom_key' => 'custom_value'],
            ]);

            expect($notification->user_id)->toBe($user->id);
            expect($notification->type)->toBe('custom_type');
            expect($notification->title)->toBe('Custom Title');
            expect($notification->message)->toBe('Custom Message');
            expect($notification->data['custom_key'])->toBe('custom_value');
        });

        it('can store complex data in data attribute', function () {
            $complexData = [
                'ticket_id' => 1,
                'user_id' => 2,
                'metadata' => [
                    'action' => 'updated',
                    'fields' => ['status', 'priority']
                ]
            ];

            $notification = Notification::factory()->create(['data' => $complexData]);

            expect($notification->fresh()->data)->toEqual($complexData);
        });
    });

    describe('Notification Types', function () {

        it('can create notification with specific type', function () {
            $notification = Notification::factory()->ofType('ticket_assigned')->create();

            expect($notification->type)->toBe('ticket_assigned');
        });

        it('supports multiple notification types', function () {
            $types = ['ticket_assigned', 'ticket_updated', 'comment_added', 'status_changed'];

            foreach ($types as $type) {
                $notification = Notification::factory()->ofType($type)->create();
                expect($notification->type)->toBe($type);
            }
        });
    });
});

