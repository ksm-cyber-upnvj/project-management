<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(DatabaseMigrations::class);

test('user can reset password with valid token', function () {
    // Create user
    $user = User::factory()->create([
        'email' => 'resetpass@example.com',
        'password' => bcrypt('oldpassword'),
    ]);

    // Generate password reset token
    $token = Password::createToken($user);

    $this->browse(function (Browser $browser) use ($user, $token) {
        // Visit reset password page with token
        $browser->visit("/admin/password-reset/reset?token={$token}&email={$user->email}")
                ->pause(2000) // Wait for page to load
                ->assertSee('Reset password')
                ->type('input[type="password"]', 'newpassword123') // First password field
                ->keys('input[type="password"]', '{tab}') // Tab to next field
                ->type('input[type="password"]:last-of-type', 'newpassword123') // Password confirmation
                ->press('Reset password')
                ->pause(3000)
                ->assertPathIs('/admin/login'); // Should redirect to login after success

        // Verify password was changed
        $user->refresh();
        expect(Hash::check('newpassword123', $user->password))->toBeTrue();
    });
});

test('user can login with new password after reset', function () {
    // Create role
    $adminRole = Role::firstOrCreate(
        ['name' => 'admin'],
        ['guard_name' => 'web']
    );

    // Create user with role
    $user = User::factory()->create([
        'email' => 'resetlogin@example.com',
        'password' => bcrypt('oldpassword'),
    ]);
    $user->assignRole($adminRole);

    // Generate password reset token
    $token = Password::createToken($user);

    $this->browse(function (Browser $browser) use ($user, $token) {
        // Reset password
        $browser->visit("/admin/password-reset/reset?token={$token}&email={$user->email}")
                ->pause(2000) // Wait for page to load
                ->type('input[type="password"]', 'newpassword456')
                ->keys('input[type="password"]', '{tab}')
                ->type('input[type="password"]:last-of-type', 'newpassword456')
                ->press('Reset password')
                ->pause(3000);

        // Now try to login with new password
        $browser->visit('/admin/login')
                ->waitFor('input[type="email"]', 5)
                ->type('input[type="email"]', $user->email)
                ->type('input[type="password"]', 'newpassword456')
                ->press('Sign in')
                ->waitForLocation('/admin', 10)
                ->assertPathIs('/admin')
                ->assertAuthenticated();
    });
});
