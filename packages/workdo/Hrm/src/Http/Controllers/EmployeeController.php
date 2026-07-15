<?php

namespace Workdo\Hrm\Http\Controllers;

use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Http\Requests\StoreEmployeeRequest;
use Workdo\Hrm\Http\Requests\UpdateEmployeeRequest;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\User;
use Workdo\Hrm\Models\Branch;
use Workdo\Hrm\Models\Department;
use Workdo\Hrm\Models\Designation;
use Workdo\Hrm\Models\EmployeeDocumentType;
use Workdo\Hrm\Models\EmployeeDocument;
use Workdo\Hrm\Models\Shift;
use Workdo\Hrm\Events\CreateEmployee;
use Workdo\Hrm\Events\DestroyEmployee;
use Workdo\Hrm\Events\UpdateEmployee;
use Workdo\Hrm\Models\Attendance;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    private function checkEmployeeAccess(Employee $employee)
    {
        if(Auth::user()->can('manage-any-employees')) {
            return $employee->created_by == creatorId();
        } elseif(Auth::user()->can('manage-own-employees')) {
            return ($employee->creator_id == Auth::id() || $employee->user_id == Auth::id());
        }
        return false;
    }
    public function index()
    {
        if (Auth::user()->can('manage-employees')) {
            $employees = Employee::query()
                ->with(['user:id,name,email,avatar,is_disable', 'branch', 'department', 'designation', 'shift'])
                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-employees')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-employees')) {
                        $q->where('creator_id',Auth::id())->orWhere('user_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('employee_id'), function ($q) {
                    $q->where(function ($query) {
                        $query->where('employee_id', 'like', '%' . request('employee_id') . '%');
                        $query->orWhereHas('user', function($userQuery) {
                            $userQuery->where('name', 'like', '%' . request('employee_id') . '%');
                        });
                    });
                })
                ->when(request('branch_id') && request('branch_id') !== 'all', fn($q) => $q->where('branch_id', request('branch_id')))
                ->when(request('department_id') && request('department_id') !== 'all', fn($q) => $q->where('department_id', request('department_id')))
                ->when(request('employment_type') !== null && request('employment_type') !== '', fn($q) => $q->where('employment_type', request('employment_type')))
                ->when(request('gender') !== null && request('gender') !== '', fn($q) => $q->where('gender', request('gender')))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            return Inertia::render('Hrm/Employees/Index', [
                'employees' => $employees,
                'users' => User::emp()->where('created_by', creatorId())->select('id', 'name')->get(),
                'branches' => Branch::where('created_by', creatorId())->select('id', 'branch_name')->get(),
                'departments' => Department::where('created_by', creatorId())->select('id', 'department_name', 'branch_id')->get(),
                'designations' => Designation::where('created_by', creatorId())->select('id', 'designation_name', 'branch_id', 'department_id')->get(),
                'shifts' => Shift::where('created_by', creatorId())->select('id', 'shift_name')->get(),
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function create()
    {
        if (Auth::user()->can('create-employees')) {
            return Inertia::render('Hrm/Employees/Create', [
                'users' => User::emp()->where('created_by', creatorId())->whereNotIn('id', Employee::where('created_by', creatorId())->pluck('user_id'))->select('id', 'name')->get(),
                'branches' => Branch::where('created_by', creatorId())->select('id', 'branch_name')->get(),
                'departments' => Department::where('created_by', creatorId())->select('id', 'department_name', 'branch_id')->get(),
                'designations' => Designation::where('created_by', creatorId())->select('id', 'designation_name', 'branch_id', 'department_id')->get(),
                'shifts' => Shift::where('created_by', creatorId())->select('id', 'shift_name')->get(),
                'documentTypes' => EmployeeDocumentType::where('created_by', creatorId())->select('id', 'document_name', 'is_required')->get(),
                'generatedEmployeeId' => Employee::generateEmployeeId(),
            ]);
        } else {
            return redirect()->route('hrm.employees.index')->with('error', __('Permission denied'));
        }
    }

    public function store(StoreEmployeeRequest $request)
    {
        if (Auth::user()->can('create-employees')) {
            $validated = $request->validated();
            $employee = new Employee();
            $employee->employee_id = $validated['employee_id'];
            $employee->date_of_birth = $validated['date_of_birth'];
            $employee->gender = $validated['gender'];
            $employee->shift = $validated['shift_id'];
            $employee->date_of_joining = $validated['date_of_joining'];
            $employee->employment_type = $validated['employment_type'];
            $employee->address_line_1 = $validated['address_line_1'];
            $employee->address_line_2 = $validated['address_line_2'];
            $employee->city = $validated['city'];
            $employee->state = $validated['state'];
            $employee->country = $validated['country'];
            $employee->postal_code = $validated['postal_code'];
            $employee->emergency_contact_name = $validated['emergency_contact_name'];
            $employee->emergency_contact_relationship = $validated['emergency_contact_relationship'];
            $employee->emergency_contact_number = $validated['emergency_contact_number'];
            $employee->bank_name = $validated['bank_name'];
            $employee->account_holder_name = $validated['account_holder_name'];
            $employee->account_number = $validated['account_number'];
            $employee->bank_identifier_code = $validated['bank_identifier_code'];
            $employee->bank_branch = $validated['bank_branch'];
            $employee->tax_payer_id = $validated['tax_payer_id'];
            $employee->basic_salary = $validated['basic_salary'];
            $employee->hours_per_day = $validated['hours_per_day'];
            $employee->days_per_week = $validated['days_per_week'];
            $employee->rate_per_hour = $validated['rate_per_hour'];
            $employee->user_id = $validated['user_id'];
            $employee->branch_id = $validated['branch_id'];
            $employee->department_id = $validated['department_id'];
            $employee->designation_id = $validated['designation_id'];

            $employee->creator_id = Auth::id();
            $employee->created_by = creatorId();
            $employee->save();

            // Load user relationship for email access
            $employee->load('user');

           

            // Store documents
            if ($request->has('documents')) {
                foreach ($request->input('documents', []) as $index => $document) {
                    if ($request->hasFile("documents.{$index}.file") && !empty($document['document_type_id'])) {
                        $file = $request->file("documents.{$index}.file");

                        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        $extension = $file->getClientOriginalExtension();
                        $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                        $upload = upload_file($request, "documents.{$index}.file", $fileNameToStore, 'employee_documents');

                        if (isset($upload['flag']) && $upload['flag'] == 1 && isset($upload['url'])) {
                            EmployeeDocument::create([
                                'user_id' => $employee->id,
                                'document_type_id' => $document['document_type_id'],
                                'file_path' => $upload['url'],
                                'creator_id' => Auth::id(),
                                'created_by' => creatorId(),
                            ]);
                        }
                    }
                }
            }
            CreateEmployee::dispatch($request, $employee);
            return redirect()->route('hrm.employees.index')->with('success', __('The employee has been created successfully.'));
        } else {
            return redirect()->route('hrm.employees.index')->with('error', __('Permission denied'));
        }
    }

    public function edit(Employee $employee)
    {
        if (Auth::user()->can('edit-employees')) {
            if(!$this->checkEmployeeAccess($employee)) {
                return redirect()->route('hrm.employees.index')->with('error', __('Permission denied'));
            }
            $existingDocuments = EmployeeDocument::where('user_id', $employee->id)
                ->with('documentType')
                ->get()
                ->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'document_type_id' => $doc->document_type_id,
                        'file_path' => $doc->file_path,
                        'document_name' => $doc->documentType->document_name ?? '',
                    ];
                });

            return Inertia::render('Hrm/Employees/Edit', [
                'employee' => $employee,
                'users' => User::emp()->where('created_by', creatorId())->select('id', 'name')->get(),
                'branches' => Branch::where('created_by', creatorId())->select('id', 'branch_name')->get(),
                'departments' => Department::where('created_by', creatorId())->select('id', 'department_name', 'branch_id')->get(),
                'designations' => Designation::where('created_by', creatorId())->select('id', 'designation_name', 'branch_id', 'department_id')->get(),
                'shifts' => Shift::where('created_by', creatorId())->select('id', 'shift_name')->get(),
                'documentTypes' => EmployeeDocumentType::where('created_by', creatorId())->select('id', 'document_name', 'is_required')->get(),
                'existingDocuments' => $existingDocuments,
            ]);
        } else {
            return redirect()->route('hrm.employees.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        if (Auth::user()->can('edit-employees')) {
            $validated = $request->validated();
            $employee->date_of_birth = $validated['date_of_birth'];
            $employee->gender = $validated['gender'];
            $employee->shift = $validated['shift_id'];
            $employee->date_of_joining = $validated['date_of_joining'];
            $employee->employment_type = $validated['employment_type'];
            $employee->address_line_1 = $validated['address_line_1'];
            $employee->address_line_2 = $validated['address_line_2'];
            $employee->city = $validated['city'];
            $employee->state = $validated['state'];
            $employee->country = $validated['country'];
            $employee->postal_code = $validated['postal_code'];
            $employee->emergency_contact_name = $validated['emergency_contact_name'];
            $employee->emergency_contact_relationship = $validated['emergency_contact_relationship'];
            $employee->emergency_contact_number = $validated['emergency_contact_number'];
            $employee->bank_name = $validated['bank_name'];
            $employee->account_holder_name = $validated['account_holder_name'];
            $employee->account_number = $validated['account_number'];
            $employee->bank_identifier_code = $validated['bank_identifier_code'];
            $employee->bank_branch = $validated['bank_branch'];
            $employee->tax_payer_id = $validated['tax_payer_id'];
            $employee->basic_salary = $validated['basic_salary'];
            $employee->hours_per_day = $validated['hours_per_day'];
            $employee->days_per_week = $validated['days_per_week'];
            $employee->rate_per_hour = $validated['rate_per_hour'];
            $employee->branch_id = $validated['branch_id'];
            $employee->department_id = $validated['department_id'];
            $employee->designation_id = $validated['designation_id'];

            $employee->save();


            // Handle document updates
            if ($request->has('documents')) {
                foreach ($request->input('documents', []) as $index => $document) {
                    if ($request->hasFile("documents.{$index}.file") && !empty($document['document_type_id'])) {
                        $file = $request->file("documents.{$index}.file");

                        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        $extension = $file->getClientOriginalExtension();
                        $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                        $upload = upload_file($request, "documents.{$index}.file", $fileNameToStore, 'employee_documents');

                        if (isset($upload['flag']) && $upload['flag'] == 1 && isset($upload['url'])) {
                            EmployeeDocument::create([
                                'user_id' => $employee->id,
                                'document_type_id' => $document['document_type_id'],
                                'file_path' => $upload['url'],
                                'creator_id' => Auth::id(),
                                'created_by' => creatorId(),
                            ]);
                        }
                    }
                }
            }
            UpdateEmployee::dispatch($request, $employee);

            return redirect()->route('hrm.employees.index')->with('success', __('The employee details are updated successfully.'));
        } else {
            return redirect()->route('hrm.employees.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(Employee $employee)
    {
        if (Auth::user()->can('delete-employees')) {
            DestroyEmployee::dispatch($employee);
            $employee->delete();

            return redirect()->back()->with('success', __('The employee has been deleted.'));
        } else {
            return redirect()->route('hrm.employees.index')->with('error', __('Permission denied'));
        }
    }

    public function show(Employee $employee)
    {
        if (Auth::user()->can('view-employees')) {
            if(!$this->checkEmployeeAccess($employee)) {
                return redirect()->route('hrm.employees.index')->with('error', __('Permission denied'));
            }
            $employee->load(['user:id,name,email,avatar', 'branch', 'department', 'designation', 'shift']);
            $documents = EmployeeDocument::where('user_id', $employee->id)
                ->with('documentType')
                ->get()
                ->map(function($doc) {
                    return [
                        'id' => $doc->id,
                        'document_type_id' => $doc->document_type_id,
                        'file_path' => $doc->file_path,
                        'document_name' => $doc->documentType->document_name ?? '',
                    ];
                });

            $attendanceFilters = $this->normalizeAttendanceFilters();
            $attendanceQuery = Attendance::query()
                ->where('created_by', creatorId())
                ->where('employee_id', $employee->user_id)
                ->when($attendanceFilters['from'], fn ($query, $from) => $query->whereDate('date', '>=', $from))
                ->when($attendanceFilters['to'], fn ($query, $to) => $query->whereDate('date', '<=', $to));

            $attendanceSummary = (clone $attendanceQuery)
                ->selectRaw('COUNT(*) as total_records')
                ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count")
                ->selectRaw("SUM(CASE WHEN status IN ('half day', 'half_day') THEN 1 ELSE 0 END) as half_day_count")
                ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count")
                ->selectRaw("SUM(CASE WHEN work_status = 'completed' THEN 1 ELSE 0 END) as completed_count")
                ->selectRaw("SUM(CASE WHEN work_status IN ('working', 'paused') THEN 1 ELSE 0 END) as working_count")
                ->selectRaw('COALESCE(SUM(total_hour), 0) as net_hours')
                ->selectRaw('COALESCE(SUM(overtime_hours), 0) as overtime_hours')
                ->first();

            return Inertia::render('Hrm/Employees/Show', [
                'employee' => $employee,
                'documents' => $documents,
                'attendanceFilters' => $attendanceFilters,
                'attendanceSummary' => [
                    'total_records' => (int) ($attendanceSummary->total_records ?? 0),
                    'present_count' => (int) ($attendanceSummary->present_count ?? 0),
                    'half_day_count' => (int) ($attendanceSummary->half_day_count ?? 0),
                    'absent_count' => (int) ($attendanceSummary->absent_count ?? 0),
                    'completed_count' => (int) ($attendanceSummary->completed_count ?? 0),
                    'working_count' => (int) ($attendanceSummary->working_count ?? 0),
                    'net_hours' => round((float) ($attendanceSummary->net_hours ?? 0), 2),
                    'overtime_hours' => round((float) ($attendanceSummary->overtime_hours ?? 0), 2),
                ],
                'attendanceHistory' => (clone $attendanceQuery)
                    ->with(['shift', 'intervals', 'actionLogs', 'correctionRequests.requester', 'correctionRequests.reviewer'])
                    ->latest('date')->paginate(15, ['*'], 'attendance_page')->withQueryString(),
            ]);
        } else {
            return redirect()->route('hrm.employees.index')->with('error', __('Permission denied'));
        }
    }

    private function normalizeAttendanceFilters(): array
    {
        $filter = request('attendance_filter', 'month');

        request()->validate([
            'tab' => ['nullable', 'in:attendance,employment,contact,banking,hours,documents'],
            'attendance_filter' => ['nullable', 'in:today,yesterday,date,month,range,all'],
            'attendance_date' => ['nullable', 'required_if:attendance_filter,date', 'date_format:Y-m-d'],
            'attendance_month' => ['nullable', 'date_format:Y-m'],
            'attendance_from' => ['nullable', 'required_if:attendance_filter,range', 'date_format:Y-m-d'],
            'attendance_to' => ['nullable', 'required_if:attendance_filter,range', 'date_format:Y-m-d', 'after_or_equal:attendance_from'],
            'attendance_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $today = Carbon::today();
        $from = null;
        $to = null;
        $label = __('All Time');

        if ($filter === 'today') {
            $from = $to = $today->toDateString();
            $label = __('Today').' · '.$today->format('M j, Y');
        } elseif ($filter === 'yesterday') {
            $from = $to = $today->copy()->subDay()->toDateString();
            $label = __('Yesterday').' · '.$today->copy()->subDay()->format('M j, Y');
        } elseif ($filter === 'date') {
            $from = $to = request('attendance_date');
            $label = Carbon::parse($from)->format('M j, Y');
        } elseif ($filter === 'month') {
            $month = request('attendance_month', $today->format('Y-m'));
            $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $from = $monthDate->toDateString();
            $to = $monthDate->copy()->endOfMonth()->toDateString();
            $label = $monthDate->format('F Y');
        } elseif ($filter === 'range') {
            $from = request('attendance_from');
            $to = request('attendance_to');
            $label = Carbon::parse($from)->format('M j, Y').' – '.Carbon::parse($to)->format('M j, Y');
        }

        return [
            'filter' => $filter,
            'date' => request('attendance_date', $today->toDateString()),
            'month' => request('attendance_month', $today->format('Y-m')),
            'from' => $from,
            'to' => $to,
            'range_from' => request('attendance_from', $today->copy()->startOfMonth()->toDateString()),
            'range_to' => request('attendance_to', $today->copy()->endOfMonth()->toDateString()),
            'label' => $label,
        ];
    }

    public function deleteDocument($employeeId, EmployeeDocument $document)
    {
        if (Auth::user()->can('edit-employees')) {
            if ($document->user_id != $employeeId) {
                return redirect()->back()->with('error', __('Document not found'));
            }

            delete_file($document->file_path);
            $document->delete();

            return redirect()->back()->with('success', __('The document has been deleted successfully'));
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }
}
