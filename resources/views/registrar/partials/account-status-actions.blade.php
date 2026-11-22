{{--
    Account actions for a guardian or student portal account, shown next to the portal status
    badge. The server re-checks every action: guardian and student accounts only, so a request
    naming a staff account (or a guardian who also holds a staff role) is refused with 403.

      Pending  → Resend invite · Activate · Deactivate
      Active   → Deactivate
      Inactive → Reactivate, or a short reason when it can never be reactivated
--}}
@inject('accounts', 'App\Services\UserAccountService')
@php($blocker = $accounts->reactivationBlocker($account))
<span class="inline-flex flex-wrap items-center gap-x-3 gap-y-1 ml-2 align-middle">
    @if ($account->status === 'Pending' && ! $blocker)
        <form method="POST" action="{{ route('registrar.accounts.resend-invite', $account) }}" class="inline">
            @csrf
            <button type="submit" class="text-xs font-semibold text-info hover:underline">Resend invite</button>
        </form>
        <form method="POST" action="{{ route('registrar.accounts.activate', $account) }}" class="inline"
              data-confirm="Activate {{ $account->name }}'s portal account now? A temporary password will be shown once, on the next page. They must change it at first sign-in."
              onsubmit="return confirm(this.dataset.confirm);">
            @csrf
            <button type="submit" class="text-xs font-semibold text-brand hover:underline">Activate</button>
        </form>
    @endif

    @if (in_array($account->status, ['Active', 'Pending'], true))
        <form method="POST" action="{{ route('registrar.accounts.deactivate', $account) }}" class="inline"
              data-confirm="Deactivate the portal account for {{ $account->name }}? They will no longer be able to sign in. The account and its records are kept, and it can be reactivated."
              onsubmit="return confirm(this.dataset.confirm);">
            @csrf
            <button type="submit" class="text-xs font-semibold text-danger hover:underline">Deactivate portal account</button>
        </form>
    @elseif ($account->status === 'Inactive' && $blocker)
        <span class="text-xs text-neutral-500" title="{{ $blocker === 'Erased account' ? 'Erased under a data-erasure request; it cannot be reactivated.' : 'No portal login exists for this record.' }}">{{ $blocker }}: can't be reactivated</span>
    @elseif ($account->status === 'Inactive')
        <form method="POST" action="{{ route('registrar.accounts.reactivate', $account) }}" class="inline">
            @csrf
            <button type="submit" class="text-xs font-semibold text-brand hover:underline">Reactivate portal account</button>
        </form>
    @endif
</span>
