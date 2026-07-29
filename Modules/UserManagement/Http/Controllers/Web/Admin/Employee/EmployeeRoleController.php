<?php

namespace Modules\UserManagement\Http\Controllers\Web\Admin\Employee;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\AdminModule\Service\Interfaces\ActivityLogServiceInterface;
use Modules\UserManagement\Entities\Role;
use Modules\UserManagement\Http\Requests\RoleStoreOrUpdateRequest;
use Modules\UserManagement\Service\Interfaces\EmployeeRoleServiceInterface;
use Modules\UserManagement\Service\Interfaces\EmployeeServiceInterface;
use App\Exports\StyledReport\ColumnFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeRoleController extends BaseController
{
    protected $employeeRoleService;
    protected $employeeService;
    protected $activityLogService;

    public function __construct(EmployeeRoleServiceInterface $employeeRoleService, EmployeeServiceInterface $employeeService,
                                ActivityLogServiceInterface $activityLogService)
    {
        parent::__construct($employeeRoleService);
        $this->employeeRoleService = $employeeRoleService;
        $this->employeeService = $employeeService;
        $this->activityLogService = $activityLogService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('user_view');
        $roles = $this->employeeRoleService->index(criteria: $request?->all(), limit: paginationLimit(), offset: $request['page'] ?? 1);
        return view('usermanagement::admin.employee.role.index', compact('roles'));
    }

    public function store(RoleStoreOrUpdateRequest $request): RedirectResponse|Renderable
    {
        $this->authorize('user_add');
        $this->employeeRoleService->create($request->validated());
        Toastr::success(ROLE_STORE_200['message']);
        return back();

    }

    public function edit($id): Renderable
    {
        $this->authorize('user_edit');
        $role = $this->employeeRoleService->findOne(id: $id);
        return view('usermanagement::admin.employee.role.edit', compact('role'));
    }

    public function update(Request $request, $id): Renderable|RedirectResponse
    {
        $this->authorize('user_edit');
        $this->employeeRoleService->update(id: $id, data: $request->all());
        return redirect(route('admin.employee.role.index'));
    }

    public function destroy(string $id): RedirectResponse|Renderable
    {
        $employee = $this->employeeService->getBy(criteria: ['role_id' => $id]);
        if ($employee->count() > 0) {
            Toastr::error(ROLE_DESTROY_403['message']);
            return back();
        }
        $this->authorize('user_delete');
        $this->employeeRoleService->permanentDelete(id: $id);
        Toastr::success(ROLE_DESTROY_200['message']);
        return back();
    }


    /**
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $this->authorize('user_edit');
        $validated = $request->validate([
            'status' => 'boolean',
        ]);
        $role = $this->employeeRoleService->statusChange(id: $id, data: $request->all());
        return response()->json($role);
    }

    public function getRoles(Request $request)
    {
        if ($request->has('employee_id')) {
            $employee = $this->employeeService->findOne(id: $request->employee_id);

        } else {
            $employee = null;
        }
        $role = $this->employeeRoleService->findOne(id: $request->id);
        return response()->json(
            ['view' => view('usermanagement::admin.employee.partials._employee_roles', compact('role', 'employee'))->render()], 200);
    }

    public function export(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('user_export');
        $data = $this->employeeRoleService->export(criteria: $request->all(), orderBy: ['created_at' => 'desc']);
        $config = styledExportConfig(
            $data,
            title: 'Employee Roles',
            summary: [
                'Total Roles'    => $data->count(),
                'Active Roles'   => $data->where('Status', 'Active')->count(),
                'Inactive Roles' => $data->where('Status', 'Inactive')->count(),
            ],
            filters: [
                'Search' => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'Status' => ColumnFormat::STATUS,
            ],
            fileName: 'employee-roles-' . time() . '.xlsx',
            headings: ['Name', 'Modules', 'Status'],
        );
        return exportData($data, $request['file'], 'usermanagement::admin.employee.role.print', $config);
    }

    public function log(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('user_log');
        $request->merge(['logable_type' => Role::class]);
        $logs = $this->activityLogService->log($request->all());
        $file = array_key_exists('file', $request->all()) ? $request['file'] : '';
        return logViewerNew($logs,$file);
    }
}
