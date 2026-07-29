<?php

namespace Modules\UserManagement\Http\Controllers\Web\Admin\Employee;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\AdminModule\Service\Interfaces\ActivityLogServiceInterface;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Http\Requests\EmployeeStoreOrUpdateRequest;
use Modules\UserManagement\Service\Interfaces\EmployeeRoleServiceInterface;
use Modules\UserManagement\Service\Interfaces\EmployeeServiceInterface;
use Modules\UserManagement\Service\Interfaces\UserAddressServiceInterface;
use App\Exports\StyledReport\ColumnFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends BaseController
{
    protected $employeeService;
    protected $employeeRoleService;
    protected $activityLogService;
    protected $userAddressService;

    public function __construct(EmployeeServiceInterface $employeeService, EmployeeRoleServiceInterface $employeeRoleService,
                                UserAddressServiceInterface $userAddressService,ActivityLogServiceInterface $activityLogService)
    {
        parent::__construct($employeeService);
        $this->employeeService = $employeeService;
        $this->employeeRoleService = $employeeRoleService;
        $this->activityLogService = $activityLogService;
        $this->userAddressService = $userAddressService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('user_view');
        $employees = $this->employeeService
            ->index(criteria: $request?->all(), relations: ['role', 'moduleAccess'], limit: paginationLimit(), offset:$request['page']??1);
        return view('usermanagement::admin.employee.index', compact('employees'));
    }

    public function create()
    {
        $this->authorize('user_add');
        $roles = $this->employeeRoleService->getBy(criteria: [
            'is_active' => 1
        ]);
        return view('usermanagement::admin.employee.create', compact('roles'));
    }

    public function store(EmployeeStoreOrUpdateRequest $request): RedirectResponse
    {
        $this->authorize('user_add');
        $this->employeeService->create($request->validated());
        Toastr::success(EMPLOYEE_STORE_200['message']);
        return redirect(route('admin.employee.index'));

    }


    public function show($id)
    {
        $this->authorize('user_view');
        $employee = $this->employeeService->findOne(id: $id);
        if (!$employee) {
            Toastr::warning(translate("Employee not found"));
            return back();
        }
        $attributes['logable_type'] = 'Modules\UserManagement\Entities\User';

        $attributes['logable_id'] = $id;

        $attributes['user_type'] = 'admin-employee';

        $logs = $this->activityLogService->getBy(criteria: $attributes, limit: paginationLimit(), offset: 1);

        $employee = $this->employeeService->findOne(id: $id);
        $roles = $this->employeeRoleService->getAll();

        return view('usermanagement::admin.employee.show', compact('employee', 'roles', 'logs'));
    }


    public function edit($id)
    {
        $this->authorize('user_edit');
        $employee = $this->employeeService->findOne(id: $id);
        if (!$employee){
            Toastr::warning(translate("Employee not found"));
            return back();
        }
        $employeeAddress = $this->userAddressService->findOneBy(criteria: ['user_id' => $id]);
        $roles = $this->employeeRoleService->getAll();
        $role = $this->employeeRoleService->findOne(id: $employee?->role_id);
        return view('usermanagement::admin.employee.edit', compact('employee', 'employeeAddress', 'roles', 'role'));
    }


    public function update(EmployeeStoreOrUpdateRequest $request, $id): RedirectResponse
    {
        $this->authorize('user_edit');
        $this->employeeService->update(id: $id, data: $request->validated());
        Toastr::success(EMPLOYEE_UPDATE_200['message']);
        return back();
    }


    public function destroy($id): RedirectResponse
    {
        $this->authorize('user_delete');
        $this->employeeService
            ->delete(id: $id);
        Toastr::success(EMPLOYEE_DELETE_200['message']);
        return back();

    }

    public function updateStatus(Request $request): JsonResponse
    {
        $this->authorize('user_edit');
        $validated = $request->validate([
            'status' => 'required',
            'id' => 'required'
        ]);
        $employee = $this->employeeService
            ->statusChange(id: $request->id, data: $request->all());

        return response()->json($employee);
    }

    public function export(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('user_export');
        $data = $this->employeeService->export(criteria: $request->all(), relations: ['role'], orderBy: ['created_at' => 'desc']);
        $config = styledExportConfig(
            $data,
            title: 'Employee List',
            summary: [
                'Total Employee'    => $data->count(),
                'Active Employee'   => $data->where('Status', 'Active')->count(),
                'Inactive Employee' => $data->where('Status', 'Inactive')->count(),
            ],
            filters: [
                'Status'  => $request->status   ?? translate('All'),
                'Role'    => $request->role     ?? translate('All'),
                'Search'  => $request->search   ?? translate('N/A'),
            ],
            columnFormats: [
                'Status' => ColumnFormat::STATUS,
            ],
            fileName: 'employees-' . time() . '.xlsx',
            headings: ['Name', 'Email', 'Phone', 'Identification Number', 'Identification Type', 'User Type', 'Role', 'Modules', 'Status'],
        );
        return exportData($data, $request['file'], 'usermanagement::admin.employee.print', $config);
    }


    public function log(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('user_log');
        $request->merge([
            'logable_type' => User::class,
            'user_type' => 'admin-employee'
        ]);
        $logs = $this->activityLogService->log($request->all());
        $file = array_key_exists('file', $request->all()) ? $request['file'] : '';
        return logViewerNew($logs,$file);
    }

    public function trash(Request $request)
    {
        $this->authorize('super-admin');
        $employees = $this->employeeService->trashedData(criteria: $request->all(), relations: ['role','moduleAccess'], limit: paginationLimit(), offset:$request['page']??1);
        return view('usermanagement::admin.employee.trashed', compact('employees'));

    }

    public function restore($id): RedirectResponse
    {
        $this->authorize('super-admin');
        $this->employeeService->restoreData(id: $id);
        Toastr::success(DEFAULT_RESTORE_200['message']);
        return redirect()->route('admin.employee.index');

    }

    public function permanentDelete($id)
    {
        $this->authorize('super-admin');
        $this->employeeService->permanentDelete(id: $id);
        Toastr::success(EMPLOYEE_DELETE_200['message']);
        return back();
    }
}
