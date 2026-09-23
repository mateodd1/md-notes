<input id="{{ $fieldId }}" type="hidden" name="parent" value="">
<details id="{{ $pickerId }}" class="parent-picker" data-parent-picker data-parent-field="{{ $fieldId }}">
    <summary><span data-parent-label>{{ __('ui.root') }}</span><x-icon name="chevron-down" class="menu-icon" /></summary>
    <div class="parent-options" role="listbox">
        <button type="button" class="parent-option" data-parent-option data-parent-value="" data-parent-label="{{ __('ui.root') }}"><x-icon name="home" class="menu-icon" />{{ __('ui.root') }}</button>
        @include('notes._parent-options', ['nodes' => $nodes, 'depth' => 0])
    </div>
</details>
