<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $fillable = [
        'mode',
        'manual_qris_path',
        'manual_qris_mime',
        'whatsapp_number',
    ];

    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1], ['mode' => 'realtime']);
    }

    public function isManual(): bool
    {
        return $this->mode === 'manual';
    }
}
