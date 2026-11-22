<?php

namespace App\Services\LessonPlans;

use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Decides what a lesson-plan upload really is from its content, never from its name or the
 * browser's declared type. Accepted: PDF, Word (.docx) and PowerPoint (.pptx). The extension must
 * match what the content is. Refused: anything else, including old .doc/.ppt and macro-enabled
 * Office files (.docm/.pptm and look-alikes renamed to .docx/.pptx).
 */
class LessonPlanFileInspector
{
    public const TYPES = [
        'pdf' => ['mime' => 'application/pdf', 'label' => 'PDF'],
        'docx' => ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'label' => 'Word'],
        'pptx' => ['mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'label' => 'PowerPoint'],
    ];

    /** The main-part content type each Office format must declare in [Content_Types].xml. */
    private const OFFICE_MAIN_PARTS = [
        'docx' => ['part' => 'word/document.xml', 'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'],
        'pptx' => ['part' => 'ppt/presentation.xml', 'type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml'],
    ];

    /**
     * @return array{extension: string, mime: string, label: string}|string the detected type, or
     *                                                                      the reason it is refused
     */
    public function inspect(UploadedFile $file): array|string
    {
        $path = $file->getRealPath();
        if (! $path || ! is_readable($path) || filesize($path) === 0) {
            return 'The file is empty or could not be read.';
        }

        $detected = $this->detect($path);
        if (! is_string($detected) || ! isset(self::TYPES[$detected])) {
            return $detected === 'macro'
                ? 'Files with macros are not accepted. Save it as a normal .docx or .pptx and upload that.'
                : 'This file is not a PDF, Word (.docx) or PowerPoint (.pptx) document.';
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== $detected) {
            return 'The file name ends in .'.($extension ?: '(nothing)').' but the file is a '.self::TYPES[$detected]['label']
                .' document. Rename it to .'.$detected.' and upload it again.';
        }

        return ['extension' => $detected] + self::TYPES[$detected];
    }

    /** 'pdf' | 'docx' | 'pptx' | 'macro' | null */
    private function detect(string $path): ?string
    {
        $head = (string) file_get_contents($path, false, null, 0, 8);

        if (str_starts_with($head, '%PDF-')) {
            return 'pdf';
        }

        if (! str_starts_with($head, "PK\x03\x04")) {
            return null;
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return null;
        }

        try {
            // Only this small part is read, with a length cap, so a crafted archive is never unpacked.
            $contentTypes = $zip->getFromName('[Content_Types].xml', 262144);
            if (! is_string($contentTypes) || $contentTypes === '') {
                return null;
            }

            if (stripos($contentTypes, 'macroEnabled') !== false
                || $zip->locateName('word/vbaProject.bin') !== false
                || $zip->locateName('ppt/vbaProject.bin') !== false) {
                return 'macro';
            }

            foreach (self::OFFICE_MAIN_PARTS as $type => $main) {
                if ($zip->locateName($main['part']) !== false && str_contains($contentTypes, $main['type'])) {
                    return $type;
                }
            }

            return null;
        } finally {
            $zip->close();
        }
    }
}
