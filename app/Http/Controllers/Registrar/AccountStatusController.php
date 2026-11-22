<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\Request;

/**
 * Registrar → deactivate / reactivate guardian and student portal accounts.
 *
 * Bound to the User (not the Guardian or Student record) on purpose: the role scope is then
 * decided by {@see UserAccountService::assertCanManage()} for whichever account id is sent,
 * so a hand-crafted request naming a staff account is refused with 403.
 */
class AccountStatusController extends Controller
{
    public function __construct(private UserAccountService $accounts) {}

    public function deactivate(Request $request, User $user)
    {
        $this->accounts->deactivate($request->user(), $user);

        return back()->with('status', "{$user->name}'s portal account deactivated. They can no longer sign in.");
    }

    public function reactivate(Request $request, User $user)
    {
        $user = $this->accounts->reactivate($request->user(), $user);

        return back()->with('status', $user->status === 'Pending'
            ? "{$user->name}'s portal account reactivated. They had never set a password, so it is Pending again and the setup link was e-mailed to {$user->email}."
            : "{$user->name}'s portal account reactivated.");
    }

    public function resendInvite(Request $request, User $user)
    {
        $this->accounts->resendInvite($request->user(), $user);

        return back()->with('status', "Account-setup link re-sent to {$user->email}.");
    }

    /**
     * The temporary password is shown on this response only: not redirected, not flashed (so not
     * in the session), and no-store. A refresh re-sends the POST, which is refused.
     */
    public function activate(Request $request, User $user)
    {
        $temporary = $this->accounts->activate($request->user(), $user);

        return response()
            ->view('accounts.activated', [
                'account' => $user->fresh(),
                'temporaryPassword' => $temporary,
                'layoutRole' => 'registrar',
                'backUrl' => url()->previous(route('registrar.dashboard')),
            ])
            ->header('Cache-Control', 'no-store, private, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
