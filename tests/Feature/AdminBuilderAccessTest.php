<?php

use App\Models\Template;
use Livewire\Livewire;

test('the builder create route renders successfully', function () {
    $response = $this->get('/admin/templates/create');

    $response->assertOk()
        ->assertSee('Save Template');

    Livewire::test('admin.builder')
        ->assertSet('template', null)
        ->assertSet('name', '')
        ->assertSet('type', 'page')
        ->assertSet('is_active', true)
        ->assertSet('schema', ['blocks' => []])
        ->assertOk();
});

test('the builder edit route loads an existing template and mounts its schema correctly', function () {
    $template = Template::factory()->create([
        'name' => 'Custom Landing Page',
        'slug' => 'custom-landing-page',
        'type' => 'page',
        'is_active' => true,
        'schema' => [
            'blocks' => [
                [
                    'id' => 'block-hero-1',
                    'type' => 'custom',
                    'config' => ['title' => 'Welcome to Leaf'],
                ],
            ],
        ],
    ]);

    $response = $this->get("/admin/templates/{$template->id}");

    $response->assertOk()
        ->assertSee('Custom Landing Page');

    Livewire::test('admin.builder', ['template' => $template])
        ->assertSet('template.id', $template->id)
        ->assertSet('name', 'Custom Landing Page')
        ->assertSet('slug', 'custom-landing-page')
        ->assertSet('type', 'page')
        ->assertSet('is_active', true)
        ->assertSet('schema.blocks.0.id', 'block-hero-1')
        ->assertOk();
});

test('admin layout contains page builder navigation link', function () {
    $response = $this->get('/admin');

    $response->assertOk()
        ->assertSee('/admin/templates')
        ->assertSee('Page Builder');
});
