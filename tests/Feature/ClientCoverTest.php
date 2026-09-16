<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientCoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_cover_theme_is_saved_per_client_and_rejects_unknown_values(): void
    {
        $client = Client::create(['name' => 'Horizonte']);
        $other = Client::create(['name' => 'Outro']);
        $this->actingAs(User::factory()->create());
        $this->post("/clients/{$client->id}/cover", ['theme' => 'dune'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('dune', $client->fresh()->cover_theme);
        $this->assertNotSame('dune', $other->fresh()->cover_theme);
        $this->post("/clients/{$client->id}/cover", ['theme' => '../invalid'])->assertSessionHasErrors('theme');
        $this->assertSame('dune', $client->fresh()->cover_theme);
    }

    public function test_uploaded_image_is_private_and_can_be_replaced_by_a_theme(): void
    {
        Storage::fake('local');
        $client = Client::create(['name' => 'Horizonte']);
        $this->actingAs(User::factory()->create());
        $this->post("/clients/{$client->id}/cover", ['theme' => 'iris', 'image' => UploadedFile::fake()->image('cover.jpg', 800, 400)])->assertRedirect()->assertSessionHasNoErrors();
        $path = $client->fresh()->cover_path;
        $this->assertNotEmpty($path);
        Storage::disk('local')->assertExists($path);
        $this->get("/clients/{$client->id}/cover")->assertOk();
        $this->post("/clients/{$client->id}/cover", ['theme' => 'mist'])->assertRedirect();
        $this->assertNull($client->fresh()->cover_path);
        $this->get("/clients/{$client->id}/cover")->assertNotFound();
        $this->post("/clients/{$client->id}/cover", ['theme' => 'iris', 'image' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('image');
    }

    public function test_guest_cannot_read_or_change_covers(): void
    {
        $client = Client::create(['name' => 'Horizonte']);
        $this->post("/clients/{$client->id}/cover", ['theme' => 'dune'])->assertRedirect('/login');
        $this->get("/clients/{$client->id}/cover")->assertRedirect('/login');
    }
}
