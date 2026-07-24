<?php

namespace App\Http\Requests;

use App\Models\Previaje;

/** RF-09: alta de un previaje nuevo. */
class StorePreviajeRequest extends PreviajeRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Previaje::class);
    }

    protected function previajeExistente(): ?Previaje
    {
        return null;
    }
}
