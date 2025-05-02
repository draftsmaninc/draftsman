<?php

it('defaults to the layout', function () {
    $response = $this->get('/draftsman');
    $response->assertStatus(200);
    $response->assertSee('Draftsman');
});
