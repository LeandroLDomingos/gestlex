<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Use se 'id' for UUID
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ContactDocument extends Model
{
    use HasFactory;
    use HasUuids; // Remova se 'id' não for UUID

    protected $fillable = [
        'contact_id',
        'uploader_user_id',
        'name',
        'path',
        'mime_type',
        'size',
        'description',
    ];

    protected $casts = [
        'size' => 'integer',
        'contact_id' => 'string', // Se UUID
        'uploader_user_id' => 'string', // Se UUID
    ];

    // Adiciona o atributo 'url' acessível no frontend via $document->url
    protected $appends = ['url', 'formatted_size'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_user_id');
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->path) {
            // Assumindo que você usa o disco 'public' e `php artisan storage:link` foi executado
            return Storage::disk('public')->url($this->path);
        }
        return null;
    }

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
