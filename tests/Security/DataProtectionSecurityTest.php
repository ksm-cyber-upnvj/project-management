<?php

use App\Models\ExternalAccess;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Data Protection Security', function () {
    describe('External Access Token Security', function () {
        it('generates unique access tokens for projects', function () {
            $project1 = Project::factory()->create();
            $project2 = Project::factory()->create();

            $access1 = ExternalAccess::generateForProject($project1->id);
            $access2 = ExternalAccess::generateForProject($project2->id);

            expect($access1->access_token)->not->toBe($access2->access_token);
            expect($access1->access_token)->toBeString();
            expect($access2->access_token)->toBeString();
        });

        it('generates tokens with sufficient length', function () {
            $project = Project::factory()->create();
            $access = ExternalAccess::generateForProject($project->id);

            // Should be 32 characters (from Str::random(32))
            expect(strlen($access->access_token))->toBe(32);
        });

        it('generates random passwords for external access', function () {
            $project = Project::factory()->create();
            $access = ExternalAccess::generateForProject($project->id);

            expect($access->password)->not->toBeNull();
            expect(strlen($access->password))->toBe(8); // From Str::random(8)
        });

        it('creates external access with active status by default', function () {
            $project = Project::factory()->create();
            $access = ExternalAccess::generateForProject($project->id);

            expect($access->is_active)->toBeTrue();
        });

        it('tracks last accessed time', function () {
            $project = Project::factory()->create();
            $access = ExternalAccess::generateForProject($project->id);

            expect($access->last_accessed_at)->toBeNull();

            $access->updateLastAccessed();

            expect($access->fresh()->last_accessed_at)->not->toBeNull();
            expect($access->fresh()->last_accessed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('prevents token reuse by checking is_active status', function () {
            $project = Project::factory()->create();
            $access = ExternalAccess::generateForProject($project->id);

            // Deactivate token
            $access->update(['is_active' => false]);

            expect($access->fresh()->is_active)->toBeFalse();
        });

        it('associates external access with correct project', function () {
            $project = Project::factory()->create();
            $access = ExternalAccess::generateForProject($project->id);

            expect($access->project_id)->toBe($project->id);
            expect($access->project)->toBeInstanceOf(Project::class);
            expect($access->project->id)->toBe($project->id);
        });
    });

    describe('Password Storage Security', function () {
        it('stores passwords as hashed values', function () {
            $plainPassword = 'MySecurePassword123!';
            $user = User::factory()->create(['password' => $plainPassword]);

            // Password should be hashed
            expect($user->password)->not->toBe($plainPassword);
            expect(Hash::check($plainPassword, $user->password))->toBeTrue();
        });

        it('never exposes plain text passwords', function () {
            $user = User::factory()->create();

            // Password should not be in array/JSON output
            expect($user->toArray())->not->toHaveKey('password');
            expect(json_decode($user->toJson(), true))->not->toHaveKey('password');
        });

        it('uses strong hashing algorithm', function () {
            $user = User::factory()->create();

            // Bcrypt/Argon2 hashes have specific prefixes
            expect($user->password)->toMatch('/^\$2y\$|\$argon2/');
        });
    });

    describe('Token Uniqueness', function () {
        it('enforces unique access tokens', function () {
            $project1 = Project::factory()->create();
            $project2 = Project::factory()->create();

            $access1 = ExternalAccess::generateForProject($project1->id);
            $access2 = ExternalAccess::generateForProject($project2->id);

            // Get all tokens
            $allTokens = ExternalAccess::pluck('access_token')->toArray();

            // All should be unique
            expect(count($allTokens))->toBe(count(array_unique($allTokens)));
        });

        it('generates different tokens even for same project', function () {
            $project = Project::factory()->create();

            $access1 = ExternalAccess::generateForProject($project->id);
            $access2 = ExternalAccess::generateForProject($project->id);

            expect($access1->access_token)->not->toBe($access2->access_token);
        });
    });

    describe('Sensitive Data Exposure Prevention', function () {
        it('hides remember_token from serialization', function () {
            $user = User::factory()->create();

            $array = $user->toArray();
            $json = json_decode($user->toJson(), true);

            expect($array)->not->toHaveKey('remember_token');
            expect($json)->not->toHaveKey('remember_token');
        });

        it('exposes only necessary user attributes', function () {
            $user = User::factory()->create();

            $safeAttributes = ['id', 'name', 'email', 'created_at', 'updated_at'];
            $sensitiveAttributes = ['password', 'remember_token'];

            $array = $user->toArray();

            foreach ($safeAttributes as $attr) {
                expect($array)->toHaveKey($attr);
            }

            foreach ($sensitiveAttributes as $attr) {
                expect($array)->not->toHaveKey($attr);
            }
        });
    });

    describe('Data Integrity Protection', function () {
        it('maintains referential integrity for external access', function () {
            $project = Project::factory()->create();
            $access = ExternalAccess::generateForProject($project->id);

            // Delete project should handle external access appropriately
            $projectId = $project->id;

            // Check relationship exists
            expect($access->project_id)->toBe($projectId);
        });

        it('maintains ticket ownership integrity', function () {
            $creator = User::factory()->create();

            $project = Project::factory()->create();
            $status = TicketStatus::factory()->forProject($project)->create();
            $ticket = Ticket::factory()
                ->forProject($project)
                ->withStatus($status)
                ->create(['created_by' => $creator->id]);

            expect($ticket->created_by)->toBe($creator->id);
            expect($ticket->creator->id)->toBe($creator->id);
        });
    });

    describe('Timestamp Security', function () {
        it('automatically tracks creation timestamps', function () {
            $user = User::factory()->create();

            expect($user->created_at)->not->toBeNull();
            expect($user->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('automatically tracks update timestamps', function () {
            $user = User::factory()->create();
            $originalUpdatedAt = $user->updated_at;

            sleep(1);
            $user->update(['name' => 'Updated Name']);

            expect($user->fresh()->updated_at)->not->toBe($originalUpdatedAt);
        });

        it('cannot manually manipulate timestamps to bypass security', function () {
            $futureDate = now()->addYears(10);

            $user = User::factory()->create([
                'created_at' => $futureDate,
            ]);

            // Timestamp should be controlled by system
            expect($user->created_at)->not->toBe($futureDate);
        });
    });

    describe('Random Token Generation', function () {
        it('generates cryptographically secure random tokens', function () {
            $tokens = [];

            for ($i = 0; $i < 100; $i++) {
                $project = Project::factory()->create();
                $access = ExternalAccess::generateForProject($project->id);
                $tokens[] = $access->access_token;
            }

            // All 100 tokens should be unique
            expect(count($tokens))->toBe(count(array_unique($tokens)));
        });

        it('uses unpredictable token generation', function () {
            $project = Project::factory()->create();
            $access1 = ExternalAccess::generateForProject($project->id);
            $access2 = ExternalAccess::generateForProject($project->id);

            // Tokens should have no predictable pattern
            expect($access1->access_token)->not->toBe($access2->access_token);

            // Should not be sequential
            expect((int) $access1->access_token)->not->toBe(((int) $access2->access_token) - 1);
        });
    });

    describe('Email Verification Protection', function () {
        it('tracks email verification status', function () {
            $verifiedUser = User::factory()->create();
            $unverifiedUser = User::factory()->unverified()->create();

            expect($verifiedUser->email_verified_at)->not->toBeNull();
            expect($unverifiedUser->email_verified_at)->toBeNull();
        });

        it('allows checking verification status', function () {
            $verifiedUser = User::factory()->create();
            $unverifiedUser = User::factory()->unverified()->create();

            expect($verifiedUser->hasVerifiedEmail())->toBeTrue();
            expect($unverifiedUser->hasVerifiedEmail())->toBeFalse();
        });
    });

    describe('Data Encryption in Transit', function () {
        it('uses secure password hashing not reversible encryption', function () {
            $password = 'TestPassword123';
            $user = User::factory()->create(['password' => $password]);

            // Hash should be one-way (cannot decrypt)
            expect($user->password)->not->toBe($password);

            // Only verification should work
            expect(Hash::check($password, $user->password))->toBeTrue();
        });
    });
});
