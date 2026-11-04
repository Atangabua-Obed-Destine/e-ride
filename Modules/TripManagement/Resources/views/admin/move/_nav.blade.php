<ul class="nav nav--tabs p-1 rounded bg-white mb-3" role="tablist">
    @foreach ([
        ['list', 'admin.move.index', 'Moves'],
        ['deposits', 'admin.move.deposits', 'Deposits'],
        ['directory', 'admin.move.directory', 'Directory'],
        ['settings', 'admin.move.settings', 'Settings'],
    ] as [$key, $route, $label])
        <li class="nav-item">
            <a class="nav-link {{ $active === $key ? 'active' : '' }}" href="{{ route($route) }}">{{ translate($label) }}</a>
        </li>
    @endforeach
</ul>
