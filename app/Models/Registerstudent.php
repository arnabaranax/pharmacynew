<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registerstudent extends Model
{
    use HasFactory;
    protected $table        =   'pharmacy_register_student_final';
    protected $primaryKey   =   's_id';
    public $timestamps      =   true;

    protected $guarded = [];
}
