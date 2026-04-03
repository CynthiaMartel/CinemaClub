<script setup>
defineProps({
  rating: { type: Number, default: 0 }
})
</script>

<template>
  <span class="star-display">
    <span class="stars-filled">{{ '★'.repeat(Math.floor(rating / 2)) }}</span><span v-if="rating % 2 !== 0" class="stars-half">½</span>
  </span>
</template>

<style scoped>
.star-display {
  font-size: 11px;
  letter-spacing: 0.05em;
  line-height: 1;
}

.stars-filled,
.stars-half {
  color: #D08700;
}
</style>
