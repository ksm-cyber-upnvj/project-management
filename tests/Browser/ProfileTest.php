<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Role;

uses(DatabaseMigrations::class);

test('authenticated user can access profile page', function () {
    // Create role
    $adminRole = Role::firstOrCreate(
        ['name' => 'admin'],
        ['guard_name' => 'web']
    );

    // Create a user with admin role
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'profile@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole($adminRole);

    $this->browse(function (Browser $browser) use ($user) {
        // Login first
        $browser->visit('/admin/login')
                ->waitFor('input[type="email"]', 5)
                ->type('input[type="email"]', $user->email)
                ->type('input[type="password"]', 'password')
                ->press('Sign in')
                ->waitForLocation('/admin', 10)
                ->assertAuthenticated();

        // Access profile page
        $browser->visit('/admin/profile')
                ->pause(2000)
                ->assertPathIs('/admin/profile')
                ->assertSee('Profile')
                ->assertInputValue('input[id="data.name"]', $user->name)
                ->assertInputValue('input[id="data.email"]', $user->email);
    });
});

test('profile page has name and email fields', function () {
    // Create role
    $adminRole = Role::firstOrCreate(
        ['name' => 'admin'],
        ['guard_name' => 'web']
    );

    // Create a user with admin role
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'profilefields@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole($adminRole);

    $this->browse(function (Browser $browser) use ($user) {
        // Login using helper
        $browser->loginAs($user)
                ->visit('/admin/profile')
                ->pause(2000)
                ->assertPathIs('/admin/profile')
                ->assertInputValue('input[id="data.name"]', $user->name)
                ->assertInputValue('input[id="data.email"]', $user->email)
                ->assertPresent('button'); // Has save button
    });
});
