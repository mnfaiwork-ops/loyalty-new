@php($logo = ($dark ?? false) ? $theme['logo_dark'] : $theme['logo'])
<img class="{{ $class ?? 'brand-logo' }}" src="{{ asset($logo['src']) }}" width="{{ $logo['w'] }}" height="{{ $logo['h'] }}" alt="{{ $theme['name'] }}" style="--ls: {{ $theme['logo_scale'] }}">
