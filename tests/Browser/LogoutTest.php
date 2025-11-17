<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;

uses(DatabaseMigrations::class);

test('authenticated user session can be invalidated', function () {
    // Create role
    $adminRole = Role::firstOrCreate(
        ['name' => 'admin'],
        ['guard_name' => 'web']
    );

    // Create a user with admin role
    $user = User::factory()->create([
        'email' => 'logout@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole($adminRole);

    $this->browse(function (Browser $browser) use ($user) {
        // Login using loginAs helper
        $browser->loginAs($user)
                ->visit('/admin')
                ->pause(1000)
                ->assertAuthenticatedAs($user);

        // Simulate logout by clearing session
        Auth::logout();

        // Try to access admin again - should be redirected to login
        $browser->visit('/admin')
                ->pause(2000)
                ->assertPathIs('/admin/login')
                ->assertGuest();
    });
});

test('guest user cannot access admin panel without authentication', function () {
    $this->browse(function (Browser $browser) {
        // Try to access admin panel without logging in
        $browser->visit('/admin')
                ->pause(2000)
                ->assertPathIs('/admin/login') // Should be redirected to login
                ->assertGuest();
    });
});
