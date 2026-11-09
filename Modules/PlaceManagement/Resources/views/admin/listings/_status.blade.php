@php($badge = ['pending' => 'bg-warning text-dark', 'changes' => 'bg-info text-dark', 'live' => 'bg-success', 'paused' => 'bg-secondary', 'rented' => 'bg-primary', 'refused' => 'bg-danger', 'removed' => 'bg-danger'][$listing->status] ?? 'bg-light text-dark')
<span class="badge {{ $badge }}">{{ translate($listing->status === 'pending' ? 'Pending_review' : ($listing->status === 'changes' ? 'Changes_asked' : $listing->status)) }}</span>
@if($listing->isVerified())<span class="badge bg-success ms-1"><i class="bi bi-patch-check-fill"></i> {{ translate('Verified') }}</span>@endif
@if($listing->isLate())<span class="badge bg-warning text-dark ms-1">{{ translate('Not_confirmed_recently') }}</span>@endif
@if($listing->needs_review)<span class="badge bg-info text-dark ms-1">{{ translate('Edited') }}</span>@endif
@if($listing->taken_check_until)<span class="badge bg-danger ms-1">{{ translate('Reported_taken') }}</span>@endif
