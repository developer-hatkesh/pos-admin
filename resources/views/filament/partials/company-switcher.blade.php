@php
    $currentCompany = app(\App\Support\CurrentCompany::class);
    $companies = \App\Models\Company::query()->orderBy('name')->get(['id', 'name']);
    $selectedCompanyId = $currentCompany->id();
@endphp

@if ($currentCompany->canSwitchCompany() && $companies->count() > 1)
    <form method="POST" action="{{ route('admin.switch-company') }}" class="flux-company-switcher">
        @csrf
        <label>
            <span class="sr-only">Company</span>
            <select name="company_id" onchange="this.form.submit()">
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected((int) $selectedCompanyId === (int) $company->id)>
                        {{ $company->name }}
                    </option>
                @endforeach
            </select>
        </label>
    </form>
@endif
