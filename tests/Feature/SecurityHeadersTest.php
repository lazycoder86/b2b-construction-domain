<?php

test('web responses carry baseline security headers', function () {
    $this->get('/')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('the health check verifies the database', function () {
    $this->get('/up')->assertOk();
});
