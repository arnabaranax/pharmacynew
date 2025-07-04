<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    use HasFactory;
    protected $table        =   'pharmacy_tokens';
    protected $primaryKey   =   't_token';
    public $timestamps      =   false;

    protected $guarded = [];
}
