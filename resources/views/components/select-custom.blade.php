{{-- resources/views/components/select-custom.blade.php --}}

@props([
    'name',
    'class',
    'options' => [],
    'selected' => null,
    'placeholder' => '선택하세요',
    'disabled' => false,
    'type' => 'select',
])


<div
    class="ui-select {{$class}}"
    data-placeholder="{{ $placeholder }}"
    data-type="{{ $type }}"
    style="--width: 120"
>
    <select name="{{ $name }}" @disabled($disabled)>
        <option value="">{{ $placeholder }}</option>

        @foreach ($options as $value => $label)
            <option
                value="{{ $value }}"
                @selected((string)$selected === (string)$value)
            >
                {{ $label }}
            </option>
        @endforeach
    </select>

    <button type="button" class="ui-select-btn"></button>

    <ul class="ui-select-list"></ul>
</div>
