<?php

namespace App\Models\Concerns;

use Illuminate\Validation\ValidationException;

trait MelindungiRiwayat
{
    protected static function bootMelindungiRiwayat(): void
    {
        static::deleting(function ($model) {
            foreach ($model->relasiDilindungi() as $relation) {
                if ($model->{$relation}()->exists()) {
                    throw ValidationException::withMessages([
                        'hapus' => 'Data masih dirujuk riwayat atau data terkait. Nonaktifkan data bila tersedia; jangan hapus riwayat.',
                    ]);
                }
            }
        });
    }
}
