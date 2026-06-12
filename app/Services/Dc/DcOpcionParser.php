<?php

namespace App\Services\Dc;

class DcOpcionParser
{
    /** Opción válida (1, 2 o 3) solo si description es exactamente ese dígito. */
    public function parse(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $desc = trim($description);

        return in_array($desc, config('dc_sap_export.valid_opciones', ['1', '2', '3']), true)
            ? $desc
            : null;
    }
}
