<?php

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * An account change that is within the actor's authority but breaks a business rule —
 * deactivating your own account, removing the last active Admin, "reactivating" an account
 * that never finished its invite. Rendered as a warning on the page the request came from,
 * not as an error page: nothing was changed and the person just needs to know why.
 *
 * Out-of-scope changes (a Registrar touching a staff account) are not this — those are
 * authorization failures and return 403.
 */
class AccountChangeRefused extends RuntimeException
{
    public function render(): RedirectResponse
    {
        return back()->with('warning', $this->getMessage());
    }
}
