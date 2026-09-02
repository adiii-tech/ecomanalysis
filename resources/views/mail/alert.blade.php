<x-mail::message>
# {{ $title }}

**{{ $tenantName }}** · {{ ucfirst($severity) }} alert@if ($rule) · rule "{{ $rule }}"@endif

{{ $body }}

<x-mail::button :url="$url">Open alerts</x-mail::button>

You are receiving this because this alert rule lists email as a delivery channel. Mute or edit the rule to stop it.
</x-mail::message>
