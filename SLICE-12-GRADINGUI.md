# Slice 12: Grading Page UI Redesign

## Overview
Complete redesign of the grading page with three-column layout, improved rubric display, and better visual hierarchy.

## Layout Structure

```
Page: max-w-[1600px] mx-auto px-8 (small borders on sides)

┌────────────────────────────────────────────────────────────────┐
│                     AUDIO PLAYER (full width)                   │
├──────────────────┬──────────────────────┬──────────────────────┤
│    TRANSCRIPT    │    RUBRIC COLUMN     │  CHECKPOINTS/NOTES   │
│      w-[35%]     │       w-[40%]        │       w-[25%]        │
└──────────────────┴──────────────────────┴──────────────────────┘
```

---

## File: resources/js/Pages/Manager/Grading/Show.vue

```vue
<template>
  <ManagerLayout>
    <div class="max-w-[1600px] mx-auto px-8 py-6">
      <!-- Audio Player -->
      <AudioPlayer 
        :call="call" 
        :audio-url="audioUrl"
        @time-update="onTimeUpdate"
        class="mb-6"
      />

      <!-- Three Column Layout -->
      <div class="flex gap-6">
        <!-- Column 1: Transcript (35%) -->
        <div class="w-[35%] flex-shrink-0">
          <TranscriptViewer
            :utterances="call.transcript"
            :current-time="currentTime"
            :speakers-swapped="call.speakers_swapped"
            @click-utterance="openNoteModal"
            @swap-speakers="swapSpeakers"
          />
        </div>

        <!-- Column 2: Rubric Scoring (40%) -->
        <div class="w-[40%] flex-shrink-0">
          <RubricPanel
            :categories="categories"
            :scores="categoryScores"
            :call="call"
            :reps="reps"
            :projects="projects"
            @update-score="updateCategoryScore"
            @update-call-details="updateCallDetails"
            @save-draft="saveDraft"
            @submit-grade="submitGrade"
          />
        </div>

        <!-- Column 3: Checkpoints & Notes (25%) -->
        <div class="w-[25%] flex-shrink-0">
          <CheckpointsPanel
            :checkpoints="checkpoints"
            :checkpoint-values="checkpointValues"
            @update-checkpoint="updateCheckpoint"
          />
          <NotesPanel
            :notes="notes"
            :call-id="call.id"
            @add-note="openNoteModal"
            @click-note="jumpToTimestamp"
            class="mt-6"
          />
        </div>
      </div>
    </div>

    <!-- Add Note Modal -->
    <AddNoteModal
      v-if="showNoteModal"
      :utterance="selectedUtterance"
      :categories="categories"
      :objection-types="objectionTypes"
      @save="saveNote"
      @close="showNoteModal = false"
    />
  </ManagerLayout>
</template>
```

---

## File: resources/js/Components/Grading/TranscriptViewer.vue

```vue
<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <!-- Header -->
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Transcript</h2>
        <p class="text-sm text-gray-500">{{ utterances.length }} utterances · Click to add note</p>
      </div>
      <button
        @click="$emit('swap-speakers')"
        class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1.5 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
        </svg>
        Swap Speakers
      </button>
    </div>

    <!-- Utterances List -->
    <div class="max-h-[calc(100vh-280px)] overflow-y-auto p-4 space-y-3">
      <div
        v-for="(utterance, index) in utterances"
        :key="index"
        @click="$emit('click-utterance', utterance, index)"
        :class="[
          'rounded-lg p-4 cursor-pointer transition-all duration-150',
          'border-l-4',
          utterance.speaker === 'Rep' 
            ? 'bg-blue-50 border-l-blue-400 hover:bg-blue-100' 
            : 'bg-gray-50 border-l-green-400 hover:bg-gray-100',
          isCurrentUtterance(utterance) ? 'ring-2 ring-blue-400' : ''
        ]"
      >
        <!-- Speaker & Timestamp -->
        <div class="flex items-center gap-2 mb-2">
          <span 
            :class="[
              'text-xs font-semibold px-2 py-0.5 rounded-full',
              utterance.speaker === 'Rep' 
                ? 'bg-blue-100 text-blue-700' 
                : 'bg-green-100 text-green-700'
            ]"
          >
            {{ utterance.speaker }}
          </span>
          <span class="text-xs text-gray-400 flex items-center gap-1">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ formatTime(utterance.start) }}
          </span>
        </div>
        
        <!-- Text -->
        <p class="text-sm text-gray-700 leading-relaxed">{{ utterance.text }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  utterances: { type: Array, default: () => [] },
  currentTime: { type: Number, default: 0 },
  speakersSwapped: { type: Boolean, default: false },
});

defineEmits(['click-utterance', 'swap-speakers']);

function formatTime(seconds) {
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

function isCurrentUtterance(utterance) {
  return props.currentTime >= utterance.start && props.currentTime < utterance.end;
}
</script>
```

