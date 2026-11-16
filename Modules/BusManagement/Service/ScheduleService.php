<?php

namespace Modules\BusManagement\Service;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusDestination;
use Modules\BusManagement\Entities\BusSchedule;
use Modules\BusManagement\Entities\BusSeatClaim;
use Modules\BusManagement\Entities\BusVehicle;
use Modules\BusManagement\Lib\BusSettings;

/**
 * The weekly timetable and the departures it opens. A timetable line opens its departures some days
 * ahead (bus_settings open_days), once each (unique schedule and date). Extra departures are added by hand.
 */
class ScheduleService
{
    public function saveLine(BusAgency $agency, array $input, ?BusSchedule $line = null): BusSchedule
    {
        $data = Validator::make($input, [
            'destination_id' => ['required', Rule::exists('bus_destinations', 'id')->where('agency_id', $agency->id)],
            'class' => ['required', Rule::in(BusVehicle::CLASSES)],
            'kind' => ['required', Rule::in([BusSchedule::FIXED, BusSchedule::WHEN_FULL])],
            'time' => ['required', 'date_format:H:i'],
            'days' => 'required|integer|min:1|max:127',
            'vehicle_id' => ['nullable', Rule::exists('bus_vehicles', 'id')->where('agency_id', $agency->id)],
            'auto_accept' => 'boolean',
            'active' => 'boolean',
        ])->validate();

        $this->checkVehicle(isset($data['vehicle_id']) ? BusVehicle::find($data['vehicle_id']) : null, $data['class']);
        $this->checkFares(BusDestination::find($data['destination_id']), $data['class']);

        $line ??= new BusSchedule(['agency_id' => $agency->id]);
        $line->fill($data + ['auto_accept' => false, 'active' => true]);
        $line->agency_id = $agency->id;
        $line->save();

        // Its departures not opened yet follow the change; opened ones with sales keep their bus and time.
        if ($line->wasChanged(['time', 'vehicle_id', 'class', 'kind', 'destination_id', 'auto_accept'])) {
            BusDeparture::where('schedule_id', $line->id)->where('date', '>=', today())->where('status', BusDeparture::SCHEDULED)->get()
                ->each(function (BusDeparture $d) use ($line) {
                    $d->update(['auto_accept' => $line->auto_accept]);
                    if (!BusSeatClaim::where('departure_id', $d->id)->exists()) {
                        $d->update(['time' => $line->time, 'class' => $line->class, 'kind' => $line->kind, 'destination_id' => $line->destination_id]);
                        $this->trySetVehicle($d, $line->vehicle);
                    }
                });
        }
        return $line->fresh();
    }

    /** Opens the departures of every timetable line for the coming days. Returns how many were opened. */
    public function openAhead(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $days = BusSettings::all()['open_days'];
        $opened = 0;
        BusSchedule::with(['vehicle', 'destination'])->where('active', true)
            ->whereIn('agency_id', BusAgency::whereIn('status', [BusAgency::SETUP, BusAgency::LIVE, BusAgency::PAUSED])->select('id'))
            ->chunkById(200, function ($lines) use ($now, $days, &$opened) {
                foreach ($lines as $line) {
                    if (!$line->destination?->active) {
                        continue;
                    }
                    for ($i = 0; $i < $days; $i++) {
                        $date = $now->copy()->startOfDay()->addDays($i);
                        if (!$line->runsOn($date)) {
                            continue;
                        }
                        if ($i === 0 && $date->copy()->setTimeFromTimeString($line->time)->lte($now)) {
                            continue;
                        }
                        $opened += $this->openOne($line, $date) ? 1 : 0;
                    }
                }
            });
        return $opened;
    }

    protected function openOne(BusSchedule $line, CarbonInterface $date): bool
    {
        if (BusDeparture::where('schedule_id', $line->id)->whereDate('date', $date)->exists()) {
            return false;
        }
        try {
            $departure = BusDeparture::create([
                'agency_id' => $line->agency_id,
                'schedule_id' => $line->id,
                'destination_id' => $line->destination_id,
                'class' => $line->class,
                'kind' => $line->kind,
                'date' => $date->toDateString(),
                'time' => $line->time,
                'auto_accept' => $line->auto_accept,
                'status' => BusDeparture::SCHEDULED,
            ]);
        } catch (\Illuminate\Database\QueryException) {
            return false; // opened at the same moment by another run (unique schedule and date)
        }
        $this->trySetVehicle($departure, $line->vehicle);
        return true;
    }

