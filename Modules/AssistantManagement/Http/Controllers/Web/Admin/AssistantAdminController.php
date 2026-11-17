<?php

namespace Modules\AssistantManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Modules\AssistantManagement\Entities\AssistantEntry;
use Modules\AssistantManagement\Entities\AssistantQuestion;
use Modules\AssistantManagement\Lib\AssistantSettings;
use Modules\AssistantManagement\Service\AssistantService;

/** Admin > Ask E-Ride: switch, providers in order, limits, the knowledge pack and what customers ask. */
class AssistantAdminController extends Controller
{
    public function __construct(protected AssistantService $assistant)
    {
    }

    public function index(Request $request): View
    {
        $settings = AssistantSettings::all();
        foreach ($settings['providers'] as &$p) {
            $p['has_key'] = $p['key'] !== '';
            $p['skipped'] = Cache::get('assistant_skip_' . $p['name']);
            unset($p['key']);
        }
        unset($p);
        $filter = (string)$request->query('show', 'all');
        $questions = AssistantQuestion::query()
            ->when($filter === 'unhelpful', fn($q) => $q->where('helped', false))
            ->when($filter === 'unanswered', fn($q) => $q->whereIn('status', [AssistantQuestion::NO_ANSWER, AssistantQuestion::BUSY]))
            ->latest()->paginate(25)->appends(['show' => $filter]);
        $today = AssistantQuestion::where('created_at', '>=', now()->startOfDay());
        $stats = [
            'today' => (clone $today)->count(),
            'answered' => (clone $today)->where('status', AssistantQuestion::ANSWERED)->count(),
            'unhelpful' => (clone $today)->where('helped', false)->count(),
            'by_provider' => (clone $today)->whereNotNull('provider')->selectRaw('provider, count(*) as n')->groupBy('provider')->pluck('n', 'provider')->all(),
        ];
        $entries = AssistantEntry::orderBy('sort')->get();
        return view('assistantmanagement::admin.index', compact('settings', 'questions', 'stats', 'entries', 'filter'));
    }

    public function settings(Request $request): RedirectResponse
    {
        $names = array_keys(AssistantSettings::PROVIDERS);
        $data = $request->validate([
            'per_user_daily' => 'required|integer|min:1|max:200',
            'app_daily' => 'required|integer|min:10|max:100000',
            'first' => 'required|in:' . implode(',', $names),
            'model' => 'array',
            'model.*' => 'nullable|string|max:80',
            'key' => 'array',
            'key.*' => 'nullable|string|max:300',
        ]);
        $type = AssistantSettings::TYPE;
        updateBusinessSetting(keyName: 'assistant_enabled', settingType: $type, value: $request->boolean('enabled') ? '1' : '0');
        updateBusinessSetting(keyName: 'assistant_per_user_daily', settingType: $type, value: (string)$data['per_user_daily']);
        updateBusinessSetting(keyName: 'assistant_app_daily', settingType: $type, value: (string)$data['app_daily']);
        $order = array_merge([$data['first']], array_values(array_diff($names, [$data['first']])));
        updateBusinessSetting(keyName: 'assistant_order', settingType: $type, value: implode(',', $order));
        foreach ($names as $name) {
            updateBusinessSetting(keyName: "assistant_{$name}_enabled", settingType: $type, value: $request->boolean("on.$name") ? '1' : '0');
            updateBusinessSetting(keyName: "assistant_{$name}_model", settingType: $type, value: trim((string)($data['model'][$name] ?? '')));
            $key = trim((string)($data['key'][$name] ?? ''));
            // A blank key field keeps the saved key; "Remove key" clears it.
            if ($request->boolean("clear.$name")) {
                updateBusinessSetting(keyName: "assistant_{$name}_key", settingType: $type, value: '');
            } elseif ($key !== '') {
                updateBusinessSetting(keyName: "assistant_{$name}_key", settingType: $type, value: AssistantSettings::encrypt($key));
                Cache::forget('assistant_skip_' . $name);
            }
        }
        Toastr::success(translate('Ask E-Ride settings saved'));
        return back();
    }

    public function test(string $provider): RedirectResponse
    {
        abort_unless(isset(AssistantSettings::PROVIDERS[$provider]), 404);
        $result = $this->assistant->test($provider);
        $label = AssistantSettings::PROVIDERS[$provider]['label'];
        $result['ok'] ? Toastr::success($label . ': ' . $result['message']) : Toastr::error($label . ': ' . $result['message']);
        return back();
    }

    public function saveEntry(Request $request, ?AssistantEntry $entry = null): RedirectResponse
    {
        $data = $request->validate([
            'help_key' => 'nullable|string|max:40|alpha_dash|unique:assistant_entries,help_key' . ($entry?->id ? ',' . $entry->id : ''),
            'service' => 'nullable|string|max:40',
            'title_en' => 'required|string|max:120',
            'title_fr' => 'required|string|max:120',
            'body_en' => 'required|string|max:2000',
            'body_fr' => 'required|string|max:2000',
            'sort' => 'nullable|integer',
        ]);
        $entry = $entry ?? new AssistantEntry();
        $entry->fill($data + ['is_active' => $request->boolean('is_active', true)]);
        $entry->help_key = filled($data['help_key'] ?? null) ? $data['help_key'] : null;
        $entry->save();
        Toastr::success(translate('Knowledge entry saved'));
        return back();
    }

    public function deleteEntry(AssistantEntry $entry): RedirectResponse
    {
        $entry->delete();
        Toastr::success(translate('Knowledge entry removed'));
        return back();
    }
}
