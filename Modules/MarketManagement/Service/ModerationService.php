<?php

namespace Modules\MarketManagement\Service;

use Illuminate\Validation\ValidationException;
use Modules\AdminModule\Entities\AdminNotification;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketReport;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Lib\MarketPush;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\UserManagement\Entities\User;
use Throwable;

/**
 * Reports and the admin's decisions. Items go live at once; an item is hidden until an
 * admin decides when enough different people report it, or at once when it is a digital
 * product of an unverified shop reported as a scam.
 */
class ModerationService
{
    public function __construct(protected ShopService $shops)
    {
    }

    public function report(User $reporter, ?MarketItem $item, MarketShop $shop, string $reason, ?string $note): MarketReport
    {
        if ($shop->user_id === $reporter->id) {
            throw ValidationException::withMessages(['report' => translate('You cannot report your own shop.')]);
        }
        // One open report per person and item (or shop): a second one updates it.
        $report = MarketReport::updateOrCreate(
            ['reporter_id' => $reporter->id, 'shop_id' => $shop->id, 'item_id' => $item?->id, 'status' => MarketReport::OPEN],
            ['reason' => $reason, 'note' => $note ? mb_substr(trim($note), 0, 600) : null],
        );
        if ($item) {
            $reporters = MarketReport::where('item_id', $item->id)->where('status', MarketReport::OPEN)->distinct()->count('reporter_id');
            $item->update(['report_count' => $reporters]);
            $settings = MarketSettings::all();
            $scamOnRiskyDigital = $reason === 'scam' && $item->isDigital() && !$shop->isVerified();
            if ($item->status === MarketItem::LIVE && ($reporters >= $settings['hide_after_reports'] || $scamOnRiskyDigital)) {
                $item->update(['status' => MarketItem::HIDDEN, 'status_note' => 'Hidden while E-Ride checks reports']);
                MarketPush::send($shop->owner, 'market_item_hidden', 'Your item is hidden for now',
                    'Buyers reported "{title}". E-Ride is checking and will tell you what it decides.', ['title' => $item->title], ['item_code' => $item->code]);
            }
        }
        $this->safely(fn() => AdminNotification::create(['model' => 'market_report', 'model_id' => $report->id,
            'message' => $reason === 'scam' ? 'Market scam report' : 'Market report']));
        return $report;
    }

    /**
     * The admin decides on an item: kept (live again), hidden, removed, or the whole shop closed.
     * Every open report on it takes the decision.
     */
    public function decide(MarketItem $item, string $decision, User $admin, ?string $reason = null): MarketItem
    {
        $shop = $item->shop;
        switch ($decision) {
            case 'kept':
                $item->update(['status' => $item->quantity < 1 ? MarketItem::SOLD_OUT : MarketItem::LIVE, 'status_note' => null, 'blocked_word' => null]);
                break;
            case 'hidden':
                $item->update(['status' => MarketItem::HIDDEN, 'status_note' => $reason ?: 'Hidden by E-Ride']);
                break;
            case 'removed':
                $item->update(['status' => MarketItem::REMOVED, 'status_note' => $reason ?: 'Removed by E-Ride']);
                MarketPush::send($shop?->owner, 'market_item_removed', 'Your item was removed',
                    'E-Ride removed "{title}" because it breaks the E-Ride Market rules.', ['title' => $item->title], ['item_code' => $item->code]);
                break;
            case 'closed':
                if ($shop) {
                    $this->shops->close($shop, $reason ?: translate('it broke the E-Ride Market rules'));
                }
                break;
            default:
                throw ValidationException::withMessages(['decision' => translate('Unknown decision.')]);
        }
        MarketReport::where('item_id', $item->id)->where('status', MarketReport::OPEN)
            ->update(['status' => $decision, 'decided_by' => $admin->id, 'decided_at' => now()]);
        if ($decision === 'kept') {
            $item->update(['report_count' => 0]);
        }
        return $item->fresh(['shop', 'photos']);
    }

    /** A report on a whole shop (no item). */
    public function decideShop(MarketShop $shop, string $decision, User $admin, ?string $reason = null): MarketShop
    {
        if ($decision === 'closed') {
            $shop = $this->shops->close($shop, $reason ?: translate('it broke the E-Ride Market rules'));
        }
        MarketReport::where('shop_id', $shop->id)->whereNull('item_id')->where('status', MarketReport::OPEN)
            ->update(['status' => $decision === 'closed' ? 'closed' : 'kept', 'decided_by' => $admin->id, 'decided_at' => now()]);
        return $shop;
    }

    private function safely(callable $step): void
    {
        try {
            $step();
        } catch (Throwable) {
        }
    }
}
