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
        $this->accounts->reactivate($request->user(), $user);

        return back()->with('status', "{$user->name}'s portal account reactivated.");
    }
}
