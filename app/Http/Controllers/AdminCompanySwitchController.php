<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminCompanySwitchController extends Controller
{
    public function __invoke(Request $request, CurrentCompany $currentCompany): RedirectResponse
    {
        abort_unless($currentCompany->canSwitchCompany($request->user()), 403);

        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $currentCompany->set((int) $data['company_id']);

        return back();
    }
}
