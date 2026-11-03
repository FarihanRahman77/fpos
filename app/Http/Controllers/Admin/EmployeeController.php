<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\SafeJsonErrors;
use App\Models\Admin\Employee;
use App\Models\Admin\EmployeeDocument;
use App\Models\Admin\EmployeeEducation;
use App\Models\Admin\EmployeeExperience;
use App\Models\Admin\Setting;
use App\Traits\SequentialCodeTrait;
use App\Traits\VoucherHelperTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Milon\Barcode\DNS1D;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use PDF;

class EmployeeController extends Controller
{
    use SequentialCodeTrait, VoucherHelperTrait, SafeJsonErrors;

    public const DOCUMENT_TYPES = [
        'NID', 'Passport', 'Driving License', 'Appointment Letter', 'Joining Letter', 'Birth Certificate',
        'Educational Certificate', 'Experience Certificate', 'TIN Certificate', 'Police Clearance',
        'Medical Certificate', 'Other',
    ];

    private const GENDERS          = ['Male', 'Female', 'Other'];
    private const MARITAL_STATUSES = ['Single', 'Married', 'Divorced', 'Widowed'];
    private const BLOOD_GROUPS     = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    private const EMPLOYMENT_TYPES = ['Permanent', 'Probation', 'Contractual', 'Part-Time', 'Intern'];
    private const PAYMENT_MODES    = ['Cash', 'Bank', 'Mobile Banking'];

    private const UPLOAD_DIR = 'uploads/employees';
    // Separate rules: a '|' inside a rules array is not split, so it would become part of the mimes list.
    private const IMAGE_RULE = ['mimes:jpg,jpeg,png,webp', 'max:2048'];
    private const DOC_RULE   = ['mimes:jpg,jpeg,png,webp,pdf', 'max:4096'];

    /* ------------------------------------------------------------------ */
    /*  Page                                                              */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        $active = BranchController::scope(DB::table('employees'), 'branch_id')->where('deleted', 'No');

