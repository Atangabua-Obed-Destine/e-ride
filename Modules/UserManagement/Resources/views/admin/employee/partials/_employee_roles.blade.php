<div class="bg-fafafa p-4 rounded mt-4">
    <h5 class="fw-semibold mb-3 text-capitalize">{{ translate('module_access') }}</h5>

    <div class="row g-3">
        <input type="hidden" name="role_id" value="{{ $role['id'] }}">
        @foreach ($role['modules'] as $key => $module)
            <div class="col-lg-12">
                <div class="card border-0 shadow-none">
                    <div class="p-3 pb-0">
                        <div class="d-flex gap-3 flex-wrap justify-content-between align-items-center">
                            <h5 class="fw-semibold">{{ translate($module) }}</h5>
                            <label class="custom-checkbox">
                                <input type="checkbox" class="select-all-module" id="select-all-{{ $key }}"
                                       data-module="{{ $key }}">
                                {{ translate('Select all') }}
                            </label>
                        </div>
                        <hr class="off-white-gray">
                    </div>
                    <div class="card-body ">
                        <div class="row">
                            @if (array_key_exists($module, MODULES))
                                @foreach (MODULES[$module] as $permission)
                                    <div class="col">
                                        <label class="custom-checkbox pb-3">
                                            <input type="checkbox" name="permission[{{ $module }}][]"
                                                   value="{{ $permission }}"
                                                   class="module-checkbox"
                                                   data-module="{{ $key }}"
                                                {{$employee &&  $employee->moduleAccess->where('module_name', $module)->first()?->$permission == 1 && $employee->role_id == $role['id'] ? 'checked' : '' }}>
                                            {{ translate($permission) }}
                                        </label>

                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
