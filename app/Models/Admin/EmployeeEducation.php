<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeEducation extends Model
{
    use HasFactory;

    // Laravel treats "education" as uncountable and would look for employee_education.
    protected $table = 'employee_educations';
}
