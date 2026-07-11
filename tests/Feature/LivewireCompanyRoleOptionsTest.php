<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Http\Middleware\SetPermissionCompany;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentCompany;
use Filament\Support\Services\RelationshipJoiner;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use ReflectionMethod;
use Tests\TestCase;

class LivewireCompanyRoleOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_company_middleware_is_persistent_for_livewire_requests(): void
    {
        $this->assertContains(
            SetPermissionCompany::class,
            app(PersistentMiddleware::class)->getPersistentMiddleware(),
        );
    }

    public function test_livewire_role_options_follow_the_selected_company_when_it_changes(): void
    {
        $firstCompany = Company::factory()->create();
        $secondCompany = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $firstCompany->id,
            'role' => UserRole::Admin,
        ]);
        $user->companies()->attach([$firstCompany->id, $secondCompany->id]);

        $firstRole = $this->createRole($firstCompany, 'first_accountant');
        $secondRole = $this->createRole($secondCompany, 'second_accountant');

        $this->actingAs($user);
        request()->setLaravelSession(app('session')->driver());

        session()->put(CurrentCompany::SESSION_KEY, $firstCompany->id);
        setPermissionsTeamId(null);
        $this->runPersistentCompanyMiddleware();

        $this->assertSame($firstCompany->id, getPermissionsTeamId());
        $this->assertSame([$firstRole->id], $this->roleOptionIds());

        session()->put(CurrentCompany::SESSION_KEY, $secondCompany->id);
        setPermissionsTeamId(null);
        $this->runPersistentCompanyMiddleware();

        $this->assertSame($secondCompany->id, getPermissionsTeamId());
        $this->assertSame([$secondRole->id], $this->roleOptionIds());
    }

    private function runPersistentCompanyMiddleware(): void
    {
        $request = Request::create('/livewire/update', 'POST');
        $request->setLaravelSession(app('session')->driver());
        $request->setUserResolver(fn (): ?User => auth()->user());

        app(SetPermissionCompany::class)->handle(
            $request,
            static fn () => response()->noContent(),
        );
    }

    /** @return list<int> */
    private function roleOptionIds(): array
    {
        $relationship = Relation::noConstraints(fn () => (new User())->roles());
        $query = app(RelationshipJoiner::class)->prepareQueryForNoConstraints($relationship);
        $method = new ReflectionMethod(UserResource::class, 'roleOptionsQuery');
        $query = $method->invoke(null, $query);

        return $query->orderBy('roles.id')->pluck('roles.id')->all();
    }

    private function createRole(Company $company, string $name): Role
    {
        return Role::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => $name,
            'guard_name' => 'web',
        ]);
    }
}
