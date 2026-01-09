<?php

namespace App\Http\Controllers;

use App\Models\CastCrew;
use Illuminate\Http\Request;

class CastCrewController extends Controller
{
    // Muestra la ficha de una persona (Actor/Director)con sus films asociados
     
    public function show($id)
    {
        // Buscamos por la clave primaria personalizada idPerson
        // Cargamos también sus películas usando la relación 'films' definida en el modelo
        $person = CastCrew::with(['films' => function($query) {
            $query->orderBy('release_date', 'desc');
        }])->find($id);

        if (!$person) {
            return response()->json(['message' => 'Persona no encontrada'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $person
        ]);
    }
}
