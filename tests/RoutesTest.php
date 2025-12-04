<?php

it('defaults to the layout', function () {
    $response = $this->get('/draftsman');
    $response->assertStatus(200);
    $response->assertSee('Draftsman');
});

it('gets a js file from _next ', function () {
    $response = $this->get('/draftsman/_next/static/chunks/1a258343-4e35aaf719b73d9c.js');
    $response->assertStatus(200);
});

it('gets a css file from _next ', function () {
    $response = $this->get('/draftsman/_next/static/css/8cfc3c2c8dcafd94.css');
    $response->assertStatus(200);
});
