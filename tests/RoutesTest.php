<?php

it('defaults to the layout', function () {
    $response = $this->get('/draftsman');
    $response->assertStatus(200);
    $response->assertSee('Draftsman');
});

it('gets a js file from assets', function () {
    $base_url = '/draftsman/assets/';
    $front_dir = 'resources/front/assets/';
    $package_root_path = '/../';
    $pattern = '*.js';
    $dir = $package_root_path.$front_dir;
    $dir = __DIR__.implode(DIRECTORY_SEPARATOR, explode('/', $dir));
    $files = glob($dir.$pattern);
    $file = $files[array_rand($files)];
    $name = basename($file);
    $response = $this->get($base_url.$name);
    $response->assertStatus(200);
});

it('gets a css file from assets', function () {
    $base_url = '/draftsman/assets/';
    $front_dir = 'resources/front/assets/';
    $package_root_path = '/../';
    $pattern = '*.css';
    $dir = $package_root_path.$front_dir;
    $dir = __DIR__.implode(DIRECTORY_SEPARATOR, explode('/', $dir));
    $files = glob($dir.$pattern);
    $file = $files[array_rand($files)];
    $name = basename($file);
    $response = $this->get($base_url.$name);
    $response->assertStatus(200);
});

it('gets an svg file from front root', function () {
    $base_url = '/draftsman/';
    $front_dir = 'resources/front/';
    $package_root_path = '/../';
    $pattern = '*.svg';
    $dir = $package_root_path.$front_dir;
    $dir = __DIR__.implode(DIRECTORY_SEPARATOR, explode('/', $dir));
    $files = glob($dir.$pattern);
    $file = $files[array_rand($files)];
    $name = basename($file);
    $response = $this->get($base_url.$name);
    $response->assertStatus(200);
});
