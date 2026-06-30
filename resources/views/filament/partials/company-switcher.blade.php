@php
    $placement = $placement ?? 'sidebar';
    $currentCompany = app(\App\Support\CurrentCompany::class);
    $companies = $currentCompany->companiesFor();
    $selectedCompanyId = $currentCompany->id();
    $selectedCompanyName = $companies->firstWhere('id', $selectedCompanyId)?->name ?? 'Company';
    $canSwitchCompany = $companies->count() > 1;
    $class = 'flux-company-switcher flux-company-switcher--'.$placement;
@endphp

@if ($canSwitchCompany)
    <form method="POST" action="{{ route('admin.switch-company') }}" class="{{ $class }}">
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
@else
    <div class="{{ $class }} flux-company-switcher--static">
        {{ $selectedCompanyName }}
    </div>
@endif
