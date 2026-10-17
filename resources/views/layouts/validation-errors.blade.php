@if($errors->any())
<div class="alert error validation-summary" role="alert" tabindex="-1" aria-labelledby="validation-heading" data-validation-summary data-submitted-form="{{ is_string(old('_form')) ? old('_form') : '' }}">
    <strong id="validation-heading">Periksa isian berikut.</strong>
    <ul>
        @foreach($errors->messages() as $field => $messages)
            <li data-error-field="{{ $field }}">{{ implode(' ', $messages) }}</li>
        @endforeach
    </ul>
</div>
@endif