---

## File: resources/js/Components/Grading/RubricPanel.vue

```vue
<template>
  <div class="space-y-4">
    <!-- Call Details Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
      <h3 class="text-sm font-semibold text-gray-900 mb-3">Call Details</h3>
      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Rep</label>
          <select 
            v-model="localCallDetails.rep_id"
            @change="emitCallDetails"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">Select Rep...</option>
            <option v-for="rep in reps" :key="rep.id" :value="rep.id">
              {{ rep.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Project</label>
          <select 
            v-model="localCallDetails.project_id"
            @change="emitCallDetails"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">Select Project...</option>
            <option v-for="project in projects" :key="project.id" :value="project.id">
              {{ project.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Outcome</label>
          <select 
            v-model="localCallDetails.outcome"
            @change="emitCallDetails"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">Select Outcome...</option>
            <option value="appointment_set">Appointment Set</option>
            <option value="no_appointment">No Appointment</option>
            <option value="callback">Callback Scheduled</option>
            <option value="not_qualified">Not Qualified</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Rubric Scoring Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <!-- Header with Scale Legend -->
      <div class="px-4 py-3 border-b border-gray-100">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-lg font-semibold text-gray-900">Sales Call Evaluation</h2>
          <span class="text-sm text-gray-500">{{ scoredCount }}/{{ categories.length }} categories</span>
        </div>
        
        <!-- Progress Bar -->
        <div class="w-full bg-gray-100 rounded-full h-1.5 mb-3">
          <div 
            class="bg-blue-500 h-1.5 rounded-full transition-all duration-300"
            :style="{ width: `${(scoredCount / categories.length) * 100}%` }"
          />
        </div>
        
        <!-- Scale Legend -->
        <div class="flex items-center gap-4 text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2">
          <span class="font-medium text-gray-700">Scale:</span>
          <span><strong class="text-gray-900">1</strong> = Did not demonstrate</span>
          <span><strong class="text-gray-900">2</strong> = Partially demonstrated</span>
          <span><strong class="text-gray-900">3</strong> = Fully demonstrated</span>
          <span><strong class="text-gray-900">4</strong> = Exceeded expectations</span>
        </div>
      </div>

      <!-- Categories -->
      <div class="divide-y divide-gray-100">
        <CategoryScoreRow
          v-for="category in categories"
          :key="category.id"
          :category="category"
          :score="scores[category.id]"
          :is-expanded="expandedCategory === category.id"
          :is-next-to-score="nextToScore === category.id"
          @update-score="(score) => $emit('update-score', category.id, score)"
          @toggle-expand="toggleExpand(category.id)"
        />
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex gap-3">
      <button
        @click="$emit('save-draft')"
        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-colors"
      >
        Save Draft
      </button>
      <button
        @click="$emit('submit-grade')"
        class="flex-1 px-4 py-2.5 bg-blue-600 rounded-xl text-white font-medium hover:bg-blue-700 transition-colors"
      >
        Submit Grade
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import CategoryScoreRow from './CategoryScoreRow.vue';

const props = defineProps({
  categories: { type: Array, default: () => [] },
  scores: { type: Object, default: () => ({}) },
  call: { type: Object, required: true },
  reps: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
});

const emit = defineEmits(['update-score', 'update-call-details', 'save-draft', 'submit-grade']);

const expandedCategory = ref(null);

const localCallDetails = ref({
  rep_id: props.call.rep_id || '',
  project_id: props.call.project_id || '',
  outcome: props.call.outcome || '',
});

const scoredCount = computed(() => {
  return Object.values(props.scores).filter(s => s !== null && s !== undefined).length;
});

// Auto-expand next unscored category
const nextToScore = computed(() => {
  for (const cat of props.categories) {
    if (!props.scores[cat.id]) {
      return cat.id;
    }
  }
  return null;
});

// Auto-expand the next category to score
watch(nextToScore, (newVal) => {
  if (newVal && !expandedCategory.value) {
    expandedCategory.value = newVal;
  }
}, { immediate: true });

function toggleExpand(categoryId) {
  expandedCategory.value = expandedCategory.value === categoryId ? null : categoryId;
}

function emitCallDetails() {
  emit('update-call-details', localCallDetails.value);
}
</script>
```

