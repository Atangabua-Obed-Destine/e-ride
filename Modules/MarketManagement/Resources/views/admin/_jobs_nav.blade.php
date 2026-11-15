<ul class="nav nav--tabs p-1 rounded bg-white mb-3">
    <li class="nav-item"><a class="nav-link {{ $active === 'jobs' ? 'active' : '' }}" href="{{ route('admin.jobs.index') }}">{{ translate('Jobs to check') }}</a></li>
    <li class="nav-item"><a class="nav-link {{ $active === 'employers' ? 'active' : '' }}" href="{{ route('admin.jobs.employers') }}">{{ translate('Employers') }}</a></li>
</ul>
