<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireLedgerReview;
use App\Ledger\Models\JournalEntry;
use App\Ledger\Services\Preview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/**
 * The review of any form marked data-review (paxhitestbed ReviewController).
 *
 * The form's own request is replayed through the HTTP kernel inside a
 * transaction that is always rolled back, with mail, notifications, queued
 * jobs and outgoing HTTP faked, and the user's session put back as it was.
 * The dialog shows the entries it wrote and what it would have said. Because
 * it runs the real code, the preview cannot drift from the real rules.
 *
 * Only routes carrying the ledger.review middleware can be replayed here.
 */
class ReviewController extends Controller
{
    public function dryRun(Request $request)
    {
        $target = (string) $request->input('_review_target');
        $method = strtoupper((string) ($request->input('_method') ?: $request->input('_review_method') ?: 'POST'));
        $path = parse_url($target, PHP_URL_PATH) ?: '';
        $base = rtrim($request->getBaseUrl(), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($path, $method));
        } catch (\Throwable $e) {
            $route = null;
        }
        if (! $route || ! in_array(RequireLedgerReview::ALIAS, $route->gatherMiddleware(), true)) {
            return $this->render(['ok' => false, 'problems' => [__('ledger.review_not_here')]] + Preview::blank());
        }

        $fields = $request->except(['_review_target', '_review_method']);
        $fields['reviewed'] = 1;

        $session = $request->session();
        $before = $session->all();
        $lastEntry = (int) DB::table('journal_entries')->max('id');

        Mail::fake();
        Notification::fake();
        Queue::fake();
        Http::fake();

        $p = Preview::blank();
        DB::beginTransaction();
        try {
            $sub = Request::create($request->getBaseUrl().$path, $method, $fields, $request->cookies->all(), [], $request->server->all());
            $sub->setLaravelSession($session);
            $sub->headers->set('referer', (string) $request->headers->get('referer'));
            $sub->headers->set('accept', 'text/html');
            $sub->headers->remove('X-Requested-With');

            $response = app(\Illuminate\Contracts\Http\Kernel::class)->handle($sub);
            app()->instance('request', $request);

            if ($response->getStatusCode() >= 400) {
                $p['problems'][] = __('ledger.review_refused', ['status' => $response->getStatusCode()]);
            }
            // Validation refusals: ViewErrorBag answers all() through __call, so ask its bags.
            $errors = $session->get('errors');
            if ($errors instanceof \Illuminate\Support\ViewErrorBag) {
                foreach ($errors->getBags() as $bag) {
                    foreach ($bag->all() as $m) {
                        $p['problems'][] = $m;
                    }
                }
            }
            // What it would have said (PosInnova flashes status = [success, msg]).
            $status = $session->get('status');
            if (is_array($status) && isset($status['msg']) && $status['msg'] !== '') {
                if (empty($status['success'])) {
                    $p['problems'][] = $status['msg'];
                } else {
                    $p['notes'][] = __('ledger.review_would_say', ['message' => $status['msg']]);
                }
            }
            $made = JournalEntry::with('lines.account')->where('id', '>', $lastEntry)->orderBy('id')->get();
            foreach ($made as $e) {
                $p['entries'][] = Preview::entry($e);
            }
        } catch (\Throwable $e) {
            $p['problems'][] = $e->getMessage();
        } finally {
            DB::rollBack();
            app()->instance('request', $request);
            $session->flush();
            $session->put($before);
        }

        $p['problems'] = array_values(array_unique($p['problems']));
        if ($p['problems']) {
            $p['ok'] = false;
            $p['entries'] = [];
        } elseif (! $p['entries']) {
            $p['notes'][] = __('ledger.review_posts_nothing');
        }
        $p['notes'][] = __('ledger.review_nothing_saved');

        return $this->render($p);
    }

    private function render(array $p)
    {
        return view('ledger.partials.preview', ['p' => $p]);
    }
}
