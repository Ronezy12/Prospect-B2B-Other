<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    protected $table = 'pm_import_rows';

    protected $fillable = ['import_id', 'row_number', 'result', 'messages', 'company_id', 'contact_id'];

    protected function casts(): array
    {
        return ['messages' => 'array'];
    }

    public const RESULT_LABELS = [
        'created' => 'Créé',
        'updated' => 'Mis à jour',
        'skipped_duplicate' => 'Ignoré (doublon)',
        'skipped_suppressed' => 'Contact exclu (opposition)',
        'error' => 'Erreur',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }
}
