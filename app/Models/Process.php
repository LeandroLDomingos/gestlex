<?php

namespace App\Models;

use App\Enums\WorkflowType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Process extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'origin',
        'negotiated_value',
        'description',
        'responsible_id',
        'workflow',
        'stage',
    ];

    protected $casts = [
        'workflow' => WorkflowType::class,
    ];

    protected $appends = ['workflow_label'];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_processes');
    }

    /**
     * Accessor: human readable label for workflow enum
     */
    public function getWorkflowLabelAttribute(): string
    {
        try {
            return $this->workflow->label();
        } catch (\ValueError $e) {
            return 'Unknown';
        }
    }

    /**
     * Helper to get options for select inputs
     *
     * @return array<string, string> [value => label]
     */
    public static function getWorkflowOptions(): array
    {
        return collect(WorkflowType::cases())
            ->mapWithKeys(fn(WorkflowType $case) => [
                $case->value => $case->label(),
            ])
            ->toArray();
    }
}