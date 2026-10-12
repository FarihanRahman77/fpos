@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Employees
@endsection

@push('styles')
    <style>
        .emp-thumb { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex: 0 0 40px; border: 2px solid #e2e8f0; }
        .emp-initial { display: inline-flex; align-items: center; justify-content: center; background: #e0e7ff; color: #4338ca; font-weight: 700; }
        #employeeModal .nav-tabs .nav-link { font-size: .85rem; }
        #employeeModal .nav-tabs .nav-link.has-error { color: #dc3545; font-weight: 600; }
        .photo-box { width: 140px; height: 160px; border: 2px dashed #cbd5e1; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f8fafc; cursor: pointer; }
        .photo-box img { width: 100%; height: 100%; object-fit: cover; }
        .sign-box { width: 200px; height: 70px; border: 2px dashed #cbd5e1; border-radius: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #fff; cursor: pointer; }
        .sign-box img { max-width: 100%; max-height: 100%; }
        .doc-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; background: #fff; margin-bottom: 12px; }
        .doc-file { border: 1px dashed #cbd5e1; border-radius: 10px; padding: 8px; background: #f8fafc; }
        .doc-preview { height: 90px; display: flex; align-items: center; justify-content: center; margin-bottom: 6px; }
        .doc-preview img { max-height: 90px; max-width: 100%; border-radius: 6px; }
        .doc-preview .fa-file-pdf { font-size: 2.6rem; color: #dc2626; }
        .rep-table td { vertical-align: top; }
        .profile-head { background: linear-gradient(135deg, #1e293b, #334155); color: #fff; border-radius: 14px; padding: 18px; }
        .profile-head img, .profile-head .ph-initial { width: 110px; height: 125px; object-fit: cover; border-radius: 10px; border: 3px solid #fff; }
        .profile-head .ph-initial { display: flex; align-items: center; justify-content: center; font-size: 2.5rem; background: #475569; }
        .profile-section h6 { border-bottom: 2px solid #e2e8f0; padding-bottom: 6px; margin-top: 18px; color: #334155; }
        .kv { display: grid; grid-template-columns: 170px 1fr; gap: 4px 12px; font-size: .9rem; }
        .kv dt { color: #64748b; font-weight: 500; }
        .kv dd { margin: 0; }
        .doc-gallery img { height: 110px; border-radius: 8px; border: 1px solid #e2e8f0; object-fit: cover; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex align-items-center mb-3">
            <div>
                <h4 class="mb-0">Employees</h4>
                <small class="text-muted">Employee profiles, salary structure, documents, ID cards.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button type="button" class="btn btn-primary" onclick="addEmployee()">
                    <i class="fas fa-user-plus"></i> Add Employee
                </button>
            </div>
        </div>

        {{-- Summary --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i4"><i class="fas fa-user-check"></i></div>
                    <div><div class="text-muted small">Active Employees</div><h5 class="mb-0" id="sumActive">0</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i2"><i class="fas fa-user-slash"></i></div>
                    <div><div class="text-muted small">Inactive</div><h5 class="mb-0" id="sumInactive">0</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i1"><i class="fas fa-sitemap"></i></div>
                    <div><div class="text-muted small">Departments</div><h5 class="mb-0" id="sumDepartments">0</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i3"><i class="fas fa-money-check-alt"></i></div>
                    <div><div class="text-muted small">Monthly Gross (active)</div><h5 class="mb-0" id="sumPayroll">0.00</h5></div>
                </div></div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card acc-card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="filterDepartment">Department</label>
                        <select id="filterDepartment" class="form-select">
                            <option value="">All departments</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department }}">{{ $department }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="filterType">Employment Type</label>
                        <select id="filterType" class="form-select">
                            <option value="">All types</option>
                            @foreach ($employmentTypes as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="filterStatus">Status</label>
                        <select id="filterStatus" class="form-select">
                            <option value="">All</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="button" class="btn btn-primary flex-fill" id="applyFilters" title="Apply filters"><i class="fas fa-filter"></i></button>
                        <button type="button" class="btn btn-outline-secondary flex-fill" id="resetFilters" title="Reset filters"><i class="fas fa-undo"></i></button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="employeeTable" class="table table-hover align-middle w-100 acc-table"
                        data-page-length="25" data-order="[]" data-auto-width="false">
                        <thead>
                            <tr>
                                <th data-orderable="false" data-searchable="false" width="4%">SL.</th>
                                <th>Employee</th>
                                <th>Designation</th>
                                <th>Contact</th>
                                <th>Type</th>
                                <th>Joined</th>
                                <th class="text-end">Gross Salary</th>
                                <th>Status</th>
                                <th data-orderable="false" data-searchable="false" width="15%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Suggestions for free-text fields --}}
    <datalist id="departmentList">@foreach ($departments as $v)<option value="{{ $v }}">@endforeach</datalist>
    <datalist id="designationList">@foreach ($designations as $v)<option value="{{ $v }}">@endforeach</datalist>
    <datalist id="shiftList">
        @foreach ($shifts->merge(['Day', 'Evening', 'Night', 'General'])->unique() as $v)<option value="{{ $v }}">@endforeach
    </datalist>

    {{-- Add / Edit modal --}}
    <div class="modal fade" id="employeeModal" tabindex="-1" aria-labelledby="employeeModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <form id="employeeForm" novalidate enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="id" id="employee_id">
                    <input type="hidden" name="remove_photo" id="remove_photo" value="0">
                    <input type="hidden" name="remove_signature" id="remove_signature" value="0">

                    <div class="modal-header">
                        <h5 class="modal-title" id="employeeModalTitle">Add Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li class="nav-item"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#tabPersonal"><i class="fas fa-user me-1"></i> Personal</button></li>
                            <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tabContact"><i class="fas fa-address-book me-1"></i> Contact</button></li>
                            <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tabJob"><i class="fas fa-briefcase me-1"></i> Job &amp; Salary</button></li>
                            <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tabBank"><i class="fas fa-university me-1"></i> Payment</button></li>
                            <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tabEducation"><i class="fas fa-graduation-cap me-1"></i> Education</button></li>
                            <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tabExperience"><i class="fas fa-history me-1"></i> Experience</button></li>
                            <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tabDocuments"><i class="fas fa-id-card me-1"></i> Documents</button></li>
                        </ul>

                        <div class="tab-content">
                            {{-- ===== Personal ===== --}}
                            <div class="tab-pane fade show active" id="tabPersonal">
                                <div class="row g-3">
                                    <div class="col-lg-3 text-center">
                                        <label class="form-label d-block">Photograph</label>
                                        <div class="photo-box mx-auto" id="photoBox" title="Click to choose a photo">
                                            <span class="text-muted small"><i class="fas fa-camera fa-2x d-block mb-1"></i>Upload photo</span>
                                        </div>
                                        <div>
                                            <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp" class="d-none">
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                        <button type="button" class="btn btn-link btn-sm text-danger d-none" id="removePhotoBtn">Remove photo</button>
                                        <div class="text-muted small">JPG / PNG / WEBP, max 2 MB. Passport size works best.</div>

                                        <label class="form-label d-block mt-3">Signature</label>
                                        <div class="sign-box mx-auto" id="signBox" title="Click to choose a signature image">
                                            <span class="text-muted small">Upload signature</span>
                                        </div>
                                        <div>
                                            <input type="file" name="signature" id="signature" accept="image/jpeg,image/png,image/webp" class="d-none">
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                        <button type="button" class="btn btn-link btn-sm text-danger d-none" id="removeSignBtn">Remove signature</button>
                                    </div>

                                    <div class="col-lg-9">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label" for="name">Full Name <span class="text-danger">*</span></label>
                                                <input type="text" name="name" id="name" class="form-control" maxlength="150" placeholder="Full name">
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="employee_code_view">Employee ID</label>
                                                <input type="text" id="employee_code_view" class="form-control" readonly placeholder="Auto generated">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
                                                <select name="status" id="status" class="form-select">
                                                    <option value="Active">Active</option>
                                                    <option value="Inactive">Inactive</option>
                                                </select>
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label" for="father_name">Father's Name</label>
                                                <input type="text" name="father_name" id="father_name" class="form-control" maxlength="150" placeholder="Enter father's name">
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label" for="mother_name">Mother's Name</label>
                                                <input type="text" name="mother_name" id="mother_name" class="form-control" maxlength="150" placeholder="Enter mother's name">
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label" for="spouse_name">Spouse's Name</label>
                                                <input type="text" name="spouse_name" id="spouse_name" class="form-control" maxlength="150" placeholder="Enter spouse's name (optional)">
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="date_of_birth">Date of Birth</label>
                                                <input type="date" name="date_of_birth" id="date_of_birth" class="form-control">
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="gender">Gender</label>
                                                <select name="gender" id="gender" class="form-select">
                                                    <option value="">Select</option>
                                                    @foreach ($genders as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                                                </select>
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="marital_status">Marital Status</label>
                                                <select name="marital_status" id="marital_status" class="form-select">
                                                    <option value="">Select</option>
                                                    @foreach ($maritalStatuses as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                                                </select>
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="blood_group">Blood Group</label>
                                                <select name="blood_group" id="blood_group" class="form-select">
                                                    <option value="">Select</option>
                                                    @foreach ($bloodGroups as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                                                </select>
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="religion">Religion</label>
                                                <input type="text" name="religion" id="religion" class="form-control" maxlength="50" list="religionList" placeholder="e.g. Islam">
                                                <datalist id="religionList"><option value="Islam"><option value="Hinduism"><option value="Christianity"><option value="Buddhism"></datalist>
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="nationality">Nationality</label>
                                                <input type="text" name="nationality" id="nationality" class="form-control" maxlength="50" placeholder="e.g. Bangladeshi">
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="nid_no">NID No</label>
                                                <input type="text" name="nid_no" id="nid_no" class="form-control" maxlength="50" placeholder="e.g. 1234567890">
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="tin_no">TIN No</label>
                                                <input type="text" name="tin_no" id="tin_no" class="form-control" maxlength="50" placeholder="e.g. 123456789012">
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="description">Notes</label>
                                                <textarea name="description" id="description" class="form-control" rows="2" maxlength="2000" placeholder="Anything else about this employee (optional)"></textarea>
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- ===== Contact ===== --}}
                            <div class="tab-pane fade" id="tabContact">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label" for="phone">Mobile <span class="text-danger">*</span></label>
                                        <input type="text" name="phone" id="phone" class="form-control" maxlength="30" placeholder="e.g. 01711000000">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="alt_phone">Alternate Mobile</label>
                                        <input type="text" name="alt_phone" id="alt_phone" class="form-control" maxlength="30" placeholder="e.g. 01811000000">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="email">Email</label>
                                        <input type="email" name="email" id="email" class="form-control" maxlength="150" placeholder="e.g. employee@example.com">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="present_address">Present Address</label>
                                        <textarea name="present_address" id="present_address" class="form-control" rows="3" maxlength="1000" placeholder="House, road, area, city"></textarea>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label d-flex" for="permanent_address">Permanent Address
                                            <span class="ms-auto form-check small fw-normal mb-0">
                                                <input class="form-check-input" type="checkbox" id="sameAddress">
                                                <label class="form-check-label" for="sameAddress">Same as present</label>
                                            </span>
                                        </label>
                                        <textarea name="permanent_address" id="permanent_address" class="form-control" rows="3" maxlength="1000" placeholder="Village, upazila, district"></textarea>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-12"><h6 class="mb-0 mt-2">Emergency Contact</h6></div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="emergency_contact_name">Name</label>
                                        <input type="text" name="emergency_contact_name" id="emergency_contact_name" class="form-control" maxlength="150" placeholder="Enter contact person's name">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="emergency_contact_relation">Relation</label>
                                        <input type="text" name="emergency_contact_relation" id="emergency_contact_relation" class="form-control" maxlength="50" list="relationList" placeholder="e.g. Father">
                                        <datalist id="relationList"><option value="Father"><option value="Mother"><option value="Spouse"><option value="Brother"><option value="Sister"><option value="Son"><option value="Daughter"></datalist>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="emergency_contact_phone">Phone</label>
                                        <input type="text" name="emergency_contact_phone" id="emergency_contact_phone" class="form-control" maxlength="30" placeholder="e.g. 01711000000">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- ===== Job & salary ===== --}}
                            <div class="tab-pane fade" id="tabJob">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label" for="designation">Designation <span class="text-danger">*</span></label>
                                        <input type="text" name="designation" id="designation" class="form-control" maxlength="100" list="designationList" placeholder="e.g. Sales Executive">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="department">Department</label>
                                        <input type="text" name="department" id="department" class="form-control" maxlength="100" list="departmentList" placeholder="e.g. Sales">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="employment_type">Employment Type</label>
                                        <select name="employment_type" id="employment_type" class="form-select">
                                            <option value="">Select</option>
                                            @foreach ($employmentTypes as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                                        </select>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="work_shift">Work Shift</label>
                                        <input type="text" name="work_shift" id="work_shift" class="form-control" maxlength="50" list="shiftList" placeholder="e.g. Day (9 AM - 6 PM)">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label" for="reporting_to">Reporting To</label>
                                        <select name="reporting_to" id="reporting_to" class="form-select"></select>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="joining_date">Joining Date</label>
                                        <input type="date" name="joining_date" id="joining_date" class="form-control">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="confirmation_date">Confirmation Date</label>
                                        <input type="date" name="confirmation_date" id="confirmation_date" class="form-control">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="resign_date">Resign / Leaving Date</label>
                                        <input type="date" name="resign_date" id="resign_date" class="form-control">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="card_valid_until">ID Card Valid Until</label>
                                        <input type="date" name="card_valid_until" id="card_valid_until" class="form-control">
                                        <div class="text-danger small field-error"></div>
                                    </div>

                                    <div class="col-12"><h6 class="mb-0 mt-2">Monthly Salary Structure</h6></div>
                                    @foreach (['basic_salary' => 'Basic', 'house_rent' => 'House Rent', 'medical_allowance' => 'Medical', 'transport_allowance' => 'Transport', 'other_allowance' => 'Other Allowance'] as $field => $label)
                                        <div class="col-md-2">
                                            <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                                            <input type="number" step="0.01" min="0" name="{{ $field }}" id="{{ $field }}" class="form-control text-end salary-part" placeholder="0.00">
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                    @endforeach
                                    <div class="col-md-2">
                                        <label class="form-label" for="gross_salary_view">Gross Salary</label>
                                        <input type="text" id="gross_salary_view" class="form-control text-end acc-grand" readonly value="0.00" placeholder="0.00">
                                    </div>
                                </div>
                            </div>

                            {{-- ===== Payment / bank ===== --}}
                            <div class="tab-pane fade" id="tabBank">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label" for="salary_payment_mode">Salary Payment Mode</label>
                                        <select name="salary_payment_mode" id="salary_payment_mode" class="form-select">
                                            <option value="">Select</option>
                                            @foreach ($paymentModes as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                                        </select>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="col-12 pay-bank">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label" for="bank_name">Bank Name</label>
                                                <input type="text" name="bank_name" id="bank_name" class="form-control" maxlength="100" placeholder="e.g. Dhaka Bank">
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="bank_branch">Branch</label>
                                                <input type="text" name="bank_branch" id="bank_branch" class="form-control" maxlength="100" placeholder="e.g. Motijheel Branch">
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="bank_account_name">Account Name</label>
                                                <input type="text" name="bank_account_name" id="bank_account_name" class="form-control" maxlength="150" placeholder="Name as on bank account">
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="bank_account_no">Account Number</label>
                                                <input type="text" name="bank_account_no" id="bank_account_no" class="form-control" maxlength="50" placeholder="e.g. 1234567890123">
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 pay-mobile">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label" for="mobile_banking_provider">Provider</label>
                                                <input type="text" name="mobile_banking_provider" id="mobile_banking_provider" class="form-control" maxlength="50" list="mfsList" placeholder="e.g. bKash">
                                                <datalist id="mfsList"><option value="bKash"><option value="Nagad"><option value="Rocket"><option value="Upay"></datalist>
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="mobile_banking_no">Account Number</label>
                                                <input type="text" name="mobile_banking_no" id="mobile_banking_no" class="form-control" maxlength="30" placeholder="e.g. 01711000000">
                                                <div class="text-danger small field-error"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- ===== Education ===== --}}
                            <div class="tab-pane fade" id="tabEducation">
                                <div class="d-flex mb-2">
                                    <span class="text-muted small">Academic qualifications, latest first.</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" onclick="addEducation()"><i class="fas fa-plus"></i> Add Qualification</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm rep-table" style="min-width: 820px">
                                        <thead><tr><th>Degree / Exam *</th><th>Institute</th><th>Board / University</th><th width="11%">Passing Year</th><th width="11%">Result</th><th width="4%"></th></tr></thead>
                                        <tbody id="educationBody"></tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- ===== Experience ===== --}}
                            <div class="tab-pane fade" id="tabExperience">
                                <div class="d-flex mb-2">
                                    <span class="text-muted small">Previous employment.</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" onclick="addExperience()"><i class="fas fa-plus"></i> Add Experience</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm rep-table" style="min-width: 900px">
                                        <thead><tr><th>Company *</th><th>Designation</th><th width="13%">From</th><th width="13%">To</th><th>Responsibilities</th><th width="4%"></th></tr></thead>
                                        <tbody id="experienceBody"></tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- ===== Documents ===== --}}
                            <div class="tab-pane fade" id="tabDocuments">
                                <div class="d-flex mb-2">
                                    <span class="text-muted small">NID, passport, driving license, appointment letter ... with a photo / scan of each side (JPG, PNG, WEBP or PDF, max 4 MB).</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" onclick="addDocument()"><i class="fas fa-plus"></i> Add Document</button>
                                </div>
                                <div id="documentsBody"></div>
                            </div>
                        </div>

                        <div class="text-danger small mt-2" id="form_error"></div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="saveBtn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Profile view modal --}}
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalTitle">Employee Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-info me-auto" id="viewIdCardBtn"><i class="fas fa-id-badge"></i> ID Card</button>
                    <button type="button" class="btn btn-outline-dark" id="viewProfilePdfBtn"><i class="fas fa-file-pdf"></i> Profile PDF</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        let employeeTable, employeeModal, viewModal;
        let docIndex = 0, eduIndex = 0, expIndex = 0;

        const listUrl = "{{ route('admin.employee.list') }}";
        const showUrl = "{{ route('admin.employee.show') }}";
        const documentTypes = @json($documentTypes);

        /* ================= photo / signature ================= */
        function setImageBox($box, url, emptyHtml) {
            $box.html(url ? '<img src="' + Voucher.esc(url) + '" alt="">' : emptyHtml);
        }

        const PHOTO_EMPTY = '<span class="text-muted small"><i class="fas fa-camera fa-2x d-block mb-1"></i>Upload photo</span>';
        const SIGN_EMPTY = '<span class="text-muted small">Upload signature</span>';

        function previewFile(input, $box, onDone) {
            const file = input.files && input.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                $box.html('<img src="' + e.target.result + '" alt="">');
                if (onDone) onDone();
            };
            reader.readAsDataURL(file);
        }

        /* ================= salary ================= */
        function calcGross() {
            let gross = 0;
            $('.salary-part').each(function() { gross += parseFloat(this.value) || 0; });
            $('#gross_salary_view').val(gross.toFixed(2));
        }

        function togglePaymentFields() {
            const mode = $('#salary_payment_mode').val();
            $('.pay-bank').toggle(mode === 'Bank');
            $('.pay-mobile').toggle(mode === 'Mobile Banking');
        }

        /* ================= education / experience rows ================= */
        function cell(name, value, attrs, placeholder) {
            return '<td><input name="' + name + '" class="form-control form-control-sm" value="' + Voucher.esc(value || '') + '" placeholder="' + Voucher.esc(placeholder || '') + '" ' + (attrs || '') + '>' +
                '<div class="text-danger small field-error"></div></td>';
        }

        const removeCell = '<td><button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Remove"><i class="fas fa-times"></i></button></td>';

        function addEducation(d) {
            d = d || {};
            const i = eduIndex++;
            $('#educationBody').append('<tr>' +
                cell('educations[' + i + '][degree]', d.degree, 'maxlength="100"', 'e.g. HSC, BSc') +
                cell('educations[' + i + '][institute]', d.institute, 'maxlength="150"', 'e.g. Dhaka College') +
                cell('educations[' + i + '][board_university]', d.board_university, 'maxlength="150"', 'e.g. Dhaka Board') +
                cell('educations[' + i + '][passing_year]', d.passing_year, 'maxlength="10"', 'e.g. 2018') +
                cell('educations[' + i + '][result]', d.result, 'maxlength="30"', 'GPA / Class') +
                removeCell + '</tr>');
        }

        function addExperience(d) {
            d = d || {};
            const i = expIndex++;
            $('#experienceBody').append('<tr>' +
                cell('experiences[' + i + '][company_name]', d.company_name, 'maxlength="150"', 'Enter company name') +
                cell('experiences[' + i + '][designation]', d.designation, 'maxlength="100"', 'e.g. Sales Officer') +
                cell('experiences[' + i + '][from_date]', d.from_date, 'type="date"') +
                cell('experiences[' + i + '][to_date]', d.to_date, 'type="date"') +
                cell('experiences[' + i + '][responsibilities]', d.responsibilities, 'maxlength="500"', 'Main duties (optional)') +
                removeCell + '</tr>');
        }

        /* ================= documents ================= */
        function docTypeOptions(selected) {
            return documentTypes.map(function(t) {
                return '<option value="' + Voucher.esc(t) + '"' + (t === selected ? ' selected' : '') + '>' + Voucher.esc(t) + '</option>';
            }).join('');
        }

        function filePreview(url, isImage) {
            if (!url) return '<span class="text-muted small">No file</span>';
            return isImage
                ? '<a href="' + Voucher.esc(url) + '" target="_blank"><img src="' + Voucher.esc(url) + '" alt=""></a>'
                : '<a href="' + Voucher.esc(url) + '" target="_blank" title="Open PDF"><i class="fas fa-file-pdf"></i></a>';
        }

        function fileBox(i, side, label, url, isImage) {
            const name = 'documents[' + i + '][' + side + ']';
            return '<div class="col-md-3"><div class="doc-file">' +
                '<div class="small fw-semibold mb-1">' + label + '</div>' +
                '<div class="doc-preview">' + filePreview(url, isImage) + '</div>' +
                '<div><input type="file" name="' + name + '" class="form-control form-control-sm doc-input" accept="image/jpeg,image/png,image/webp,application/pdf">' +
                '<div class="text-danger small field-error"></div></div>' +
                '<input type="hidden" name="documents[' + i + '][remove_' + side + ']" value="0" class="remove-flag">' +
                (url ? '<button type="button" class="btn btn-link btn-sm text-danger p-0 remove-file">Remove file</button>' : '') +
                '</div></div>';
        }

        function addDocument(d) {
            d = d || {};
            const i = docIndex++;

            $('#documentsBody').append(
                '<div class="doc-card">' +
                    '<input type="hidden" name="documents[' + i + '][id]" value="' + (d.id || '') + '">' +
                    '<div class="d-flex align-items-center mb-2">' +
                        '<h6 class="mb-0 doc-title">' + Voucher.esc(d.document_type || 'New document') + '</h6>' +
                        (d.expired ? '<span class="badge bg-danger ms-2">Expired</span>' : '') +
                        '<button type="button" class="btn btn-sm btn-outline-danger ms-auto remove-doc" title="Remove document"><i class="fas fa-trash"></i></button>' +
                    '</div>' +
                    '<div class="row g-2">' +
                        '<div class="col-md-3"><label class="form-label small">Type *</label>' +
                            '<select name="documents[' + i + '][document_type]" class="form-select form-select-sm doc-type">' + docTypeOptions(d.document_type || 'NID') + '</select>' +
                            '<div class="text-danger small field-error"></div></div>' +
                        '<div class="col-md-3"><label class="form-label small">Document No</label>' +
                            '<input name="documents[' + i + '][document_no]" class="form-control form-control-sm" maxlength="100" placeholder="e.g. 1234567890" value="' + Voucher.esc(d.document_no || '') + '">' +
                            '<div class="text-danger small field-error"></div></div>' +
                        '<div class="col-md-2"><label class="form-label small">Issue Date</label>' +
                            '<input type="date" name="documents[' + i + '][issue_date]" class="form-control form-control-sm" value="' + (d.issue_date || '') + '">' +
                            '<div class="text-danger small field-error"></div></div>' +
                        '<div class="col-md-2"><label class="form-label small">Expiry Date</label>' +
                            '<input type="date" name="documents[' + i + '][expiry_date]" class="form-control form-control-sm" value="' + (d.expiry_date || '') + '">' +
                            '<div class="text-danger small field-error"></div></div>' +
                        '<div class="col-md-2"><label class="form-label small">Issued By</label>' +
                            '<input name="documents[' + i + '][issued_by]" class="form-control form-control-sm" maxlength="150" placeholder="e.g. Election Commission" value="' + Voucher.esc(d.issued_by || '') + '">' +
                            '<div class="text-danger small field-error"></div></div>' +
                        fileBox(i, 'file_front', 'Front side / Photo', d.file_front_url, d.front_is_image) +
                        fileBox(i, 'file_back', 'Back side', d.file_back_url, d.back_is_image) +
                        '<div class="col-md-6"><label class="form-label small">Remarks</label>' +
                            '<textarea name="documents[' + i + '][remarks]" class="form-control form-control-sm" rows="4" maxlength="255" placeholder="Optional note about this document">' + Voucher.esc(d.remarks || '') + '</textarea>' +
                            '<div class="text-danger small field-error"></div></div>' +
                    '</div>' +
                '</div>'
            );
        }

        /* ================= add / edit ================= */
        function resetForm() {
            const $form = $('#employeeForm');
            $form[0].reset();
            Voucher.clearErrors($form);
            $('#form_error').text('');
            $('#employeeModal .nav-link').removeClass('has-error');

            $('#employee_id, #employee_code_view').val('');
            $('#remove_photo, #remove_signature').val('0');
            setImageBox($('#photoBox'), null, PHOTO_EMPTY);
            setImageBox($('#signBox'), null, SIGN_EMPTY);
            $('#removePhotoBtn, #removeSignBtn').addClass('d-none');

            $('#reporting_to').empty().trigger('change.select2');
            $('#educationBody, #experienceBody, #documentsBody').empty();
            docIndex = eduIndex = expIndex = 0;

            bootstrap.Tab.getOrCreateInstance(document.querySelector('#employeeModal [data-bs-target="#tabPersonal"]')).show();
            calcGross();
            togglePaymentFields();
        }

        function addEmployee() {
            resetForm();
            $('#nationality').val('Bangladeshi');
            $('#employeeModalTitle').text('Add Employee');
            $('#saveBtn').prop('disabled', false).text('Save');
            employeeModal.show();
        }

        function fillForm(d) {
            const skip = ['id', 'photo', 'signature', 'documents', 'educations', 'experiences', 'reporting_to'];

            Object.keys(d).forEach(function(key) {
                if (skip.indexOf(key) !== -1) return;
                const $el = $('#employeeForm [name="' + key + '"]');
                if ($el.length && $el.attr('type') !== 'file') {
                    $el.val(d[key] === null ? '' : d[key]);
                }
            });

            ['basic_salary', 'house_rent', 'medical_allowance', 'transport_allowance', 'other_allowance'].forEach(function(f) {
                $('#' + f).val(parseFloat(d[f]) > 0 ? d[f] : '');
            });

            $('#employee_id').val(d.id);
            $('#employee_code_view').val(d.employee_code);

            setImageBox($('#photoBox'), d.photo_url, PHOTO_EMPTY);
            setImageBox($('#signBox'), d.signature_url, SIGN_EMPTY);
            $('#removePhotoBtn').toggleClass('d-none', !d.photo_url);
            $('#removeSignBtn').toggleClass('d-none', !d.signature_url);

            if (d.reporting_to) {
                $('#reporting_to').append(new Option(d.reporting_to_label, d.reporting_to, true, true)).trigger('change.select2');
            }

            (d.educations || []).forEach(addEducation);
            (d.experiences || []).forEach(addExperience);
            (d.documents || []).forEach(addDocument);

            calcGross();
            togglePaymentFields();
        }

        function loadEmployee(id, done) {
            $.ajax({
                url: showUrl,
                type: 'GET',
                data: { id: id },
                success: function(res) {
                    if (!res.status) {
                        toastr.error(res.message || 'Unable to load employee.');
                        return;
                    }
                    done(res.data);
                },
                error: function(xhr) {
                    Voucher.ajaxError(xhr, 'Unable to load employee.');
                }
            });
        }

        function editEmployee(id) {
            loadEmployee(id, function(d) {
                resetForm();
                fillForm(d);
                $('#employeeModalTitle').text('Edit Employee · ' + d.name + ' (' + d.employee_code + ')');
                $('#saveBtn').prop('disabled', false).text('Update');
                employeeModal.show();
            });
        }

        /* ================= profile view ================= */
        function kv(rows) {
            return '<dl class="kv">' + rows.filter(function(r) { return r[1]; }).map(function(r) {
                return '<dt>' + r[0] + '</dt><dd>' + Voucher.esc(r[1]) + '</dd>';
            }).join('') + '</dl>';
        }

        function fmtDate(v) {
            if (!v) return '';
            const d = new Date(v + 'T00:00:00');
            return isNaN(d) ? v : d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function viewEmployee(id) {
            loadEmployee(id, function(d) {
                const photo = d.photo_url
                    ? '<img src="' + Voucher.esc(d.photo_url) + '" alt="">'
                    : '<div class="ph-initial">' + Voucher.esc((d.name || '?').charAt(0).toUpperCase()) + '</div>';

                const docs = (d.documents || []).map(function(doc) {
                    const img = function(url, isImage) {
                        if (!url) return '';
                        return isImage
                            ? '<a href="' + Voucher.esc(url) + '" target="_blank"><img src="' + Voucher.esc(url) + '" alt="" class="me-2"></a>'
                            : '<a href="' + Voucher.esc(url) + '" target="_blank" class="btn btn-sm btn-outline-danger me-2"><i class="fas fa-file-pdf"></i> Open PDF</a>';
                    };
                    return '<div class="col-md-6"><div class="doc-card h-100">' +
                        '<div class="d-flex"><strong>' + Voucher.esc(doc.document_type) + '</strong>' +
                        (doc.expired ? '<span class="badge bg-danger ms-2">Expired</span>' : '') + '</div>' +
                        kv([['Number', doc.document_no], ['Issued', fmtDate(doc.issue_date)], ['Expires', fmtDate(doc.expiry_date)], ['Issued By', doc.issued_by], ['Remarks', doc.remarks]]) +
                        '<div class="doc-gallery mt-2">' + img(doc.file_front_url, doc.front_is_image) + img(doc.file_back_url, doc.back_is_image) + '</div>' +
                        '</div></div>';
                }).join('');

                const table = function(head, rows) {
                    return rows ? '<div class="table-responsive"><table class="table table-sm acc-table"><thead><tr>' + head.map(function(h) { return '<th>' + h + '</th>'; }).join('') + '</tr></thead><tbody>' + rows + '</tbody></table></div>' : '<p class="text-muted small">None added.</p>';
                };

                const edu = (d.educations || []).map(function(e) {
                    return '<tr><td>' + Voucher.esc(e.degree) + '</td><td>' + Voucher.esc(e.institute || '-') + '</td><td>' + Voucher.esc(e.board_university || '-') + '</td><td>' + Voucher.esc(e.passing_year || '-') + '</td><td>' + Voucher.esc(e.result || '-') + '</td></tr>';
                }).join('');

                const exp = (d.experiences || []).map(function(x) {
                    return '<tr><td>' + Voucher.esc(x.company_name) + '</td><td>' + Voucher.esc(x.designation || '-') + '</td><td>' + fmtDate(x.from_date) + ' - ' + (fmtDate(x.to_date) || 'Present') + '</td><td>' + Voucher.esc(x.responsibilities || '-') + '</td></tr>';
                }).join('');

                $('#viewModalTitle').text(d.name + ' · ' + d.employee_code);
                $('#viewBody').html(
                    '<div class="profile-head d-flex gap-3 align-items-center mb-2">' + photo +
                        '<div><h4 class="mb-1">' + Voucher.esc(d.name) + '</h4>' +
                        '<div>' + Voucher.esc(d.designation || '') + (d.department ? ' · ' + Voucher.esc(d.department) : '') + '</div>' +
                        '<div class="small opacity-75 mt-1">ID: ' + Voucher.esc(d.employee_code) + ' · ' + Voucher.esc(d.status) +
                        (d.blood_group ? ' · Blood: ' + Voucher.esc(d.blood_group) : '') + '</div>' +
                        '<div class="small opacity-75">' + Voucher.esc(d.phone || '') + (d.email ? ' · ' + Voucher.esc(d.email) : '') + '</div></div>' +
                    '</div>' +
                    '<div class="row profile-section">' +
                        '<div class="col-lg-6"><h6>Personal</h6>' + kv([
                            ["Father's Name", d.father_name], ["Mother's Name", d.mother_name], ["Spouse's Name", d.spouse_name],
                            ['Date of Birth', fmtDate(d.date_of_birth)], ['Gender', d.gender], ['Marital Status', d.marital_status],
                            ['Religion', d.religion], ['Nationality', d.nationality], ['NID No', d.nid_no], ['TIN No', d.tin_no]
                        ]) + '</div>' +
                        '<div class="col-lg-6"><h6>Job</h6>' + kv([
                            ['Employment Type', d.employment_type], ['Work Shift', d.work_shift], ['Reporting To', d.reporting_to_label],
                            ['Joining Date', fmtDate(d.joining_date)], ['Confirmation', fmtDate(d.confirmation_date)], ['Resigned', fmtDate(d.resign_date)],
                            ['Card Valid Until', fmtDate(d.card_valid_until)]
                        ]) + '</div>' +
                        '<div class="col-lg-6"><h6>Contact</h6>' + kv([
                            ['Alt. Mobile', d.alt_phone], ['Present Address', d.present_address], ['Permanent Address', d.permanent_address],
                            ['Emergency Contact', [d.emergency_contact_name, d.emergency_contact_relation ? '(' + d.emergency_contact_relation + ')' : '', d.emergency_contact_phone].filter(Boolean).join(' ')]
                        ]) + '</div>' +
                        '<div class="col-lg-6"><h6>Salary &amp; Payment</h6>' + kv([
                            ['Basic', Voucher.money(d.basic_salary)], ['House Rent', Voucher.money(d.house_rent)], ['Medical', Voucher.money(d.medical_allowance)],
                            ['Transport', Voucher.money(d.transport_allowance)], ['Other', Voucher.money(d.other_allowance)], ['Gross', Voucher.money(d.gross_salary)],
                            ['Payment Mode', d.salary_payment_mode],
                            ['Bank', [d.bank_name, d.bank_branch].filter(Boolean).join(', ')], ['Account', [d.bank_account_name, d.bank_account_no].filter(Boolean).join(' · ')],
                            ['Mobile Banking', [d.mobile_banking_provider, d.mobile_banking_no].filter(Boolean).join(' · ')]
                        ]) + '</div>' +
                        '<div class="col-12"><h6>Education</h6>' + table(['Degree', 'Institute', 'Board / University', 'Year', 'Result'], edu) + '</div>' +
                        '<div class="col-12"><h6>Experience</h6>' + table(['Company', 'Designation', 'Period', 'Responsibilities'], exp) + '</div>' +
                        '<div class="col-12"><h6>Documents</h6>' + (docs ? '<div class="row g-3">' + docs + '</div>' : '<p class="text-muted small">None added.</p>') + '</div>' +
                        (d.description ? '<div class="col-12"><h6>Notes</h6><p>' + Voucher.esc(d.description) + '</p></div>' : '') +
                    '</div>'
                );

                $('#viewIdCardBtn').off('click').on('click', function() { printIdCard(d.id); });
                $('#viewProfilePdfBtn').off('click').on('click', function() { printProfile(d.id); });
                viewModal.show();
            });
        }

        /* ================= status / delete / pdf ================= */
        function toggleEmployeeStatus(id) {
            $.ajax({
                url: "{{ route('admin.employee.status') }}",
                type: 'POST',
                data: { id: id },
                success: function(res) {
                    if (res.status) {
                        toastr.success(res.message);
                        employeeTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function(xhr) {
                    Voucher.ajaxError(xhr, 'Unable to change status.');
                }
            });
        }

        function deleteEmployee(id) {
            Voucher.confirmDelete(
                'Delete this employee?',
                'The profile will be removed from the list. Expense history is kept.',
                "{{ route('admin.employee.delete') }}",
                id,
                function() { employeeTable.ajax.reload(null, false); }
            );
        }

        function printIdCard(id) {
            window.open("{{ route('admin.employee.id-card') }}?id=" + encodeURIComponent(id), '_blank');
        }

        function printProfile(id) {
            window.open("{{ route('admin.employee.profile-pdf') }}?id=" + encodeURIComponent(id), '_blank');
        }

        /* ================= ready ================= */
        $(document).ready(function() {
            employeeModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('employeeModal'));
            viewModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal'));

            Voucher.initSelect2($('#filterDepartment, #filterType, #filterStatus'));

            $('#reporting_to').select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $('#employeeModal'),
                placeholder: 'Search by name or ID',
                allowClear: true,
                ajax: {
                    url: "{{ route('admin.employee.search') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { q: params.term || '', exclude: $('#employee_id').val() };
                    }
                }
            });

            $('#employeeTable').on('xhr.dt', function(e, settings, json) {
                if (!json || !json.summary) return;

                $('#sumActive').text(json.summary.active);
                $('#sumInactive').text(json.summary.inactive);
                $('#sumDepartments').text(json.summary.departments);
                $('#sumPayroll').text(json.summary.payroll);
            });

            Voucher.keepSerial($('#employeeTable'));

            employeeTable = $('#employeeTable').DataTable({
                ajax: listUrl,
                processing: true
            });

            /* ----- filters ----- */
            $('#applyFilters').on('click', function() {
                const params = $.param({
                    department: $('#filterDepartment').val(),
                    employment_type: $('#filterType').val(),
                    status: $('#filterStatus').val()
                });
                employeeTable.ajax.url(listUrl + '?' + params).load();
            });

            $('#resetFilters').on('click', function() {
                $('#filterDepartment, #filterType, #filterStatus').val('').trigger('change.select2');
                employeeTable.ajax.url(listUrl).load();
            });

            $('#refreshBtn').on('click', function() {
                employeeTable.ajax.reload(null, false);
            });

            /* ----- photo / signature ----- */
            $('#photoBox').on('click', function() { $('#photo').trigger('click'); });
            $('#signBox').on('click', function() { $('#signature').trigger('click'); });

            $('#photo').on('change', function() {
                previewFile(this, $('#photoBox'), function() {
                    $('#remove_photo').val('0');
                    $('#removePhotoBtn').removeClass('d-none');
                });
            });

            $('#signature').on('change', function() {
                previewFile(this, $('#signBox'), function() {
                    $('#remove_signature').val('0');
                    $('#removeSignBtn').removeClass('d-none');
                });
            });

            $('#removePhotoBtn').on('click', function() {
                $('#photo').val('');
                $('#remove_photo').val('1');
                setImageBox($('#photoBox'), null, PHOTO_EMPTY);
                $(this).addClass('d-none');
            });

            $('#removeSignBtn').on('click', function() {
                $('#signature').val('');
                $('#remove_signature').val('1');
                setImageBox($('#signBox'), null, SIGN_EMPTY);
                $(this).addClass('d-none');
            });

            /* ----- contact / salary / payment ----- */
            $('#sameAddress').on('change', function() {
                if (this.checked) $('#permanent_address').val($('#present_address').val());
            });

            $('.salary-part').on('input', calcGross);
            $('#salary_payment_mode').on('change', togglePaymentFields);

            /* ----- repeatable rows ----- */
            $('#educationBody, #experienceBody').on('click', '.remove-row', function() {
                $(this).closest('tr').remove();
            });

            $('#documentsBody').on('click', '.remove-doc', function() {
                $(this).closest('.doc-card').remove();
            });

            $('#documentsBody').on('change', '.doc-type', function() {
                $(this).closest('.doc-card').find('.doc-title').text(this.value);
            });

            // Preview a newly chosen document file.
            $('#documentsBody').on('change', '.doc-input', function() {
                const $box = $(this).closest('.doc-file');
                const file = this.files && this.files[0];
                if (!file) return;

                $box.find('.remove-flag').val('0');

                if (file.type === 'application/pdf') {
                    $box.find('.doc-preview').html('<i class="fas fa-file-pdf"></i>');
                } else {
                    const reader = new FileReader();
                    reader.onload = function(e) { $box.find('.doc-preview').html('<img src="' + e.target.result + '" alt="">'); };
                    reader.readAsDataURL(file);
                }
            });

            $('#documentsBody').on('click', '.remove-file', function() {
                const $box = $(this).closest('.doc-file');
                $box.find('.remove-flag').val('1');
                $box.find('.doc-input').val('');
                $box.find('.doc-preview').html('<span class="text-muted small">File will be removed</span>');
                $(this).remove();
            });

            /* ----- save (create or update) ----- */
            $('#employeeForm').on('submit', function(e) {
                e.preventDefault();

                const $form = $(this);
                const $button = $('#saveBtn');
                const label = $('#employee_id').val() ? 'Update' : 'Save';

                Voucher.clearErrors($form);
                $('#form_error').text('');
                $('#employeeModal .nav-link').removeClass('has-error');

                $.ajax({
                    url: "{{ route('admin.employee.store') }}",
                    type: 'POST',
                    data: new FormData(this),
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
                    },
                    success: function(res) {
                        if (res.status) {
                            employeeModal.hide();
                            toastr.success(res.message);
                            employeeTable.ajax.reload(null, false);
                        } else {
                            toastr.error(res.message || 'Unable to save employee.');
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($form, xhr.responseJSON.errors, $('#form_error'));

                            // Mark tabs with errors and open the first one.
                            const $panes = $form.find('.is-invalid').closest('.tab-pane');
                            $panes.each(function() {
                                $('#employeeModal [data-bs-target="#' + this.id + '"]').addClass('has-error');
                            });
                            if ($panes.length) {
                                bootstrap.Tab.getOrCreateInstance(document.querySelector('#employeeModal [data-bs-target="#' + $panes.first().attr('id') + '"]')).show();
                            }
                            toastr.error(xhr.responseJSON.message || 'Please correct the highlighted fields.');
                        } else if (xhr.status === 413) {
                            toastr.error('The uploaded files are too large.');
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to save employee.');
                        }
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(label);
                    }
                });
            });
        });
    </script>
@endpush
