<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Authentication Security', function () {
    describe('Password Storage', function () {
        it('hashes passwords before storing in database', function () {
            $plainPassword = 'SecurePassword123!';
            $user = User::factory()->create(['password' => $plainPassword]);

            expect($user->password)->not->toBe($plainPassword);
            expect(Hash::check($plainPassword, $user->password))->toBeTrue();
        });

        it('uses bcrypt hashing algorithm', function () {
            $user = User::factory()->create(['password' => 'password123']);

            // Bcrypt hashes start with $2y$
            expect($user->password)->toStartWith('$2y$');
        });

        it('generates different hashes for same password', function () {
            $password = 'SamePassword123';
            $user1 = User::factory()->create(['password' => $password]);
            $user2 = User::factory()->create(['password' => $password]);

            expect($user1->password)->not->toBe($user2->password);
        });
    });

    describe('Password Verification', function () {
        it('validates correct password', function () {
            $user = User::factory()->create(['password' => 'correct-password']);

            expect(Hash::check('correct-password', $user->password))->toBeTrue();
        });

        it('rejects incorrect password', function () {
            $user = User::factory()->create(['password' => 'correct-password']);

            expect(Hash::check('wrong-password', $user->password))->toBeFalse();
        });

        it('is case sensitive for passwords', function () {
            $user = User::factory()->create(['password' => 'Password123']);

            expect(Hash::check('password123', $user->password))->toBeFalse();
            expect(Hash::check('PASSWORD123', $user->password))->toBeFalse();
        });
    });

    describe('Sensitive Data Protection', function () {
        it('hides password in array serialization', function () {
            $user = User::factory()->create();

            expect($user->toArray())->not->toHaveKey('password');
        });

        it('hides remember_token in array serialization', function () {
            $user = User::factory()->create();

            expect($user->toArray())->not->toHaveKey('remember_token');
        });

        it('hides password in JSON serialization', function () {
            $user = User::factory()->create();
            $json = json_decode($user->toJson(), true);

            expect($json)->not->toHaveKey('password');
        });

        it('exposes only safe attributes in API responses', function () {
            $user = User::factory()->create();
            $data = $user->toArray();

            expect($data)->toHaveKeys(['id', 'name', 'email']);
            expect($data)->not->toHaveKeys(['password', 'remember_token']);
        });
    });

    describe('Panel Access Control', function () {
        it('allows users with valid roles to access panel', function () {
            $user = User::factory()->create();
            $user->assignRole('super_admin');

            $panel = Mockery::mock(\Filament\Panel::class);

            expect($user->canAccessPanel($panel))->toBeTrue();
        });

        it('denies panel access to users without roles', function () {
            $user = User::factory()->create();
            // No roles assigned

            $panel = Mockery::mock(\Filament\Panel::class);

            expect($user->canAccessPanel($panel))->toBeFalse();
        });
    });

    describe('Google OAuth Security', function () {
        it('allows storing google_id for OAuth users', function () {
            $user = User::factory()->create([
                'google_id' => '1234567890',
                'password' => null, // OAuth users might not have password
            ]);

            expect($user->google_id)->toBe('1234567890');
        });

        it('prevents duplicate google_id registration', function () {
            User::factory()->create(['google_id' => 'unique-google-id']);

            expect(function () {
                User::factory()->create(['google_id' => 'unique-google-id']);
            })->toThrow(\Illuminate\Database\QueryException::class);
        });
    });

    describe('Email Verification', function () {
        it('tracks email verification status', function () {
            $verifiedUser = User::factory()->create();
            $unverifiedUser = User::factory()->unverified()->create();

            expect($verifiedUser->email_verified_at)->not->toBeNull();
            expect($unverifiedUser->email_verified_at)->toBeNull();
        });

        it('casts email_verified_at to datetime', function () {
            $user = User::factory()->create();

            expect($user->email_verified_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });

    describe('Remember Token Security', function () {
        it('generates unique remember tokens', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            expect($user1->remember_token)->not->toBeNull();
            expect($user2->remember_token)->not->toBeNull();
            expect($user1->remember_token)->not->toBe($user2->remember_token);
        });

        it('generates tokens with sufficient entropy', function () {
            $user = User::factory()->create();

            // Should be at least 10 characters (from factory)
            expect(strlen($user->remember_token))->toBeGreaterThanOrEqual(10);
        });
    });
});
