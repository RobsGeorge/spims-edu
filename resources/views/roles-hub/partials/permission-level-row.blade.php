@php
    $current = $matrix[$permKey][$roleKey] ?? '';
    $fieldId = 'perm-'.$roleKey.'-'.md5($permKey);
@endphp
<div class="col-12 col-md-6 col-lg-4" data-perm-row data-perm-key="{{ $permKey }}">
    <div class="roles-hub-perm-row">
        <label class="roles-hub-perm-key" for="{{ $fieldId }}">{{ $permKey }}</label>
        <select class="form-select roles-hub-level-select"
                name="permissions[{{ $permKey }}]"
                id="{{ $fieldId }}"
                aria-label="{{ __('roles_hub.perm_level_label', ['key' => $permKey]) }}">
            <option value="" @selected($current === '')>{{ __('roles_hub.level_off') }}</option>
            @foreach($grantLevels as $level)
                <option value="{{ $level }}" @selected($current === $level)>{{ __('roles_hub.level_'.$level) }}</option>
            @endforeach
        </select>
    </div>
</div>
