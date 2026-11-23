<?php

test('the sign-in page shows the Tropang Potchi branding', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('TROPANG POTCHI')
        ->assertSee('/images/logo.png', false);
});
