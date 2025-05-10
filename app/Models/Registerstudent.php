<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registerstudent extends Model
{
    use HasFactory;
    protected $table        =   'final_pharmacy_register_student';
    protected $primaryKey   =   's_id';
    public $timestamps      =   true;

    protected $guarded = [];
}
