<?php

namespace Modules\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Point d'entrée historique du module (l'API vit sous /api/v1/conversations).
     */
    public function index(Request $request)
    {
        return response()->json([
            'message' => 'L\'API des conversations est disponible sous /api/v1/conversations.',
        ]);
    }
}
