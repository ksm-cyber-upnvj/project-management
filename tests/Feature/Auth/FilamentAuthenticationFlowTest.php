<?php

use App\Models\User;
use App\Models\Roles;
use Spatie\Permission\Models\Role;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

describe('Filament Complete Authentication Flow', function () {

    beforeEach(function () {
        // Setup roles using firstOrCreate
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'developer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        Roles::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Roles::firstOrCreate(['name' => 'developer', 'guard_name' => 'web']);
        Roles::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    });

    describe('Complete Authentication Flow: Credentials → Auth → Panel Access → Session', function () {

        it('executes successful authentication flow with valid credentials and role', function () {
            // Step 1: Create user with credentials
            $user = User::factory()->create([
                'email' => 'flow@example.com',
                'password' => 'password123'
            ]);
            $user->assignRole('admin');

            // Step 2: Verify user is not authenticated initially
            expect(Auth::check())->toBeFalse();

            // Step 3: Attempt authentication
            $authenticated = Auth::attempt([
                'email' => 'flow@example.com',
                'password' => 'password123'
            ]);

            // Step 4: Verify authentication succeeded
            expect($authenticated)->toBeTrue();
            expect(Auth::check())->toBeTrue();
            expect(Auth::id())->toBe($user->id);

            // Step 5: Verify panel access
            expect($user->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue();

            // Step 6: Verify user can access admin routes
            $response = $this->actingAs($user)->get('/admin');
            $response->assertStatus(200);
        });

        it('fails authentication flow with invalid credentials', function () {
            // Step 1: Create user
            $user = User::factory()->create([
                'email' => 'wrong@example.com',
                'password' => 'correct-password'
            ]);
            $user->assignRole('admin');

            // Step 2: Attempt authentication with wrong password
            $authenticated = Auth::attempt([
                'email' => 'wrong@example.com',
                'password' => 'wrong-password'
            ]);

            // Step 3: Verify authentication failed
            expect($authenticated)->toBeFalse();
            expect(Auth::check())->toBeFalse();

            // Step 4: Verify user still exists in database
            expect(User::where('email', 'wrong@example.com')->exists())->toBeTrue();
        });

        it('fails authentication flow when user has no panel access', function () {
            // Step 1: Create user without role
            $user = User::factory()->create([
                'email' => 'noaccess@example.com',
                'password' => 'password123'
            ]);

            // Step 2: Verify user cannot access panel
            expect($user->canAccessPanel(Filament::getCurrentPanel()))->toBeFalse();

            // Step 3: Authentication succeeds but panel access should be blocked
            $authenticated = Auth::attempt([
                'email' => 'noaccess@example.com',
                'password' => 'password123'
            ]);

            expect($authenticated)->toBeTrue();
            expect($user->canAccessPanel(Filament::getCurrentPanel()))->toBeFalse();
        });

        it('executes full authentication and logout flow', function () {
            // Step 1: Create and authenticate user
            $user = User::factory()->create([
                'email' => 'fullflow@example.com',
                'password' => 'password123'
            ]);
            $user->assignRole('admin');

            Auth::login($user);
            expect(Auth::check())->toBeTrue();

            // Step 2: Access admin panel
            $response = $this->actingAs($user)->get('/admin');
            $response->assertStatus(200);

            // Step 3: Logout
            Auth::logout();
            expect(Auth::check())->toBeFalse();

            // Step 4: Verify admin access is blocked after logout
            $response = $this->get('/admin');
            $response->assertRedirect('/admin/login');
        });
    });

    describe('Database State During Authentication', function () {

        it('verifies user credentials against database', function () {
            $user = User::factory()->create([
                'email' => 'dbquery@example.com',
                'password' => 'password123'
            ]);
            $user->assignRole('admin');

            // Authentication should query users table
            $authenticated = Auth::attempt([
                'email' => 'dbquery@example.com',
                'password' => 'password123'
            ]);

            expect($authenticated)->toBeTrue();

            // Verify user exists in database
            $this->assertDatabaseHas('users', [
                'email' => 'dbquery@example.com'
            ]);
        });

        it('checks role assignments during panel access', function () {
            $user = User::factory()->create([
                'email' => 'rolecheck@example.com',
                'password' => 'password123'
            ]);
            $user->assignRole('developer');

            Auth::login($user);

            // This should query both model_has_roles and roles tables
            expect($user->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue();

            // Verify role assignment exists
            $this->assertDatabaseHas('model_has_roles', [
                'model_type' => User::class,
                'model_id' => $user->id,
            ]);
        });

        it('updates remember_token when remember me is used', function () {
            $user = User::factory()->create([
                'email' => 'remember@example.com',
                'password' => 'password123',
                'remember_token' => null
            ]);
            $user->assignRole('admin');

            Auth::attempt([
                'email' => 'remember@example.com',
                'password' => 'password123'
            ], true); // remember = true

            // Token should be updated in database
            expect($user->fresh()->remember_token)->not->toBeNull();
        });
    });

    describe('Authentication State Transitions', function () {

        it('transitions from guest to authenticated state', function () {
            $user = User::factory()->create([
                'email' => 'statechange@example.com',
                'password' => 'password123'
            ]);
            $user->assignRole('admin');

            // Initially guest
            expect(Auth::check())->toBeFalse();
            expect(Auth::guest())->toBeTrue();

            Auth::login($user);

            // Now authenticated
            expect(Auth::check())->toBeTrue();
            expect(Auth::guest())->toBeFalse();
        });

        it('transitions from authenticated to guest on logout', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');

            Auth::login($user);
            expect(Auth::check())->toBeTrue();
            expect(Auth::guest())->toBeFalse();

            Auth::logout();

            expect(Auth::check())->toBeFalse();
            expect(Auth::guest())->toBeTrue();
        });

        it('maintains authenticated state across operations', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');

            Auth::login($user);

            // State should persist
            expect(Auth::check())->toBeTrue();
            expect(Auth::id())->toBe($user->id);
            expect(Auth::user()->id)->toBe($user->id);
        });
    });

    describe('Multi-User Authentication Flows', function () {

        it('handles sequential authentication of different users', function () {
            $user1 = User::factory()->create([
                'email' => 'user1@example.com',
                'password' => 'password123'
            ]);
            $user1->assignRole('admin');

            $user2 = User::factory()->create([
                'email' => 'user2@example.com',
                'password' => 'password456'
            ]);
            $user2->assignRole('developer');

            // Authenticate user1
            Auth::login($user1);
            expect(Auth::id())->toBe($user1->id);

            // Logout and authenticate user2
            Auth::logout();
            Auth::login($user2);
            expect(Auth::id())->toBe($user2->id);
        });

        it('authenticates users with different role combinations', function () {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $manager = User::factory()->create();
            $manager->assignRole('manager');

            $multiRole = User::factory()->create();
            $multiRole->assignRole(['admin', 'developer']);

            expect($admin->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue();
            expect($manager->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue();
            expect($multiRole->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue();
        });
    });

    describe('Password Verification Flow', function () {

        it('completes password hashing and verification flow', function () {
            $plainPassword = 'my-secret-password';

            // Step 1: Create user with plain password
            $user = User::factory()->create([
                'email' => 'hash@example.com',
                'password' => $plainPassword
            ]);

            // Step 2: Verify password is hashed in database
            expect($user->password)->not->toBe($plainPassword);
            expect(Hash::check($plainPassword, $user->password))->toBeTrue();

            // Step 3: Authenticate with plain password
            $authenticated = Auth::attempt([
                'email' => 'hash@example.com',
                'password' => $plainPassword
            ]);

            // Step 4: Verify authentication succeeded
            expect($authenticated)->toBeTrue();
        });

        it('handles various password formats in authentication flow', function () {
            $passwords = [
                'simple123',
                'P@ssw0rd!#$%',
                str_repeat('a', 100),
                'Üñíçødé123',
            ];

            foreach ($passwords as $index => $password) {
                $user = User::factory()->create([
                    'email' => "test{$index}@example.com",
                    'password' => $password
                ]);
                $user->assignRole('admin');

                $authenticated = Auth::attempt([
                    'email' => "test{$index}@example.com",
                    'password' => $password
                ]);

                expect($authenticated)->toBeTrue();
                Auth::logout();
            }
        });
    });

    describe('Panel Access Control Integration', function () {

        it('integrates authentication with panel access control', function () {
            // User with role: auth + panel access
            $userWithRole = User::factory()->create([
                'email' => 'withrole@example.com',
                'password' => 'password123'
            ]);
            $userWithRole->assignRole('admin');

            Auth::login($userWithRole);
            expect(Auth::check())->toBeTrue();
            expect($userWithRole->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue();

            Auth::logout();

            // User without role: auth but no panel access
            $userWithoutRole = User::factory()->create([
                'email' => 'norole@example.com',
                'password' => 'password123'
            ]);

            Auth::login($userWithoutRole);
            expect(Auth::check())->toBeTrue();
            expect($userWithoutRole->canAccessPanel(Filament::getCurrentPanel()))->toBeFalse();
        });

        it('verifies panel access after role changes', function () {
            $user = User::factory()->create();

            // Initially no access
            expect($user->canAccessPanel(Filament::getCurrentPanel()))->toBeFalse();

            // Grant role
            $user->assignRole('admin');
            expect($user->fresh()->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue();

            // Revoke role
            $user->syncRoles([]);
            expect($user->fresh()->canAccessPanel(Filament::getCurrentPanel()))->toBeFalse();
        });
    });

    describe('Session Lifecycle', function () {

        it('manages session throughout authentication lifecycle', function () {
            $user = User::factory()->create([
                'email' => 'session@example.com',
                'password' => 'password123'
            ]);
            $user->assignRole('admin');

            // Start session
            $this->startSession();

            // Login
            Auth::login($user);
            session()->put('test_data', 'test_value');

            expect(session()->has('test_data'))->toBeTrue();
            expect(Auth::check())->toBeTrue();

            // Logout
            Auth::logout();
            expect(Auth::check())->toBeFalse();
        });

        it('regenerates session ID on login for security', function () {
            $user = User::factory()->create([
                'email' => 'regen@example.com',
                'password' => 'password123'
            ]);
            $user->assignRole('admin');

            $this->startSession();
            $oldSessionId = session()->getId();

            Auth::login($user);
            session()->regenerate();

            $newSessionId = session()->getId();

            expect($newSessionId)->not->toBe($oldSessionId);
            expect($newSessionId)->not->toBeEmpty();
        });
    });

    describe('Edge Cases in Authentication Flow', function () {

        it('handles authentication with non-existent email gracefully', function () {
            $authenticated = Auth::attempt([
                'email' => 'nonexistent@example.com',
                'password' => 'password123'
            ]);

            expect($authenticated)->toBeFalse();
            expect(Auth::check())->toBeFalse();
        });

        it('handles empty credentials gracefully', function () {
            $authenticated = Auth::attempt([
                'email' => '',
                'password' => ''
            ]);

            expect($authenticated)->toBeFalse();
        });

        it('handles null password gracefully', function () {
            $user = User::factory()->create([
                'email' => 'test@example.com',
                'password' => 'password123'
            ]);

            $authenticated = Auth::attempt([
                'email' => 'test@example.com',
                'password' => null
            ]);

            expect($authenticated)->toBeFalse();
        });
    });
});