        return view('admin.payroll.employee.index', [
            'documentTypes'   => self::DOCUMENT_TYPES,
            'genders'         => self::GENDERS,
            'maritalStatuses' => self::MARITAL_STATUSES,
            'bloodGroups'     => self::BLOOD_GROUPS,
            'employmentTypes' => self::EMPLOYMENT_TYPES,
            'paymentModes'    => self::PAYMENT_MODES,
            // Scale or Contractual for everyone; chosen on the Payroll Setup page.
            'payroll'         => PayrollSetupController::salarySettings(),
            // Managed on the Payroll Setup page.
            'departments'     => $this->setupRows('departments'),
            'designations'    => $this->setupRows('designations', ['department_id']),
            'grades'          => $this->setupRows('pay_grades'),
            'leaveGroups'     => $this->setupRows('leave_groups'),
            // Free-text field with suggestions from what is already used.
            'shifts'          => (clone $active)->whereNotNull('work_shift')->distinct()->orderBy('work_shift')->pluck('work_shift'),
            // Attendance shifts (Attendance > Setup).
            'workShifts'      => DB::table('work_shifts')->where('deleted', 'No')->orderBy('start_time')->get(['id', 'name', 'status']),
        ]);
    }

    /**
     * Steps of a pay grade with their salary, for the employee form (and the setup page's
     * "next step" hint with ?all=1). Steps of the given department and the all-department
     * steps are listed; ?exact=1 lists only the given department's (or the all-department) steps.
     * ?include=<step id> keeps an employee's current step in the list even when it has been set Inactive.
     */
    public function steps(Request $request)
    {
        $departmentId = $request->filled('department_id') ? (int) $request->query('department_id') : null;

        $steps = DB::table('pay_steps')
            ->where('pay_grade_id', (int) $request->query('grade_id'))
            ->where('deleted', 'No')
            ->where(function ($q) use ($request, $departmentId) {
                if ($request->boolean('exact')) {
                    $departmentId ? $q->where('department_id', $departmentId) : $q->whereNull('department_id');
                } else {
                    $q->whereNull('department_id')->when($departmentId, fn ($w) => $w->orWhere('department_id', $departmentId));
                }
            })
            ->when(!$request->boolean('all'), fn ($q) => $q->where(fn ($w) => $w->where('status', 'Active')
                ->orWhere('id', (int) $request->query('include'))))
            ->orderByRaw('department_id IS NULL')->orderBy('step_no')
            ->get(['id', 'step_no', 'status', 'department_id', 'basic_salary'])
            ->map(fn ($s) => (array) $s + ['gross_salary' => PayrollSetupController::salaryBreakdown((float) $s->basic_salary, false)['gross_salary']]);

        return response()->json(['status' => true, 'data' => $steps]);
    }

    /* ------------------------------------------------------------------ */
    /*  DataTable                                                         */
    /* ------------------------------------------------------------------ */

    public function list(Request $request)
    {
        try {
            $query = BranchController::scope(DB::table('employees as e'), 'e.branch_id')
                ->leftJoin('pay_steps as ps', 'ps.id', '=', 'e.step_id')
                ->leftJoin('pay_grades as pg', 'pg.id', '=', 'ps.pay_grade_id')
                ->where('e.deleted', 'No')
                ->select('e.*', 'ps.step_no', 'pg.name as grade_name');

            if ($request->filled('department_id')) {
                $query->where('e.department_id', (int) $request->query('department_id'));
            }

            if ($request->filled('employment_type')) {
                $query->where('e.employment_type', $request->query('employment_type'));
            }

            $isScale = PayrollSetupController::isScale();

            if (in_array($request->query('status'), ['Active', 'Inactive'], true)) {
                $query->where('e.status', $request->query('status'));
            }

            $employees = $query->orderBy('e.employee_code')->get();

            $output = ['data' => []];
            $i = 1;

            foreach ($employees as $emp) {
                $id = (int) $emp->id;

                $photo = $emp->photo && is_file(public_path($emp->photo))
                    ? '<img src="' . e(asset($emp->photo)) . '" class="emp-thumb" alt="">'
                    : '<span class="emp-thumb emp-initial">' . e(mb_strtoupper(mb_substr($emp->name, 0, 1))) . '</span>';

                $joined = $emp->joining_date ? Carbon::parse($emp->joining_date) : null;

                $statusBtn = '<button type="button" class="btn btn-sm badge border-0 ' . ($emp->status === 'Active' ? 'bg-success' : 'bg-secondary') . '"'
                    . ' title="Click to change" onclick="toggleEmployeeStatus(' . $id . ')">' . e($emp->status) . '</button>';

                $actions = action_dropdown([
                    ['label' => 'View Profile', 'icon' => 'fas fa-eye', 'onclick' => 'viewEmployee(' . $id . ')'],
                    ['label' => 'Edit', 'icon' => 'fas fa-edit', 'onclick' => 'editEmployee(' . $id . ')'],
                    ['label' => 'ID Card (PDF)', 'icon' => 'fas fa-id-badge', 'onclick' => 'printIdCard(' . $id . ')'],
                    ['label' => 'Profile (PDF)', 'icon' => 'fas fa-file-pdf', 'onclick' => 'printProfile(' . $id . ')'],
                    ['label' => 'PF Ledger', 'icon' => 'fas fa-piggy-bank', 'onclick' => "window.open('" . route('admin.reports.pf-ledger', ['employee_id' => $id]) . "', '_blank')"],
                    ['divider' => true],
                    ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger', 'onclick' => 'deleteEmployee(' . $id . ')'],
                ], $emp->branch_id);

                $output['data'][] = [
                    $i++,
                    '<div class="d-flex align-items-center gap-2">' . $photo . '<div>'
                        . '<div class="fw-semibold">' . e($emp->name) . '</div>'
                        . '<div class="text-muted small">ID: ' . e($emp->employee_code) . '</div>' . branch_tag($emp->branch_id) . '</div></div>',
                    e($emp->designation ?: '-') . ($emp->department ? '<div class="text-muted small">' . e($emp->department) . '</div>' : ''),
                    e($emp->phone ?: '-') . ($emp->email ? '<div class="text-muted small">' . e($emp->email) . '</div>' : ''),
                    e($emp->employment_type ?: '-'),
                    $joined ? '<span class="d-none">' . $joined->format('Y-m-d') . '</span>' . e($joined->format('d M Y')) : '<span class="text-muted">-</span>',
                    '<span class="d-none">' . sprintf('%021.2f', (float) $emp->gross_salary) . '</span>'
                        . '<span class="d-block text-end">' . number_format((float) $emp->gross_salary, 2) . '</span>'
                        . '<div class="text-muted small text-end">'
                        . ($isScale
                            ? ($emp->grade_name ? e($emp->grade_name) . ' / Step ' . (int) $emp->step_no : '<span class="text-danger">No step</span>')
                            : 'Contract')
                        . ' · Net ' . number_format((float) $emp->net_salary, 2)
                        . '</div>',
                    $statusBtn,
                    $actions,
                ];
            }

            $all = BranchController::scope(DB::table('employees'), 'branch_id')->where('deleted', 'No');

            $output['summary'] = [
                'active'      => (clone $all)->where('status', 'Active')->count(),
                'inactive'    => (clone $all)->where('status', 'Inactive')->count(),
                'departments' => (clone $all)->whereNotNull('department_id')->distinct()->count('department_id'),
                'payroll'     => number_format((float) (clone $all)->where('status', 'Active')->sum('gross_salary'), 2),
            ];

            return response()->json($output);
        } catch (Throwable $e) {
            Log::error('Employee list error', $this->ctx($e));

            return response()->json(['data' => [], 'message' => 'Unable to load employees.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  One employee with documents, education, experience                */
    /* ------------------------------------------------------------------ */

    public function show(Request $request)
    {
        $employee = $this->findEmployee((int) $request->query('id'));

        if (!$employee) {
            return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
        }

        $data = (array) $employee;

        foreach (['date_of_birth', 'joining_date', 'confirmation_date', 'resign_date', 'card_valid_until'] as $col) {
            $data[$col] = $employee->$col ? Carbon::parse($employee->$col)->format('Y-m-d') : '';
        }

        $data['photo_url']     = $this->fileUrl($employee->photo);
        $data['signature_url'] = $this->fileUrl($employee->signature);
        $data['salary_structure'] = PayrollSetupController::salarySettings()->salary_structure;
        $step = $employee->step_id
            ? DB::table('pay_steps as ps')->join('pay_grades as pg', 'pg.id', '=', 'ps.pay_grade_id')
                ->where('ps.id', $employee->step_id)->first(['ps.pay_grade_id', 'ps.step_no', 'pg.name as grade_name'])
            : null;
        $data['pay_grade_id'] = $step->pay_grade_id ?? null;
        $data['grade_name']   = $step->grade_name ?? null;
        $data['step_no']      = $step->step_no ?? null;

        $data['reporting_to_label'] = $employee->reporting_to
            ? DB::table('employees')->where('id', $employee->reporting_to)->selectRaw("CONCAT(name, ' (', employee_code, ')') as label")->value('label')
            : '';

        $data['documents'] = $this->documentsOf($employee->id)->map(function ($d) {
            return [
                'id'             => $d->id,
                'document_type'  => $d->document_type,
                'document_no'    => $d->document_no,
                'issue_date'     => $d->issue_date ? Carbon::parse($d->issue_date)->format('Y-m-d') : '',
                'expiry_date'    => $d->expiry_date ? Carbon::parse($d->expiry_date)->format('Y-m-d') : '',
                'issued_by'      => $d->issued_by,
                'remarks'        => $d->remarks,
                'file_front_url' => $this->fileUrl($d->file_front),
                'file_back_url'  => $this->fileUrl($d->file_back),
                'front_is_image' => $this->isImage($d->file_front),
                'back_is_image'  => $this->isImage($d->file_back),
                'expired'        => $d->expiry_date && Carbon::parse($d->expiry_date)->lt(now()->startOfDay()),
            ];
        });

        $data['educations']  = $this->rowsOf('employee_educations', $employee->id, ['degree', 'institute', 'board_university', 'passing_year', 'result']);
        $data['experiences'] = $this->rowsOf('employee_experiences', $employee->id, ['company_name', 'designation', 'from_date', 'to_date', 'responsibilities'])
            ->map(function ($x) {
                $x->from_date = $x->from_date ? Carbon::parse($x->from_date)->format('Y-m-d') : '';
                $x->to_date   = $x->to_date ? Carbon::parse($x->to_date)->format('Y-m-d') : '';

                return $x;
            });

        return response()->json(['status' => true, 'data' => $data]);
    }

    /** Select2 search for the "Reporting to" field. */
    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        $rows = BranchController::scopeOwn(DB::table('employees'), 'branch_id')
            ->where('deleted', 'No')
            ->when($request->filled('exclude'), fn ($q) => $q->where('id', '<>', (int) $request->query('exclude')))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('employee_code', 'like', "%{$term}%")))
            ->orderBy('name')->limit(20)
            ->get(['id', 'name', 'employee_code', 'designation']);

        return response()->json([
            'results' => $rows->map(fn ($r) => [
                'id'   => $r->id,
                'text' => $r->name . ' (' . $r->employee_code . ')' . ($r->designation ? ' - ' . $r->designation : ''),
            ]),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Create / update (update when "id" is posted). Multipart form.     */
    /* ------------------------------------------------------------------ */

    public function store(Request $request)
    {
        $id = $request->filled('id') ? (int) $request->input('id') : null;

        $date    = ['nullable', 'date'];
        $isScale = PayrollSetupController::isScale();

        $rules = [
            'id'                         => ['nullable', 'integer'],
            // Personal
            'name'                       => ['required', 'string', 'max:150'],
            'father_name'                => ['nullable', 'string', 'max:150'],
            'mother_name'                => ['nullable', 'string', 'max:150'],
            'spouse_name'                => ['nullable', 'string', 'max:150'],
            'date_of_birth'              => ['nullable', 'date', 'before:today'],
            'gender'                     => ['nullable', Rule::in(self::GENDERS)],
            'marital_status'             => ['nullable', Rule::in(self::MARITAL_STATUSES)],
            'blood_group'                => ['nullable', Rule::in(self::BLOOD_GROUPS)],
            'religion'                   => ['nullable', 'string', 'max:50'],
            'nationality'                => ['nullable', 'string', 'max:50'],
            'nid_no'                     => ['nullable', 'string', 'max:50'],
            'tin_no'                     => ['nullable', 'string', 'max:50'],
            'photo'                      => ['nullable', 'file', ...self::IMAGE_RULE],
            'signature'                  => ['nullable', 'file', ...self::IMAGE_RULE],
            // Contact
            'phone'                      => ['required', 'string', 'max:30'],
            'alt_phone'                  => ['nullable', 'string', 'max:30'],
            'email'                      => ['nullable', 'email', 'max:150'],
            'present_address'            => ['nullable', 'string', 'max:1000'],
            'permanent_address'          => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name'     => ['nullable', 'string', 'max:150'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'emergency_contact_phone'    => ['nullable', 'string', 'max:30'],
            // Job
            'designation_id'             => ['required', 'integer', Rule::exists('designations', 'id')->where('deleted', 'No')],
            'department_id'              => ['nullable', 'integer', Rule::exists('departments', 'id')->where('deleted', 'No')],
            'employment_type'            => ['nullable', Rule::in(self::EMPLOYMENT_TYPES)],
            'leave_group_id'             => ['nullable', 'integer', Rule::exists('leave_groups', 'id')->where('deleted', 'No')],
            'work_shift'                 => ['nullable', 'string', 'max:50'],
            'work_shift_id'              => ['nullable', 'integer', Rule::exists('work_shifts', 'id')->where('deleted', 'No')],
            'device_user_id'             => ['nullable', 'string', 'max:30', Rule::unique('employees', 'device_user_id')->where('deleted', 'No')->ignore($id)],
            'reporting_to'               => ['nullable', 'integer', Rule::exists('employees', 'id')->where('deleted', 'No'), Rule::notIn(array_filter([$id]))],
            'joining_date'               => ['required', 'date'],   // salary, PF and leave are pro-rated from it
            'confirmation_date'          => ['nullable', 'date', 'after_or_equal:joining_date'],
            'resign_date'                => ['nullable', 'date', 'after_or_equal:joining_date'],
            'card_valid_until'           => $date,
            'status'                     => ['required', Rule::in(['Active', 'Inactive'])],
            'description'                => ['nullable', 'string', 'max:2000'],
            // Salary & payment (only the active structure's field is required)
            'step_id'                    => [$isScale ? 'required' : 'nullable', 'integer', Rule::exists('pay_steps', 'id')->where('deleted', 'No')],
            'contract_salary'            => [$isScale ? 'nullable' : 'required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'bonus_eligible'             => ['required', Rule::in(['Yes', 'No'])],
            'last_increment_date'        => $date,
            'salary_payment_mode'        => ['nullable', Rule::in(self::PAYMENT_MODES)],
            'bank_name'                  => ['nullable', 'required_if:salary_payment_mode,Bank', 'string', 'max:100'],
            'bank_branch'                => ['nullable', 'string', 'max:100'],
            'bank_account_name'          => ['nullable', 'string', 'max:150'],
            'bank_account_no'            => ['nullable', 'required_if:salary_payment_mode,Bank', 'string', 'max:50'],
            'mobile_banking_provider'    => ['nullable', 'required_if:salary_payment_mode,Mobile Banking', 'string', 'max:50'],
            'mobile_banking_no'          => ['nullable', 'required_if:salary_payment_mode,Mobile Banking', 'string', 'max:30'],

            // Documents (repeatable, with front / back scans)
            'documents'                  => ['nullable', 'array', 'max:30'],
            'documents.*.id'             => ['nullable', 'integer'],
            'documents.*.document_type'  => ['required', Rule::in(self::DOCUMENT_TYPES)],
            'documents.*.document_no'    => ['nullable', 'string', 'max:100'],
            'documents.*.issue_date'     => $date,
            'documents.*.expiry_date'    => $date,
            'documents.*.issued_by'      => ['nullable', 'string', 'max:150'],
            'documents.*.remarks'        => ['nullable', 'string', 'max:255'],
            'documents.*.file_front'     => ['nullable', 'file', ...self::DOC_RULE],
            'documents.*.file_back'      => ['nullable', 'file', ...self::DOC_RULE],

            // Education
            'educations'                    => ['nullable', 'array', 'max:20'],
            'educations.*.degree'           => ['required', 'string', 'max:100'],
            'educations.*.institute'        => ['nullable', 'string', 'max:150'],
            'educations.*.board_university' => ['nullable', 'string', 'max:150'],
            'educations.*.passing_year'     => ['nullable', 'string', 'max:10'],
            'educations.*.result'           => ['nullable', 'string', 'max:30'],

            // Experience
            'experiences'                    => ['nullable', 'array', 'max:20'],
            'experiences.*.company_name'     => ['required', 'string', 'max:150'],
            'experiences.*.designation'      => ['nullable', 'string', 'max:100'],
            'experiences.*.from_date'        => $date,
            'experiences.*.to_date'          => $date,
            'experiences.*.responsibilities' => ['nullable', 'string', 'max:500'],
        ];

        $validator = Validator::make($request->all(), $rules, [
            'name.required'                        => 'Enter the employee name.',
            'phone.required'                       => 'Enter a phone number.',
            'designation_id.required'              => 'Select the designation.',
            'joining_date.required'                => 'Enter the joining date (salary, PF and leave start from it).',
            'step_id.required'                     => 'Select the pay grade and step.',
            'contract_salary.required'             => 'Enter the contract salary.',
            'date_of_birth.before'                 => 'Date of birth must be in the past.',
            'reporting_to.not_in'                  => 'An employee cannot report to themselves.',
            'photo.mimes'                          => 'Photo must be a JPG, PNG or WEBP image.',
            'photo.max'                            => 'Photo must be 2 MB or smaller.',
            'signature.mimes'                      => 'Signature must be a JPG, PNG or WEBP image.',
            'documents.*.document_type.required'   => 'Select the document type.',
            'documents.*.file_front.mimes'         => 'Upload a JPG, PNG, WEBP or PDF file.',
            'documents.*.file_front.max'           => 'File must be 4 MB or smaller.',
            'documents.*.file_back.mimes'          => 'Upload a JPG, PNG, WEBP or PDF file.',
            'documents.*.file_back.max'            => 'File must be 4 MB or smaller.',
            'educations.*.degree.required'         => 'Enter the degree / exam.',
            'experiences.*.company_name.required'  => 'Enter the company name.',
            'bank_name.required_if'                => 'Bank name is required for bank payment.',
            'bank_account_no.required_if'          => 'Account number is required for bank payment.',
            'mobile_banking_provider.required_if'  => 'Provider is required for mobile banking.',
            'mobile_banking_no.required_if'        => 'Number is required for mobile banking.',
        ]);

        $validator->after(function ($v) use ($request, $isScale) {
            // A designation tied to a department can only be used in that department.
            $designationDept = DB::table('designations')->where('id', (int) $request->input('designation_id'))->value('department_id');
            if ($designationDept && (int) $designationDept !== (int) $request->input('department_id')) {
                $v->errors()->add('designation_id', 'This designation belongs to another department.');
            }

            // Same for a department-wise pay step.
            if ($isScale && $request->filled('step_id')) {
                $stepDept = DB::table('pay_steps')->where('id', (int) $request->input('step_id'))->value('department_id');
                if ($stepDept && (int) $stepDept !== (int) $request->input('department_id')) {
                    $v->errors()->add('step_id', 'This step belongs to another department.');
                }
            }

            if (!$isScale && (float) $request->input('contract_salary') <= 0) {
                $v->errors()->add('contract_salary', 'Contract salary must be greater than zero.');
            }

            foreach ((array) $request->input('documents', []) as $i => $doc) {
                if (!empty($doc['issue_date']) && !empty($doc['expiry_date']) && $doc['expiry_date'] < $doc['issue_date']) {
                    $v->errors()->add("documents.$i.expiry_date", 'Expiry date cannot be before the issue date.');
                }
            }
            foreach ((array) $request->input('experiences', []) as $i => $x) {
                if (!empty($x['from_date']) && !empty($x['to_date']) && $x['to_date'] < $x['from_date']) {
                    $v->errors()->add("experiences.$i.to_date", 'End date cannot be before the start date.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $newFiles = [];   // files moved during this request; deleted again if saving fails
        $oldFiles = [];   // replaced / removed files; deleted only after commit

        DB::beginTransaction();

        try {
            if ($id) {
                BranchController::guardOwn('employees', $id);
                $employee = BranchController::scopeOwn(Employee::where('id', $id), 'branch_id')->where('deleted', 'No')->lockForUpdate()->first();

                if (!$employee) {
                    abort(404, 'Employee not found.');
                }

                $employee->updated_by   = Auth::id();
                $employee->updated_date = now();
            } else {
                $employee = new Employee();
                $employee->employee_code = $this->nextSequentialCode(Employee::class, 'employee_code', '', 5);
                $employee->created_by    = Auth::id();
                $employee->created_date  = now();
            }

            $plain = [
                'name', 'father_name', 'mother_name', 'spouse_name', 'gender', 'marital_status', 'blood_group',
                'religion', 'nationality', 'nid_no', 'tin_no', 'phone', 'alt_phone', 'email', 'present_address',
                'permanent_address', 'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
                'employment_type', 'work_shift', 'description',
                'salary_payment_mode', 'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_no',
                'mobile_banking_provider', 'mobile_banking_no',
            ];

            foreach ($plain as $col) {
                $employee->$col = $this->nullIfBlank($request->input($col));
            }

            foreach (['date_of_birth', 'joining_date', 'confirmation_date', 'resign_date', 'card_valid_until'] as $col) {
                $employee->$col = $request->input($col) ?: null;
            }

            $employee->reporting_to   = $request->input('reporting_to') ?: null;
            $employee->leave_group_id = $request->input('leave_group_id') ?: null;
            $employee->work_shift_id  = $request->input('work_shift_id') ?: null;
            $employee->device_user_id = $this->nullIfBlank($request->input('device_user_id'));
            $employee->status       = $request->input('status', 'Active');

            // The name columns are kept as a copy for the ID card / profile PDFs.
            $employee->designation_id = (int) $request->input('designation_id');
            $employee->designation    = DB::table('designations')->where('id', $employee->designation_id)->value('name');
            $employee->department_id  = $request->input('department_id') ?: null;
            $employee->department     = $employee->department_id
                ? DB::table('departments')->where('id', $employee->department_id)->value('name')
                : null;

            // Only the active structure's input is posted; the other one keeps its saved value
            // so switching the structure later does not lose it.
            if ($isScale) {
                $employee->step_id             = (int) $request->input('step_id');
                $employee->last_increment_date = $request->input('last_increment_date') ?: null;
            } else {
                $employee->contract_salary = round((float) $request->input('contract_salary'), 2);
            }
            $employee->bonus_eligible = $request->input('bonus_eligible');

            // Photo / signature: new upload replaces, "remove_*" clears.
            foreach (['photo' => 'photos', 'signature' => 'signatures'] as $col => $dir) {
                if ($request->hasFile($col)) {
                    $oldFiles[] = $employee->$col;
                    $employee->$col = $newFiles[] = $this->upload($request->file($col), $dir);
                } elseif ($request->boolean('remove_' . $col)) {
                    $oldFiles[] = $employee->$col;
                    $employee->$col = null;
                }
            }

            $employee->save();

            // The amounts are always calculated, never taken from the form. A new employee's
            // first salary is effective from the joining date.
            PayrollSetupController::recalculateSalaries([$employee->id], $id ? 'Salary updated' : 'Joining',
                $id ? null : ($employee->joining_date ?: null));

            $this->saveDocuments($employee->id, $request, $newFiles, $oldFiles);

            $this->replaceRows('employee_educations', $employee->id, (array) $request->input('educations', []),
                ['degree', 'institute', 'board_university', 'passing_year', 'result']);

            $this->replaceRows('employee_experiences', $employee->id, (array) $request->input('experiences', []),
                ['company_name', 'designation', 'from_date', 'to_date', 'responsibilities']);

            DB::commit();

            $this->deleteFiles($oldFiles);

            return response()->json([
                'status'  => true,
                'message' => 'Employee ' . $employee->name . ' (' . $employee->employee_code . ')' . ($id ? ' updated' : ' created') . ' successfully.',
                'id'      => $employee->id,
            ]);
        } catch (HttpExceptionInterface $e) {
            DB::rollBack();
            $this->deleteFiles($newFiles);

            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            DB::rollBack();
            $this->deleteFiles($newFiles);
            Log::error('Employee store error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the employee. Please try again.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Status / delete                                                   */
    /* ------------------------------------------------------------------ */

    public function status(Request $request)
    {
        BranchController::guardOwn('employees', $request->input('id'));
        $employee = BranchController::scopeOwn(Employee::where('id', (int) $request->input('id')), 'branch_id')->where('deleted', 'No')->first();

        if (!$employee) {
            return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
        }

        $employee->status       = $employee->status === 'Active' ? 'Inactive' : 'Active';
        $employee->updated_by   = Auth::id();
        $employee->updated_date = now();
        $employee->save();

        return response()->json(['status' => true, 'message' => $employee->name . ' is now ' . $employee->status . '.']);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), ['id' => ['required', 'integer', 'min:1']]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'A valid employee id is required.'], 422);
        }

        try {
            DB::transaction(function () use ($request) {
                BranchController::guardOwn('employees', $request->input('id'));
                $employee = BranchController::scopeOwn(Employee::where('id', (int) $request->input('id')), 'branch_id')->where('deleted', 'No')->lockForUpdate()->first();

                if (!$employee) {
                    abort(404, 'Employee not found.');
                }

                if (DB::table('employees')->where('reporting_to', $employee->id)->where('deleted', 'No')->exists()) {
                    abort(422, 'Other employees report to ' . $employee->name . '. Change their "Reporting to" first.');
                }

                // A deleted employee drops off the salary sheet: settle what is still open first.
                $loanLeft = (float) DB::table('employee_loans')->where('employee_id', $employee->id)->where('deleted', 'No')
                    ->where('loan_status', 'Running')->selectRaw('COALESCE(SUM(amount - recovered), 0) as b')->value('b');
                if ($loanLeft > 0.004) {
                    abort(422, $employee->name . ' still owes ' . number_format($loanLeft, 2) . ' on a loan / advance. Recover or cancel it first, or set the employee Inactive.');
                }
                $suspense = SuspenseController::totals('Employee', (int) $employee->id)['outstanding'] ?? 0;
                if (abs($suspense) > 0.004) {
                    abort(422, $suspense > 0
                        ? $employee->name . ' still holds ' . number_format($suspense, 2) . ' of suspense (office advance). Return or adjust it first, or set the employee Inactive.'
                        : 'The office still owes ' . $employee->name . ' ' . number_format(-$suspense, 2) . ' (suspense deposit). Pay it first, or set the employee Inactive.');
                }
                $salaryDue = (float) DB::table('payroll_run_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
                    ->where('i.employee_id', $employee->id)->where('r.run_status', 'Posted')
                    ->selectRaw('COALESCE(SUM(i.net_payable - i.paid_amount), 0) as d')->value('d');
                if ($salaryDue > 0.004) {
                    abort(422, number_format($salaryDue, 2) . ' of processed salary is still unpaid to ' . $employee->name . '. Pay it first, or set the employee Inactive.');
                }

                // Soft delete keeps the files and the expense history.
                DB::table('employees')->where('id', $employee->id)->update($this->deletedColumns());

                foreach (['employee_documents', 'employee_educations', 'employee_experiences'] as $table) {
                    DB::table($table)->where('employee_id', $employee->id)->where('deleted', 'No')->update($this->deletedColumns());
                }
            });

            return response()->json(['status' => true, 'message' => 'Employee deleted.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Employee delete error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to delete the employee. Please try again.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  PDFs                                                              */
    /* ------------------------------------------------------------------ */

    /** CR80 ID card (54 x 85.6 mm, portrait): page 1 front, page 2 back. */
    public function idCard(Request $request)
    {
        $employee = $this->findEmployee((int) $request->query('id'));
        abort_unless($employee !== null, 404, 'Employee not found.');

        $mm = 72 / 25.4;

        // the PDF template's colors/fonts ($pdfT) and company ($pdfCo) like every PDF, but not SettingController::pdf():
        // its "Page X of Y" footer would be printed on the card
        $pdf = PDF::loadView('admin.payroll.employee.id-card', [
            'employee'  => $employee,
            'company'   => $this->company(),
            'photo'     => $this->imageData($employee->photo),
            'signature' => $this->imageData($employee->signature),
            'barcode'   => 'data:image/png;base64,' . (new DNS1D())->getBarcodePNG((string) $employee->employee_code, 'C128', 1.4, 34),
            'validTill' => $employee->card_valid_until
                ? Carbon::parse($employee->card_valid_until)
                : now()->addYears(2)->endOfMonth(),
            'pdfT'      => SettingController::pdfTheme(),
            'pdfCo'     => SettingController::pdfCompany(),
        ])->setPaper([0, 0, 54 * $mm, 85.6 * $mm])
            ->setOption('isFontSubsettingEnabled', true);   // embeds only the glyphs used: ~880 KB -> small

        return $pdf->stream('ID-Card-' . $employee->employee_code . '.pdf');
    }

    /** Full profile on A4, with the photo and the document photographs. */
    public function profilePdf(Request $request)
    {
        $employee = $this->findEmployee((int) $request->query('id'));
        abort_unless($employee !== null, 404, 'Employee not found.');

        $documents = $this->documentsOf($employee->id)->map(function ($d) {
            $d->front_image = $this->imageData($d->file_front);
            $d->back_image  = $this->imageData($d->file_back);

            return $d;
        });

        $pdf = SettingController::pdf('admin.payroll.employee.profile-pdf', [
            'employee'    => $employee,
            'photo'       => $this->imageData($employee->photo),
            'signature'   => $this->imageData($employee->signature),
            'reportingTo' => $employee->reporting_to ? DB::table('employees')->where('id', $employee->reporting_to)->first(['name', 'employee_code', 'designation']) : null,
            'documents'   => $documents,
            'educations'  => $this->rowsOf('employee_educations', $employee->id, ['degree', 'institute', 'board_university', 'passing_year', 'result']),
            'experiences' => $this->rowsOf('employee_experiences', $employee->id, ['company_name', 'designation', 'from_date', 'to_date', 'responsibilities']),
            'printedAt'   => now()->format('d M Y, h:i A'),
            'isScale'     => PayrollSetupController::isScale(),
        ]);

        $fileName = 'Employee-' . $employee->employee_code . '.pdf';

        return $request->boolean('download') ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    /* ================================================================== */
    /*  Helpers                                                           */
    /* ================================================================== */

    /** Active rows of a payroll setup table for the form dropdowns. */
    private function setupRows(string $table, array $extra = [])
    {
        return DB::table($table)->where('deleted', 'No')->where('status', 'Active')
            ->orderBy('name')->get(['id', 'name', ...$extra]);
    }

    private function findEmployee(int $id): ?object
    {
        return BranchController::scope(DB::table('employees'), 'branch_id')->where('id', $id)->where('deleted', 'No')->first();
    }

    private function documentsOf(int $employeeId)
    {
        return DB::table('employee_documents')
            ->where('employee_id', $employeeId)->where('deleted', 'No')
            ->orderByRaw('FIELD(document_type, ' . implode(',', array_fill(0, count(self::DOCUMENT_TYPES), '?')) . ')', self::DOCUMENT_TYPES)
            ->orderBy('id')
            ->get();
    }

    private function rowsOf(string $table, int $employeeId, array $columns)
    {
        return DB::table($table)->where('employee_id', $employeeId)->where('deleted', 'No')->orderBy('id')->get($columns);
    }

    /**
     * Documents are matched by id so their files survive an edit. Rows that are no
     * longer posted are soft-deleted; a new upload replaces the old file.
     */
    private function saveDocuments(int $employeeId, Request $request, array &$newFiles, array &$oldFiles): void
    {
        $keepIds = [];

        foreach ((array) $request->input('documents', []) as $i => $input) {
            $doc = null;

            if (!empty($input['id'])) {
                $doc = EmployeeDocument::where('id', (int) $input['id'])->where('employee_id', $employeeId)->where('deleted', 'No')->first();
            }

            if (!$doc) {
                $doc = new EmployeeDocument();
                $doc->employee_id  = $employeeId;
                $doc->created_by   = Auth::id();
                $doc->created_date = now();
            } else {
                $doc->updated_by   = Auth::id();
                $doc->updated_date = now();
            }

            $doc->document_type = $input['document_type'];
            $doc->document_no   = $this->nullIfBlank($input['document_no'] ?? null);
            $doc->issue_date    = ($input['issue_date'] ?? null) ?: null;
            $doc->expiry_date   = ($input['expiry_date'] ?? null) ?: null;
            $doc->issued_by     = $this->nullIfBlank($input['issued_by'] ?? null);
            $doc->remarks       = $this->nullIfBlank($input['remarks'] ?? null);

            foreach (['file_front', 'file_back'] as $side) {
                $file = $request->file("documents.$i.$side");

                if ($file) {
                    $oldFiles[] = $doc->$side;
                    $doc->$side = $newFiles[] = $this->upload($file, 'documents');
                } elseif (!empty($input['remove_' . $side])) {
                    $oldFiles[] = $doc->$side;
                    $doc->$side = null;
                }
            }

            $doc->save();
            $keepIds[] = $doc->id;
        }

        DB::table('employee_documents')
            ->where('employee_id', $employeeId)->where('deleted', 'No')
            ->whereNotIn('id', $keepIds ?: [0])
            ->update($this->deletedColumns());
    }

    /** Education / experience rows have no files, so they are simply replaced. */
    private function replaceRows(string $table, int $employeeId, array $rows, array $columns): void
    {
        DB::table($table)->where('employee_id', $employeeId)->where('deleted', 'No')->update($this->deletedColumns());

        foreach ($rows as $row) {
            $insert = ['employee_id' => $employeeId, 'created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now()];

            foreach ($columns as $col) {
                $value = $row[$col] ?? null;
                $insert[$col] = is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value);
            }

            DB::table($table)->insert($insert);
        }
    }

    private function upload(UploadedFile $file, string $folder): string
    {
        $dir = public_path(self::UPLOAD_DIR . '/' . $folder);

        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        // Extension from the file's content (already validated as an image / PDF), never the client's file name,
        // so nothing like "photo.html" or "x.phtml" can be written into the public folder.
        $ext = strtolower((string) $file->guessExtension());
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        if (!in_array($ext, ['jpg', 'png', 'webp', 'pdf'], true)) {
            abort(422, 'Upload a JPG, PNG, WEBP or PDF file.');
        }
        $name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $file->move($dir, $name);

        return self::UPLOAD_DIR . '/' . $folder . '/' . $name;
    }

    private function deleteFiles(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            // Only ever delete inside the employee upload folder.
            if (str_starts_with($path, self::UPLOAD_DIR . '/') && File::exists(public_path($path))) {
                File::delete(public_path($path));
            }
        }
    }

    private function fileUrl(?string $path): ?string
    {
        return $path && is_file(public_path($path)) ? asset($path) : null;
    }

    private function isImage(?string $path): bool
    {
        return $path !== null && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    /**
     * Base64 data URI for dompdf (avoids file-path / remote access issues).
     * WEBP is converted to PNG because dompdf's WEBP support depends on the build.
     */
    private function imageData(?string $path): ?string
    {
        if (!$path || !$this->isImage($path) || !is_file($full = public_path($path))) {
            return null;
        }

        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));

        if ($ext === 'webp') {
            if (!function_exists('imagecreatefromwebp') || !($img = @imagecreatefromwebp($full))) {
                return null;
            }

            ob_start();
            imagepng($img);
            $png = ob_get_clean();
            imagedestroy($img);

            return 'data:image/png;base64,' . base64_encode($png);
        }

        return 'data:image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode(file_get_contents($full));
    }

    private function company(): array
    {
        $s = Setting::where('deleted', 'No')->where('status', 'Active')->first();

        return [
            'name'    => $s->company_name ?? 'Company Name',
            'short'   => $s->company_short_name ?? null,
            'tagline' => $s->company_tagline ?? null,
            'address' => trim(implode(', ', array_filter([$s->address ?? null, $s->city ?? null, $s->country ?? null]))),
            'phone'   => $s->phone ?? null,
            'email'   => $s->email ?? null,
            'website' => $s->website ?? null,
            'logo'    => $this->imageData($s->logo ?? null),
        ];
    }
}
