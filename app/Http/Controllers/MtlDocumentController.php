<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\StreamedResponse;

// TODO: 1. based on user group (jaunsargi vs instruktori) set
//       2. in user field - "notes" - parent information for underage students.
//       3. in user field - "employee_num" - personal code (personas kods) for Latvian users.
//       4. in user field - "start date" - date of birth for Latvian users.
//       5. if user is underage (<18 years), add parent/guardian in nodod_persons_information and add the user's name and surname itself in jaunsarga_vards_uzvards
//       6. if user is adult (>=18 years), add user themselves in nodod_persons_information and also add them in jaunsarga_vards_uzvards
//       7.

/**
 * This controller handles MTL (Material Transfer List) document generation
 * for the Snipe-IT Asset Management application.
 *
 * @version    v1.0
 */
class MtlDocumentController extends Controller
{
    /**
     * Generate MTL document for giving away items (Izsniegšanas lapa)
     *
     * The authenticated user is the one giving away items (nodod)
     * The user specified by $userId is the one receiving items (pienem)
     *
     * @author [Your Name]
     * @since [v1.0]
     * @param int $userId The ID of the user receiving the items
     * @return StreamedResponse
     */
    public function generateGiving($userId): StreamedResponse
    {
        $user = User::findOrFail($userId);
        $this->authorize('view', $user);

        $authUser = auth()->user();

        return $this->generateDocument($authUser, $user, 'Izsniegšanas');
    }

    /**
     * Generate MTL document for receiving items (Saņemšanas lapa)
     *
     * The user specified by $userId is the one giving away items (nodod)
     * The authenticated user is the one receiving items (pienem)
     *
     * @author [Your Name]
     * @since [v1.0]
     * @param int $userId The ID of the user giving away the items
     * @return StreamedResponse
     */
    public function generateReceiving($userId): StreamedResponse
    {
        $user = User::findOrFail($userId);
        $this->authorize('view', $user);

        $authUser = auth()->user();

        // Reversed: user gives, authUser receives
        return $this->generateDocument($user, $authUser, 'Saņemšanas');
    }

    /**
     * Generate the actual MTL document
     *
     * @param User $giver The user giving away items (nodod)
     * @param User $receiver The user receiving items (pienem)
     * @param string $type Document type for filename
     * @return StreamedResponse
     */
    private function generateDocument(User $giver, User $receiver, string $type): StreamedResponse
    {
        // Load the template
        $templateProcessor = new TemplateProcessor(storage_path('templates/mtl_template_v1.docx'));

        // Fill in the "giving away" (nodod) data
        $this->fillUserData($templateProcessor, 'nodod', $giver);
        $templateProcessor->setValue('nodod_personas_informacija', $this->formatPersonalInfo($giver));

        // Fill in the "receiving" (pienem) data
        $this->fillUserData($templateProcessor, 'pienem', $receiver);
        $templateProcessor->setValue('pienem_personas_informacija', $this->formatPersonalInfo($receiver));

        // For now, jaunsarga_vards is the receiving person's name
        $templateProcessor->setValue('jaunsarga_vards');

        // Set checkboxes - these could be made dynamic in the future
        $templateProcessor->setValue('nodod_jcp', '');
        $templateProcessor->setValue('nodod_pj', '');
        $templateProcessor->setValue('nodod_njlp', '');

        $templateProcessor->setValue('pienem_jcp', '');
        $templateProcessor->setValue('pienem_pj', '');
        $templateProcessor->setValue('pienem_njlp', '');

        // Set current date in dd.mm.yyyy format (Riga timezone)
        $templateProcessor->setValue('datums', now()->timezone('Europe/Riga')->format('d.m.Y'));

        // Get accessories/items for the table (from receiver's assigned items)
        $accessories = $this->getAccessoriesData($receiver);

        // Fill in the accessories table
        $this->fillAccessoriesTable($templateProcessor, $accessories);

        // Generate filename
        $filename = 'MTL_' . $type . '_' . $receiver->username . '_' . date('Y-m-d_His') . '.docx';

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

        // Create file upload record attached to receiver
        $receiver->logUpload($filename, 'MTL dokumenta automātiska izveide (' . $type . ')');

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
     * Format personal information into a single line
     *
     * Format: Name Surname, Personal Code, Phone, Email
     *
     * @param User $user
     * @return string
     */
    private function formatPersonalInfo(User $user): string
    {
        $parts = [
            $user->first_name . ' ' . $user->last_name,
            $user->employee_num ?? '',
            $user->phone ?? '',
            $user->email ?? '',
        ];

        // Remove empty parts and join with ", "
        return implode(', ', array_filter($parts));
    }

    /**
     * Get accessories/items data for the user
     *
     * Group by accessory ID and sum quantities
     *
     * @param User $user
     * @return array
     */
    private function getAccessoriesData(User $user): array
    {
        // Get all accessories checked out to this user
        $accessories = $user->accessories()->get();

        // Group accessories by accessory ID
        $grouped = [];
        foreach ($accessories as $accessory) {
            $id = $accessory->id;

            if (!isset($grouped[$id])) {
                $grouped[$id] = [
                    'name' => $accessory->name,
                    'model' => $accessory->model_number ?? '',
                    'size' => $accessory->category->name ?? '',
                    'quantity' => 0,
                    'notes' => '',
                ];
            }

            // Increment quantity
            $grouped[$id]['quantity']++;

            // Keep only the first note
            if (empty($grouped[$id]['notes']) && ($note = $accessory->pivot->note ?? '')) {
                $grouped[$id]['notes'] = $note;
            }
        }

        // Convert to array
        $data = [];
        foreach ($grouped as $item) {
            $data[] = [
                'name' => $item['name'],
                'model' => $item['model'],
                'size' => $item['size'],
                'quantity' => (string) $item['quantity'],
                'notes' => $item['notes'],
            ];
        }

        return $data;
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
                $template->setValue('mtl_izmers#' . $rowNumber, $accessory['model']);
                $template->setValue('mtl_skaits#' . $rowNumber, $accessory['quantity']);
                $template->setValue('mtl_piezimes#' . $rowNumber, $accessory['notes']);
            }
        }
    }
}
