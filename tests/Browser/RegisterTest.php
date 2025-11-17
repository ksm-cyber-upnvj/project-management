<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

test('user can register with valid data', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/admin/register')
                ->waitFor('input[id="data.name"]', 5)
                ->assertSee('Sign up')
                ->type('input[id="data.name"]', 'New User')
                ->type('input[id="data.email"]', 'newuser@example.com')
                ->type('input[id="data.password"]', 'password123')
                ->type('input[id="data.passwordConfirmation"]', 'password123')
                ->press('Sign up')
                ->pause(3000) // Wait for registration process
                ->assertPathIs('/admin'); // Filament auto-logins user after registration

        // Verify user was created in database
        expect(User::where('email', 'newuser@example.com')->exists())->toBeTrue();
    });
});

test('user cannot register with existing email', function () {
    // Create existing user
    User::factory()->create([
        'email' => 'existing@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->browse(function (Browser $browser) {
        $browser->visit('/admin/register')
                ->waitFor('input[id="data.name"]', 5)
                ->type('input[id="data.name"]', 'Another User')
                ->type('input[id="data.email"]', 'existing@example.com')
                ->type('input[id="data.password"]', 'password123')
                ->type('input[id="data.passwordConfirmation"]', 'password123')
                ->press('Sign up')
                ->pause(2000)
                ->assertPathIs('/admin/register') // Should stay on register page
                ->assertPresent('.fi-fo-field-wrp-error-message'); // Error message should be present
    });
});
