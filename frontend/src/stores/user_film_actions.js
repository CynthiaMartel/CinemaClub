import { ref } from 'vue';
import { defineStore } from 'pinia';
import api from '@/services/api';

export const useUserFilmActionsStore = defineStore('userFilmActions', () => {
    const userVote = ref(0);
    const isSavingRate = ref(false);
    const isProcessingAction = ref(false);

    const saveRating = async (filmId, filmRef) => {
        if (isSavingRate.value || !filmId) return;
        
        // Detectar si es un ref o un objeto plano
        const filmData = filmRef.value !== undefined ? filmRef.value : filmRef;
        if (!filmData) return;

        isSavingRate.value = true;
        try {
            const url = `/films/createOrEdit/${filmId}`;
            const payload = { rating: userVote.value };
            const response = await api.post(url, payload);
            
            if (response.data.success) {
                filmData.globalRate = response.data.new_global_rate;
                if (!filmData.user_action) filmData.user_action = {};
                filmData.user_action.rating = response.data.data.rating;
                console.log("¡Puntuación actualizada!");
            }
        } catch (err) {
            console.error("Error al guardar calificación:", err);
        } finally {
            isSavingRate.value = false;
        }
    };

    const toggleAction = async (filmId, filmRef, field) => {
        if (isProcessingAction.value || !filmId) return;

        
        // Si filmRef.value existe, lo usamos. Si no, usamos filmRef directamente.
        const filmData = filmRef.value !== undefined ? filmRef.value : filmRef;
        
        if (!filmData) {
            console.error("No se pudo acceder a los datos de la película.");
            return;
        }

        const currentState = filmData.user_action?.[field] || false;
        isProcessingAction.value = true;

        try {
            let response;
            if (currentState) {
                response = await api.delete(`/films/unmarkAction/${filmId}`, {
                    data: { field: field }
                });
            } else {
                const payload = { [field]: true };
                response = await api.post(`/films/createOrEdit/${filmId}`, payload);
            }

            if (response.data.success) {
                if (!filmData.user_action) filmData.user_action = {};
                // Actualizamos el campo específico con la respuesta del servidor
                filmData.user_action[field] = response.data.data[field];
                console.log(`Acción ${field} sincronizada.`);
            }
        } catch (err) {
            console.error(`Error al procesar ${field}:`, err);
        } finally {
            isProcessingAction.value = false;
        }
    };

    const toggleFavorite = (filmId, filmRef) => toggleAction(filmId, filmRef, 'is_favorite');
    const toggleWatched = (filmId, filmRef) => toggleAction(filmId, filmRef, 'watched');
    const toggleWatchLater = (filmId, filmRef) => toggleAction(filmId, filmRef, 'watch_later');

    const deleteRating = async (filmId, filmRef) => {
        if (isSavingRate.value || !filmId) return;
        const filmData = filmRef.value !== undefined ? filmRef.value : filmRef;
        if (!filmData) return;

        isSavingRate.value = true;
        try {
            const response = await api.delete(`/films/unmarkAction/${filmId}`, {
                data: { field: 'rating' }
            });
            if (response.data.success) {
                userVote.value = 0;
                if (filmData.user_action) filmData.user_action.rating = null;
            }
        } catch (err) {
            console.error('Error al borrar puntuación:', err);
        } finally {
            isSavingRate.value = false;
        }
    };

    return {
        userVote,
        isSavingRate,
        isProcessingAction,
        saveRating,
        deleteRating,
        toggleFavorite,
        toggleWatched,
        toggleWatchLater,
        toggleAction
    };



});
