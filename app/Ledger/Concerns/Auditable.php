<?php

namespace App\Ledger\Concerns;

/**
 * paxhitestbed's Auditable: every create / update / delete of a ledger record
 * goes to the audit trail with the model's own readable description. PosInnova
 * keeps its audit trail in the activity log (spatie), so it is written there.
 */
trait Auditable
{
    protected static function bootAuditable()
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function ($model) use ($event) {
                try {
                    if (! function_exists('activity')) {
                        return;
                    }
                    $text = method_exists($model, 'getAuditDescription') ? $model->getAuditDescription($event) : class_basename($model).' '.$event;
                    $log = activity()->performedOn($model)->withProperties([
                        'business_id' => $model->business_id ?? null,
                        'changes' => $event === 'updated' ? $model->getChanges() : null,
                    ]);
                    if (auth()->check()) {
                        $log->causedBy(auth()->user());
                    }
                    $activity = $log->log($text);
                    if (! empty($model->business_id)) {
                        $activity->business_id = $model->business_id; // the activity log screen filters by business
                        $activity->save();
                    }
                } catch (\Throwable $e) {
                    // the audit trail never stops an accounting action
                }
            });
        }
    }
}
