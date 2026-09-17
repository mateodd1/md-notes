<input id="{{ $fieldId }}" type="hidden" name="parent" value="">
<details id="{{ $pickerId }}" class="parent-picker" data-parent-picker data-parent-field="{{ $fieldId }}">
    <summary><span data-parent-label>{{ __('ui.root') }}</span><span aria-hidden="true">⌄</span></summary>
    <div class="parent-options" role="listbox">
        <button type="button" class="parent-option" data-parent-option data-parent-value="" data-parent-label="{{ __('ui.root') }}">⌂ {{ __('ui.root') }}</button>
        @include('notes._parent-options', ['nodes' => $nodes, 'depth' => 0])
    </div>
</details>
