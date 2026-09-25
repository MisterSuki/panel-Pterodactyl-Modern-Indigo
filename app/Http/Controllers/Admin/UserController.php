<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Model;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Translation\Translator;
use Pterodactyl\Services\Admin\UserLiveService;
use Pterodactyl\Services\Users\UserUpdateService;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Services\Users\UserCreationService;
use Pterodactyl\Services\Users\UserDeletionService;
use Pterodactyl\Http\Requests\Admin\UserFormRequest;
use Pterodactyl\Http\Requests\Admin\NewUserFormRequest;
use Pterodactyl\Contracts\Repository\UserRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class UserController extends Controller
{
    use AvailableLanguages;

    /**
     * UserController constructor.
     */
    public function __construct(
        protected AlertsMessageBag $alert,
        protected UserCreationService $creationService,
        protected UserDeletionService $deletionService,
        protected Translator $translator,
        protected UserUpdateService $updateService,
        protected UserRepositoryInterface $repository,
        protected ViewFactory $view,
    ) {
    }

    /**
     * Display user index page.
     */
    public function index(Request $request): View
    {
        $users = QueryBuilder::for(
            User::query()->with('adminRole')->select('users.*')
                ->selectRaw('COUNT(DISTINCT(subusers.id)) as subuser_of_count')
                ->selectRaw('COUNT(DISTINCT(servers.id)) as servers_count')
                ->leftJoin('subusers', 'subusers.user_id', '=', 'users.id')
                ->leftJoin('servers', 'servers.owner_id', '=', 'users.id')
                ->groupBy('users.id')
        )
            ->allowedFilters(['username', 'email', 'uuid'])
            ->defaultSort('-root_admin')
            ->allowedSorts(['id', 'uuid'])
            ->paginate(50);

        return view('admin.users.index', ['users' => $users]);
    }

    /**
     * Display new user page.
     */
    public function create(): View
    {
        return view('admin.users.new', [
            'languages' => $this->getAvailableLanguages(true),
            'roles' => AdminRole::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Display user view page.
     */
    public function view(User $user): View
    {
        return view('admin.users.view', [
            'user' => $user,
            'live' => app(UserLiveService::class)->build($user),
            'languages' => $this->getAvailableLanguages(true),
            'roles' => AdminRole::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Delete a user from the system.
     *
     * @throws \Exception
     * @throws DisplayException
     */
    public function delete(Request $request, User $user): RedirectResponse
    {
        $this->assertCanChange($request, $user);

        if ($request->user()->is($user)) {
            throw new DisplayException(__('admin/user.exceptions.delete_self'));
        }

        $this->deletionService->handle($user);

        return redirect()->route('admin.users');
    }

    /**
     * Create a user.
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function store(NewUserFormRequest $request): RedirectResponse
    {
        $data = $request->normalize();
        $this->assertCannotGrantAccess($request, $data);

        $user = $this->creationService->handle($data);
        $this->alert->success($this->translator->get('admin/user.notices.account_created'))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    /**
     * Update a user on the system.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(UserFormRequest $request, User $user): RedirectResponse
    {
        $this->assertCanChange($request, $user);

        $data = $request->normalize();
        $this->assertCannotGrantAccess($request, $data);
        if (!$request->user()->root_admin) {
            unset($data['root_admin'], $data['admin_role_id']);
        }

        $this->updateService
            ->setUserLevel(User::USER_LEVEL_ADMIN)
            ->handle($user, $data);

        $this->alert->success(trans('admin/user.notices.account_updated'))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    /**
     * Get a JSON response of users on the system.
     */
    public function json(Request $request): Model|Collection
    {
        $users = QueryBuilder::for(User::query())->allowedFilters(['email'])->paginate(25);

        // Handle single user requests.
        if ($request->query('user_id')) {
            $user = User::query()->findOrFail($request->input('user_id'));
            // @phpstan-ignore-next-line property.notFound
            $user->avatar_url = $user->avatarUrl();

            return $user;
        }

        return $users->map(function ($item) {
            // @phpstan-ignore-next-line property.notFound
            $item->avatar_url = $item->avatarUrl();

            return $item;
        });
    }

    /**
     * Staff can only change regular users. Administrators and other staff are off limits,
     * otherwise changing someone's email or password would be a way to take over their access.
     *
     * @throws AccessDeniedHttpException
     */
    private function assertCanChange(Request $request, User $target): void
    {
        if (!$request->user()->root_admin && $target->isStaff()) {
            throw new AccessDeniedHttpException('Only administrators can change administrators or other staff.');
        }
    }

    /**
     * Only administrators can make someone an administrator or give them a staff role.
     *
     * @throws AccessDeniedHttpException
     */
    private function assertCannotGrantAccess(Request $request, array $data): void
    {
        if ($request->user()->root_admin) {
            return;
        }

        if (!empty($data['root_admin']) || !empty($data['admin_role_id'])) {
            throw new AccessDeniedHttpException('Only administrators can grant administrator access or a staff role.');
        }
    }
}
