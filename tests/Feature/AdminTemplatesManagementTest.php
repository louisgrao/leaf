<?php

use App\Models\ItemBase;
use App\Models\Template;
use Livewire\Livewire;

test('the admin templates index route renders successfully and displays seeded templates', function () {
    $pageTemplate = Template::factory()->create([
        'name' => 'Landing Page Template',
        'slug' => 'landing-page',
        'type' => 'page',
        'is_active' => true,
    ]);

    $detailTemplate = Template::factory()->itemDetail()->create([
        'name' => 'Apparel Detail Template',
        'is_active' => false,
    ]);

    $response = $this->get('/admin/templates');

    $response->assertOk()
        ->assertSee('Page Builder Templates')
        ->assertSee('Landing Page Template')
        ->assertSee('Apparel Detail Template')
        ->assertSee('New Template')
        ->assertSee("/admin/templates/{$pageTemplate->id}")
        ->assertSee("/admin/templates/{$detailTemplate->id}");

    Livewire::test('admin.templates-index')
        ->assertSee('Landing Page Template')
        ->assertSee('Apparel Detail Template')
        ->set('typeFilter', 'item_detail')
        ->assertSee('Apparel Detail Template')
        ->assertDontSee('Landing Page Template');
});

test('the builder create route correctly captures and initializes state from query parameters', function () {
    $group = ItemBase::create([
        'name' => 'Jewelry',
        'slug' => 'jewelry',
        'status' => 'Published',
    ]);

    $url = "/admin/templates/create?type=item_detail&group_id={$group->id}&return_to=".urlencode("/admin/groups/{$group->id}");

    $response = $this->get($url);

    $response->assertOk()
        ->assertSee('Save Template');

    $this->withServerVariables([
        'QUERY_STRING' => "type=item_detail&group_id={$group->id}&return_to=/admin/groups/{$group->id}",
    ]);

    Livewire::withQueryParams([
        'type' => 'item_detail',
        'group_id' => (string) $group->id,
        'return_to' => "/admin/groups/{$group->id}",
    ])
        ->test('admin.builder')
        ->assertSet('type', 'item_detail')
        ->assertSet('group_id', $group->id)
        ->assertSet('return_to', "/admin/groups/{$group->id}")
        ->set('name', 'Custom Jewelry Layout')
        ->call('save')
        ->assertRedirect("/admin/groups/{$group->id}");

    $group->refresh();
    expect($group->template_id)->not->toBeNull()
        ->and($group->template->name)->toBe('Custom Jewelry Layout');
});

test('item group creation provides save and design layout action and detail template selection', function () {
    $template = Template::factory()->itemDetail()->create([
        'name' => 'Standard Apparel Layout',
    ]);

    // Verify create group page has helper text and no direct design link
    $createResponse = $this->get('/admin/groups/create');
    $createResponse->assertOk()
        ->assertSee('Detail Page Template')
        ->assertSee("To design a custom layout tied to this group's custom schema, save the group first or select", false)
        ->assertDontSee('/admin/templates/create?type=item_detail');

    // Test Save Group form saves template_id and redirects to /admin
    Livewire::test('admin.create-group')
        ->set('name', 'Hats')
        ->set('template_id', $template->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect('/admin');

    $createdGroup = ItemBase::where('name', 'Hats')->first();
    expect($createdGroup)->not->toBeNull()
        ->and($createdGroup->template_id)->toBe($template->id);

    // Test Save & Design Layout saves group and redirects to builder with group_id and return_to
    Livewire::test('admin.create-group')
        ->set('name', 'Scarves')
        ->call('saveAndDesign')
        ->assertHasNoErrors();

    $scarvesGroup = ItemBase::where('name', 'Scarves')->first();
    expect($scarvesGroup)->not->toBeNull();

    Livewire::test('admin.create-group')
        ->set('name', 'Gloves')
        ->call('saveAndDesign')
        ->assertRedirect('/admin/templates/create?type=item_detail&group_id='.($scarvesGroup->id + 1).'&return_to=/admin/groups/'.($scarvesGroup->id + 1));

    // Test Group Detail view displays and updates template_id
    $group = ItemBase::create([
        'name' => 'Jackets',
        'slug' => 'jackets',
        'status' => 'Published',
    ]);

    $detailResponse = $this->get("/admin/groups/{$group->id}");
    $detailResponse->assertOk()
        ->assertSee('Detail Page Template:')
        ->assertSee('Design Custom Layout');

    Livewire::test('admin.group-detail', ['id' => $group->id])
        ->assertSet('template_id', null)
        ->set('template_id', $template->id);

    $group->refresh();
    expect($group->template_id)->toBe($template->id);
});

test('saving a template of type page auto-slugifies name, allows manual edits, and validates uniqueness', function () {
    // 1. Auto-slugifies name when untouched
    Livewire::test('admin.builder')
        ->set('type', 'page')
        ->set('name', 'Summer Campaign 2026')
        ->assertSet('slug', 'summer-campaign-2026')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('templates', [
        'name' => 'Summer Campaign 2026',
        'slug' => 'summer-campaign-2026',
        'type' => 'page',
    ]);

    // 2. Requires unique slug for page type
    Livewire::test('admin.builder')
        ->set('type', 'page')
        ->set('name', 'Duplicate Summer Campaign')
        ->set('slug', 'summer-campaign-2026')
        ->call('save')
        ->assertHasErrors(['slug' => 'unique']);

    // 3. Allows manual edit of slug
    Livewire::test('admin.builder')
        ->set('type', 'page')
        ->set('name', 'Winter Collection')
        ->set('slug', 'custom-winter-url')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('templates', [
        'name' => 'Winter Collection',
        'slug' => 'custom-winter-url',
        'type' => 'page',
    ]);

    // 4. Detail templates do not require slug
    Livewire::test('admin.builder')
        ->set('type', 'item_detail')
        ->set('name', 'Generic Detail')
        ->set('slug', '')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('templates', [
        'name' => 'Generic Detail',
        'slug' => null,
        'type' => 'item_detail',
    ]);
});