---

## File: resources/js/Components/Grading/CategoryScoreRow.vue

```vue
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
```

---

## File: resources/js/Components/Grading/CheckpointsPanel.vue

```vue
<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-4 py-3 border-b border-gray-100">
      <h2 class="text-lg font-semibold text-gray-900">Checkpoints</h2>
    </div>

    <div class="p-4 space-y-6">
      <!-- Should Observe -->
      <div>
        <h3 class="text-sm font-semibold text-green-700 mb-3 flex items-center gap-1.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          Should Observe
        </h3>
        <div class="space-y-3">
          <CheckpointRow
            v-for="cp in positiveCheckpoints"
            :key="cp.id"
            :checkpoint="cp"
            :value="checkpointValues[cp.id]"
            @update="(val) => $emit('update-checkpoint', cp.id, val)"
          />
        </div>
      </div>

      <!-- Should NOT Observe -->
      <div>
        <h3 class="text-sm font-semibold text-red-700 mb-3 flex items-center gap-1.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
          Should NOT Observe
        </h3>
        <div class="space-y-3">
          <CheckpointRow
            v-for="cp in negativeCheckpoints"
            :key="cp.id"
            :checkpoint="cp"
            :value="checkpointValues[cp.id]"
            @update="(val) => $emit('update-checkpoint', cp.id, val)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import CheckpointRow from './CheckpointRow.vue';

const props = defineProps({
  checkpoints: { type: Array, default: () => [] },
  checkpointValues: { type: Object, default: () => ({}) },
});

defineEmits(['update-checkpoint']);

const positiveCheckpoints = computed(() => 
  props.checkpoints.filter(cp => cp.type === 'positive')
);

const negativeCheckpoints = computed(() => 
  props.checkpoints.filter(cp => cp.type === 'negative')
);
</script>
```

---

## File: resources/js/Components/Grading/CheckpointRow.vue

