<?php

/*
 * Worker for BehaviorConcurrencyTest: boots the app and makes one VP deduction for a student.
 * Run as a separate PHP process so two deductions can really happen at the same moment.
 *
 * Usage: php behavior-deduct-worker.php <studentId> <vpUserId> <categoryId> <points> <label>
 * Prints one JSON line with the ledger entry's id, score_before and score_after.
 */

use App\Models\Student;
use App\Models\User;
use App\Services\Behavior\BehaviorService;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[, $studentId, $vpId, $categoryId, $points, $label] = $argv;

$entry = app(BehaviorService::class)->createAndDeduct(
    User::findOrFail((int) $vpId),
    Student::findOrFail((int) $studentId),
    [
        'incident_date' => now()->toDateString(),
        'behavior_category_id' => (int) $categoryId,
        'description' => "Concurrent deduction test {$label}.",
        'is_office_referral' => false,
    ],
    (int) $points,
    "Concurrent deduction {$label}",
);

echo json_encode(['id' => $entry->id, 'before' => $entry->score_before, 'after' => $entry->score_after]), PHP_EOL;
