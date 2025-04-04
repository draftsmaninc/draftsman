<?php

it('defaults to the layout', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
    $response->assertSee('Draftsman');
});

it('provides api models index', function () {
    $response = $this->get('/api/models');
    $response->assertStatus(200);
});
