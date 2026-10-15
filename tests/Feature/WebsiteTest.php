<?php

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->manager = User::factory()->management()->create();
});

function websiteFields(array $overrides = []): array
{
    return [
        'home_title' => 'Fresh from the sea',
        'home_intro' => 'Order today.',
        'about_title' => 'Our story',
        'about_body' => "First paragraph.\n\nSecond paragraph.",
        'contact_phone' => '+233 30 222 3333',
        'contact_email' => 'shop@bluefoods.test',
        'contact_address' => 'Tema',
        'contact_hours' => 'Every day',
        'delivery_areas' => 'Accra only.',
        'collection_point' => 'Tema harbour',
        'seo_description' => 'Fish online.',
        'delivery_fee' => '15.50',
        ...$overrides,
    ];
}

test('W1: management changes page text and photos without a developer', function () {
    $this->actingAs($this->manager)->post(route('website.update'), websiteFields([
        'home_image' => UploadedFile::fake()->image('banner.jpg', 1200, 600),
    ]))->assertSessionHasNoErrors();

    expect(SiteSetting::deliveryFeePesewas())->toBe(1550);
    Storage::disk('public')->assertExists(SiteSetting::get('home_image'));

    $this->get(route('site.home'))->assertOk()->assertSee('Fresh from the sea')->assertSee(SiteSetting::get('home_image'));
    $this->get(route('site.about'))->assertSee('Our story')->assertSee('Second paragraph.');
    $this->get(route('site.contact'))->assertSee('+233 30 222 3333')->assertSee('Tema harbour')->assertSee('GHS 15.50');

    expect(AuditLog::where('entity', 'site_setting')->exists())->toBeTrue();
});

test('W1: page text is escaped', function () {
    $this->actingAs($this->manager)->post(route('website.update'), websiteFields(['about_body' => '<script>alert(1)</script>']));

    $this->get(route('site.about'))->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
});

test('the delivery fee must be a cedi amount', function () {
    $this->actingAs($this->manager)->post(route('website.update'), websiteFields(['delivery_fee' => '-5']))
        ->assertSessionHasErrors('delivery_fee');
});

test('W1/W3: management uploads a product photo shown on the shop', function () {
    $product = Product::factory()->priced(2500)->create();

    $this->actingAs($this->manager)->post(route('products.photo', $product), [
        'photo' => UploadedFile::fake()->image('fish.jpg'),
    ])->assertSessionHasNoErrors();

    $product->refresh();
    Storage::disk('public')->assertExists($product->photo_path);
    $this->get(route('shop.show', $product->sku))->assertSee($product->photoUrl());

    $old = $product->photo_path;
    $this->post(route('products.photo', $product), ['photo' => UploadedFile::fake()->image('new.jpg')]);
    Storage::disk('public')->assertMissing($old);
});

test('W3: a new product is created with its photo, which is required', function () {
    $fields = ['category' => 'Fish', 'unit' => 'pack', 'reorder_level' => 0, 'price' => '35.50'];

    $this->actingAs($this->manager)->post(route('products.store'), [
        'sku' => 'BF-1', 'name' => 'With photo', 'photo' => UploadedFile::fake()->image('fish.jpg'), ...$fields,
    ])->assertSessionHasNoErrors();
    $this->post(route('products.store'), ['sku' => 'BF-2', 'name' => 'No photo', ...$fields])->assertSessionHasErrors('photo');

    $product = Product::where('sku', 'BF-1')->sole();
    Storage::disk('public')->assertExists($product->photo_path);
    $this->get(route('shop.show', $product->sku))->assertSee($product->photoUrl());
    expect(Product::where('sku', 'BF-2')->exists())->toBeFalse();
});

test('a new product is not created when its photo is not an image', function () {
    $this->actingAs($this->manager)->post(route('products.store'), [
        'sku' => 'BF-1', 'name' => 'X', 'category' => 'Fish', 'unit' => 'pack', 'reorder_level' => 0, 'price' => '1',
        'photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('photo');

    expect(Product::where('sku', 'BF-1')->exists())->toBeFalse();
});

test('product photo must be an image', function () {
    $product = Product::factory()->priced(2500)->create();

    $this->actingAs($this->manager)->post(route('products.photo', $product), [
        'photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('photo');
});

test('W12: sitemap lists the pages and active products, and pages have titles and descriptions', function () {
    $active = Product::factory()->priced(2500)->create(['description' => 'Wood-smoked over coconut husk.']);
    $retired = Product::factory()->priced(2500)->retired()->create();

    $this->get(route('site.sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee(route('shop.index'))
        ->assertSee(route('shop.show', $active->sku))
        ->assertDontSee(route('shop.show', $retired->sku));

    $this->get(route('shop.show', $active->sku))
        ->assertSee("<title>{$active->name} | Blue Foods</title>", false)
        ->assertSee('<meta name="description" content="Wood-smoked over coconut husk.">', false);
});

test('the online shop system account cannot be edited or re-enabled', function () {
    $system = User::systemAccount();

    $this->actingAs($this->manager)->post(route('users.enable', $system))->assertNotFound();
    $this->actingAs($this->manager)->post(route('users.reset-password', $system), ['password' => 'new-password-1'])->assertNotFound();
    $this->get(route('users.index'))->assertDontSee(User::SYSTEM_EMAIL);

    $this->post(route('login'), ['login' => User::SYSTEM_EMAIL, 'password' => 'anything']);
    $this->assertAuthenticatedAs($this->manager);
});
