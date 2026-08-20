<?php

test('registration screen redirects to home modal', function () {
    $response = $this->get('/register');

    $response->assertRedirect('/');
});

test('new users can register with valid fields', function () {
    $response = $this->post('/register', [
        'name' => 'TestUser',
        'gender' => 'female',
        'birth_date' => now()->subYears(20)->toDateString(),
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('profile', absolute: false));
});

test('registration rejects users under 18 years old', function () {
    $response = $this->post('/register', [
        'name' => 'YoungUser',
        'gender' => 'male',
        'birth_date' => now()->subYears(17)->toDateString(),
        'email' => 'young@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('birth_date');
});

test('registration rejects username with spaces', function () {
    $response = $this->post('/register', [
        'name' => 'User With Spaces',
        'gender' => 'male',
        'birth_date' => now()->subYears(20)->toDateString(),
        'email' => 'spaces@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('name');
});
