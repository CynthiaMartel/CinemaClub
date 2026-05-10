<?php

namespace App\Http\Controllers;

use App\Models\UserEntry;
use App\Models\UserEntryFilm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;

class UserEntryFilmController extends Controller
{
    /**
     * Asociar una película a una entrada (review o lista)
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'user_entry_id' => 'required|integer|exists:user_entries,id',
            'film_id' => 'required|integer|exists:films,idFilm',
            'order' => 'nullable|integer|min:1',
        ]);

        $entry = UserEntry::findOrFail($request->user_entry_id);
        if ($entry->user_id !== $user->id) {
            return response()->json(['error' => 'No tienes permiso para modificar esta entrada.'], 403);
        }

        $already = UserEntryFilm::where('user_entry_id', $request->user_entry_id)
            ->where('film_id', $request->film_id)
            ->first();

        if ($already) {
            return response()->json([
                'success' => false,
                'message' => 'Esta película ya está en la lista.',
                'data' => $already,
            ], 409);
        }

        try {
            $relation = UserEntryFilm::create([
                'user_entry_id' => $request->user_entry_id,
                'film_id' => $request->film_id,
                'order' => $request->order,
            ]);
        } catch (QueryException $e) {
            // Race condition: another request inserted the row between our check and create
            if ($e->errorInfo[1] === 1062) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta película ya está en la lista.',
                ], 409);
            }
            throw $e;
        }

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
        $user = Auth::user();
        $relation = UserEntryFilm::with('entry')->findOrFail($id);

        if ($relation->entry->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'No tienes permiso para eliminar esta relación.'], 403);
        }

        $relation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Película eliminada de la entrada.',
        ], 200);
    }
}

