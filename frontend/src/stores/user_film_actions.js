import { ref } from 'vue';
import { defineStore } from 'pinia';
import api from '@/services/api'; 

export const useUserFilmActionsStore = defineStore('userFilmActions', () => {
    const userVote = ref(0);
    const isSavingRate = ref(false);

    //Funci para guardar nota individual de cada user
    const saveRating = async (filmId, filmRef) => {
        if (isSavingRate.value || !filmId) return;
        
        isSavingRate.value = true;
        try {
            
            const url = `/films/createOrEdit/${filmId}`;
            const payload = { rating: userVote.value };

            const response = await api.post(url, payload);
            
            if (response.data.success) {
                // Actualizamos los datos en el objeto film que pasamos por referencia
                if (filmRef.value) {
                    //Actualizamos la media global del club
                    filmRef.value.globalRate = response.data.new_global_rate;
                    
                    // Aseguramos que el objeto user_action existe y actualizamos el rating
                    if (!filmRef.value.user_action) filmRef.value.user_action = {};
                    filmRef.value.user_action.rating = response.data.data.rating;
                }
                console.log("¡Puntuación actualizada en CinemaClub!");
            }
        } catch (err) {
            console.error("Error al guardar calificación:", err);
            alert("No se pudo guardar la nota.");
        } finally {
            isSavingRate.value = false;
        }
    };

    return {
        userVote,
        isSavingRate,
        saveRating
    };
});