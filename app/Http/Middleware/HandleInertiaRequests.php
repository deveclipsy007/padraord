<?php

namespace App\Http\Middleware;

use App\AI\AiConfiguration;
use App\Models\Opportunity;
use App\Services\CaseWorkspaceSummary;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'isAdmin' => $request->user()->isAdmin(),
                    'workspaceFocus' => $request->user()->workspace_focus ?? ($request->user()->isAdmin() ? 'management' : 'production'),
                    'canApproveCommercial' => $request->user()->can_approve_commercial,
                ] : null,
            ],
            'ai' => collect(app(AiConfiguration::class)->publicState())->only(['mode', 'status'])->all(),
            'caseShell' => function () use ($request) {
                $case = $request->route('opportunity');
                if (! $request->user() || ! $case) {
                    return null;
                }
                if (! $case instanceof Opportunity) {
                    $case = Opportunity::find($case);
                }

                return $case ? app(CaseWorkspaceSummary::class)->for($case) : null;
            },
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
