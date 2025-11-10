<?php

namespace App\Http\Controllers;

use App\Models\UserEntryFilm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserEntryFilmController extends Controller
{
    /**
     * 🎬 Asociar una película a una entrada (review o lista)
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_entry_id' => 'required|integer|exists:user_entries,id',
            'film_id' => 'required|integer|exists:films,idFilm',
            'order' => 'nullable|integer|min:1',
        ]);

        $relation = UserEntryFilm::create([
            'user_entry_id' => $request->user_entry_id,
            'film_id' => $request->film_id,
            'order' => $request->order,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Película asociada correctamente.',
            'data' => $relation,
        ], 201);
    }

    // MOSTRAR entradas asociada a todas las películas 
     
        public function showByFilm($filmId)
    {
        $entries = UserEntryFilm::where('film_id', $filmId)
            ->with(['entry:id,title,type,user_id', 'entry.user:id,name'])
            ->get();

        return response()->json([
            'success' => true,
            'total' => $entries->count(),
            'data' => $entries,
        ], 200);
    }


    // ELIMINAR una asociación película- entrada con id de la asociación UserEntryFilm
     
    public function destroy($id)
    {
        $relation = UserEntryFilm::findOrFail($id);
        $relation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Película eliminada de la entrada.',
        ], 200);
    }
}

