<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Se 'id' for UUID
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage; // Para gerar URLs de acesso, se necessário

class ProcessDocument extends Model
{
    use HasFactory;
    use HasUuids; // Remova se 'id' não for UUID

    protected $fillable = [
        'process_id',
        'uploader_user_id',
        'name',
        'path',
        'mime_type',
        'size',
        'description',
    ];

    protected $casts = [
        'size' => 'integer',
        'process_id' => 'string', // Se UUID
        'uploader_user_id' => 'string', // Se UUID
    ];

    /**
     * Get the process that owns the document.
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    /**
     * Get the user who uploaded the document.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_user_id');
    }

    /**
     * Accessor for the public URL of the document.
     * Certifique-se de que seu disco de storage (ex: 'public') está configurado
     * e que você executou `php artisan storage:link`.
     */
    public function getUrlAttribute(): ?string
    {
        if ($this->path) {
            // Assumindo que você está usando o disco 'public'
            // Se usar outro disco (ex: S3), a lógica para gerar URL será diferente.
            return Storage::disk('public')->url($this->path);
        }
        return null;
    }

    /**
     * Accessor for a user-friendly file size.
     */
    public function getFormattedSizeAttribute(): string
    {
        if (is_null($this->size)) {
            return 'N/A';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->size;
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
