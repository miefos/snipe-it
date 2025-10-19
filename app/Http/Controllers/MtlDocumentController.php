<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * This controller handles MTL (Material Transfer List) document generation
 * for the Snipe-IT Asset Management application.
 *
 * @version    v1.0
 */
class MtlDocumentController extends Controller
{
    /**
     * Generate MTL document for a user
     *
     * The authenticated user is the one giving away items (nodod)
     * The user specified by $userId is the one receiving items (pienem)
     *
     * @author [Your Name]
     * @since [v1.0]
     * @param int $userId The ID of the user receiving the items
     * @return StreamedResponse
     */
    public function generate($userId): StreamedResponse
    {
        $user = User::findOrFail($userId);
        $this->authorize('view', $user);

        $authUser = auth()->user();

        // Load the template
        $templateProcessor = new TemplateProcessor(storage_path('templates/mtl_template_v1.docx'));

        // Fill in the "giving away" (nodod) data - authenticated user
        $this->fillUserData($templateProcessor, 'nodod', $authUser);

        // Fill in the "receiving" (pienem) data - viewed user
        $this->fillUserData($templateProcessor, 'pienem', $user);

        // Set checkboxes - these could be made dynamic in the future
        $templateProcessor->setValue('nodod_jcp', 'X');
        $templateProcessor->setValue('nodod_pj', '');
        $templateProcessor->setValue('nodod_njlp', '');

        $templateProcessor->setValue('pienem_jcp', '');
        $templateProcessor->setValue('pienem_pj', 'X');
        $templateProcessor->setValue('pienem_njlp', '');

        // Set current date
        $templateProcessor->setValue('date', now()->toFormattedDateString());

        // Get accessories/items for the table
        $accessories = $this->getAccessoriesData($user);

        // Fill in the accessories table
        $this->fillAccessoriesTable($templateProcessor, $accessories);

        // Generate filename
        $filename = 'MTL_' . $user->username . '_' . date('Y-m-d_His') . '.docx';

        // Save to temp directory first
        $tempDir = storage_path('app/temp');
        if (!File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0775, true, true);
        }
        $tempFilePath = $tempDir . '/' . $filename;
        $templateProcessor->saveAs($tempFilePath);

        // Move to proper storage location using Storage facade
        $storagePath = 'private_uploads/users/' . $filename;
        Storage::put($storagePath, file_get_contents($tempFilePath));

        // Clean up temp file
        unlink($tempFilePath);

        // Create file upload record attached to user
        $user->logUpload($filename, 'MTL dokumenta automātiska izveide');

        // Download the file using Storage
        return Storage::download($storagePath, $filename);
    }

    /**
     * Fill user data in the template
     *
     * @param TemplateProcessor $template
     * @param string $prefix Either 'nodod' or 'pienem'
     * @param User $user
     * @return void
     */
    private function fillUserData(TemplateProcessor $template, string $prefix, User $user): void
    {
        $template->setValue($prefix . '_vards_uzv', $user->first_name . ' ' . $user->last_name);
        $template->setValue($prefix . '_pers_kods', $user->employee_num ?? '');
        $template->setValue($prefix . '_telefons', $user->phone ?? '');
        $template->setValue($prefix . '_epasts', $user->email ?? '');
    }

    /**
     * Get accessories/items data for the user
     *
     * TODO: This should be replaced with actual asset data from the database
     *
     * @param User $user
     * @return array
     */
    private function getAccessoriesData(User $user): array
    {
        // Placeholder data - replace with actual asset queries
        return [
            ['name' => 'Jaka', 'quantity' => '2', 'notes' => '65W USB-C'],
            ['name' => 'Bikses',  'quantity' => '2', 'notes' => 'Logitech MX Master 3'],
            ['name' => 'Manikens', 'quantity' => '1', 'notes' => 'Targus 15-inch'],
            ['name' => 'Jaka', 'quantity' => '2', 'notes' => '65W USB-C'],
            ['name' => 'Bikses',  'quantity' => '2', 'notes' => 'Logitech MX Master 3'],
            ['name' => 'Manikens', 'quantity' => '1', 'notes' => 'Targus 15-inch'],
            ['name' => 'Jaka', 'quantity' => '2', 'notes' => '65W USB-C'],
            ['name' => 'Bikses',  'quantity' => '2', 'notes' => 'Logitech MX Master 3'],
            ['name' => 'Manikens', 'quantity' => '1', 'notes' => 'Targus 15-inch'],
            ['name' => 'Jaka', 'quantity' => '2', 'notes' => '65W USB-C'],
            ['name' => 'Bikses',  'quantity' => '2', 'notes' => 'Logitech MX Master 3'],
            ['name' => 'Manikens', 'quantity' => '1', 'notes' => 'Targus 15-inch'],
        ];
    }

    /**
     * Fill the accessories table in the template
     *
     * @param TemplateProcessor $template
     * @param array $accessories
     * @return void
     */
    private function fillAccessoriesTable(TemplateProcessor $template, array $accessories): void
    {
        $accessoryCount = count($accessories);

        if ($accessoryCount > 0) {
            $template->cloneRow('mtl_nosaukums', $accessoryCount);
            foreach ($accessories as $index => $accessory) {
                $rowNumber = $index + 1;
                $template->setValue('npk#' . $rowNumber, $rowNumber);
                $template->setValue('mtl_nosaukums#' . $rowNumber, $accessory['name']);
                $template->setValue('mtl_izmers#' . $rowNumber, $accessory['notes']);
                $template->setValue('mtl_skaits#' . $rowNumber, $accessory['quantity']);
                $template->setValue('mtl_piezimes#' . $rowNumber, $accessory['notes']);
            }
        }
    }
}
