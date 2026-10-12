<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $itAdmin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itAdmin = User::factory()->create([
            'role' => 'IT Admin',
            'email' => 'itadmin@example.com',
            'department' => 'Management',
            'password' => Hash::make('password123'),
        ]);

        $this->user = User::factory()->create([
            'role' => 'Staff',
            'email' => 'staff@example.com',
            'department' => 'Creative',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_activity_logged_when_user_accesses_financial_performance_tab(): void
    {
        $response = $this->actingAs($this->itAdmin)->get(route('financial-performance.index'));
        $response->assertOk();

        $this->assertDatabaseHas('activities', [
            'user_id' => $this->itAdmin->id,
            'description' => 'Accessed Financial Performance tab',
        ]);
    }

    public function test_activity_logged_when_user_changes_password(): void
    {
        $response = $this->actingAs($this->user)->post(route('password.change'), [
            'current_password' => 'password123',
            'password' => 'newSecretPassword123!',
            'password_confirmation' => 'newSecretPassword123!',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('activities', [
            'user_id' => $this->user->id,
            'description' => 'Reset password',
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
        ]);
    }

    public function test_activity_logged_when_user_resets_password_via_otp(): void
    {
        // Seed an OTP record in password_reset_tokens
        $otp = '123456';
        DB::table('password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => Hash::make($otp),
            'created_at' => now(),
        ]);

        $response = $this->post(route('password.update-otp'), [
            'email' => $this->user->email,
            'otp' => $otp,
            'password' => 'brandNewPassword123!',
            'password_confirmation' => 'brandNewPassword123!',
        ]);

        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('activities', [
            'user_id' => $this->user->id,
            'description' => 'Reset password via OTP',
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
        ]);
    }

    public function test_activity_logged_when_admin_resets_user_password(): void
    {
        $response = $this->actingAs($this->itAdmin)->put(route('users.update', $this->user), [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'role' => $this->user->role,
            'department' => $this->user->department,
            'password' => 'adminResetPassword123!',
            'password_confirmation' => 'adminResetPassword123!',
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('activities', [
            'user_id' => $this->itAdmin->id,
            'description' => "Reset password for user: {$this->user->name}",
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
        ]);
    }
}
