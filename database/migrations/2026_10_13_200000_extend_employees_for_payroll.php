<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Personal
            $table->string('father_name', 150)->nullable()->after('name');
            $table->string('mother_name', 150)->nullable()->after('father_name');
            $table->string('spouse_name', 150)->nullable()->after('mother_name');
            $table->date('date_of_birth')->nullable()->after('spouse_name');
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable()->after('date_of_birth');
            $table->enum('marital_status', ['Single', 'Married', 'Divorced', 'Widowed'])->nullable()->after('gender');
            $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable()->after('marital_status');
            $table->string('religion', 50)->nullable()->after('blood_group');
            $table->string('nationality', 50)->nullable()->after('religion');
            $table->string('nid_no', 50)->nullable()->after('nationality');
            $table->string('photo', 255)->nullable()->after('nid_no');
            $table->string('signature', 255)->nullable()->after('photo');

            // Contact
            $table->string('alt_phone', 30)->nullable()->after('phone');
            $table->text('present_address')->nullable()->after('email');
            $table->text('permanent_address')->nullable()->after('present_address');
            $table->string('emergency_contact_name', 150)->nullable()->after('permanent_address');
            $table->string('emergency_contact_relation', 50)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_relation');

            // Job
            $table->string('department', 100)->nullable()->after('designation');
            $table->enum('employment_type', ['Permanent', 'Probation', 'Contractual', 'Part-Time', 'Intern'])->nullable()->after('department');
            $table->string('work_shift', 50)->nullable()->after('employment_type');
            $table->foreignId('reporting_to')->nullable()->after('work_shift')->constrained('employees');
            $table->date('joining_date')->nullable()->after('reporting_to');
            $table->date('confirmation_date')->nullable()->after('joining_date');
            $table->date('resign_date')->nullable()->after('confirmation_date');
            $table->date('card_valid_until')->nullable()->after('resign_date');

            // Salary & payment
            $table->decimal('basic_salary', 18, 2)->default(0)->after('card_valid_until');
            $table->decimal('house_rent', 18, 2)->default(0)->after('basic_salary');
            $table->decimal('medical_allowance', 18, 2)->default(0)->after('house_rent');
            $table->decimal('transport_allowance', 18, 2)->default(0)->after('medical_allowance');
            $table->decimal('other_allowance', 18, 2)->default(0)->after('transport_allowance');
            $table->decimal('gross_salary', 18, 2)->default(0)->after('other_allowance');
            $table->enum('salary_payment_mode', ['Cash', 'Bank', 'Mobile Banking'])->nullable()->after('gross_salary');
            $table->string('bank_name', 100)->nullable()->after('salary_payment_mode');
            $table->string('bank_branch', 100)->nullable()->after('bank_name');
            $table->string('bank_account_name', 150)->nullable()->after('bank_branch');
            $table->string('bank_account_no', 50)->nullable()->after('bank_account_name');
            $table->string('mobile_banking_provider', 50)->nullable()->after('bank_account_no');
            $table->string('mobile_banking_no', 30)->nullable()->after('mobile_banking_provider');
            $table->string('tin_no', 50)->nullable()->after('mobile_banking_no');

            $table->dateTime('deleted_datetime')->nullable()->after('updated_date');
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            // NID, Passport, Driving License, Appointment Letter, Birth Certificate, ...
            $table->string('document_type', 50);
            $table->string('document_no', 100)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('issued_by', 150)->nullable();
            $table->string('file_front', 255)->nullable();   // scan / photograph (front side)
            $table->string('file_back', 255)->nullable();    // back side (NID, licence ...)
            $table->string('remarks', 255)->nullable();
            $this->auditColumns($table);
        });

        Schema::create('employee_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('degree', 100);
            $table->string('institute', 150)->nullable();
            $table->string('board_university', 150)->nullable();
            $table->string('passing_year', 10)->nullable();
            $table->string('result', 30)->nullable();
            $this->auditColumns($table);
        });

        Schema::create('employee_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('company_name', 150);
            $table->string('designation', 100)->nullable();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->string('responsibilities', 500)->nullable();
            $this->auditColumns($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_experiences');
        Schema::dropIfExists('employee_educations');
        Schema::dropIfExists('employee_documents');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['reporting_to']);
            $table->dropColumn([
                'father_name', 'mother_name', 'spouse_name', 'date_of_birth', 'gender', 'marital_status',
                'blood_group', 'religion', 'nationality', 'nid_no', 'photo', 'signature',
                'alt_phone', 'present_address', 'permanent_address',
                'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
                'department', 'employment_type', 'work_shift', 'reporting_to',
                'joining_date', 'confirmation_date', 'resign_date', 'card_valid_until',
                'basic_salary', 'house_rent', 'medical_allowance', 'transport_allowance', 'other_allowance', 'gross_salary',
                'salary_payment_mode', 'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_no',
                'mobile_banking_provider', 'mobile_banking_no', 'tin_no', 'deleted_datetime',
            ]);
        });
    }

    private function auditColumns(Blueprint $table): void
    {
        $table->enum('deleted', ['Yes', 'No'])->default('No');
        $table->enum('status', ['Active', 'Inactive'])->default('Active');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->unsignedBigInteger('deleted_by')->nullable();
        $table->dateTime('created_date')->nullable();
        $table->dateTime('updated_date')->nullable();
        $table->dateTime('deleted_datetime')->nullable();
        $table->timestamps();
    }
};