```vue
<template>
  <div class="flex items-start gap-3">
    <!-- Label -->
    <p class="flex-1 text-sm text-gray-700 leading-snug">
      {{ checkpoint.label }}
    </p>
    
    <!-- Buttons -->
    <div class="flex items-center gap-1 flex-shrink-0">
      <button
        @click="$emit('update', null)"
        :class="[
          'w-8 h-8 rounded-lg text-xs font-medium transition-colors',
          value === null || value === undefined
            ? 'bg-gray-200 text-gray-600'
            : 'bg-gray-100 text-gray-400 hover:bg-gray-200'
        ]"
      >
        —
      </button>
      <button
        @click="$emit('update', true)"
        :class="[
          'w-8 h-8 rounded-lg text-xs font-medium transition-colors',
          value === true
            ? 'bg-green-500 text-white'
            : 'bg-gray-100 text-gray-400 hover:bg-gray-200'
        ]"
      >
        Y
      </button>
      <button
        @click="$emit('update', false)"
        :class="[
          'w-8 h-8 rounded-lg text-xs font-medium transition-colors',
          value === false
            ? 'bg-red-500 text-white'
            : 'bg-gray-100 text-gray-400 hover:bg-gray-200'
        ]"
      >
        N
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  checkpoint: { type: Object, required: true },
  value: { default: null },
});

defineEmits(['update']);
</script>
```

---

## File: resources/js/Components/Grading/NotesPanel.vue

```vue
<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
      <h2 class="text-lg font-semibold text-gray-900">Notes ({{ notes.length }})</h2>
    </div>

    <div class="p-4">
      <!-- Notes List -->
      <div v-if="notes.length > 0" class="space-y-2 mb-4">
        <div
          v-for="note in notes"
          :key="note.id"
          @click="$emit('click-note', note)"
          class="p-2 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 transition-colors"
        >
          <div class="flex items-center gap-2 mb-1">
            <span class="text-xs text-blue-600 font-medium">
              {{ formatTime(note.timestamp_start) }}
            </span>
            <span v-if="note.category" class="text-xs bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded">
              {{ note.category.name }}
            </span>
            <span v-if="note.is_objection" class="text-xs bg-orange-100 text-orange-600 px-1.5 py-0.5 rounded">
              Objection
            </span>
          </div>
          <p class="text-sm text-gray-700 line-clamp-2">{{ note.note_text }}</p>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-4">
        <p class="text-sm text-gray-400">No notes yet</p>
        <p class="text-xs text-gray-400 mt-1">Click transcript to add</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  notes: { type: Array, default: () => [] },
  callId: { type: Number, required: true },
});

defineEmits(['add-note', 'click-note']);

function formatTime(seconds) {
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}
</script>
```

---

## File: resources/js/Components/Grading/AudioPlayer.vue

