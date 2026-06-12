@php
  $s = strtoupper((string) ($state ?? ''));
  $class = match ($s) {
    'APPROVED'    => 'badge-success',
    'IN_PROGRESS' => 'badge-info',
    'PENDING'     => 'badge-warning',
    'REJECTED'    => 'badge-danger',
    'CANCELLED'   => 'badge-secondary',
    default       => 'badge-primary',
  };
@endphp
<span class="badge {{ $class }}">{{ $s }}</span>
