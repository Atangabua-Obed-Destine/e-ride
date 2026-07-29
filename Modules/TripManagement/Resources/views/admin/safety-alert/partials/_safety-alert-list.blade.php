<div class="table-responsive mt-3">
    <table class="table table-borderless align-middle table-hover">
        <thead class="table-light align-middle text-capitalize text-nowrap">
        <tr>
            <th class="text-center sl">{{translate('SL')}}</th>
            <th class="text-center trip-id">{{translate('trip_ID')}}</th>
            <th class="text-center date">{{translate('date')}}</th>
            <th class="text-center customer-name">{{translate('customer')}}</th>
            <th class="text-center driver">{{translate('driver')}}</th>
            <th class="text-center alert-location">{{translate('Alert_Location')}}</th>
            <th class="text-center resolved-location">{{translate('Resolved_Location')}}</th>
            <th class="text-center no-of-alert">{{translate('No_of_Alert')}}</th>
            <th class="text-center solved-by">{{translate('Solved_By')}}</th>
            <th class="text-center trip-status-when-make-alert">{{translate('Trip_Status_When_Make_Alert')}}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($safetyAlerts as $key => $safetyAlert)
            <tr>
                <td class="text-center sl">{{$safetyAlerts->firstItem() + $key}}</td>
                <td class="text-center trip-id"><a
                        href="{{route('admin.trip.show', ['type' => $type, 'id' => $safetyAlert->trip?->id, 'page' => 'summary'])}}">{{$safetyAlert->trip?->ref_id}}</a>
                </td>
                <td class="text-center text-nowrap date">
                    <div dir="ltr">
                        {{date('d F Y', strtotime($safetyAlert->created_at))}},
                        <br/> {{date('h:i a', strtotime($safetyAlert->created_at))}}
                    </div>
                </td>
                <td class="text-center customer-name"><a target="_blank"
                                                         @if($safetyAlert->trip?->customer)
                                                             href="{{route('admin.customer.show', [$safetyAlert->trip?->customer?->id])}}"
                        @endif>
                        {{ $safetyAlert->trip?->customer?->id ? $safetyAlert->trip?->customer?->first_name. ' ' . $safetyAlert->trip?->customer?->last_name : translate('no_customer_assigned') }}
                    </a>
                </td>
                <td class="text-center text-capitalize driver">
                    <a target="_blank"
                       @if($safetyAlert->trip?->driver)
                           href="{{route('admin.driver.show', [$safetyAlert->trip?->driver?->id])}}"
                        @endif
                    >
                        {{ $safetyAlert->trip?->driver?->id ? $safetyAlert->trip?->driver?->first_name. ' ' . $safetyAlert->trip?->driver?->last_name : translate('no_driver_assigned') }}
                    </a>
                </td>
                <td class="text-center text-capitalize alert-location">
                    {{ $safetyAlert?->alert_location}}
                </td>
                <td class="text-center text-capitalize resolved-location">
                    {{ $safetyAlert?->resolved_location != null ? $safetyAlert?->resolved_location : 'N/A'}}
                </td>

                <td class="text-center text-capitalize no-of-alert">
                    {{ $safetyAlert?->number_of_alert }}
                </td>

                <td class="text-center text-capitalize resolved-by">
                   <b>{{ $safetyAlert?->solvedBy?->user_type == 'admin-employee' ? 'Employee' : $safetyAlert?->solvedBy?->user_type }}</b>
                    <br>
                    {{ $safetyAlert?->solvedBy?->user_type == 'admin-employee' && $safetyAlert?->solvedBy?->id ? $safetyAlert?->solvedBy?->first_name. ' ' . $safetyAlert?->solvedBy?->last_name : ' ' }}
                </td>

                <td class="text-center text-capitalize trip_status_when_make_alert">
                    <span class="badge badge-primary">
                        {{ $safetyAlert?->trip_status_when_make_alert }}
                    </span>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="14">
                    <div class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                        <img src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}" alt=""
                             width="100">
                        <p class="text-center">{{translate('no_data_available')}}</p>
                    </div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="table-bottom d-flex flex-column flex-sm-row justify-content-sm-between align-items-center gap-2 pt-2">
    <p class="mb-0"></p>
        {{$safetyAlerts->links()}}
</div>
