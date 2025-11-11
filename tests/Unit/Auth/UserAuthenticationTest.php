<?php

use App\Models\User;
use App\Models\Roles;
use Filament\Panel;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

describe('User Authentication Methods', function () {

    beforeEach(function () {
        // Create roles in database for canAccessPanel testing using firstOrCreate
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'developer', 'guard_name' => 'web']);

        // Create corresponding entries in Roles table
        Roles::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Roles::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Roles::firstOrCreate(['name' => 'developer', 'guard_name' => 'web']);
    });

    describe('canAccessPanel()', function () {

        it('returns true when user has a valid role', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');

            $panel = Mockery::mock(Panel::class);

            expect($user->canAccessPanel($panel))->toBeTrue();
        });

        it('returns false when user has no roles', function () {
            $user = User::factory()->create();

            $panel = Mockery::mock(Panel::class);

            expect($user->canAccessPanel($panel))->toBeFalse();
        });

        it('returns true when user has any of the available roles', function () {
            $user = User::factory()->create();
            $user->assignRole('developer');

            $panel = Mockery::mock(Panel::class);

            expect($user->canAccessPanel($panel))->toBeTrue();
        });

        it('returns true when user has multiple roles', function () {
            $user = User::factory()->create();
            $user->assignRole(['admin', 'manager']);

            $panel = Mockery::mock(Panel::class);

            expect($user->canAccessPanel($panel))->toBeTrue();
        });

        it('returns false when Roles table is empty even if user has Spatie role', function () {
            // Create user with admin role first (while Roles table has data)
            $user = User::factory()->create();
            $user->assignRole('admin');

            // Now delete all roles from Roles table
            Roles::query()->delete();

            $panel = Mockery::mock(Panel::class);

            // Should return false because Roles::all() will be empty
            expect($user->canAccessPanel($panel))->toBeFalse();
        });

        it('returns false when user has non-standard role not in approved list', function () {
            // This tests the scenario where a role exists but is not in the approved Roles list
            // Create a new role that exists in both tables
            $newRoleName = 'contractor';

            Role::firstOrCreate(['name' => $newRoleName, 'guard_name' => 'web']);
            Roles::firstOrCreate(['name' => $newRoleName, 'guard_name' => 'web']);

            // Assign to user
            $user = User::factory()->create();
            $user->assignRole($newRoleName);

            // User should have the role
            expect($user->hasRole($newRoleName))->toBeTrue();

            // Now remove from Roles table (simulating role being removed from approved list)
            Roles::where('name', $newRoleName)->delete();

            $panel = Mockery::mock(Panel::class);

            // canAccessPanel should return false because role is no longer in approved Roles list
            expect($user->fresh()->canAccessPanel($panel))->toBeFalse();
        });
    });

    describe('Password Hashing', function () {

        it('automatically hashes password when creating user', function () {
            $user = User::factory()->create([
                'password' => 'plain-text-password'
            ]);

            expect(Hash::check('plain-text-password', $user->password))->toBeTrue();
        });

        it('does not store plain text password', function () {
            $plainPassword = 'my-secret-password';

            $user = User::factory()->create([
                'password' => $plainPassword
            ]);

            expect($user->password)->not->toBe($plainPassword);
        });

        it('can verify correct password', function () {
            $user = User::factory()->create([
                'password' => 'correct-password'
            ]);

            expect(Hash::check('correct-password', $user->password))->toBeTrue();
        });

        it('rejects incorrect password', function () {
            $user = User::factory()->create([
                'password' => 'correct-password'
            ]);

            expect(Hash::check('wrong-password', $user->password))->toBeFalse();
        });
    });

    describe('FilamentUser Interface', function () {

        it('implements FilamentUser interface', function () {
            $user = User::factory()->create();

            expect($user)->toBeInstanceOf(\Filament\Models\Contracts\FilamentUser::class);
        });

        it('has canAccessPanel method required by FilamentUser', function () {
            $user = User::factory()->create();

            expect(method_exists($user, 'canAccessPanel'))->toBeTrue();
        });
    });

    describe('User Model Attributes', function () {

        it('has fillable attributes for authentication', function () {
            $user = new User();

            expect($user->getFillable())->toContain('email', 'password', 'name', 'google_id');
        });

        it('hides password in array representation', function () {
            $user = User::factory()->create();

            expect($user->toArray())->not->toHaveKey('password');
        });

        it('hides remember_token in array representation', function () {
            $user = User::factory()->create();

            expect($user->toArray())->not->toHaveKey('remember_token');
        });

        it('casts email_verified_at to datetime', function () {
            $user = User::factory()->create([
                'email_verified_at' => '2024-01-01 10:00:00'
            ]);

            expect($user->email_verified_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });

    describe('User Factory', function () {

        it('creates user with default password', function () {
            $user = User::factory()->create();

            expect(Hash::check('password', $user->password))->toBeTrue();
        });

        it('creates user with custom password', function () {
            $user = User::factory()->create([
                'password' => 'custom-password'
            ]);

            expect(Hash::check('custom-password', $user->password))->toBeTrue();
        });

        it('creates unverified user', function () {
            $user = User::factory()->unverified()->create();

            expect($user->email_verified_at)->toBeNull();
        });

        it('creates verified user by default', function () {
            $user = User::factory()->create();

            expect($user->email_verified_at)->not->toBeNull();
        });
    });

    describe('Google ID Support', function () {

        it('allows google_id to be set', function () {
            $user = User::factory()->create([
                'google_id' => '1234567890'
            ]);

            expect($user->google_id)->toBe('1234567890');
        });

        it('allows google_id to be null', function () {
            $user = User::factory()->create([
                'google_id' => null
            ]);

            expect($user->google_id)->toBeNull();
        });

        it('can update google_id', function () {
            $user = User::factory()->create(['google_id' => null]);

            $user->update(['google_id' => '9876543210']);

            expect($user->fresh()->google_id)->toBe('9876543210');
        });
    });
});
