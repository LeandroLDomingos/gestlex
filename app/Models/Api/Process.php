<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Process extends Model
{
    use HasFactory, HasUuids;

    const WORKFLOW_TYPE = [
        1 => 'Prospecção',
        2 => 'Consultivo',
        3 => 'Administrativo',
        4 => 'Judicia',
    ];

    protected $fillable = [
        'title',
        'origin',
        'negotiated_value',
        'description',
        'responsible_id',
        'workflow',
        'stage',
    ];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_processes');
    }

    public function getWorkflowType(){
        return self::WORKFLOW_TYPE[$this->type];
    }

    // $this->service->getCourseType()
}
