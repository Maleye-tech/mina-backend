<?php

namespace Modules\WellBeing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\WellBeing\Http\Resources\EmergencyContactResource;
use Modules\WellBeing\Models\EmergencyContact;

class EmergencyContactController extends Controller
{
    /**
     * Liste des contacts d'urgence actifs, validés par Volkeno.
     */
    public function index(): AnonymousResourceCollection
    {
        $contacts = EmergencyContact::query()
            ->active()
            ->orderBy('name')
            ->get();

        return EmergencyContactResource::collection($contacts);
    }
}
