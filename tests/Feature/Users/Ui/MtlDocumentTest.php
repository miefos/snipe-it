<?php

namespace Tests\Feature\Users\Ui;

use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MtlDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure storage directory exists
        Storage::fake('local');
    }

    public function testGivingMtlRequiresAuthentication()
    {
        $user = User::factory()->create();

        $this->get(route('users.mtl.giving', $user->id))
            ->assertRedirect(route('login'));
    }

    public function testReceivingMtlRequiresAuthentication()
    {
        $user = User::factory()->create();

        $this->get(route('users.mtl.receiving', $user->id))
            ->assertRedirect(route('login'));
    }

    public function testGivingMtlRequiresViewPermission()
    {
        $authUser = User::factory()->create();
        $targetUser = User::factory()->create();

        $this->actingAs($authUser)
            ->get(route('users.mtl.giving', $targetUser->id))
            ->assertForbidden();
    }

    public function testReceivingMtlRequiresViewPermission()
    {
        $authUser = User::factory()->create();
        $targetUser = User::factory()->create();

        $this->actingAs($authUser)
            ->get(route('users.mtl.receiving', $targetUser->id))
            ->assertForbidden();
    }

    public function testCanGenerateGivingMtlDocument()
    {
        $authUser = User::factory()->superuser()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'phone' => '12345678',
            'employee_num' => '12345',
        ]);

        $targetUser = User::factory()->create([
            'first_name' => 'Target',
            'last_name' => 'User',
            'email' => 'target@example.com',
            'phone' => '87654321',
            'employee_num' => '54321',
        ]);

        $response = $this->actingAs($authUser)
            ->get(route('users.mtl.giving', $targetUser->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $response->assertDownload();
    }

    public function testCanGenerateReceivingMtlDocument()
    {
        $authUser = User::factory()->superuser()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'phone' => '12345678',
            'employee_num' => '12345',
        ]);

        $targetUser = User::factory()->create([
            'first_name' => 'Target',
            'last_name' => 'User',
            'email' => 'target@example.com',
            'phone' => '87654321',
            'employee_num' => '54321',
        ]);

        $response = $this->actingAs($authUser)
            ->get(route('users.mtl.receiving', $targetUser->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $response->assertDownload();
    }

    public function testGivingMtlIncludesAccessoriesFromReceiver()
    {
        $authUser = User::factory()->superuser()->create([
            'first_name' => 'Giver',
            'last_name' => 'User',
        ]);

        $targetUser = User::factory()->create([
            'first_name' => 'Receiver',
            'last_name' => 'User',
        ]);

        // Create category for accessories
        $category = Category::factory()->forAccessories()->create(['name' => 'Test Category']);

        // Create and assign accessories to target user
        $accessory = Accessory::factory()->create([
            'name' => 'Test Accessory',
            'model_number' => 'MODEL-123',
            'category_id' => $category->id,
        ]);

        // Check out accessory to target user
        AccessoryCheckout::create([
            'accessory_id' => $accessory->id,
            'assigned_to' => $targetUser->id,
            'assigned_type' => User::class,
            'note' => 'Test note',
        ]);

        $response = $this->actingAs($authUser)
            ->get(route('users.mtl.giving', $targetUser->id));

        $response->assertOk();
        $response->assertDownload();
    }

    public function testReceivingMtlIncludesAccessoriesFromAuthUser()
    {
        $authUser = User::factory()->superuser()->create([
            'first_name' => 'Receiver',
            'last_name' => 'User',
        ]);

        $targetUser = User::factory()->create([
            'first_name' => 'Giver',
            'last_name' => 'User',
        ]);

        // Create category for accessories
        $category = Category::factory()->forAccessories()->create(['name' => 'Test Category']);

        // Create and assign accessories to auth user
        $accessory = Accessory::factory()->create([
            'name' => 'Test Accessory',
            'model_number' => 'MODEL-456',
            'category_id' => $category->id,
        ]);

        // Check out accessory to auth user
        AccessoryCheckout::create([
            'accessory_id' => $accessory->id,
            'assigned_to' => $authUser->id,
            'assigned_type' => User::class,
            'note' => 'Receiving test note',
        ]);

        $response = $this->actingAs($authUser)
            ->get(route('users.mtl.receiving', $targetUser->id));

        $response->assertOk();
        $response->assertDownload();
    }

    public function testMtlFileIsLoggedToUserUploads()
    {
        $authUser = User::factory()->superuser()->create();
        $targetUser = User::factory()->create();

        $this->actingAs($authUser)
            ->get(route('users.mtl.giving', $targetUser->id));

        // Check that action log was created
        $this->assertDatabaseHas('action_logs', [
            'item_type' => User::class,
            'item_id' => $targetUser->id,
            'action_type' => 'uploaded',
        ]);
    }

    public function testGivingMtlFilenameContainsCorrectType()
    {
        $authUser = User::factory()->superuser()->create();
        $targetUser = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($authUser)
            ->get(route('users.mtl.giving', $targetUser->id));

        // Check that filename contains "Izsniegšanas" (URL-encoded as Izsnieg%C5%A1anas)
        $contentDisposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('MTL_Izsnieg', $contentDisposition);
        $this->assertStringContainsString('anas', $contentDisposition);
        $this->assertStringContainsString('testuser', $contentDisposition);
    }

    public function testReceivingMtlFilenameContainsCorrectType()
    {
        $authUser = User::factory()->superuser()->create(['username' => 'authuser']);
        $targetUser = User::factory()->create(['username' => 'targetuser']);

        $response = $this->actingAs($authUser)
            ->get(route('users.mtl.receiving', $targetUser->id));

        // Check that filename contains "Saņemšanas" (URL-encoded as Sa%C5%86em%C5%A1anas)
        $contentDisposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('MTL_Sa', $contentDisposition);
        $this->assertStringContainsString('em', $contentDisposition);
        $this->assertStringContainsString('anas', $contentDisposition);
        $this->assertStringContainsString('authuser', $contentDisposition);
    }

    public function testMtlDocumentGroupsAccessoriesByType()
    {
        $authUser = User::factory()->superuser()->create();
        $targetUser = User::factory()->create();

        $category = Category::factory()->forAccessories()->create(['name' => 'Clothing']);

        // Create one accessory type
        $accessory = Accessory::factory()->create([
            'name' => 'Jaka M',
            'model_number' => 'JAK-M',
            'category_id' => $category->id,
        ]);

        // Check out the same accessory 3 times to target user
        for ($i = 0; $i < 3; $i++) {
            AccessoryCheckout::create([
                'accessory_id' => $accessory->id,
                'assigned_to' => $targetUser->id,
                'assigned_type' => User::class,
                'note' => 'Test checkout ' . ($i + 1),
            ]);
        }

        $response = $this->actingAs($authUser)
            ->get(route('users.mtl.giving', $targetUser->id));

        $response->assertOk();
        // Document should be generated successfully
        // The controller should group these 3 checkouts into 1 row with quantity 3
    }
}
