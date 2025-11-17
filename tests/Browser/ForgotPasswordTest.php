<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;

uses(DatabaseMigrations::class);

test('user can request password reset with valid email', function () {
    // Create user
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => bcrypt('oldpassword'),
    ]);

    // Fake notifications to prevent actual emails
    Notification::fake();

    $this->browse(function (Browser $browser) use ($user) {
        // Directly visit password reset request page
        $browser->visit('/admin/password-reset/request')
                ->waitFor('input[type="email"]', 5)
                ->assertSee('Request password reset')
                ->type('input[type="email"]', $user->email)
                ->press('Email password reset link')
                ->pause(2000)
                ->assertSee('You will receive an email with the password reset link'); // Success message
    });
});

test('user cannot request password reset with non-existent email', function () {
    Notification::fake();

    $this->browse(function (Browser $browser) {
        $browser->visit('/admin/password-reset/request')
                ->waitFor('input[type="email"]', 5)
                ->type('input[type="email"]', 'nonexistent@example.com')
                ->press('Email password reset link')
                ->pause(2000)
                ->assertPresent('.fi-fo-field-wrp-error-message'); // Error message should appear
    });
});
