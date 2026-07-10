<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imported_fee_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->date('txn_date');
            $table->decimal('amount', 12, 2);
            $table->decimal('balance', 12, 2);
            $table->enum('status', ['Owed', 'Paid', 'Partial', 'Outstanding']);
            $table->boolean('is_restricted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_fee_records');
    }
};
