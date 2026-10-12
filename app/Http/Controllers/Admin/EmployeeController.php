<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
    use SequentialCodeTrait, VoucherHelperTrait;

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

    private const SALARY_PARTS = ['basic_salary', 'house_rent', 'medical_allowance', 'transport_allowance', 'other_allowance'];

    private const UPLOAD_DIR = 'uploads/employees';
    // Separate rules: a '|' inside a rules array is not split, so it would become part of the mimes list.
    private const IMAGE_RULE = ['mimes:jpg,jpeg,png,webp', 'max:2048'];
    private const DOC_RULE   = ['mimes:jpg,jpeg,png,webp,pdf', 'max:4096'];

    /* ------------------------------------------------------------------ */
    /*  Page                                                              */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        $active = DB::table('employees')->where('deleted', 'No');

        return view('admin.payroll.employee.index', [
            'documentTypes'   => self::DOCUMENT_TYPES,
            'genders'         => self::GENDERS,
            'maritalStatuses' => self::MARITAL_STATUSES,
            'bloodGroups'     => self::BLOOD_GROUPS,
            'employmentTypes' => self::EMPLOYMENT_TYPES,
            'paymentModes'    => self::PAYMENT_MODES,
            // Free-text fields with suggestions from what is already used.
            'departments'     => (clone $active)->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'designations'    => (clone $active)->whereNotNull('designation')->distinct()->orderBy('designation')->pluck('designation'),
            'shifts'          => (clone $active)->whereNotNull('work_shift')->distinct()->orderBy('work_shift')->pluck('work_shift'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  DataTable                                                         */
    /* ------------------------------------------------------------------ */

    public function list(Request $request)
    {
        try {
            $query = DB::table('employees')->where('deleted', 'No');

            if ($request->filled('department')) {
                $query->where('department', $request->query('department'));
            }

            if ($request->filled('employment_type')) {
                $query->where('employment_type', $request->query('employment_type'));
            }

            if (in_array($request->query('status'), ['Active', 'Inactive'], true)) {
                $query->where('status', $request->query('status'));
            }

            $employees = $query->orderBy('employee_code')->get();

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

                $actions = '
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary" title="View profile" onclick="viewEmployee(' . $id . ')"><i class="fas fa-eye"></i></button>
                        <button type="button" class="btn btn-outline-primary" title="Edit" onclick="editEmployee(' . $id . ')"><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn btn-outline-info" title="ID card (PDF)" onclick="printIdCard(' . $id . ')"><i class="fas fa-id-badge"></i></button>
                        <button type="button" class="btn btn-outline-dark" title="Profile (PDF)" onclick="printProfile(' . $id . ')"><i class="fas fa-file-pdf"></i></button>
                        <button type="button" class="btn btn-outline-danger" title="Delete" onclick="deleteEmployee(' . $id . ')"><i class="fas fa-trash"></i></button>
                    </div>';

                $output['data'][] = [
                    $i++,
                    '<div class="d-flex align-items-center gap-2">' . $photo . '<div>'
                        . '<div class="fw-semibold">' . e($emp->name) . '</div>'
                        . '<div class="text-muted small">ID: ' . e($emp->employee_code) . '</div></div></div>',
                    e($emp->designation ?: '-') . ($emp->department ? '<div class="text-muted small">' . e($emp->department) . '</div>' : ''),
                    e($emp->phone ?: '-') . ($emp->email ? '<div class="text-muted small">' . e($emp->email) . '</div>' : ''),
                    e($emp->employment_type ?: '-'),
                    $joined ? '<span class="d-none">' . $joined->format('Y-m-d') . '</span>' . e($joined->format('d M Y')) : '<span class="text-muted">-</span>',
                    '<span class="d-none">' . sprintf('%021.2f', (float) $emp->gross_salary) . '</span>'
                        . '<span class="d-block text-end">' . number_format((float) $emp->gross_salary, 2) . '</span>',
                    $statusBtn,
                    $actions,
                ];
            }

            $all = DB::table('employees')->where('deleted', 'No');

            $output['summary'] = [
                'active'      => (clone $all)->where('status', 'Active')->count(),
                'inactive'    => (clone $all)->where('status', 'Inactive')->count(),
                'departments' => (clone $all)->whereNotNull('department')->distinct()->count('department'),
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

        $rows = DB::table('employees')
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

        $money = ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'];
        $date  = ['nullable', 'date'];

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
            'designation'                => ['required', 'string', 'max:100'],
            'department'                 => ['nullable', 'string', 'max:100'],
            'employment_type'            => ['nullable', Rule::in(self::EMPLOYMENT_TYPES)],
            'work_shift'                 => ['nullable', 'string', 'max:50'],
            'reporting_to'               => ['nullable', 'integer', Rule::exists('employees', 'id')->where('deleted', 'No'), Rule::notIn(array_filter([$id]))],
            'joining_date'               => $date,
            'confirmation_date'          => ['nullable', 'date', 'after_or_equal:joining_date'],
            'resign_date'                => ['nullable', 'date', 'after_or_equal:joining_date'],
            'card_valid_until'           => $date,
            'status'                     => ['required', Rule::in(['Active', 'Inactive'])],
            'description'                => ['nullable', 'string', 'max:2000'],
            // Salary & payment
            'basic_salary'               => $money,
            'house_rent'                 => $money,
            'medical_allowance'          => $money,
            'transport_allowance'        => $money,
            'other_allowance'            => $money,
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
            'designation.required'                 => 'Enter the designation.',
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

        $validator->after(function ($v) use ($request) {
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
                $employee = Employee::where('id', $id)->where('deleted', 'No')->lockForUpdate()->first();

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
                'designation', 'department', 'employment_type', 'work_shift', 'description',
                'salary_payment_mode', 'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_no',
                'mobile_banking_provider', 'mobile_banking_no',
            ];

            foreach ($plain as $col) {
                $employee->$col = $this->nullIfBlank($request->input($col));
            }

            foreach (['date_of_birth', 'joining_date', 'confirmation_date', 'resign_date', 'card_valid_until'] as $col) {
                $employee->$col = $request->input($col) ?: null;
            }

            $employee->reporting_to = $request->input('reporting_to') ?: null;
            $employee->status       = $request->input('status', 'Active');

            $gross = 0;
            foreach (self::SALARY_PARTS as $col) {
                $employee->$col = round((float) $request->input($col, 0), 2);
                $gross += $employee->$col;
            }
            $employee->gross_salary = round($gross, 2);

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
        $employee = Employee::where('id', (int) $request->input('id'))->where('deleted', 'No')->first();

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
                $employee = Employee::where('id', (int) $request->input('id'))->where('deleted', 'No')->lockForUpdate()->first();

                if (!$employee) {
                    abort(404, 'Employee not found.');
                }

                if (DB::table('employees')->where('reporting_to', $employee->id)->where('deleted', 'No')->exists()) {
                    abort(422, 'Other employees report to ' . $employee->name . '. Change their "Reporting to" first.');
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

        $pdf = PDF::loadView('admin.payroll.employee.id-card', [
            'employee'  => $employee,
            'company'   => $this->company(),
            'photo'     => $this->imageData($employee->photo),
            'signature' => $this->imageData($employee->signature),
            'barcode'   => 'data:image/png;base64,' . (new DNS1D())->getBarcodePNG((string) $employee->employee_code, 'C128', 1.4, 34),
            'validTill' => $employee->card_valid_until
                ? Carbon::parse($employee->card_valid_until)
                : now()->addYears(2)->endOfMonth(),
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

        $pdf = PDF::loadView('admin.payroll.employee.profile-pdf', [
            'employee'    => $employee,
            'company'     => $this->company(),
            'photo'       => $this->imageData($employee->photo),
            'signature'   => $this->imageData($employee->signature),
            'reportingTo' => $employee->reporting_to ? DB::table('employees')->where('id', $employee->reporting_to)->first(['name', 'employee_code', 'designation']) : null,
            'documents'   => $documents,
            'educations'  => $this->rowsOf('employee_educations', $employee->id, ['degree', 'institute', 'board_university', 'passing_year', 'result']),
            'experiences' => $this->rowsOf('employee_experiences', $employee->id, ['company_name', 'designation', 'from_date', 'to_date', 'responsibilities']),
            'printedAt'   => now()->format('d M Y, h:i A'),
        ])->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true);

        $fileName = 'Employee-' . $employee->employee_code . '.pdf';

        return $request->boolean('download') ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    /* ================================================================== */
    /*  Helpers                                                           */
    /* ================================================================== */

    private function findEmployee(int $id): ?object
    {
        return DB::table('employees')->where('id', $id)->where('deleted', 'No')->first();
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

        $name = time() . '_' . bin2hex(random_bytes(6)) . '.' . strtolower($file->getClientOriginalExtension());
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
