<?php

it('defaults to the layout', function () {
    $response = $this->get('/draftsman');
    $response->assertStatus(200);
    $response->assertSee('Draftsman');
});

it('gets a js file from _next ', function () {
    $base_url = '/draftsman/_next/static/chunks/';
    $next_dir = 'resources/front/_next/static/chunks/';
    $package_root_path = '/../';
    $pattern = '*.js';
    $dir = $package_root_path.$next_dir;
    $dir = __DIR__.implode(DIRECTORY_SEPARATOR, explode('/', $dir));
    $files = glob($dir.$pattern);
    $file = $files[array_rand($files)];
    $name = basename($file);
    $response = $this->get($base_url.$name);
    $response->assertStatus(200);
});

it('gets a css file from _next ', function () {
    $base_url = '/draftsman/_next/static/chunks/';
    $next_dir = 'resources/front/_next/static/chunks/';
    $package_root_path = '/../';
    $pattern = '*.css';
    $dir = $package_root_path.$next_dir;
    $dir = __DIR__.implode(DIRECTORY_SEPARATOR, explode('/', $dir));
    $files = glob($dir.$pattern);
    $file = $files[array_rand($files)];
    $name = basename($file);
    $response = $this->get($base_url.$name);
    $response->assertStatus(200);
});
