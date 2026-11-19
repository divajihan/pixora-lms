<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Running certificate number per year (see NextCertificateNumber).
 *
 * @property int $id
 * @property int $year
 * @property int $last_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['year', 'last_number'])]
class CertificateSequence extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['year' => 'integer', 'last_number' => 'integer'];
    }
}
