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
     
    public function toggle($entryId): JsonResponse
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
            UserProfile::where('user_id', $user->id)->decrement('lists_saved');
            $message = 'Lista eliminada de tus guardadas.';
        } else {
            UserSavedList::create([
                'user_id' => $user->id,
                'user_entry_id' => $entryId,
            ]);
            UserProfile::where('user_id', $user->id)->increment('lists_saved');
            $message = 'Lista guardada correctamente.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ], 200);
    }
}

