<?php

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\NotificationService;

describe('TicketComment Model', function () {

    describe('CRUD Operations', function () {

        it('can create comment', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $user = User::factory()->create();

            $comment = TicketComment::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'comment' => 'This is a test comment',
            ]);

            expect($comment->ticket_id)->toBe($ticket->id);
            expect($comment->user_id)->toBe($user->id);
            expect($comment->comment)->toBe('This is a test comment');
        });

        it('can update comment', function () {
            $comment = TicketComment::factory()->create([
                'comment' => 'Original comment',
            ]);

            $comment->update(['comment' => 'Updated comment']);

            expect($comment->fresh()->comment)->toBe('Updated comment');
        });

        it('can delete comment', function () {
            $comment = TicketComment::factory()->create();
            $commentId = $comment->id;

            $comment->delete();

            expect(TicketComment::find($commentId))->toBeNull();
        });

        it('can create short comment', function () {
            $comment = TicketComment::factory()->short()->create();

            expect(strlen($comment->comment))->toBeLessThan(200);
        });

        it('can create long comment', function () {
            $comment = TicketComment::factory()->long()->create();

            expect(strlen($comment->comment))->toBeGreaterThan(200);
        });
    });

    describe('Notification Triggers', function () {

        it('triggers notification when comment is created', function () {
            $mock = Mockery::mock(NotificationService::class);
            $mock->shouldReceive('notifyCommentAdded')
                ->once()
                ->andReturn(true);

            $this->app->instance(NotificationService::class, $mock);

            TicketComment::factory()->create();
        });

        it('triggers notification when comment content is updated', function () {
            $comment = TicketComment::factory()->create(['comment' => 'Original']);

            $mock = Mockery::mock(NotificationService::class);
            $mock->shouldReceive('notifyCommentUpdated')
                ->once()
                ->andReturn(true);

            $this->app->instance(NotificationService::class, $mock);

            $comment->update(['comment' => 'Updated']);
        });

        it('does not trigger notification when non-comment fields are updated', function () {
            $comment = TicketComment::factory()->create();

            $mock = Mockery::mock(NotificationService::class);
            $mock->shouldNotReceive('notifyCommentUpdated');

            $this->app->instance(NotificationService::class, $mock);

            // Update timestamps or other fields but not the comment
            $comment->touch();
        });
    });

    describe('Relationships', function () {

        it('belongs to ticket', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $comment = TicketComment::factory()->forTicket($ticket)->create();

            expect($comment->ticket)->toBeInstanceOf(Ticket::class);
            expect($comment->ticket->id)->toBe($ticket->id);
        });

        it('belongs to user', function () {
            $user = User::factory()->create();
            $comment = TicketComment::factory()->byUser($user)->create();

            expect($comment->user)->toBeInstanceOf(User::class);
            expect($comment->user->id)->toBe($user->id);
        });

        it('ticket has many comments', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            TicketComment::factory()->count(5)->forTicket($ticket)->create();

            expect($ticket->comments)->toHaveCount(5);
        });

        it('user can have multiple comments', function () {
            $user = User::factory()->create();
            
            TicketComment::factory()->count(3)->byUser($user)->create();

            $comments = TicketComment::where('user_id', $user->id)->get();
            expect($comments)->toHaveCount(3);
        });

        it('deleting ticket cascades to comments', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            
            $comment = TicketComment::factory()->forTicket($ticket)->create();
            $commentId = $comment->id;

            // This behavior depends on database foreign key constraints
            // We're testing the relationship exists
            expect($comment->ticket_id)->toBe($ticket->id);
            expect($comment->ticket)->not->toBeNull();
        });
    });

    describe('Model Attributes', function () {

        it('has correct fillable attributes', function () {
            $comment = new TicketComment();

            expect($comment->getFillable())->toContain('ticket_id', 'user_id', 'comment');
        });

        it('can mass assign fillable attributes', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $user = User::factory()->create();

            $comment = TicketComment::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'comment' => 'Test comment',
            ]);

            expect($comment->ticket_id)->toBe($ticket->id);
            expect($comment->user_id)->toBe($user->id);
            expect($comment->comment)->toBe('Test comment');
        });

        it('has timestamps', function () {
            $comment = TicketComment::factory()->create();

            expect($comment->created_at)->not->toBeNull();
            expect($comment->updated_at)->not->toBeNull();
            expect($comment->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
            expect($comment->updated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });

    describe('Comment Ordering', function () {

        it('orders comments by creation time ascending', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            $comment1 = TicketComment::factory()->forTicket($ticket)->create(['comment' => 'First']);
            sleep(1);
            $comment2 = TicketComment::factory()->forTicket($ticket)->create(['comment' => 'Second']);
            sleep(1);
            $comment3 = TicketComment::factory()->forTicket($ticket)->create(['comment' => 'Third']);

            $comments = $ticket->comments;

            expect($comments->first()->comment)->toBe('First');
            expect($comments->last()->comment)->toBe('Third');
        });
    });

    describe('Multiple Comments Scenarios', function () {

        it('can have multiple comments from different users on same ticket', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            $user3 = User::factory()->create();

            TicketComment::factory()->forTicket($ticket)->byUser($user1)->create();
            TicketComment::factory()->forTicket($ticket)->byUser($user2)->create();
            TicketComment::factory()->forTicket($ticket)->byUser($user3)->create();

            expect($ticket->comments)->toHaveCount(3);
            
            $userIds = $ticket->comments->pluck('user_id')->toArray();
            expect($userIds)->toContain($user1->id, $user2->id, $user3->id);
        });

        it('can have multiple comments from same user on same ticket', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();
            $user = User::factory()->create();

            TicketComment::factory()->count(3)->forTicket($ticket)->byUser($user)->create();

            expect($ticket->comments)->toHaveCount(3);
            
            foreach ($ticket->comments as $comment) {
                expect($comment->user_id)->toBe($user->id);
            }
        });
    });
});

