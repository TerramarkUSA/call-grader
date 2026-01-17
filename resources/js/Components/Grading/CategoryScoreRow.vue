<template>
  <div
    :class="[
      'transition-all duration-200',
      isNextToScore && !score ? 'bg-blue-50/50' : ''
    ]"
  >
    <!-- Main Row (Always Visible) -->
    <div class="px-4 py-3">
      <div class="flex items-start justify-between gap-4">
        <!-- Left: Category Info -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-1">
            <h3 class="font-medium text-gray-900">{{ category.name }}</h3>
            <span class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">
              {{ category.weight }}%
            </span>
          </div>
          <p class="text-sm text-gray-500">{{ category.description }}</p>
        </div>

        <!-- Right: Score Buttons -->
        <div class="flex items-center gap-1.5">
          <button
            v-for="n in 4"
            :key="n"
            @click="$emit('update-score', n)"
            :class="[
              'w-10 h-10 rounded-lg font-medium text-sm transition-all duration-150',
              score === n
                ? 'bg-blue-600 text-white shadow-sm'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            ]"
          >
            {{ n }}
          </button>
        </div>
      </div>

      <!-- Expand Toggle -->
      <button
        @click="$emit('toggle-expand')"
        class="mt-2 text-xs text-gray-400 hover:text-gray-600 flex items-center gap-1"
      >
        <svg
          :class="['w-3 h-3 transition-transform', isExpanded ? 'rotate-90' : '']"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        {{ isExpanded ? 'Hide' : 'Show' }} training details
      </button>
    </div>

    <!-- Expanded Details -->
    <div
      v-if="isExpanded"
      class="px-4 pb-4"
    >
      <div class="bg-gray-50 rounded-lg p-3 text-sm">
        <!-- Training Reference -->
        <div v-if="category.training_reference" class="mb-3">
          <h4 class="font-medium text-gray-700 mb-1 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            Training Reference
          </h4>
          <p class="text-gray-600 italic">"{{ category.training_reference }}"</p>
        </div>

        <!-- Score Meanings -->
        <div>
          <h4 class="font-medium text-gray-700 mb-2">What each score means:</h4>
          <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="flex items-start gap-2">
              <span class="bg-red-100 text-red-700 font-medium px-1.5 py-0.5 rounded">1</span>
              <span class="text-gray-600">Did not demonstrate this skill at all</span>
            </div>
            <div class="flex items-start gap-2">
              <span class="bg-yellow-100 text-yellow-700 font-medium px-1.5 py-0.5 rounded">2</span>
              <span class="text-gray-600">Showed some attempt but incomplete</span>
            </div>
            <div class="flex items-start gap-2">
              <span class="bg-blue-100 text-blue-700 font-medium px-1.5 py-0.5 rounded">3</span>
              <span class="text-gray-600">Fully demonstrated as expected</span>
            </div>
            <div class="flex items-start gap-2">
              <span class="bg-green-100 text-green-700 font-medium px-1.5 py-0.5 rounded">4</span>
              <span class="text-gray-600">Exceeded expectations, exceptional</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  category: { type: Object, required: true },
  score: { type: Number, default: null },
  isExpanded: { type: Boolean, default: false },
  isNextToScore: { type: Boolean, default: false },
});

defineEmits(['update-score', 'toggle-expand']);
</script>
