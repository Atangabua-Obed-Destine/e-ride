{{-- A budget's status in words (ap-chip): never colour alone. Expects $status. --}}
<span class="ap-chip {{ \App\Ledger\Models\Budget::statusChip($status) }}">{{ \App\Ledger\Models\Budget::statusLabel($status) }}</span>