    /** An extra departure, outside the timetable (busy days before a holiday). */
    public function addExtra(BusAgency $agency, array $input): BusDeparture
    {
        $data = Validator::make($input, [
            'destination_id' => ['required', Rule::exists('bus_destinations', 'id')->where('agency_id', $agency->id)],
            'class' => ['required', Rule::in(BusVehicle::CLASSES)],
            'kind' => ['required', Rule::in([BusSchedule::FIXED, BusSchedule::WHEN_FULL])],
            'date' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:' . now()->addDays(60)->toDateString(),
            'time' => ['required', 'date_format:H:i'],
            'vehicle_id' => ['nullable', Rule::exists('bus_vehicles', 'id')->where('agency_id', $agency->id)],
            'auto_accept' => 'boolean',
        ])->validate();
        if (now()->gte(\Illuminate\Support\Carbon::parse($data['date'] . ' ' . $data['time']))) {
            throw ValidationException::withMessages(['time' => translate('This time has already passed.')]);
        }
        $vehicle = isset($data['vehicle_id']) ? BusVehicle::find($data['vehicle_id']) : null;
        $this->checkVehicle($vehicle, $data['class']);
        $this->checkFares(BusDestination::find($data['destination_id']), $data['class']);
        return DB::transaction(function () use ($agency, $data, $vehicle) {
            $departure = BusDeparture::create([
                'agency_id' => $agency->id,
                'destination_id' => $data['destination_id'],
                'class' => $data['class'],
                'kind' => $data['kind'],
                'date' => $data['date'],
                'time' => $data['time'],
                'auto_accept' => (bool)($data['auto_accept'] ?? false),
                'status' => BusDeparture::SCHEDULED,
            ]);
            $this->setVehicle($departure, $vehicle);
            return $departure->fresh();
        });
    }

    /**
     * Puts a bus on a departure (or takes it off). With seats already sold, the new bus must have every sold
     * seat number, and its plan replaces the old one.
     */
    public function setVehicle(BusDeparture $departure, ?BusVehicle $vehicle): BusDeparture
    {
        return DB::transaction(function () use ($departure, $vehicle) {
            $departure = BusDeparture::lockForUpdate()->findOrFail($departure->id);
            $claimed = BusSeatClaim::where('departure_id', $departure->id)->pluck('seat')->all();
            if (!$vehicle) {
                if ($claimed) {
                    throw ValidationException::withMessages(['vehicle_id' => translate('Seats are sold on this departure: choose another bus instead.')]);
                }
                $departure->update(['vehicle_id' => null, 'seat_plan' => null, 'capacity' => 0]);
                return $departure;
            }
            if ((string)$vehicle->agency_id !== (string)$departure->agency_id) {
                throw ValidationException::withMessages(['vehicle_id' => translate('This bus is not yours.')]);
            }
            $this->checkVehicle($vehicle, $departure->class);
            $plan = $vehicle->seatPlan();
            $missing = array_diff($claimed, $plan['seats']);
            if ($missing) {
                throw ValidationException::withMessages(['vehicle_id' => translate(key: 'This bus has no seat {seats}, already sold. Choose a bus with these seats.',
                    replace: ['seats' => implode(', ', $missing)])]);
            }
            $departure->update(['vehicle_id' => $vehicle->id, 'seat_plan' => $plan, 'capacity' => $plan['total']]);
            return $departure;
        });
    }

    /** The timetable's bus when it can take the departure; otherwise the departure waits for one ("bus not set"). */
    protected function trySetVehicle(BusDeparture $departure, ?BusVehicle $vehicle): void
    {
        try {
            $this->setVehicle($departure, $vehicle);
        } catch (ValidationException) {
        }
    }

    protected function checkVehicle(?BusVehicle $vehicle, string $class): void
    {
        if (!$vehicle) {
            return;
        }
        if ($vehicle->status !== BusVehicle::READY) {
            throw ValidationException::withMessages(['vehicle_id' => translate(key: '{bus} is not ready (in the garage or retired).', replace: ['bus' => $vehicle->registration])]);
        }
        if ($vehicle->class !== $class) {
            throw ValidationException::withMessages(['vehicle_id' => translate(key: '{bus} is not a {class} bus: choose a bus of the same class as the departure.',
                replace: ['bus' => $vehicle->registration, 'class' => self::classLabel($class)])]);
        }
    }

    protected function checkFares(?BusDestination $destination, string $class): void
    {
        $hasFare = $destination?->dropPoints()->where('active', true)->get()->contains(fn($p) => $p->fareFor($class) !== null);
        if (!$hasFare) {
            throw ValidationException::withMessages(['class' => translate(key: 'Set a {class} fare for {city} first, in Destinations.',
                replace: ['class' => self::classLabel($class), 'city' => $destination?->city])]);
        }
    }

    public static function classLabel(string $class): string
    {
        return match ($class) {
            'vip' => 'VIP',
            'executive' => translate('Executive'),
            default => translate('Classic'),
        };
    }
}
