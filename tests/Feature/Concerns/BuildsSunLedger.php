<?php

namespace Tests\Feature\Concerns;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\SunAccount;
use App\Models\SunImportBatch;
use App\Models\User;
use App\Support\Sun\ListingWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

/**
 * Sun ledger tests: import a synthetic listing (invented names only) through the Treasurer's
 * upload, summary and import, link accounts, publish, and make families. Needs $this->treasurer.
 */
trait BuildsSunLedger
{
    /** A small listing: an allowance families see (T4), a payment, a line that will be hidden by hand. */
    protected function ledger(int $fy = 2021, array $names = []): ListingWriter
    {
        return (new ListingWriter($fy, 30))
            ->account('1000286000', $names['1000286000'] ?? 'Saw Example Student', '25000.00', [
                [9, '52500.00', 'Charging tuition fee for Dec 2020'],
                [9, '-10500.00', 'Providing 20% edu allow on tuition fee for Dec 2020'],
                [10, '-42000.00', 'Payment monthly fee for Dec 2020', 'CR'],
                [11, '-3000.00', 'Internal correction of a posting error'],
            ])
            ->account('1000286100', $names['1000286100'] ?? 'Naw Example Girl', '0.00', [
                [1, '300000.00', 'Charging for 2020-2021 yearly fees'],
                [2, '-350000.00', 'Payment yearly fee', 'CR'],
            ]);
    }

    protected function importListing(string $text, string $name = 'Student GL.PRN'): SunImportBatch
    {
        $page = $this->actingAs($this->treasurer->user)->post('/treasurer/ledger/upload', [
            'file' => UploadedFile::fake()->createWithContent($name, $text),
        ])->assertOk();
        preg_match('/name="token" value="([0-9a-f-]{36})"/', $page->getContent(), $m);
        $this->assertNotEmpty($m, 'no upload token');
        $this->actingAs($this->treasurer->user)->post('/treasurer/ledger/import', ['token' => $m[1], 'file_name' => $name])->assertSessionHas('status');

        return SunImportBatch::latest('id')->first();
    }

    protected function linkCode(string $code, Student $student): void
    {
        $account = SunAccount::where('code', $code)->sole();
        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/accounts/{$account->id}/link", ['student_id_number' => $student->student_id_number])
            ->assertSessionHas('status');
    }

    protected function publish(SunImportBatch $batch): void
    {
        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/{$batch->id}/publish")->assertSessionHas('status');
    }

    /** A guardian user linked to $student. */
    protected function familyOf(Student $student, string $email): User
    {
        $user = User::create(['name' => 'Parent of '.$student->name, 'email' => $email, 'password' => Hash::make('password'), 'status' => 'Active']);
        $user->assignRole('guardian');
        Guardian::create(['user_id' => $user->id, 'relationship' => 'Mother', 'phone' => '09'])->students()->attach($student->id, ['is_primary' => true]);

        return $user;
    }

    protected function guardianFees(User $guardian, Student $child)
    {
        return $this->actingAs($guardian)->get("/guardian/fees?child={$child->id}")->assertOk();
    }
}
