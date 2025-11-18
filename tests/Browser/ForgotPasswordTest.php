<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;

uses(DatabaseMigrations::class);

test('password reset request page has email input field', function () {
    // Create user
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => bcrypt('oldpassword'),
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        // Visit password reset request page
        $browser->visit('/admin/password-reset/request')
                ->waitFor('input[type="email"]', 5)
                ->assertPathIs('/admin/password-reset/request')
                ->assertPresent('input[type="email"]') // Email field exists
                ->assertPresent('button'); // Has submit button
    });
});

test('password reset page is accessible to guest users', function () {
    $this->browse(function (Browser $browser) {
        // Clear cookies to ensure guest state
        $browser->driver->manage()->deleteAllCookies();

        // Visit password reset page as guest
        $browser->visit('/admin/password-reset/request')
                ->waitFor('input[type="email"]', 5)
                ->assertPathIs('/admin/password-reset/request')
                ->assertGuest() // Should be guest
                ->assertPresent('input[type="email"]'); // Can access form
    });
});
