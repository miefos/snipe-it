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
        Storage::fake('local');
    }

    public function testMtlRequiresAuthentication()
    {
        $user = User::factory()->create();

        $this->get(route('users.mtl.giving', $user->id))->assertRedirect(route('login'));
        $this->get(route('users.mtl.receiving', $user->id))->assertRedirect(route('login'));
    }

    public function testMtlRequiresPermission()
    {
        $authUser = User::factory()->create();
        $targetUser = User::factory()->create();

        $this->actingAs($authUser)
            ->get(route('users.mtl.giving', $targetUser->id))
            ->assertForbidden();

        $this->actingAs($authUser)
            ->get(route('users.mtl.receiving', $targetUser->id))
            ->assertForbidden();
    }

    public function testGeneratesMtlDocumentsWithCorrectFilenames()
    {
        $authUser = User::factory()->superuser()->create(['username' => 'authuser']);
        $targetUser = User::factory()->create(['username' => 'targetuser']);

        // Test giving MTL
        $givingResponse = $this->actingAs($authUser)
            ->get(route('users.mtl.giving', $targetUser->id));

        $givingResponse->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertDownload();

        $givingDisposition = $givingResponse->headers->get('content-disposition');
        $this->assertStringContainsString('MTL_Izsnieg', $givingDisposition);
        $this->assertStringContainsString('targetuser', $givingDisposition);

        $storedFilesAfterGiving = array_values(Storage::disk('local')->files('private_uploads/users'));
        $this->assertCount(1, $storedFilesAfterGiving);
        $this->assertTrue(str_contains($storedFilesAfterGiving[0], 'MTL_Izsnieg'));
        $this->assertTrue(Storage::disk('local')->exists($storedFilesAfterGiving[0]));

        // Test receiving MTL
        $receivingResponse = $this->actingAs($authUser)
            ->get(route('users.mtl.receiving', $targetUser->id));

        $receivingResponse->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertDownload();

        $receivingDisposition = $receivingResponse->headers->get('content-disposition');
        $this->assertStringContainsString('MTL_Sa', $receivingDisposition);
        $this->assertStringContainsString('em', $receivingDisposition);
        $this->assertStringContainsString('anas', $receivingDisposition);
        $this->assertStringContainsString('authuser', $receivingDisposition);

        $storedFilesAfterReceiving = Storage::disk('local')->files('private_uploads/users');
        $this->assertCount(2, $storedFilesAfterReceiving);
        $this->assertNotEmpty(array_filter($storedFilesAfterReceiving, fn ($path) => str_contains($path, 'MTL_Izsnieg')));
        $this->assertNotEmpty(array_filter($storedFilesAfterReceiving, fn ($path) => str_contains($path, 'MTL_Sa')));

        // Verify action log
        $this->assertDatabaseHas('action_logs', [
            'item_type' => User::class,
            'item_id' => $targetUser->id,
            'action_type' => 'uploaded',
        ]);

        $this->assertDatabaseHas('action_logs', [
            'item_type' => User::class,
            'item_id' => $authUser->id,
            'action_type' => 'uploaded',
        ]);
    }

    public function testMtlIncludesAccessoriesAndGroupsByType()
    {
        $authUser = User::factory()->superuser()->create();
        $targetUser = User::factory()->create();
        $category = Category::factory()->forAccessories()->create();

        // Create accessory checked out to target user (for giving MTL)
        $givingAccessory = Accessory::factory()->create([
            'name' => 'Test Accessory',
            'model_number' => 'MODEL-123',
            'category_id' => $category->id,
        ]);

        AccessoryCheckout::create([
            'accessory_id' => $givingAccessory->id,
            'assigned_to' => $targetUser->id,
            'assigned_type' => User::class,
            'note' => 'Test note',
        ]);

        // Test giving MTL includes accessories
        $this->actingAs($authUser)
            ->get(route('users.mtl.giving', $targetUser->id))
            ->assertOk();

        // Create accessory checked out to auth user (for receiving MTL)
        $receivingAccessory = Accessory::factory()->create([
            'name' => 'Receiving Accessory',
            'model_number' => 'MODEL-456',
            'category_id' => $category->id,
        ]);

        AccessoryCheckout::create([
            'accessory_id' => $receivingAccessory->id,
            'assigned_to' => $authUser->id,
            'assigned_type' => User::class,
            'note' => 'Receiving note',
        ]);

        // Test receiving MTL includes accessories
        $this->actingAs($authUser)
            ->get(route('users.mtl.receiving', $targetUser->id))
            ->assertOk();

        // Test grouping: check out same accessory 3 times
        $groupingAccessory = Accessory::factory()->create([
            'name' => 'Jaka M',
            'model_number' => 'JAK-M',
            'category_id' => $category->id,
        ]);

        for ($i = 0; $i < 3; $i++) {
            AccessoryCheckout::create([
                'accessory_id' => $groupingAccessory->id,
                'assigned_to' => $targetUser->id,
                'assigned_type' => User::class,
            ]);
        }

        // Verify document generates successfully with grouped accessories
        $this->actingAs($authUser)
            ->get(route('users.mtl.giving', $targetUser->id))
            ->assertOk();
    }

    public function testMtlReturnsNotFoundForMissingUser()
    {
        $authUser = User::factory()->superuser()->create();

        $this->actingAs($authUser)
            ->get(route('users.mtl.giving', PHP_INT_MAX))
            ->assertNotFound();

        $this->actingAs($authUser)
            ->get(route('users.mtl.receiving', PHP_INT_MAX))
            ->assertNotFound();
    }

    public function testAuthorizedTargetUserCanGenerateGivingDocument()
    {
        $giver = User::factory()->superuser()->create(['username' => 'giveruser']);
        $receiver = User::factory()->superuser()->create(['username' => 'receiveruser']);

        $response = $this->actingAs($receiver)
            ->get(route('users.mtl.giving', $giver->id));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertDownload();

        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('MTL_Izsnieg', $disposition);
        $this->assertStringContainsString($giver->username, $disposition);

        $storedFiles = Storage::disk('local')->files('private_uploads/users');
        $this->assertCount(1, $storedFiles);
        $this->assertNotEmpty(array_filter($storedFiles, fn ($path) => str_contains($path, 'MTL_Izsnieg')));

        $this->assertDatabaseHas('action_logs', [
            'item_type' => User::class,
            'item_id' => $giver->id,
            'action_type' => 'uploaded',
        ]);
    }

    public function testAuthorizedTargetUserCanGenerateReceivingDocument()
    {
        $giver = User::factory()->superuser()->create(['username' => 'giveruser']);
        $receiver = User::factory()->superuser()->create(['username' => 'receiveruser']);

        $response = $this->actingAs($receiver)
            ->get(route('users.mtl.receiving', $giver->id));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertDownload();

        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('MTL_Sa', $disposition);
        $this->assertStringContainsString('em', $disposition);
        $this->assertStringContainsString('anas', $disposition);
        $this->assertStringContainsString($receiver->username, $disposition);

        $storedFiles = Storage::disk('local')->files('private_uploads/users');
        $this->assertCount(1, $storedFiles);
        $this->assertNotEmpty(array_filter($storedFiles, fn ($path) => str_contains($path, 'MTL_Sa')));

        $this->assertDatabaseHas('action_logs', [
            'item_type' => User::class,
            'item_id' => $receiver->id,
            'action_type' => 'uploaded',
        ]);
    }
}
