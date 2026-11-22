<?php

namespace Modules\AdminModule\Http\Controllers\Web\Admin;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\AdminModule\Lib\BusinessPulse;
use Modules\AdminModule\Lib\SettingsCatalog;

/** The Settings center, and the business overview block (refreshed on the dashboard every minute). */
class SettingsCenterController extends Controller
{
    public function index(): View
    {
        return view('adminmodule::settings-center', ['groups' => SettingsCatalog::forViewer()]);
    }

    /** The overview and attention list, as HTML for the dashboard to swap in. */
    public function pulse(): Response
    {
        return response()->view('adminmodule::partials._pulse', [
            'pulse' => BusinessPulse::snapshot(),
            'attention' => BusinessPulse::attention(),
        ])->header('Cache-Control', 'no-store');
    }
}
