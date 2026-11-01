<?php

return [
    // Proposal only: administrators review/edit generated dates before activation.
    // Persist a school-specific pattern via deployment environment configuration.
    'mulai' => env('TAHUN_AJARAN_MULAI', '07-01'),
    'selesai' => env('TAHUN_AJARAN_SELESAI', '06-30'),
];
