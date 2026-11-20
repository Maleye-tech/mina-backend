<?php

namespace Modules\Library\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Library\Models\Level;
use Modules\Library\Models\Subject;

class TaxonomyController extends Controller
{
    public function subjects(): JsonResponse
    {
        return response()->json([
            'subjects' => Subject::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'description', 'color', 'sort_order']),
        ]);
    }

    public function levels(): JsonResponse
    {
        return response()->json([
            'levels' => Level::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'sort_order']),
        ]);
    }
}
