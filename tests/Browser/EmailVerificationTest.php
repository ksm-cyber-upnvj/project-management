<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

uses(DatabaseMigrations::class);

test('unverified user can still login and access admin panel', function () {
    // Create role
    $adminRole = Role::firstOrCreate(
        ['name' => 'admin'],
        ['guard_name' => 'web']
    );

    // Create unverified user
    $user = User::factory()->create([
        'email' => 'unverified@example.com',
        'password' => bcrypt('password'),
        'email_verified_at' => null, // Not verified
    ]);
    $user->assignRole($adminRole);

    $this->browse(function (Browser $browser) use ($user) {
        // Login - should work even without verification (not enforced)
        $browser->visit('/admin/login')
                ->waitFor('input[type="email"]', 5)
                ->type('input[type="email"]', $user->email)
                ->type('input[type="password"]', 'password')
                ->press('Sign in')
                ->waitForLocation('/admin', 10)
                ->assertPathIs('/admin')
                ->assertAuthenticated();

        // Verify email is still null
        $user->refresh();
        expect($user->email_verified_at)->toBeNull();
    });
});

test('verified user has email_verified_at timestamp', function () {
    // Create role
    $adminRole = Role::firstOrCreate(
        ['name' => 'admin'],
        ['guard_name' => 'web']
    );

    // Create verified user
    $user = User::factory()->create([
        'email' => 'verified@example.com',
        'password' => bcrypt('password'),
        'email_verified_at' => now(), // Already verified
    ]);
    $user->assignRole($adminRole);

    $this->browse(function (Browser $browser) use ($user) {
        // Login
        $browser->visit('/admin/login')
                ->waitFor('input[type="email"]', 5)
                ->type('input[type="email"]', $user->email)
                ->type('input[type="password"]', 'password')
                ->press('Sign in')
                ->waitForLocation('/admin', 10)
                ->assertPathIs('/admin')
                ->assertAuthenticated();

        // Verify email is verified
        $user->refresh();
        expect($user->email_verified_at)->not->toBeNull();
    });
});
