<?php

namespace App\Http\Controllers;

use App\Models\UserSavedList;
use App\Models\UserProfile;
use App\Models\UserEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class UserSavedListController extends Controller
{
    // GUARDAR o QUITAR una lista 
     
    public function toggleSaveList($entryId): JsonResponse
    {
        $user = Auth::user();
        

        $entry = UserEntry::findOrFail($entryId);

        if ($entry->type !== 'user_list') {
            return response()->json(['error' => 'Solo se pueden guardar listas.'], 400);
        }

    
        $saved = UserSavedList::where('user_id', $user->id)
            ->where('user_entry_id', $entryId)
            ->first();

        if ($saved) {
            
            $saved->delete();
            $isSavedNow = false;
            $message = 'Lista eliminada de tus guardadas.';
        } else {
            // Si no existe, la creamos
            UserSavedList::create([
                'user_id' => $user->id,
                'user_entry_id' => $entryId,
            ]);
            $isSavedNow = true;
            $message = 'Lista guardada correctamente.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_saved' => $isSavedNow, // Devolvemos el estado final para el frontend
        ], 200);
    }

 

    public function getSavedLists($userId): JsonResponse
    {
        
        $savedEntries = UserSavedList::where('user_id', $userId)
            ->whereHas('entry', function($query) {
                $query->where('type', 'user_list'); // Solo queremos listas guardadas
            })
            ->with(['entry.user:id,name', 'entry.films:idFilm,frame']) 
            ->get()
            ->pluck('entry'); // Esto hace que se reciba un array de entradas directo

        return response()->json([
            'success' => true,
            'data' => $savedEntries
        ], 200);
    }
}

