<?php

namespace Modules\StoreManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\StoreManagement\Lib\StoreTeam;

/** The owner's team page: staff logins, their role and permissions, passwords, removal, who did what. */
class PartnerTeamController extends Controller
{
    public function index(Request $request): View
    {
        $store = $request->attributes->get('partnerStore');
        return view('storemanagement::partner.team', [
            'members' => StoreTeam::members($store),
            'activity' => StoreTeam::activity($store),
            'permissions' => StoreTeam::PERMISSIONS,
            'roles' => StoreTeam::ROLES,
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:80',
            'email' => 'required|email|max:120',
            'password' => 'required|string|min:8|max:100',
            'role' => ['required', Rule::in(array_keys(StoreTeam::ROLES))],
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(StoreTeam::PERMISSIONS)],
        ]);
        StoreTeam::add($request->attributes->get('partnerStore'), $data);
        return back()->with('toast', translate(key: '{name} can now sign in with {email}.', replace: ['name' => $data['name'], 'email' => strtolower(trim($data['email']))]));
    }

    public function update(Request $request, string $staff): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(StoreTeam::ROLES))],
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(StoreTeam::PERMISSIONS)],
        ]);
        StoreTeam::update($request->attributes->get('partnerStore'), $staff, $data['role'], $data['permissions'] ?? []);
        return back()->with('toast', translate('Saved'));
    }

    public function password(Request $request, string $staff): RedirectResponse
    {
        $data = $request->validate(['password' => 'required|string|min:8|max:100']);
        StoreTeam::password($request->attributes->get('partnerStore'), $staff, $data['password']);
        return back()->with('toast', translate('The new password is set. Give it to them in person.'));
    }

    public function remove(Request $request, string $staff): RedirectResponse
    {
        StoreTeam::remove($request->attributes->get('partnerStore'), $staff);
        return back()->with('toast', translate('Removed. Their login no longer works.'));
    }
}
