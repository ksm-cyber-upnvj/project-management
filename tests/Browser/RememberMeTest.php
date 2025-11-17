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

test('remember me checkbox is unchecked by default on login page', function () {
    $this->browse(function (Browser $browser) {
        // Clear any previous cookies
        $browser->driver->manage()->deleteAllCookies();

        // Visit login page and check remember me checkbox state
        $browser->visit('/admin/login')
                ->waitFor('input[type="email"]', 5)
                ->assertSee('Sign in')
                ->assertPresent('input[id="data.remember"]') // Remember checkbox exists
                ->assertNotChecked('input[id="data.remember"]'); // But not checked by default
    });
});
