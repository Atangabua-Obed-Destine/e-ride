@extends('adminmodule::layouts.master')

@section('title', translate('Fund Setup'))

@section('content')
<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        <h2 class="fs-22 mb-20 text-capitalize">{{ translate('Wallet_bonus') }}</h2>

        <div class="card mb-3">
            <div class="card-body">
                <h3 class="mb-20 text-dark">Wallet Bonus Setup</h3>
                <div class="row align-items-end g-lg-4 g-3">
                    <div class="col-md-6 col-lg-4">
                        <div class="">
                            <label for="" class="mb-2">Bonus Title</label>
                            <input type="text" value="" class="form-control" id="" name="" placeholder="Ex: EID Dhamaka"
                                required="" tabindex="1">
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="">
                            <label for="" class="mb-2">Short Description</label>
                            <input type="text" value="" class="form-control" id="" name="" placeholder="Ex: EID Dhamaka"
                                required="" tabindex="2">
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="">
                            <label for="" class="mb-2">
                                <span id="">
                                    {{ translate('Discount Amount') }}
                                </span>
                            </label>
                            <div class="input-group input--group">
                                <input type="number" class="form-control" name="" value="" placeholder="Ex : 10"
                                    tabindex="3">
                                <select class="form-select border-start-0" name="" tabindex="4">
                                    <option value="Amount" selected="">Amount</option>
                                    <option value="">$120</option>
                                    <option value="">$254</option>
                                    <option value="">$5874</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="">
                            <label for="" class="mb-2">Minimum Add Amount ($)</label>
                            <select name="minium_amout" class="js-select cmn_focus select2-hidden-accessible" id=""
                                tabindex="4" data-placeholder="Ex: 100">
                                <option value=""></option>
                                <option value="Ex: 100">Ex:100</option>
                                <option value="Ex: 500">Ex: 500</option>
                                <option value="Ex: 1000">Ex: 1000</option>
                                <option value="Ex: 200">Ex: 200</option>
                                <option value="Ex: 400">Ex: 400</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="">
                            <label for="" class="mb-2">Maximum Bonus ($)</label>
                            <select name="maximum_amout" class="js-select cmn_focus select2-hidden-accessible" id=""
                                tabindex="5" data-placeholder="Ex: 5000">
                                <option value=""></option>
                                <option value="Ex: 100">Ex: 100</option>
                                <option value="Ex: 500">Ex: 500</option>
                                <option value="Ex: 1000">Ex: 1000</option>
                                <option value="Ex: 200">Ex: 200</option>
                                <option value="Ex: 400">Ex: 400</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="">
                            <label for="" class="mb-2">Start date</label>
                            <input type="date" value="" id="" min="2025-09-22" name="start_date" class="form-control"
                                required="" tabindex="6">
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="">
                            <label for="" class="mb-2">End date</label>
                            <input type="date" value="" id="" min="2025-09-27" name="end_date" class="form-control"
                                required="" tabindex="7">
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="">
                            <label for="" class="mb-2">User Type</label>
                            <select name="user_" class="js-select cmn_focus select2-hidden-accessible" id=""
                                tabindex="8" data-placeholder="Select User Type">
                                <option value=""></option>
                                <option value="Ak Jr">Ak Jr</option>
                                <option value="Hai Prev">Hai Prev</option>
                                <option value="Donal De">Donal De</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="d-flex justify-content-end gap-3">
                            <button class="btn btn-secondary cmn_reset" type="reset" tabindex="9">Reset</button>
                            <button class="btn btn-primary cmn_focus" type="submit" tabindex="10">Submit</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h2 class="fs-22 mb-3 text-capitalize">{{ translate('Bonus_List') }}</h2>
        <div class="card">
            <div class="card-body">
                <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                    <form action="" class="search-form search-form_style-two" method="GET">
                        <input type="hidden" name="" value="" tabindex="11">
                        <div class="input-group search-form__input_group cmn_focus">
                                <span class="search-form__icon">
                                    <i class="bi bi-search"></i>
                                </span>
                            <input type="search" class="theme-input-style search-form__input"
                                    value="{{request()->get('search')}}" name="search" id="search"
                                    placeholder="{{translate('search_here_by_title')}}" tabindex="11">
                        </div>
                        <button type="submit" class="btn btn-primary cmn_focus" tabindex="12">{{ translate('search') }}</button>
                    </form>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-primary"
                                    data-bs-toggle="dropdown" tabindex="13">
                                <i class="bi bi-download"></i>
                                {{ translate('download') }}
                                <i class="bi bi-caret-down-fill"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                <li><a class="dropdown-item"
                                        href="{{route('admin.promotion.discount-setup.export')}}?status={{request()->get('status') ?? "all"}}&&file=excel">{{translate('excel')}}</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mt-3">
                    <table class="table table-borderless mb-0 align-middle table-hover text-nowrap">
                        <thead class="table-light align-middle text-capitalize">
                            <tr>
                                <th class="p-3 fw-semibold fs-14">SL</th>
                                <th class="p-3 fw-semibold fs-14">Bonus Title</th>
                                <th class="p-3 fw-semibold fs-14">Bonus Info</th>
                                <th class="p-3 fw-semibold text-center fs-14">Bonus Amount</th>
                                <th class="p-3 fw-semibold fs-14">Targeted user</th>
                                <th class="p-3 fw-semibold fs-14">Started On</th>
                                <th class="p-3 fw-semibold fs-14">Expires On</th>
                                <th class="p-3 fw-semibold fs-14">Status</th>
                                <th class="p-3 fw-semibold text-center fs-14">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fs-14 fw-semibold text-dark">1</td>
                                <td>
                                    <div class="max-w-240 min-w-170 fs-14">
                                        Get 5% Extra on Every Add Fund
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <div class="max-w-240 min-w-170 fs-14">
                                            Minimum Add Amount - <span>$100</span>
                                        </div>
                                        <div class="max-w-240 min-w-170 fs-14">
                                            Maximum Bonus - <span>$500</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="fs-14">5%</td>
                                <td class="fs-14">Customer</td>
                                <td class="fs-14">13 July 2022</td>
                                <td class="fs-14">23 July 2022</td>
                                <td class="status">
                                    <label class="switcher mx-auto" data-bs-toggle="modal" data-bs-target="#exampleModal">
                                        <input class="switcher_input " type="checkbox" checked="">
                                        <span class="switcher_control"></span>
                                    </label>
                                </td>
                                <td class="action">
                                    <div class="d-flex justify-content-center gap-2 align-items-center">
                                        <a href="" class="btn btn-outline-primary btn-action">
                                            <i class="bi bi-pencil-fill"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-action">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="fs-14 fw-semibold text-dark">1</td>
                                <td>
                                    <div class="max-w-240 min-w-170 fs-14">
                                        Get 5% Extra on Every Add Fund
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <div class="max-w-240 min-w-170 fs-14">
                                            Minimum Add Amount - <span>$100</span>
                                        </div>
                                        <div class="max-w-240 min-w-170 fs-14">
                                            Maximum Bonus - <span>$500</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="fs-14">5%</td>
                                <td class="fs-14">Customer</td>
                                <td class="fs-14">13 July 2022</td>
                                <td class="fs-14">23 July 2022</td>
                                <td class="status">
                                    <label class="switcher mx-auto" data-bs-toggle="modal" data-bs-target="#exampleModal">
                                        <input class="switcher_input" type="checkbox" checked="">
                                        <span class="switcher_control"></span>
                                    </label>
                                </td>
                                <td class="action">
                                    <div class="d-flex justify-content-center gap-2 align-items-center">
                                        <a href="" class="btn btn-outline-primary btn-action">
                                            <i class="bi bi-pencil-fill"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-action">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="fs-14 fw-semibold text-dark">1</td>
                                <td>
                                    <div class="max-w-240 min-w-170 fs-14">
                                        Get 5% Extra on Every Add Fund
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <div class="max-w-240 min-w-170 fs-14">
                                            Minimum Add Amount - <span>$100</span>
                                        </div>
                                        <div class="max-w-240 min-w-170 fs-14">
                                            Maximum Bonus - <span>$500</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="fs-14">5%</td>
                                <td class="fs-14">Customer</td>
                                <td class="fs-14">13 July 2022</td>
                                <td class="fs-14">23 July 2022</td>
                                <td class="status">
                                    <label class="switcher mx-auto" data-bs-toggle="modal" data-bs-target="#exampleModal">
                                        <input class="switcher_input " type="checkbox" checked="">
                                        <span class="switcher_control"></span>
                                    </label>
                                </td>
                                <td class="action">
                                    <div class="d-flex justify-content-center gap-2 align-items-center">
                                        <a href="" class="btn btn-outline-primary btn-action">
                                            <i class="bi bi-pencil-fill"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-action">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="fs-14 fw-semibold text-dark">1</td>
                                <td>
                                    <div class="max-w-240 min-w-170 fs-14">
                                        Get 5% Extra on Every Add Fund
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <div class="max-w-240 min-w-170 fs-14">
                                            Minimum Add Amount - <span>$100</span>
                                        </div>
                                        <div class="max-w-240 min-w-170 fs-14">
                                            Maximum Bonus - <span>$500</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="fs-14">5%</td>
                                <td class="fs-14">Customer</td>
                                <td class="fs-14">13 July 2022</td>
                                <td class="fs-14">23 July 2022</td>
                                <td class="status">
                                    <label class="switcher mx-auto" data-bs-toggle="modal" data-bs-target="#exampleModal">
                                        <input class="switcher_input " type="checkbox" checked="">
                                        <span class="switcher_control"></span>
                                    </label>
                                </td>
                                <td class="action">
                                    <div class="d-flex justify-content-center gap-2 align-items-center">
                                        <a href="" class="btn btn-outline-primary btn-action">
                                            <i class="bi bi-pencil-fill"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-action">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="fs-14 fw-semibold text-dark">1</td>
                                <td>
                                    <div class="max-w-240 min-w-170 fs-14">
                                        Get 5% Extra on Every Add Fund
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <div class="max-w-240 min-w-170 fs-14">
                                            Minimum Add Amount - <span>$100</span>
                                        </div>
                                        <div class="max-w-240 min-w-170 fs-14">
                                            Maximum Bonus - <span>$500</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="fs-14">5%</td>
                                <td class="fs-14">Customer</td>
                                <td class="fs-14">13 July 2022</td>
                                <td class="fs-14">23 July 2022</td>
                                <td class="status">
                                    <label class="switcher mx-auto" data-bs-toggle="modal" data-bs-target="#exampleModal">
                                        <input class="switcher_input " type="checkbox" checked="">
                                        <span class="switcher_control"></span>
                                    </label>
                                </td>
                                <td class="action">
                                    <div class="d-flex justify-content-center gap-2 align-items-center">
                                        <a href="" class="btn btn-outline-primary btn-action">
                                            <i class="bi bi-pencil-fill"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-action">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between  align-items-center flex-wrap gap-2 pt-3">
                    <p class="text-color fs-14 m-0">
                        1-10 of 97
                    </p>
                    <div class="d-flex align-items-center gap-md-3 gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-2">
                            <p class="fs-14 text-color m-0">Rows per page:</p>
                            <select name="pageCount" id="" class="custom-select border-0 p-0 bg-transparent">
                                <option value="5">5</option>
                                <option value="2">2</option>
                                <option value="7">7</option>
                            </select>
                        </div>
                        <ul class="pagination m-0">
                            <li class="page-item disabled" aria-disabled="true" aria-label="« Previous">
                                <span class="page-link" aria-hidden="true">‹</span>
                            </li>
                            <li class="page-item active" aria-current="page">
                                <span class="page-link">1</span>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="">2</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="" rel="next" aria-label="Next »">›</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Status Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="exampleModalLabel"></h5>
        <button type="button" class="small border bg-light rounded-circle w-32 h-32 p-2" data-bs-dismiss="modal" aria-label="Close">
            <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="modal-body py-0">
        <div class="modal-content-box max-w-420 mx-auto text-center">
            <img src="{{dynamicAsset('public/assets/admin-module/img/status-minus.png')}}" class="mb-20" alt="img">
            <h3 class="text-dark mb-xl-3 mb-2">Are you sure to turn off the bonus?</h3>
            <p class="mb-xl-4 mb-3 text-color">
                When you turn off the bonus, this bonus offer will be hidden for all customer.
            </p>
        </div>
      </div>
      <div class="modal-footer justify-content-center border-0 pb-4 mb-1 pt-0">
        <button type="button" class="btn min-w-100px btn-secondary" data-bs-dismiss="modal">No</button>
        <button type="button" class="btn min-w-100px btn-danger">Yes</button>
      </div>
    </div>
  </div>
</div>



@endsection

@push('script')

@endpush
