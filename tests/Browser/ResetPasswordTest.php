<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(DatabaseMigrations::class);

test('password can be changed programmatically and user can login with new password', function () {
    // Create role
    $adminRole = Role::firstOrCreate(
        ['name' => 'admin'],
        ['guard_name' => 'web']
    );

    // Create user
    $user = User::factory()->create([
        'email' => 'resetpass@example.com',
        'password' => bcrypt('oldpassword'),
    ]);
    $user->assignRole($adminRole);

    // Programmatically change password (simulating reset)
    $user->password = bcrypt('newpassword123');
    $user->save();

    $this->browse(function (Browser $browser) use ($user) {
        // Clear cookies
        $browser->driver->manage()->deleteAllCookies();

        // Try to login with new password
        $browser->visit('/admin/login')
                ->waitFor('input[type="email"]', 5)
                ->type('input[type="email"]', $user->email)
                ->type('input[type="password"]', 'newpassword123')
                ->press('Sign in')
                ->waitForLocation('/admin', 10)
                ->assertPathIs('/admin')
                ->assertAuthenticated();

        // Verify password was changed
        $user->refresh();
        expect(Hash::check('newpassword123', $user->password))->toBeTrue();
    });
});

test('user cannot login with old password after password change', function () {
    // Create role
    $adminRole = Role::firstOrCreate(
        ['name' => 'admin'],
        ['guard_name' => 'web']
    );

    // Create user with old password
    $user = User::factory()->create([
        'email' => 'changepass@example.com',
        'password' => bcrypt('oldpassword'),
    ]);
    $user->assignRole($adminRole);

    // Change password
    $user->password = bcrypt('newpassword456');
    $user->save();

    $this->browse(function (Browser $browser) use ($user) {
        // Clear cookies
        $browser->driver->manage()->deleteAllCookies();

        // Try to login with OLD password - should fail
        $browser->visit('/admin/login')
                ->waitFor('input[type="email"]', 5)
                ->type('input[type="email"]', $user->email)
                ->type('input[type="password"]', 'oldpassword') // OLD password
                ->press('Sign in')
                ->pause(2000)
                ->assertPathIs('/admin/login') // Should stay on login
                ->assertGuest(); // Should not be authenticated
    });
});
