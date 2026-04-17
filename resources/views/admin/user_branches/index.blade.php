@extends('admin.layouts.master')
@section('title', 'Branch Assignments')
@section('content')

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="d-flex flex-column-fluid">
        <div class="container">
            <div class="card card-custom gutter-b">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">Management Dashboard — Branch Assignments</h3>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Users with <strong>zero</strong> branch rows see all branches (company-wide view).
                        Assign one or more branches to restrict a user to a regional subset.
                    </p>

                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Branches</th>
                                <th class="text-right">Scope</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                                @php $assigned = $assignments->get($user->id, collect())->toArray(); @endphp
                                <tr data-user-id="{{ $user->id }}">
                                    <td>
                                        <strong>{{ $user->name }}</strong><br>
                                        <small class="text-muted">{{ $user->email }}</small>
                                    </td>
                                    <td class="ub-branches-cell">
                                        @foreach($branches as $branch)
                                            <label class="mr-3">
                                                <input type="checkbox"
                                                       class="ub-branch-check"
                                                       data-user-id="{{ $user->id }}"
                                                       value="{{ $branch->id }}"
                                                       @if(in_array($branch->id, $assigned, true)) checked @endif>
                                                {{ $branch->name }}
                                            </label>
                                        @endforeach
                                    </td>
                                    <td class="text-right">
                                        <span class="ub-scope-label badge badge-{{ empty($assigned) ? 'primary' : 'secondary' }}">
                                            {{ empty($assigned) ? 'All branches' : count($assigned) . ' selected' }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <button type="button" class="btn btn-sm btn-primary ub-save" data-user-id="{{ $user->id }}">
                                            Save
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
(function () {
    'use strict';

    const endpoint = @json(url('/api/user-branches'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    document.querySelectorAll('.ub-save').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const userId = btn.dataset.userId;
            const checks = document.querySelectorAll('.ub-branch-check[data-user-id="' + userId + '"]:checked');
            const ids = Array.from(checks).map((c) => Number(c.value));

            btn.disabled = true;
            btn.textContent = 'Saving…';

            try {
                const resp = await fetch(endpoint + '/' + userId, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ branch_ids: ids, _method: 'PUT' }),
                });
                const json = await resp.json();
                if (!resp.ok) throw new Error(json.message || 'Failed');

                const row = document.querySelector('tr[data-user-id="' + userId + '"]');
                const label = row.querySelector('.ub-scope-label');
                if (ids.length === 0) {
                    label.textContent = 'All branches';
                    label.className = 'ub-scope-label badge badge-primary';
                } else {
                    label.textContent = ids.length + ' selected';
                    label.className = 'ub-scope-label badge badge-secondary';
                }
                btn.textContent = 'Saved ✓';
                setTimeout(() => { btn.textContent = 'Save'; btn.disabled = false; }, 1500);
            } catch (e) {
                btn.textContent = 'Error — retry';
                btn.disabled = false;
                console.error(e);
            }
        });
    });
})();
</script>
@endpush
@endsection
