<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalFlowArea extends Model
{
    protected $primaryKey = 'area';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['area', 'label'];
}
