<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class EquipmentController extends Controller
{
    public function index()
    {
        $headers = Schema::getColumnListing('Equipments');

        $equipments = Equipment::query()
            ->orderBy('ChangedOn', 'desc')
            ->orderBy('Equipment')
            ->paginate(25);

        return view('equipments.index', [
            'headers' => $headers,
            'initialPage' => $equipments, // paginator
        ]);
    }

    public function search(Request $request)
    {
        $headers = Schema::getColumnListing('Equipments');

        $q = trim((string) $request->query('q', ''));

        $query = Equipment::query();

        // SEARCH ONLY THESE 4 COLUMNS
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

            $query->where(function ($sub) use ($like) {
                $sub->where('Equipment', 'like', $like)
                    ->orWhere('Material', 'like', $like)
                    ->orWhere('Description', 'like', $like)
                    ->orWhere('Room', 'like', $like);
            });
        }

        $page = $query
            ->orderBy('ChangedOn', 'desc')
            ->orderBy('Equipment')
            ->paginate(25)
            ->appends(['q' => $q]); // keep query in links

        return response()->json([
            'headers' => $headers,
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
        ]);
    }
}
