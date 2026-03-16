<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeMobilityMonthlyComment extends Model
{
    protected $connection = 'pgsql_external';

    protected $table = 'employee_mobility_monthly_comments';

    protected $fillable = [
        'employee_id',
        'period_month',
        'monthly_comment',
    ];

    protected $casts = [
        'period_month' => 'date:Y-m-d',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

