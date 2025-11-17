<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Role;

uses(DatabaseMigrations::class);

test('user can login via filament login page', function () {
    // Create role if not exists
    $adminRole = Role::firstOrCreate(
        ['name' => 'admin'],
        ['guard_name' => 'web']
    );

    // Create a user with admin role
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    // Assign role to user so they can access Filament panel
    $user->assignRole($adminRole);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/admin/login')
                ->waitFor('input[type="email"]', 5) // Wait for form to load
                ->assertSee('Sign in')
                ->type('input[type="email"]', $user->email) // Use CSS selector
                ->type('input[type="password"]', 'password') // Use CSS selector
                ->press('Sign in')
                ->waitForLocation('/admin', 10)
                ->assertPathIs('/admin')
                ->assertAuthenticated();
    });
});

test('user cannot login with invalid credentials', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/admin/login')
                ->waitFor('input[type="email"]', 5) // Wait for form to load
                ->assertSee('Sign in')
                ->type('input[type="email"]', 'wrong@example.com')
                ->type('input[type="password"]', 'wrongpassword')
                ->press('Sign in')
                ->pause(2000) // Wait for form submission
                ->assertPathIs('/admin/login') // Should stay on login page
                ->assertPresent('.fi-fo-field-wrp-error-message'); // Error message should be present
    });
});

test('user without role cannot access admin panel', function () {
    // Create a user WITHOUT role
    $user = User::factory()->create([
        'email' => 'norole@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/admin/login')
                ->waitFor('input[type="email"]', 5) // Wait for form to load
                ->type('input[type="email"]', $user->email)
                ->type('input[type="password"]', 'password')
                ->press('Sign in')
                ->pause(2000) // Wait for submission
                ->assertPathIs('/admin/login') // Should stay on login page
                ->assertGuest(); // User should still be guest (not authenticated to Filament)
    });
});
