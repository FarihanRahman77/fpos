<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttributeType extends Model
{
    use HasFactory;
    protected $table = 'attribute_types';

    public $timestamps = false;
}
