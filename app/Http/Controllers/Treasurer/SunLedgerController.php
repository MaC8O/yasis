<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Treasurer\LinkSunAccountRequest;
use App\Http\Requests\Treasurer\SaveFeeCategoriesRequest;
use App\Http\Requests\Treasurer\SetLedgerLineRequest;
use App\Http\Requests\Treasurer\UploadSunListingRequest;
use App\Models\FeeCategory;
use App\Models\Student;
use App\Models\SunAccount;
use App\Models\SunAccountBalance;
use App\Models\SunAccountLink;
use App\Models\SunAccountReview;
use App\Models\SunImportBatch;
use App\Models\SunLedgerLine;
use App\Services\AuditService;
use App\Services\Sun\FeeCategoryService;
use App\Services\Sun\SunAccountService;
use App\Services\Sun\SunLedgerImporter;
use App\Services\Sun\SunLinks;
use App\Services\Sun\SunPeriods;
use App\Services\Sun\SunScheduleCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The Treasurer's Sun ledger screens (sdp_doc/YASIS_Sun_Finance_Import.md §11, gitignored):
 * Ledger Import (upload, summary, publish, revert), Ledger Review (Validate & Match for the
 * ledger), Ledger Accounts (an account's lines and overrides) and Fee Categories.
 */
class SunLedgerController extends Controller
{
    private const LIST_LIMIT = 100;

    public function index(SunLedgerImporter $importer)
    {
        $importer->purgeExpired();

        return view('treasurer.ledger.index', [
            'batches' => SunImportBatch::with(['uploadedBy', 'publishedBy'])->orderByDesc('id')->get(),
        ]);
    }

    /** Parse the upload and show what an import would do. Nothing is saved yet. */
    public function upload(UploadSunListingRequest $request, SunLedgerImporter $importer)
    {
        $staged = $importer->stage($request->file('file'), $request->user());

        return view('treasurer.ledger.summary', $staged + ['fileName' => $request->file('file')->getClientOriginalName()]);
    }

    public function import(Request $request, SunLedgerImporter $importer)
    {
        $data = $request->validate(['token' => ['required', 'string'], 'file_name' => ['required', 'string', 'max:255']]);
        $batch = $importer->import($data['token'], $data['file_name'], $request->user());

        return redirect()->route('treasurer.ledger.review', ['year' => $batch->fiscal_year])
            ->with('status', "Imported {$batch->yearLabel()}: {$batch->new_lines} new, {$batch->updated_lines} updated, {$batch->missing_lines} missing from this file. It isn't published yet.");
    }

    public function cancel(Request $request, SunLedgerImporter $importer)
    {
        $importer->cancel((string) $request->input('token'), $request->user());

        return redirect()->route('treasurer.ledger.index')->with('status', 'Upload cancelled and the file deleted.');
    }

    public function publish(Request $request, SunImportBatch $batch, SunLedgerImporter $importer)
    {
        $importer->publish($batch, $request->routeIs('*.unpublish') === false, $request->user());

        return back()->with('status', $batch->fresh()->isPublished() ? 'Import published.' : 'Import unpublished.');
    }

    public function revert(Request $request, SunImportBatch $batch, SunLedgerImporter $importer)
    {
        $importer->revert($batch, $request->user());

        return back()->with('status', "Import #{$batch->id} reverted: its new lines are removed and the lines it changed are restored.");
    }

    /** The stored file: the Treasurer and the Admin only (§0). */
    public function download(Request $request, SunImportBatch $batch, AuditService $audit)
    {
        abort_unless($batch->stored_path && Storage::disk(SunLedgerImporter::DISK)->exists($batch->stored_path), 404);
        $audit->log($request->user(), 'Downloaded Sun ledger file', 'SunImportBatch', $batch->id);

        return Storage::disk(SunLedgerImporter::DISK)->download($batch->stored_path, $batch->source_file, ['Content-Type' => 'text/plain; charset=ISO-8859-1']);
    }

    // --- Review (Validate & Match for the ledger) ---------------------------------------------------------

    public function review(Request $request, SunAccountService $accounts, SunScheduleCheck $check)
    {
        $years = SunImportBatch::current()->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year');
        $fy = (int) ($years->contains($request->integer('year')) ? $request->integer('year') : $years->first());

        $data = ['years' => $years, 'fy' => $fy];
        if ($fy) {
            $balances = SunAccountBalance::current()->where('fiscal_year', $fy);
            $unmatched = $accounts->unmatchedQuery($fy)->orderBy('code')->paginate(25, ['*'], 'unmatched')->withQueryString();
            $previous = SunAccountBalance::current()->where('fiscal_year', $fy - 1)->get()->keyBy('sun_account_id');
            $thisYear = (clone $balances)->with('account')->get();
            $lines = fn () => SunLedgerLine::current()->where('fiscal_year', $fy)->with('account')->orderBy('sun_account_code')->orderBy('period');
            $latest = SunImportBatch::current()->where('fiscal_year', $fy)->latest('id')->first();
            $reviews = SunAccountReview::with('account')->where('fiscal_year', $fy)->orderByRaw("status = 'pending' DESC")->orderBy('sun_account_id')->get();
            $reviewLinks = SunAccountLink::with('student')->covering($fy)->whereIn('sun_account_id', $reviews->pluck('sun_account_id'))->get()->keyBy('sun_account_id');

            $data += [
                'unmatched' => $unmatched,
                'unmatchedClosing' => SunAccountBalance::current()->where('fiscal_year', $fy)->whereIn('sun_account_id', $unmatched->pluck('id'))->pluck('closing_balance', 'sun_account_id'),
                'suggestions' => $unmatched->mapWithKeys(fn ($a) => [$a->id => $accounts->suggestions($a)]),
                'reviews' => $reviews,
                'reviewLinks' => $reviewLinks,
                'reviewClosing' => $thisYear->pluck('closing_balance', 'sun_account_id'),
                'reviewSuggestions' => $reviews->where('status', SunAccountReview::PENDING)
                    ->mapWithKeys(fn ($r) => [$r->id => $accounts->suggestions($r->account, name: $r->printed_name)]),
                'unclassified' => $lines()->where('category', 'unclassified')->limit(self::LIST_LIMIT)->get(),
                'unclassifiedCount' => SunLedgerLine::current()->where('fiscal_year', $fy)->where('category', 'unclassified')->count(),
                'suspect' => $lines()->where('date_suspect', true)->limit(self::LIST_LIMIT)->get(),
                'suspectCount' => SunLedgerLine::current()->where('fiscal_year', $fy)->where('date_suspect', true)->count(),
                'missing' => $lines()->where('missing_from_latest', true)->limit(self::LIST_LIMIT)->get(),
                'changed' => SunLedgerLine::current()->whereIn('id', $latest?->warnings['changed_line_ids'] ?? [])->with('account')->get(),
                'openingDiffers' => $thisYear->filter(fn ($b) => isset($previous[$b->sun_account_id]) && (string) $previous[$b->sun_account_id]->closing_balance !== (string) $b->opening_balance)
                    ->map(fn ($b) => ['balance' => $b, 'previous_closing' => (string) $previous[$b->sun_account_id]->closing_balance])->values(),
                'schedule' => $check->forYear($fy),
                'control' => $thisYear->firstWhere('is_control', true),
            ];
        }

        return view('treasurer.ledger.review', $data);
    }

    /** T3: the same student under a corrected name. */
    public function reviewSame(Request $request, SunAccountReview $review, SunAccountService $service)
    {
        $service->confirmSameStudent($review, $request->user());

        return back()->with('status', "Account {$review->account->code}: same student. Its {$review->fiscal_year} lines are matched again.");
    }

    /** T3: the code now belongs to another student. */
    public function reviewRelink(LinkSunAccountRequest $request, SunAccountReview $review, SunAccountService $service)
    {
        $student = Student::where('student_id_number', $request->validated('student_id_number'))->firstOrFail();
        $service->relink($review, $student, $request->user());

        return back()->with('status', "Account {$review->account->code} is now linked to {$student->name} from ".SunPeriods::label($review->fiscal_year).'.');
    }

    // --- Accounts ---------------------------------------------------------------------------------------

    public function accounts(Request $request)
    {
        $years = SunAccountBalance::current()->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year');
        $fy = (int) ($years->contains($request->integer('year')) ? $request->integer('year') : $years->first());
        $search = trim((string) $request->input('search'));

        $matchingStudents = $search === '' ? collect() : Student::where('name', 'like', "%{$search}%")->orWhere('student_id_number', 'like', "%{$search}%")->pluck('id');
        $linkedAccounts = $search === '' ? collect() : SunAccountLink::covering($fy)->whereIn('student_id', $matchingStudents)->pluck('sun_account_id');
        $balances = SunAccountBalance::current()->where('fiscal_year', $fy)->with('account')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereHas('account', fn ($a) => $a->where('code', 'like', "%{$search}%")
                ->orWhere('name_as_printed', 'like', "%{$search}%"))->orWhereIn('sun_account_balances.sun_account_id', $linkedAccounts)))
            ->join('sun_accounts', 'sun_accounts.id', '=', 'sun_account_balances.sun_account_id')
            ->orderBy('sun_accounts.code')->select('sun_account_balances.*')
            ->paginate(50)->withQueryString();

        $owners = $fy ? app(SunLinks::class)->forYear($fy, $balances->pluck('sun_account_id')) : [];

        return view('treasurer.ledger.accounts', [
            'years' => $years, 'fy' => $fy, 'balances' => $balances, 'search' => $search,
            'students' => Student::whereIn('id', array_values($owners))->get()->keyBy('id'), 'owners' => $owners,
            'held' => $fy ? app(SunLinks::class)->held($fy) : [],
        ]);
    }

    public function account(Request $request, SunAccount $account, SunAccountService $service)
    {
        $years = $account->balances()->current()->orderByDesc('fiscal_year')->pluck('fiscal_year');
        $fy = (int) ($years->contains($request->integer('year')) ? $request->integer('year') : $years->first());

        $owner = $fy ? app(SunLinks::class)->studentFor($account->id, $fy) : null;
        // §3.8 access logging: who opened a student's financial record.
        $studentId = $owner ?? $account->links()->whereNull('voided_at')->orderByDesc('from_fiscal_year')->value('student_id');
        if ($studentId) {
            app(AuditService::class)->log($request->user(), 'Viewed student financial record', 'Student', $studentId, ['account' => $account->code]);
        }

        return view('treasurer.ledger.account', [
            'account' => $account,
            'links' => $account->links()->with('student')->orderBy('from_fiscal_year')->orderBy('id')->get(),
            'openLink' => $account->openLink(),
            'owner' => $owner ? Student::find($owner) : null,
            'review' => $fy ? $account->reviews()->pending()->where('fiscal_year', $fy)->first() : null,
            'years' => $years,
            'fy' => $fy,
            'balance' => $account->balances()->current()->where('fiscal_year', $fy)->first(),
            'lines' => $account->lines()->current()->where('fiscal_year', $fy)->orderBy('period')->orderBy('id')->get(),
            'categories' => FeeCategory::orderBy('position')->pluck('label', 'key'),
            'suggestions' => $account->openLink() || $account->is_control ? [] : $service->suggestions($account),
        ]);
    }

    public function link(LinkSunAccountRequest $request, SunAccount $account, SunAccountService $service)
    {
        $student = Student::where('student_id_number', $request->validated('student_id_number'))->firstOrFail();
        $service->link($account, $student, $request->user());

        return back()->with('status', "Account {$account->code} linked to {$student->name}.");
    }

    public function unlink(Request $request, SunAccount $account, SunAccountService $service)
    {
        $service->unlink($account, $request->user());

        return back()->with('status', "Account {$account->code} unlinked.");
    }

    public function notIsms(Request $request, SunAccount $account, SunAccountService $service)
    {
        $service->markNotIsms($account, $request->boolean('not_isms'), $request->user());

        return back()->with('status', $request->boolean('not_isms') ? "Account {$account->code} marked as not an ISMS student." : "Account {$account->code} is back in the unmatched list.");
    }

    public function line(SetLedgerLineRequest $request, SunLedgerLine $line, SunAccountService $service)
    {
        $data = $request->validated();
        isset($data['category'])
            ? $service->setCategory($line, $data['category'], $request->user())
            : $service->setRestricted($line, (bool) $data['restricted'], $request->user());

        return back()->with('status', 'Line updated. The change is kept when the ledger is imported again.');
    }

    // --- Categories ---------------------------------------------------------------------------------------

    public function categories()
    {
        return view('treasurer.ledger.categories', [
            'categories' => FeeCategory::with('rules')->orderBy('position')->get(),
            'counts' => SunLedgerLine::current()->selectRaw('category, COUNT(*) AS n')->groupBy('category')->pluck('n', 'category'),
        ]);
    }

    public function saveCategories(SaveFeeCategoriesRequest $request, FeeCategoryService $service)
    {
        $data = $request->validated();
        $count = $service->save($data['restricted'] ?? [], $data['rules'] ?? [], $data['new'] ?? [], $request->user());

        return back()->with('status', "Category rules saved ({$count} ".str('change')->plural($count).'). Re-apply them to recategorise imported lines.');
    }

    public function reapply(Request $request, FeeCategoryService $service)
    {
        $changed = $service->reapply($request->user());

        return back()->with('status', "Rules re-applied: {$changed} ".str('line')->plural($changed).' changed. Lines you set by hand were left as they are.');
    }
}
