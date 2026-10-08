<?php

use App\Models\ItemBase;
use App\Models\Template;

test('can create a template with expected attributes', function () {
    $template = Template::create([
        'name' => 'Standard Landing Page',
        'slug' => 'standard-landing-page',
        'type' => 'page',
        'schema' => [
            'blocks' => [
                ['id' => 'block-1', 'type' => 'custom', 'config' => []],
            ],
        ],
        'is_active' => true,
    ]);

    expect($template->exists)->toBeTrue()
        ->and($template->name)->toBe('Standard Landing Page')
        ->and($template->slug)->toBe('standard-landing-page')
        ->and($template->type)->toBe('page')
        ->and($template->is_active)->toBeTrue();

    $this->assertDatabaseHas('templates', [
        'id' => $template->id,
        'name' => 'Standard Landing Page',
        'slug' => 'standard-landing-page',
        'type' => 'page',
        'is_active' => 1,
    ]);
});

test('casts schema to array and is_active to boolean', function () {
    $template = Template::create([
        'name' => 'Product Detail Template',
        'type' => 'item_detail',
        'schema' => ['layout' => 'grid', 'columns' => 2],
        'is_active' => 1,
    ]);

    $template->refresh();

    expect($template->schema)->toBeArray()
        ->and($template->schema)->toBe(['layout' => 'grid', 'columns' => 2])
        ->and($template->is_active)->toBeTrue();
});

test('allows nullable slug for item detail templates', function () {
    $template = Template::create([
        'name' => 'Apparel Detail Layout',
        'slug' => null,
        'type' => 'item_detail',
        'schema' => [],
        'is_active' => true,
    ]);

    expect($template->exists)->toBeTrue()
        ->and($template->slug)->toBeNull();

    $this->assertDatabaseHas('templates', [
        'id' => $template->id,
        'slug' => null,
        'type' => 'item_detail',
    ]);
});

test('item base belongs to a template and template has many item bases', function () {
    $template = Template::factory()->itemDetail()->create([
        'name' => 'Apparel Layout',
    ]);

    $group = ItemBase::create([
        'name' => 'T-Shirts',
        'slug' => 't-shirts',
        'status' => 'Published',
        'template_id' => $template->id,
    ]);

    expect($group->template)->not->toBeNull()
        ->and($group->template->id)->toBe($template->id)
        ->and($group->template->name)->toBe('Apparel Layout');

    expect($template->itemBases)->toHaveCount(1)
        ->and($template->itemBases->first()->id)->toBe($group->id);
});

test('sets item base template_id to null when template is deleted', function () {
    $template = Template::factory()->create();

    $group = ItemBase::create([
        'name' => 'Posters',
        'slug' => 'posters',
        'status' => 'Published',
        'template_id' => $template->id,
    ]);

    $template->delete();
    $group->refresh();

    expect($group->template_id)->toBeNull()
        ->and($group->template)->toBeNull();
});

test('factory generates valid templates with custom states', function () {
    $defaultTemplate = Template::factory()->create();
    expect($defaultTemplate->exists)->toBeTrue()
        ->and($defaultTemplate->is_active)->toBeTrue();

    $itemDetailTemplate = Template::factory()->itemDetail()->create();
    expect($itemDetailTemplate->type)->toBe('item_detail')
        ->and($itemDetailTemplate->slug)->toBeNull();

    $inactiveTemplate = Template::factory()->inactive()->create();
    expect($inactiveTemplate->is_active)->toBeFalse();
});
