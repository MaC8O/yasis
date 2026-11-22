<?php

namespace Tests\Feature\Behavior;

use App\Models\AcademicYear;
use App\Models\BehaviorCategory;
use App\Models\BehaviorLedgerEntry;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\User;
use App\Services\Behavior\BehaviorStanding;
use App\Support\BehaviorPermissions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Two deductions for the same student at the same moment must both apply, in turn.
 *
 * Deterministic, not timing-based: this test holds the lock on the student's row, starts two
 * worker processes, and waits (bounded) until MySQL reports BOTH of them blocked on that lock.
 * Only then does it release the lock, so the two deductions always contend. If the service did
 * not lock the student's row, the workers would never block and the test fails at the wait step
 * with a clear message — it can never pass by luck.
 *
 * Runs in its own database (workers need committed rows; the shared test database is wrapped in
 * per-test transactions), so it cannot interfere with other tests. Group: concurrency.
 */
#[Group('concurrency')]
class BehaviorConcurrencyTest extends TestCase
{
    private const PREFIX = 'yasis_test_concurrency_';

    private const WAIT_FOR_BLOCKED_SECONDS = 20;

    private const WORKER_TIMEOUT_SECONDS = 60;

    /** A database of its own for every run, so a stray process from an earlier run can never interfere. */
    private string $database;

    /** @var Process[] */
    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Clear anything an interrupted earlier run left behind: its connections, then its database.
        foreach (DB::select('SELECT SCHEMA_NAME AS name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE ?', [self::PREFIX.'%']) as $old) {
            $this->dropDatabase($old->name);
        }

        $this->database = self::PREFIX.bin2hex(random_bytes(4));
        DB::statement("CREATE DATABASE `{$this->database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        config(['database.connections.mysql.database' => $this->database]);
        DB::purge('mysql');
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop(1);
            }
        }
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        $this->dropDatabase($this->database);
        parent::tearDown();
    }

    private function dropDatabase(string $name): void
    {
        foreach (DB::select('SELECT ID AS id FROM information_schema.PROCESSLIST WHERE DB = ? AND ID <> CONNECTION_ID()', [$name]) as $conn) {
            try {
                DB::statement('KILL '.(int) $conn->id);
            } catch (\Throwable) {
                // already gone
            }
        }
        DB::statement("DROP DATABASE IF EXISTS `{$name}`");
    }

    public function test_two_simultaneous_deductions_end_with_the_correct_score(): void
    {
        [$student, $vp, $categoryId, $yearId] = $this->committedFixtures();

        // 1. Hold the student's row lock ourselves.
        DB::beginTransaction();
        DB::select('SELECT id FROM students WHERE id = ? FOR UPDATE', [$student->id]);

        // 2. Start both workers; each will block on that same lock inside BehaviorService.
        foreach (['A', 'B'] as $label) {
            $worker = new Process(
                [PHP_BINARY, base_path('tests/Support/behavior-deduct-worker.php'), $student->id, $vp->id, $categoryId, 30, $label],
                base_path(),
                $this->workerEnv(),
            );
            $worker->setTimeout(self::WORKER_TIMEOUT_SECONDS);
            $worker->start();
            $this->workers[] = $worker;
        }

        // 3. Wait until both workers are executing the locking read of this student's row (bounded,
        //    no fixed sleeps). While we hold that row lock such a statement cannot complete, so both
        //    are waiting on it. The process list is used rather than INNODB_TRX, which on MariaDB
        //    does not always report a wait that starts in the optimizer's const-row read.
        $deadline = microtime(true) + self::WAIT_FOR_BLOCKED_SECONDS;
        do {
            $waiting = (int) DB::selectOne(
                "SELECT COUNT(*) AS c FROM information_schema.PROCESSLIST
                  WHERE DB = ? AND ID <> CONNECTION_ID() AND COMMAND IN ('Query', 'Execute')
                    AND INFO LIKE 'select % from `students` where %for update'",
                [$this->database],
            )->c;
            if ($waiting >= 2) {
                break;
            }
            foreach ($this->workers as $w) {
                if (! $w->isRunning()) {
                    DB::rollBack();
                    $this->fail("A worker finished without waiting for the student's row lock — BehaviorService is not locking it.\n".$w->getOutput().$w->getErrorOutput());
                }
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        if ($waiting < 2) {
            $connections = collect(DB::select('SELECT ID, COMMAND, TIME, STATE, LEFT(INFO, 120) AS INFO FROM information_schema.PROCESSLIST WHERE DB = ?', [$this->database]))
                ->map(fn ($p) => "#{$p->ID} {$p->COMMAND} {$p->TIME}s [{$p->STATE}] {$p->INFO}")->implode("\n");
            $output = collect($this->workers)->map(fn ($w) => trim($w->getIncrementalOutput().$w->getIncrementalErrorOutput()))->implode("\n---\n");
            DB::rollBack();
            $this->fail("Only {$waiting} of 2 workers requested the student's row lock within ".self::WAIT_FOR_BLOCKED_SECONDS."s — is BehaviorService locking the student row?\nConnections:\n{$connections}\nWorker output:\n{$output}");
        }

        // 4. Release: both deductions now compete for the lock at the same moment.
        DB::commit();

        $results = [];
        foreach ($this->workers as $w) {
            $w->wait();
            $raw = $w->getOutput().$w->getErrorOutput();
            $this->assertSame(0, $w->getExitCode(), "Worker failed:\n{$raw}");
            $lines = preg_split('/\R/', trim($w->getOutput()));
            $result = json_decode((string) end($lines), true);
            $this->assertIsArray($result, "Worker did not print a result line:\n{$raw}");
            $results[] = $result;
        }

        // 5. Applied one after the other: 100 → 70 → 40. Neither read the stale 100.
        $this->assertSame(40, app(BehaviorStanding::class)->score($student->id, $yearId));
        $this->assertEqualsCanonicalizing([100, 70], array_column($results, 'before'));
        $this->assertEqualsCanonicalizing([70, 40], array_column($results, 'after'));
        $this->assertSame(2, BehaviorLedgerEntry::where('student_id', $student->id)->count());
    }

    /** Committed rows the worker processes can see. */
    private function committedFixtures(): array
    {
        foreach (['vp_academic', 'principal', 'guardian', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        BehaviorPermissions::sync();

        $year = AcademicYear::create(['year_label' => 'Concurrency year', 'is_active' => true]);
        $department = Department::create(['name' => 'Concurrency Dept', 'level' => 'Secondary']);

        $vp = User::create(['name' => 'VP Concurrency', 'email' => 'vp.concurrency@test.local', 'password' => Hash::make('password'), 'status' => 'Active']);
        $vp->assignRole('vp_academic');
        StaffProfile::create(['id' => $vp->id, 'staff_id_number' => 'VPC-1', 'role_type' => 'VP_Academic', 'job_title' => 'VP', 'status' => 'Active', 'joined_date' => now()->subYear()]);

        $student = Student::create(['student_id_number' => 'CONC-1', 'name' => 'Concurrent Student', 'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);

        return [$student, $vp, BehaviorCategory::firstOrFail()->id, $year->id];
    }

    private function workerEnv(): array
    {
        $db = config('database.connections.mysql');

        return [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $this->database,
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => (string) $db['password'],
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];
    }
}
