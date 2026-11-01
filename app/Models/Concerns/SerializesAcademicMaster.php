<?php

namespace App\Models\Concerns;

use App\Services\AktivasiTahunAjaran;

/** Instance writes only; bulk/raw writers must acquire the singleton themselves. */
trait SerializesAcademicMaster
{
    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            AktivasiTahunAjaran::kunci();

            return parent::save($options);
        });
    }

    public function delete()
    {
        return $this->getConnection()->transaction(function () {
            AktivasiTahunAjaran::kunci();

            return parent::delete();
        });
    }
}
