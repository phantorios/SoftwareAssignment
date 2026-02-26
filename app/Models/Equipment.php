<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $table = 'Equipments';
    protected $primaryKey = 'Equipment';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    // ✅ allow all columns (import job)
    protected $guarded = [];
}
