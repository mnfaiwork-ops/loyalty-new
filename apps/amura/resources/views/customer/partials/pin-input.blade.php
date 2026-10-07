{{-- $name, $label, $autocomplete, opsional $hint dan $autofocus. Desain 4a: satu input asli di atas 4 kotak hiasan. --}}
@php $length = \LoyaltyNew\Core\Customers\CustomerPin::LENGTH; @endphp
<div class="field pin-field @error($name) is-error @enderror" data-pin-field>
    <div class="pin-head">
        <label for="{{ $name }}">{{ $label }}<span class="sr-only">, {{ $length }} angka</span></label>
        <button type="button" class="pin-toggle" aria-controls="{{ $name }}" aria-pressed="false" data-pin-toggle hidden>Lihat</button>
    </div>
    <div class="field-alert" role="alert" id="{{ $name }}-error" data-pin-error @unless ($errors->has($name)) hidden @endunless>
        <b aria-hidden="true">!</b><span>{{ $errors->first($name) }}</span>
    </div>
    <div class="pin-wrap">
        <input class="input pin-input @error($name) is-error @enderror" type="password" id="{{ $name }}" name="{{ $name }}"
               inputmode="numeric" pattern="[0-9]*" maxlength="{{ $length }}"
               autocomplete="{{ $autocomplete }}" required data-pin
               aria-describedby="{{ $name }}-error{{ isset($hint) ? ' '.$name.'-hint' : '' }}"
               @error($name) aria-invalid="true" @enderror
               @if (! empty($autofocus) || $errors->has($name)) autofocus @endif>
        <div class="pin-boxes" aria-hidden="true">
            @for ($i = 0; $i < $length; $i++)
                <span class="pin-box"></span>
            @endfor
        </div>
    </div>
    @isset($hint)
        <div class="hint" id="{{ $name }}-hint">{{ $hint }}</div>
    @endisset
</div>
