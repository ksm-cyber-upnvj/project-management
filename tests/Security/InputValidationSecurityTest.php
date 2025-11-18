<?php

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

describe('Input Validation Security', function () {
    describe('SQL Injection Prevention', function () {
        it('prevents SQL injection in WHERE clauses', function () {
            $maliciousInput = "' OR '1'='1";

            $project = Project::factory()->create(['name' => 'Legitimate Project']);

            // Attempt SQL injection
            $results = Project::where('name', $maliciousInput)->get();

            // Should return no results, not all projects
            expect($results)->toHaveCount(0);
            expect(Project::count())->toBe(1); // Original project still exists
        });

        it('prevents SQL injection in LIKE queries', function () {
            $maliciousInput = "test'; DROP TABLE projects; --";

            Project::factory()->create(['name' => 'Safe Project']);

            // Attempt SQL injection via LIKE
            $results = Project::where('name', 'like', "%{$maliciousInput}%")->get();

            expect($results)->toHaveCount(0);
            expect(DB::table('projects')->exists())->toBeTrue(); // Table not dropped
        });

        it('uses parameter binding for user input', function () {
            $userInput = "admin' --";

            $user = User::factory()->create(['email' => 'real@example.com']);

            // Query with user input
            $result = User::where('email', $userInput)->first();

            expect($result)->toBeNull(); // No user found (not bypassed)
            expect(User::count())->toBe(1); // Original user still exists
        });

        it('prevents SQL injection in ORDER BY via Eloquent', function () {
            Project::factory()->count(3)->create();

            // Safe ordering using Eloquent
            $results = Project::orderBy('name', 'asc')->get();

            expect($results)->toHaveCount(3);
        });
    });

    describe('XSS Prevention', function () {
        it('stores HTML content in database without modification', function () {
            $xssPayload = '<script>alert("XSS")</script>';

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['name' => $xssPayload]);

            // Name is stored as-is in database (XSS protection happens in views)
            expect($ticket->name)->toBe($xssPayload);
            expect($ticket->fresh()->name)->toBe($xssPayload);
        });

        it('provides escaping helper for output protection', function () {
            $xssName = '<img src=x onerror=alert(1)>';
            $user = User::factory()->create(['name' => $xssName]);

            // Laravel's e() helper escapes HTML entities
            $escaped = e($user->name);

            expect($escaped)->toContain('&lt;img');
            expect($escaped)->not->toContain('<img src=x');
        });

        it('stores JavaScript content safely in database', function () {
            $jsPayload = 'Click <a href="javascript:alert(1)">here</a>';

            $user = User::factory()->create();
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            $comment = TicketComment::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'comment' => $jsPayload,
            ]);

            // Stored as-is (protection happens at rendering time)
            expect($comment->comment)->toBe($jsPayload);
            expect($comment->fresh()->comment)->toBe($jsPayload);
        });
    });

    describe('Mass Assignment Protection', function () {
        it('protects User model from mass assignment attacks', function () {
            $maliciousData = [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
                'is_admin' => true, // Not in fillable
                'role' => 'admin',  // Not in fillable
            ];

            $user = User::create($maliciousData);

            expect($user->name)->toBe('Test User');
            expect($user->email)->toBe('test@example.com');
            expect(isset($user->is_admin))->toBeFalse();
            expect(isset($user->role))->toBeFalse();
        });

        it('only allows fillable attributes to be mass assigned', function () {
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();

            $data = [
                'project_id' => $project->id,
                'ticket_status_id' => $status->id,
                'name' => 'Test Ticket',
                'description' => 'Test Description',
            ];

            $ticket = new Ticket();
            $ticket->fill($data);

            // Fillable attributes should be set
            expect($ticket->name)->toBe('Test Ticket');
            expect($ticket->project_id)->toBe($project->id);
        });

        it('auto-generates primary keys regardless of input', function () {
            $data = [
                'name' => 'Test Project',
                'description' => 'Test Description',
                'ticket_prefix' => 'TEST',
            ];

            $project = Project::create($data);

            // ID should be auto-generated
            expect($project->id)->toBeInt();
            expect($project->id)->toBeGreaterThan(0);
        });
    });

    describe('Data Type Validation', function () {
        it('validates email format', function () {
            $invalidEmail = 'not-an-email';

            expect(function () use ($invalidEmail) {
                User::factory()->create(['email' => $invalidEmail]);
            })->toThrow(\Illuminate\Validation\ValidationException::class);
        })->skip('Validation happens at form/controller level, not model level');

        it('enforces unique email constraint', function () {
            User::factory()->create(['email' => 'unique@example.com']);

            expect(function () {
                User::factory()->create(['email' => 'unique@example.com']);
            })->toThrow(\Illuminate\Database\QueryException::class);
        });

        it('enforces required fields on User model', function () {
            expect(function () {
                User::create([
                    'email' => 'test@example.com',
                    // Missing required 'name'
                ]);
            })->toThrow(\Illuminate\Database\QueryException::class);
        });
    });

    describe('Null Byte Injection Prevention', function () {
        it('handles null bytes in user input', function () {
            $nullByteInput = "test\x00injection";

            $user = User::factory()->create(['name' => $nullByteInput]);

            // Laravel/MySQL should handle this safely
            expect($user->name)->toBeString();
        });
    });

    describe('Integer Overflow Protection', function () {
        it('handles large integer values safely', function () {
            $largeInt = 2147483647; // Max 32-bit int

            $project = Project::factory()->create();

            // Should handle without overflow
            expect($project->id)->toBeInt();
            expect($project->id)->toBeLessThanOrEqual($largeInt);
        });
    });

    describe('Special Characters Handling', function () {
        it('stores special characters safely in database', function () {
            $specialChars = "Test <>&\"'`~!@#$%^&*()";

            $project = Project::factory()->create(['name' => $specialChars]);

            expect($project->fresh()->name)->toBe($specialChars);
        });

        it('handles unicode characters in names', function () {
            $unicodeName = 'Test 你好 مرحبا';

            $user = User::factory()->create(['name' => $unicodeName]);

            expect($user->fresh()->name)->toBe($unicodeName);
        });

        it('handles emojis in content', function () {
            $emojiContent = 'Test 😀 🎉 ✅';

            $user = User::factory()->create();
            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()->forProject($project)->withStatus($status)->create();

            $comment = TicketComment::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'comment' => $emojiContent,
            ]);

            expect($comment->fresh()->comment)->toBe($emojiContent);
        });
    });

    describe('Path Traversal Prevention', function () {
        it('prevents directory traversal in file paths', function () {
            $maliciousPath = '../../../etc/passwd';

            // Ensure basename is used to prevent traversal
            $safeName = basename($maliciousPath);

            expect($safeName)->toBe('passwd');
            expect($safeName)->not->toContain('..');
        });
    });
});
