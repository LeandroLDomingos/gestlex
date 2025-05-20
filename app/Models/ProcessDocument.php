<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage; // Importar Storage

class ProcessDocument extends Model
{
    use HasFactory;
    use HasUuids; // Se estiver usando UUIDs para a PK desta tabela

    protected $fillable = [
        'process_id',
        'uploader_user_id',
        'name',         // Nome original do arquivo
        'path',         // Caminho do arquivo no disco de storage (ex: 'process_documents/uuid_processo/nome_arquivo.pdf')
        'mime_type',
        'size',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'process_id' => 'string', // Se process.id for UUID
        'uploader_user_id' => 'string', // Se user.id for UUID
        'size' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['url']; // Adiciona o acessor 'url' à serialização do modelo

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
     * Get the publicly accessible URL for the document.
     * NOVO ACESSOR
     */
    public function getUrlAttribute(): ?string
    {
        if ($this->path) {
            // Assumindo que você está usando o disco 'public' e que o link simbólico foi criado
            return Storage::disk('public')->url($this->path);
        }
        return null;
    }
}