```vue
<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
    <div class="flex items-center gap-4">
      <!-- Skip Back -->
      <button
        @click="skip(-10)"
        class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors"
        title="Back 10s"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.333 4zM4.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0011 16V8a1 1 0 00-1.6-.8l-5.334 4z"/>
        </svg>
      </button>

      <!-- Play/Pause -->
      <button
        @click="togglePlay"
        class="w-12 h-12 flex items-center justify-center bg-blue-600 text-white rounded-full hover:bg-blue-700 transition-colors shadow-sm"
      >
        <svg v-if="!isPlaying" class="w-5 h-5 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
          <path d="M8 5v14l11-7z"/>
        </svg>
        <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
          <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
        </svg>
      </button>

      <!-- Skip Forward -->
      <button
        @click="skip(10)"
        class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors"
        title="Forward 10s"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.933 12.8a1 1 0 000-1.6L6.6 7.2A1 1 0 005 8v8a1 1 0 001.6.8l5.333-4zM19.933 12.8a1 1 0 000-1.6l-5.333-4A1 1 0 0013 8v8a1 1 0 001.6.8l5.333-4z"/>
        </svg>
      </button>

      <!-- Current Time -->
      <span class="text-sm text-gray-500 font-mono w-12">
        {{ formatTime(currentTime) }}
      </span>

      <!-- Progress Bar -->
      <div 
        class="flex-1 h-2 bg-gray-200 rounded-full cursor-pointer relative"
        @click="seek"
        ref="progressBar"
      >
        <div 
          class="h-full bg-blue-500 rounded-full transition-all duration-100"
          :style="{ width: `${progress}%` }"
        />
        <div 
          class="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-blue-600 rounded-full shadow-sm"
          :style="{ left: `calc(${progress}% - 6px)` }"
        />
      </div>

      <!-- Duration -->
      <span class="text-sm text-gray-500 font-mono w-12">
        {{ formatTime(duration) }}
      </span>

      <!-- Speed Control -->
      <select
        v-model="playbackSpeed"
        @change="updateSpeed"
        class="text-sm border border-gray-200 rounded-lg px-2 py-1 text-gray-600 bg-white"
      >
        <option value="0.5">0.5x</option>
        <option value="0.75">0.75x</option>
        <option value="1">1x</option>
        <option value="1.25">1.25x</option>
        <option value="1.5">1.5x</option>
        <option value="2">2x</option>
      </select>

      <!-- Volume -->
      <button
        @click="toggleMute"
        class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors"
      >
        <svg v-if="!isMuted && volume > 0" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
        </svg>
        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
        </svg>
      </button>
    </div>

    <!-- Hidden Audio Element -->
    <audio
      ref="audioElement"
      :src="audioUrl"
      @timeupdate="onTimeUpdate"
      @loadedmetadata="onLoadedMetadata"
      @ended="isPlaying = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
  call: { type: Object, required: true },
  audioUrl: { type: String, required: true },
});

const emit = defineEmits(['time-update']);

const audioElement = ref(null);
const progressBar = ref(null);
const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(0);
const playbackSpeed = ref('1');
const volume = ref(1);
const isMuted = ref(false);

const progress = computed(() => {
  return duration.value > 0 ? (currentTime.value / duration.value) * 100 : 0;
});

function formatTime(seconds) {
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function togglePlay() {
  if (isPlaying.value) {
    audioElement.value.pause();
  } else {
    audioElement.value.play();
  }
  isPlaying.value = !isPlaying.value;
}

function skip(seconds) {
  audioElement.value.currentTime = Math.max(0, Math.min(duration.value, currentTime.value + seconds));
}

function seek(event) {
  const rect = progressBar.value.getBoundingClientRect();
  const percent = (event.clientX - rect.left) / rect.width;
  audioElement.value.currentTime = percent * duration.value;
}

function updateSpeed() {
  audioElement.value.playbackRate = parseFloat(playbackSpeed.value);
}

function toggleMute() {
  isMuted.value = !isMuted.value;
  audioElement.value.muted = isMuted.value;
}

function onTimeUpdate() {
  currentTime.value = audioElement.value.currentTime;
  emit('time-update', currentTime.value);
}

function onLoadedMetadata() {
  duration.value = audioElement.value.duration;
}
</script>
```

---

## Database: Reps and Projects Tables

### Migration: create_reps_table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('ctm_name')->nullable(); // For matching CTM data
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['account_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reps');
    }
};
```

### Migration: create_projects_table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('ctm_name')->nullable(); // For matching CTM data
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['account_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
```

### Migration: add_rep_project_outcome_to_calls

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->foreignId('rep_id')->nullable()->constrained('reps')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('outcome')->nullable(); // appointment_set, no_appointment, callback, not_qualified, other
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropForeign(['rep_id']);
            $table->dropForeign(['project_id']);
            $table->dropColumn(['rep_id', 'project_id', 'outcome']);
        });
    }
};
```

---

## Models

### app/Models/Rep.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rep extends Model
{
    protected $fillable = [
        'account_id',
        'name',
        'email',
        'ctm_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }
}
```

