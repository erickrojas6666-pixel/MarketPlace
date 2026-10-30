<?php

namespace App\Models;

// Heredar del modelo nativo de Spatie
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $table = 'roles';

    protected $fillable = [
        'name',
        'guard_name'
    ];
}