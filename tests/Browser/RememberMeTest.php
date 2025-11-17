<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Role;

uses(DatabaseMigrations::class);

test('user can login with remember me checked', function () {
    // Create role
    $adminRole = Role::firstOrCreate(
        ['name' => 'admin'],
        ['guard_name' => 'web']
    );

    // Create a user with admin role
    $user = User::factory()->create([
        'email' => 'remember@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole($adminRole);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/admin/login')
                ->waitFor('input[type="email"]', 5)
                ->assertSee('Sign in')
                ->type('input[type="email"]', $user->email)
                ->type('input[type="password"]', 'password')
                ->check('input[id="data.remember"]') // Check remember me
                ->press('Sign in')
                ->waitForLocation('/admin', 10)
                ->assertPathIs('/admin')
                ->assertAuthenticated();

        // Verify remember cookie exists
        $cookies = $browser->driver->manage()->getCookies();
        $hasRememberCookie = false;
        foreach ($cookies as $cookie) {
            if (str_contains($cookie['name'], 'remember_web')) {
                $hasRememberCookie = true;
                break;
            }
        }
        expect($hasRememberCookie)->toBeTrue();
    });
});

test('user without remember me does not have remember cookie', function () {
    // Create role
    $adminRole = Role::firstOrCreate(
        ['name' => 'admin'],
        ['guard_name' => 'web']
    );

    // Create a user with admin role
    $user = User::factory()->create([
        'email' => 'noremember@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole($adminRole);

    $this->browse(function (Browser $browser) use ($user) {
        // Login WITHOUT remember me
        $browser->visit('/admin/login')
                ->waitFor('input[type="email"]', 5)
                ->type('input[type="email"]', $user->email)
                ->type('input[type="password"]', 'password')
                // Do NOT check remember me
                ->press('Sign in')
                ->waitForLocation('/admin', 10)
                ->assertAuthenticated();

        // Verify remember cookie does NOT exist
        $cookies = $browser->driver->manage()->getCookies();
        $hasRememberCookie = false;
        foreach ($cookies as $cookie) {
            if (str_contains($cookie['name'], 'remember_web')) {
                $hasRememberCookie = true;
                break;
            }
        }
        expect($hasRememberCookie)->toBeFalse();
    });
});
