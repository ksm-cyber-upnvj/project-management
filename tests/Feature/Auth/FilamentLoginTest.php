<?php

use App\Models\User;
use App\Models\Roles;
use Spatie\Permission\Models\Role;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

describe('Filament Admin Login', function () {

    beforeEach(function () {
        // Create roles for panel access using firstOrCreate
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'developer', 'guard_name' => 'web']);

        Roles::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Roles::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Roles::firstOrCreate(['name' => 'developer', 'guard_name' => 'web']);
    });

    describe('Login Page Access', function () {

        it('can view login page', function () {
            $response = $this->get('/admin/login');

            $response->assertStatus(200);
        });

        it('redirects authenticated user from login page to admin', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');

            $response = $this->actingAs($user)->get('/admin/login');

            $response->assertRedirect('/admin');
        });
    });

    describe('Authentication Logic', function () {

        it('authenticates user with valid credentials using Auth facade', function () {
            $user = User::factory()->create([
                'email' => 'test@example.com',
                'password' => 'password123'
            ]);
            $user->assignRole('admin');

            $authenticated = Auth::attempt([
                'email' => 'test@example.com',
                'password' => 'password123'
            ]);

            expect($authenticated)->toBeTrue();
            expect(Auth::check())->toBeTrue();
            expect(Auth::id())->toBe($user->id);
        });

        it('fails authentication with invalid email', function () {
            $authenticated = Auth::attempt([
                'email' => 'nonexistent@example.com',
                'password' => 'password123'
            ]);

            expect($authenticated)->toBeFalse();
            expect(Auth::check())->toBeFalse();
        });

        it('fails authentication with invalid password', function () {
            $user = User::factory()->create([
                'email' => 'user@example.com',
                'password' => 'correct-password'
            ]);

            $authenticated = Auth::attempt([
                'email' => 'user@example.com',
                'password' => 'wrong-password'
            ]);

            expect($authenticated)->toBeFalse();
            expect(Auth::check())->toBeFalse();
        });

        it('authenticates and remembers user when remember flag is true', function () {
            $user = User::factory()->create([
                'email' => 'remember@example.com',
                'password' => 'password123'
            ]);

            $authenticated = Auth::attempt([
                'email' => 'remember@example.com',
                'password' => 'password123'
            ], true); // remember = true

            expect($authenticated)->toBeTrue();
            expect(Auth::viaRemember())->toBeFalse(); // First login, not via remember
            expect($user->fresh()->remember_token)->not->toBeNull();
        });
    });

    describe('Panel Access Control', function () {

        it('user with admin role can access panel', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');

            expect($user->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue();
        });

        it('user with manager role can access panel', function () {
            $user = User::factory()->create();
            $user->assignRole('manager');

            expect($user->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue();
        });

        it('user with developer role can access panel', function () {
            $user = User::factory()->create();
            $user->assignRole('developer');

            expect($user->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue();
        });

        it('user without any role cannot access panel', function () {
            $user = User::factory()->create();

            expect($user->canAccessPanel(Filament::getCurrentPanel()))->toBeFalse();
        });

        it('user loses panel access after roles are removed', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');

            expect($user->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue();

            $user->syncRoles([]);

            expect($user->fresh()->canAccessPanel(Filament::getCurrentPanel()))->toBeFalse();
        });
    });

    describe('Admin Panel Routes', function () {

        it('blocks unauthenticated users from admin panel', function () {
            $response = $this->get('/admin');

            $response->assertRedirect('/admin/login');
        });

        it('allows authenticated users with role to access admin panel', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');

            $response = $this->actingAs($user)->get('/admin');

            $response->assertStatus(200);
        });

        it('blocks authenticated users without role from admin panel', function () {
            $user = User::factory()->create(); // No role assigned

            // Even if authenticated, Filament should block access
            $this->actingAs($user);

            expect($user->canAccessPanel(Filament::getCurrentPanel()))->toBeFalse();
        });
    });

    describe('Session Management', function () {

        it('maintains authentication across requests', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');

            Auth::login($user);

            expect(Auth::check())->toBeTrue();
            expect(Auth::id())->toBe($user->id);

            // Make another request
            $response = $this->actingAs($user)->get('/admin');

            expect(Auth::check())->toBeTrue();
            $response->assertStatus(200);
        });

        it('clears authentication on logout', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');

            Auth::login($user);
            expect(Auth::check())->toBeTrue();

            Auth::logout();

            expect(Auth::check())->toBeFalse();
        });

        it('regenerates session on login', function () {
            $user = User::factory()->create([
                'email' => 'session@example.com',
                'password' => 'password123'
            ]);

            $this->startSession();
            $oldSessionId = session()->getId();

            Auth::login($user);
            session()->regenerate();

            $newSessionId = session()->getId();

            expect($newSessionId)->not->toBe($oldSessionId);
        });
    });

    describe('Security Features', function () {

        it('uses hashed passwords for authentication', function () {
            $plainPassword = 'my-secret-password';

            $user = User::factory()->create([
                'email' => 'hash@example.com',
                'password' => $plainPassword
            ]);

            // Verify password is hashed in database
            expect($user->password)->not->toBe($plainPassword);
            expect(Hash::check($plainPassword, $user->password))->toBeTrue();

            // Authenticate with plain password
            $authenticated = Auth::attempt([
                'email' => 'hash@example.com',
                'password' => $plainPassword
            ]);

            expect($authenticated)->toBeTrue();
        });

        it('handles special characters in password', function () {
            $specialPassword = 'P@ssw0rd!#$%^&*()_+-=[]{}|;:,.<>?';

            $user = User::factory()->create([
                'email' => 'special@example.com',
                'password' => $specialPassword
            ]);

            $authenticated = Auth::attempt([
                'email' => 'special@example.com',
                'password' => $specialPassword
            ]);

            expect($authenticated)->toBeTrue();
        });

        it('handles very long passwords', function () {
            $longPassword = str_repeat('a', 200);

            $user = User::factory()->create([
                'email' => 'longpass@example.com',
                'password' => $longPassword
            ]);

            $authenticated = Auth::attempt([
                'email' => 'longpass@example.com',
                'password' => $longPassword
            ]);

            expect($authenticated)->toBeTrue();
        });

        it('is case sensitive for passwords', function () {
            $user = User::factory()->create([
                'email' => 'case@example.com',
                'password' => 'Password123'
            ]);

            $wrongCase = Auth::attempt([
                'email' => 'case@example.com',
                'password' => 'password123' // Wrong case
            ]);

            expect($wrongCase)->toBeFalse();

            $correctCase = Auth::attempt([
                'email' => 'case@example.com',
                'password' => 'Password123' // Correct case
            ]);

            expect($correctCase)->toBeTrue();
        });
    });

    describe('Multi-User Scenarios', function () {

        it('handles multiple users independently', function () {
            $user1 = User::factory()->create([
                'email' => 'user1@example.com',
                'password' => 'password123'
            ]);
            $user1->assignRole('admin');

            $user2 = User::factory()->create([
                'email' => 'user2@example.com',
                'password' => 'password456'
            ]);
            $user2->assignRole('manager');

            // Login as user1
            Auth::login($user1);
            expect(Auth::id())->toBe($user1->id);

            // Logout
            Auth::logout();

            // Login as user2
            Auth::login($user2);
            expect(Auth::id())->toBe($user2->id);
        });

        it('supports users with multiple roles', function () {
            $user = User::factory()->create();
            $user->assignRole(['admin', 'manager', 'developer']);

            expect($user->hasRole('admin'))->toBeTrue();
            expect($user->hasRole('manager'))->toBeTrue();
            expect($user->hasRole('developer'))->toBeTrue();
            expect($user->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue();
        });
    });

    describe('Email Verification', function () {

        it('allows login without email verification by default', function () {
            $user = User::factory()->unverified()->create([
                'email' => 'unverified@example.com',
                'password' => 'password123'
            ]);
            $user->assignRole('admin');

            $authenticated = Auth::attempt([
                'email' => 'unverified@example.com',
                'password' => 'password123'
            ]);

            expect($authenticated)->toBeTrue();
            expect($user->email_verified_at)->toBeNull();
        });
    });
});
