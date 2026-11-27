<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * §5: a ledger line's category is worked out from its memo by keyword, in category order, first
 * match wins. One exception to the §5 table's order: "any credit in a CR journal" is checked right
 * after brought_forward (its own position), so a cash receipt such as "Payment monthly fee for Jan
 * 2020" (§5's own example) is a payment and not tuition; the payment keywords stay last.
 * The Treasurer edits the rules; they are data, not code. §7 / T4: whether a category's lines
 * are hidden from families by default is data too (only allowances).
 */
return new class extends Migration
{
    /** [key, label, hidden from families by default, keywords, CR credits match] in the order checked. */
    private const CATEGORIES = [
        ['allowance', 'Allowance', true, ['edu allow', 'edu. allow', 'educational allow', 'educatinal allow', 'allowance', 'scholarship', 'stipend'], false],
        ['reversal', 'Reversal', false, ['revers'], false],
        ['brought_forward', 'Brought forward', false, ['brought forward', 'b/f'], false],
        ['deposit', 'Deposit', false, ['deposit'], false],
        ['transport', 'Transport', false, ['ferry', 'bus', 'transport'], false],
        ['yearly', 'Yearly fee', false, ['yearly', 'annual'], false],
        ['tuition', 'Tuition', false, ['tuition', 'instructional', 'monthly', 'school fee'], false],
        ['late_fee', 'Late fee', false, ['late fee'], false],
        ['remedial', 'Remedial', false, ['remedia', 'summer'], false],
        ['donation', 'Donation', false, ['donation'], false],
        ['cash_advance', 'Cash advance', false, ['cash', 'uniform', 'textbook'], false],
        ['payment', 'Payment', false, ['payment', 'paid', 'on a/c'], true],
        ['unclassified', 'Unclassified', false, [], false],
    ];

    public function up(): void
    {
        Schema::create('fee_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20)->unique();
            $table->string('label', 40);
            $table->unsignedSmallInteger('position');
            $table->boolean('restricted_by_default')->default(false);
            $table->string('pending_question', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('fee_category_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_category_id')->constrained('fee_categories')->restrictOnDelete();
            $table->string('keyword', 60)->nullable();
            $table->boolean('cr_credit')->default(false); // matches any CR journal credit not matched by an earlier rule
            $table->unsignedSmallInteger('position')->nullable(); // checked at this position instead of the category's
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now = now();
        foreach (self::CATEGORIES as $i => [$key, $label, $hidden, $keywords, $crCredit]) {
            $id = DB::table('fee_categories')->insertGetId([
                'key' => $key, 'label' => $label, 'position' => ($i + 1) * 10, 'restricted_by_default' => $hidden,
                'pending_question' => $hidden ? 'T4' : null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $rows = array_map(fn ($k) => ['fee_category_id' => $id, 'keyword' => $k, 'cr_credit' => false, 'position' => null, 'active' => true, 'created_at' => $now, 'updated_at' => $now], $keywords);
            if ($crCredit) {
                // Right after brought_forward (position 30): before the fee-type keywords such as "monthly".
                $rows[] = ['fee_category_id' => $id, 'keyword' => null, 'cr_credit' => true, 'position' => 35, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
            }
            if ($rows) {
                DB::table('fee_category_rules')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_category_rules');
        Schema::dropIfExists('fee_categories');
    }
};
