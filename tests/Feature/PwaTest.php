<?php

test('the web app manifest describes an installable app with the right icons', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)
        ->name->toBe('Tropang Potchi')
        ->short_name->toBe('TP Admin')
        ->theme_color->toBe('#1C2566')
        ->background_color->toBe('#F6F5EF')
        ->display->toBe('standalone')
        ->start_url->toBe('/');

    $icons = collect($manifest['icons'])->mapWithKeys(fn (array $icon) => [$icon['sizes'].' '.$icon['purpose'] => $icon['src']]);
    expect($icons->keys()->all())->toBe(['192x192 any', '512x512 any', '512x512 maskable']);

    foreach ($icons as $label => $src) {
        [$width, $height] = getimagesize(public_path(ltrim($src, '/')));
        expect("{$width}x{$height}")->toBe(explode(' ', $label)[0]);
    }
});

test('every page links the manifest, icons and iPhone home screen tags', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
        ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', false)
        ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false)
        ->assertSee('<meta name="apple-mobile-web-app-title" content="TP Admin">', false)
        ->assertSee('<meta name="theme-color" content="#1C2566" />', false);

    expect(getimagesize(public_path('apple-touch-icon.png'))[0])->toBe(180);
});

test('the offline page is static and shows no member data', function () {
    $html = file_get_contents(public_path('offline.html'));

    expect($html)->toContain('You’re offline')
        ->not->toContain('{{')
        ->not->toContain('/members')
        ->not->toContain('<script src');
});

test('the service worker never caches pages, member data or uploaded files', function () {
    $worker = file_get_contents(public_path('sw.js'));

    preg_match('/STATIC_PREFIXES = \[(.*?)\]/s', $worker, $prefixes);
    preg_match('/STATIC_FILES = \[(.*?)\]/s', $worker, $files);
    $cacheable = $prefixes[1].$files[1];

    expect($cacheable)->toContain("'/build/'")
        ->not->toContain('/members')
        ->not->toContain('/documents')
        ->not->toContain('/livewire')
        ->and($worker)->toContain("request.mode === 'navigate'")
        ->toContain("caches.match('/offline.html')")
        ->toContain("request.method !== 'GET'");
});
