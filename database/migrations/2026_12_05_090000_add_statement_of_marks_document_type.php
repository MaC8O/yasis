<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * §7.2: the Statement of Marks is a new document type in the document request flow. No existing
 * row changes. Rolling back is refused while any statement exists, so none is lost.
 */
return new class extends Migration
{
    private const TYPES = ['Transcript', 'Report Card', 'Transfer/Leaving Certificate', 'Completion Certificate', 'Enrollment Certificate'];

    public function up(): void
    {
        $this->setTypes([...self::TYPES, 'Statement of Marks']);
    }

    public function down(): void
    {
        if (DB::table('document_requests')->where('type', 'Statement of Marks')->exists()) {
            throw new RuntimeException('Statements of Marks exist; they would be lost. Roll back is refused.');
        }
        $this->setTypes(self::TYPES);
    }

    private function setTypes(array $types): void
    {
        $list = implode(',', array_map(fn ($t) => DB::getPdo()->quote($t), $types));
        DB::statement("ALTER TABLE document_requests MODIFY type ENUM({$list}) NOT NULL");
    }
};
