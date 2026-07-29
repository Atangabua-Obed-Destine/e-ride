@extends('adminmodule::layouts.master')

@section('title', translate('Withdrawal_Methods'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h2 class="fs-22 mb-4 text-capitalize">{{translate('Update Withdraw Method')}}</h2>

                <form action="{{route('admin.driver.withdraw-method.update', [$method->id])}}" method="POST">
                    @csrf
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                <div class="d-flex align-items-center gap-2">
                                    <h5 class="">{{translate('Setup Method Info')}}</h5>
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                       data-bs-toggle="tooltip" data-bs-title="Choose your business location"></i>
                                </div>

                                <button class="btn btn-primary cmn_focus text-capitalize" id="add-more-field"
                                        tabindex="1">
                                    <i class="tio-add"></i>
                                    {{translate('add_More_Fields')}}
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row gy-4 align-items-end">
                                <div class="col-md-6">
                                    <div class="">
                                        <label for="method_name" class="mb-2">{{translate('method_name')}} <span
                                                class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="method_name" id="method_name"
                                               placeholder="{{ translate('select_method_name') }}"
                                               value="{{$method->method_name}}" required tabindex="2">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex gap-2 align-items-center mb-3">
                                        <input type="checkbox"
                                               id="makeMethodDefault" class="cmn_focus"
                                               {{$method['is_default'] ? 'checked' : ''}} name="is_default"
                                               tabindex="3">
                                        <label for="makeMethodDefault">
                                            {{translate("Make This Method Default")}}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4" id="custom-field-section">
                                @forelse($method->method_fields as $key => $field)
                                    <div class="bg-light p-4 rounded" id="field-row--{{ $key }}">
                                        <div class="row align-items-center">
                                            @if($key != 0)
                                                <div class="col-12">
                                                    <div class="mb-1 d-flex justify-content-end">
                                                        <button type="button"
                                                                class="btn btn-outline-danger btn-action remove-field"
                                                                data-value="{{ $key }}" tabindex="4">
                                                            <i class="tio-delete"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="col-md-4 mb-4">
                                                <label for="field_type{{$key}}"
                                                       class="mb-2">{{translate('Input_Field_Type')}}
                                                    <span class="text-danger">*</span></label>
                                                <select class="form-control js-select cmn_focus"
                                                        name="field_type[{{$key}}]" required
                                                        id="field_type{{$key}}" tabindex="5">
                                                    <option
                                                        value="string" {{$field['input_type'] == 'string' ? 'selected' : ''}}>{{translate('string')}}</option>
                                                    <option
                                                        value="number" {{$field['input_type'] == 'number' ? 'selected' : ''}}>{{translate('number')}}</option>
                                                    <option
                                                        value="date" {{$field['input_type'] == 'date' ? 'selected' : ''}}>{{translate('date')}}</option>
                                                    <option
                                                        value="password" {{$field['input_type'] == 'password' ? 'selected' : ''}}>{{translate('password')}}</option>
                                                    <option
                                                        value="email" {{$field['input_type'] == 'email' ? 'selected' : ''}}>{{translate('email')}}</option>
                                                    <option
                                                        value="phone" {{$field['input_type'] == 'phone' ? 'selected' : ''}}>{{translate('phone')}}</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-4">
                                                    <label for="field_name{{$key}}"
                                                           class="mb-2">{{translate('field_name')}}
                                                        <span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="field_name[{{$key}}]"
                                                           placeholder="{{ translate('select_field_name') }}"
                                                           value="{{$field['input_name']}}"
                                                           required id="field_name{{$key}}" tabindex="6">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-4">
                                                    <label for="placeholder{{$key}}"
                                                           class="mb-2">{{translate('placeholder_text')}}
                                                        <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control"
                                                           name="placeholder_text[{{$key}}]"
                                                           placeholder="{{ translate('select_placeholder_text') }}"
                                                           value="{{$field['placeholder']}}" id="placeholder"
                                                           required tabindex="7">
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex align-items-center gap-2 justify-content-end">
                                                    @if($field['is_required'])
                                                        <input type="checkbox" value="1"
                                                               name="is_required[{{$key}}]"
                                                               id="flexCheckDefault__{{$key}}" checked tabindex="8">
                                                    @else
                                                        <input type="checkbox" value="1"
                                                               name="is_required[{{$key}}]"
                                                               id="flexCheckDefault__{{$key}}" tabindex="8">
                                                    @endif
                                                    <label for="flexCheckDefault__{{$key}}">
                                                        {{translate('make_this_field_required')}}
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                @endforelse
                            </div>

                            <div class="d-flex justify-content-end gap-3 flex-wrap mt-4">
                                <button type="submit" class="btn btn-primary demo_check" tabindex="9">
                                    {{ translate('Update Information') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </div>
@endsection


@push('script')
    <script>
        jQuery(document).ready(function ($) {
            let availableIndexes = Array.from({length: 15}, (_, i) => i);
            let existingIndexes = @json(array_keys($method->method_fields));
            availableIndexes = availableIndexes.filter(i => !existingIndexes.includes(i));

            $('#add-more-field').on('click', function (event) {
                event.preventDefault();
                if (availableIndexes.length === 0) {
                    Swal.fire({
                        title: '{{translate('maximum_limit_reached')}}',
                        confirmButtonText: '{{translate('ok')}}',
                    });
                    return;
                }
                let index = availableIndexes.shift();
                let newRow = $(`<div class="bg-light p-4 rounded mt-4" id="field-row--${index}">
                            <div class="row align-items-center">
                                <div class="col-12">
                                    <div class="mb-1 d-flex justify-content-end">
                                        <button type="button" class="btn btn-outline-danger btn-action remove-field" data-value="${index}">
                                            <i class="tio-delete"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label for="field_type${index}" class="mb-2">{{translate('Input_Field_Type')}}
                <span class="text-danger">*</span></label>
            <select class="form-control js-select cmn_focus" name="field_type[${index}]" required id="field_type${index}">
                        <option value="" selected
                                disabled>{{translate('Select_Input_Field_Type')}}</option>
                                        <option value="string">{{translate('string')}}</option>
                                        <option value="number">{{translate('number')}}</option>
                                        <option value="date">{{translate('date')}}</option>
                                        <option value="password">{{translate('password')}}</option>
                                        <option value="email">{{translate('email')}}</option>
                                        <option value="phone">{{translate('phone')}}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-4">
                                        <label for="field_name${index}" class="mb-2">{{translate('field_name')}} <span
                                                class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="field_name[${index}]"
                                            placeholder="{{ translate('select_field_name') }}" value="" required id="field_name${index}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-4">
                                        <label for="placeholder${index}" class="mb-2">{{translate('placeholder_text')}}
                <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="placeholder_text[${index}]"
                    placeholder="{{ translate('select_placeholder_text') }}" value="" id="placeholder${index}"
                                            required>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                        <input type="checkbox" value="1"
                                            name="is_required[${index}]" id="flexCheckDefault__${index}" checked>
                                        <label for="flexCheckDefault__${index}">
                                            {{translate('make_this_field_required')}}
                </label>
            </div>
        </div>
    </div>
</div>`)
                $('#custom-field-section').append(newRow);
                newRow.find('.js-select').select2();
            })

            $(document).on('click', '.remove-field', function(){
                let index = $(this).data('value');
                $(`#field-row--${index}`).remove();
                availableIndexes.push(index);
                availableIndexes.sort((a,b)=>a-b);
            });
        });
    </script>
@endpush
