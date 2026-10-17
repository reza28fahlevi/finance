<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Profil Saya');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', ['name' => 'Nama Baru', 'email' => 'baru@example.com'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('baru@example.com', $user->email);
    }

    public function test_user_cannot_change_own_role_via_profile(): void
    {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)->patch('/profile', ['name' => 'X', 'email' => $user->email, 'role' => 'admin']);

        $this->assertSame('staff', $user->fresh()->role->value);
    }

    public function test_account_self_deletion_is_not_available(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/profile')->assertStatus(405);
        $this->assertNotNull($user->fresh());
    }
}
