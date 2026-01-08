<script setup>
import { ref, watch } from 'vue';
import { useUserFilmActionsStore } from '@/stores/user_film_actions';
import { storeToRefs } from 'pinia';

const props = defineProps({
  filmId: { type: [Number, String], required: true },
  filmRef: { type: Object, required: true } // Pasamos la ref para que Pinia actualice la media
});

const store = useUserFilmActionsStore();
const { userVote, isSavingRate } = storeToRefs(store);

// Lógica visual 
const hoverWidth = ref(0);


const handleMouseMove = (e) => {
  if (isSavingRate.value) return;
  const rect = e.currentTarget.getBoundingClientRect();
  const x = e.clientX - rect.left;
  const steps = Math.ceil(x / 18);
  hoverWidth.value = steps * 18;
};

const handleMouseLeave = () => {
  hoverWidth.value = 0;
};

const handleStarClick = () => {
  if (isSavingRate.value) return;
  const points = hoverWidth.value / 18;
  userVote.value = points;
  store.saveRating(props.filmId, props.filmRef);
};
</script>

<template>
  <div class="rateit-wrapper">
    <h3 class="rating-title">Tu puntuación</h3>
    
    <div 
      class="rateit-range" 
      @mousemove="handleMouseMove" 
      @mouseleave="handleMouseLeave"
      @click="handleStarClick"
      :class="{ 'is-loading': isSavingRate }"
    >
      <div class="stars-layer stars-empty"></div>
      
      <div 
        class="stars-layer stars-selected" 
        :style="{ width: (userVote * 18) + 'px' }"
      ></div>
      
      <div 
        class="stars-layer stars-hover" 
        :style="{ width: hoverWidth + 'px' }"
      ></div>
    </div>

    <div class="rating-info">
      <span v-if="isSavingRate" class="saving-tag">Guardando...</span>
      <span v-else class="score-tag ">{{ hoverWidth > 0 ? hoverWidth / 18 : userVote }} </span>
    </div>
  </div>
</template>

<style scoped>
.rateit-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.rating-title {
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #64748b;
}

.rateit-range {
  position: relative;
  /* 18px * 10 pasos = 180px exactos */
  width: 180px; 
  height: 36px;
  cursor: pointer;
  overflow: hidden;
  background: transparent;
}

.is-loading {
  opacity: 0.5;
  pointer-events: none;
}

.stars-layer {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  white-space: nowrap;
  pointer-events: none;
  overflow: hidden;
}

.stars-layer::before {
  font-family: "bootstrap-icons";
  font-size: 36px;       /* La estrella mide 36px de ancho */
  letter-spacing: 0px;   /* No dejamos espacio entre ellas */
 
  line-height: 36px;
  display: block;
  width: 200px; 
}

.stars-empty {
  color: #334155;
  z-index: 0;
}
.stars-empty::before {
  content: "\f588 \f588 \f588 \f588 \f588"; /* bi-star estrella vacía */
}

.stars-selected {
  color: #BE2B0C;
  z-index: 1;
  transition: width 0.2s ease-out;
}
.stars-selected::before {
  content: "\f586 \f586 \f586 \f586 \f586"; /* bi-star-fill estrella llena */
}

.stars-hover {
  color: #D08700;
  z-index: 2;
}
.stars-hover::before {
  content: "\f586 \f586 \f586 \f586 \f586"; 
}

.rating-info {
  height: 14px;
  font-size: 11px;
  font-weight: bold;
}

.score-tag {
  font-size: 11px;
  font-weight: bold;
  color: #94a3b8;
}

.saving-tag {
  font-size: 10px;
  color: #D08700;
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
</style>