### app/Models/Project.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'account_id',
        'name',
        'ctm_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }
}
```

---

## Admin Pages for Reps and Projects

### app/Http/Controllers/Admin/RepController.php

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rep;
use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RepController extends Controller
{
    public function index(Request $request)
    {
        $query = Rep::with('account:id,name');

        if ($request->filled('account')) {
            $query->where('account_id', $request->account);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $reps = $query->orderBy('name')->paginate(25)->withQueryString();
        $accounts = Account::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Reps/Index', [
            'reps' => $reps,
            'accounts' => $accounts,
            'filters' => $request->only(['account', 'search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'ctm_name' => 'nullable|string|max:255',
        ]);

        Rep::create($validated);

        return back()->with('success', 'Rep added successfully.');
    }

    public function update(Request $request, Rep $rep)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'ctm_name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $rep->update($validated);

        return back()->with('success', 'Rep updated successfully.');
    }

    public function destroy(Rep $rep)
    {
        $rep->delete();
        return back()->with('success', 'Rep deleted successfully.');
    }
}
```

### app/Http/Controllers/Admin/ProjectController.php

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::with('account:id,name');

        if ($request->filled('account')) {
            $query->where('account_id', $request->account);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $projects = $query->orderBy('name')->paginate(25)->withQueryString();
        $accounts = Account::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Projects/Index', [
            'projects' => $projects,
            'accounts' => $accounts,
            'filters' => $request->only(['account', 'search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'name' => 'required|string|max:255',
            'ctm_name' => 'nullable|string|max:255',
        ]);

        Project::create($validated);

        return back()->with('success', 'Project added successfully.');
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ctm_name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $project->update($validated);

        return back()->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return back()->with('success', 'Project deleted successfully.');
    }
}
```

---

## Routes

Add to `routes/admin.php`:

```php
use App\Http\Controllers\Admin\RepController;
use App\Http\Controllers\Admin\ProjectController;

// Reps
Route::get('/reps', [RepController::class, 'index'])->name('reps.index');
Route::post('/reps', [RepController::class, 'store'])->name('reps.store');
Route::patch('/reps/{rep}', [RepController::class, 'update'])->name('reps.update');
Route::delete('/reps/{rep}', [RepController::class, 'destroy'])->name('reps.destroy');

// Projects
Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
```

---

## Update GradingController

Pass reps and projects to the grading page:

```php
public function show(Call $call)
{
    $call->load(['transcript', 'grade.categoryScores', 'grade.checkpoints', 'coachingNotes']);
    
    $categories = RubricCategory::where('is_active', true)
        ->orderBy('sort_order')
        ->get();
    
    $checkpoints = RubricCheckpoint::where('is_active', true)
        ->orderBy('type')
        ->orderBy('sort_order')
        ->get();
    
    $objectionTypes = ObjectionType::where('is_active', true)
        ->orderBy('sort_order')
        ->get();
    
    // Get reps and projects for this call's office
    $reps = Rep::where('account_id', $call->account_id)
        ->where('is_active', true)
        ->orderBy('name')
        ->get(['id', 'name']);
    
    $projects = Project::where('account_id', $call->account_id)
        ->where('is_active', true)
        ->orderBy('name')
        ->get(['id', 'name']);

    return Inertia::render('Manager/Grading/Show', [
        'call' => $call,
        'categories' => $categories,
        'checkpoints' => $checkpoints,
        'objectionTypes' => $objectionTypes,
        'reps' => $reps,
        'projects' => $projects,
        'audioUrl' => route('manager.calls.audio', $call),
    ]);
}
```

---

## Verification Checklist

After implementation:

- [ ] Three-column layout renders correctly
- [ ] Small borders on left/right (~3%)
- [ ] Transcript cards have distinct colors (blue for Rep, gray/green for Prospect)
- [ ] Scale legend shows at top of rubric
- [ ] All 8 categories visible with name, description, 1-4 buttons
- [ ] Next unscored category auto-expands
- [ ] Accordion reveals training details
- [ ] Checkpoints have proper spacing
- [ ] Notes panel shows in third column
- [ ] Audio player has all controls (play, skip, progress, speed, volume)
- [ ] Rep/Project/Outcome dropdowns work
- [ ] Admin pages exist for /admin/reps and /admin/projects
- [ ] Save Draft and Submit Grade work
