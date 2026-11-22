{{--
    Deactivate / reactivate a guardian or student portal account. Shown inline next to the
    portal status badge. The server re-checks that $account is a guardian/student account.
--}}
@if (in_array($account->status, ['Active', 'Pending'], true))
    <form method="POST" action="{{ route('registrar.accounts.deactivate', $account) }}" class="inline-block ml-2"
          data-confirm="Deactivate the portal account for {{ $account->name }}? They will no longer be able to sign in. The account and its records are kept, and it can be reactivated."
          onsubmit="return confirm(this.dataset.confirm);">
        @csrf
        <button type="submit" class="text-xs font-semibold text-danger hover:underline">Deactivate portal account</button>
    </form>
@elseif ($account->status === 'Inactive')
    <form method="POST" action="{{ route('registrar.accounts.reactivate', $account) }}" class="inline-block ml-2">
        @csrf
        <button type="submit" class="text-xs font-semibold text-brand hover:underline">Reactivate portal account</button>
    </form>
@endif
