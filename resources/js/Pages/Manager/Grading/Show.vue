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
            :utterances="transcriptUtterances"
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

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import AudioPlayer from '@/Components/Grading/AudioPlayer.vue';
import TranscriptViewer from '@/Components/Grading/TranscriptViewer.vue';
import RubricPanel from '@/Components/Grading/RubricPanel.vue';
import CheckpointsPanel from '@/Components/Grading/CheckpointsPanel.vue';
import NotesPanel from '@/Components/Grading/NotesPanel.vue';

const props = defineProps({
  call: { type: Object, required: true },
  categories: { type: Array, default: () => [] },
  checkpoints: { type: Array, default: () => [] },
  objectionTypes: { type: Array, default: () => [] },
  reps: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  audioUrl: { type: String, required: true },
  existingGrade: { type: Object, default: null },
});

// State
const currentTime = ref(0);
const showNoteModal = ref(false);
const selectedUtterance = ref(null);
const playbackStartTime = ref(Date.now());
const totalPlaybackSeconds = ref(0);

// Extract utterances from transcript (handle different formats)
const transcriptUtterances = computed(() => {
  if (!props.call.transcript) return [];
  if (Array.isArray(props.call.transcript)) return props.call.transcript;
  if (props.call.transcript.utterances) return props.call.transcript.utterances;
  return [];
});

// Category scores state
const categoryScores = ref({});

// Checkpoint values state
const checkpointValues = ref({});

// Notes state
const notes = ref([]);

// Initialize from existing grade
onMounted(() => {
  if (props.existingGrade) {
    // Load category scores
    if (props.existingGrade.categoryScores) {
      props.existingGrade.categoryScores.forEach(cs => {
        categoryScores.value[cs.rubric_category_id] = cs.score;
      });
    }
    // Load checkpoint responses
    if (props.existingGrade.checkpointResponses) {
      props.existingGrade.checkpointResponses.forEach(cr => {
        checkpointValues.value[cr.rubric_checkpoint_id] = cr.observed;
      });
    }
  }
  // Load notes
  if (props.call.coachingNotes) {
    notes.value = props.call.coachingNotes;
  }
});

// Event handlers
function onTimeUpdate(time) {
  currentTime.value = time;
}

function updateCategoryScore(categoryId, score) {
  categoryScores.value[categoryId] = score;
}

function updateCheckpoint(checkpointId, value) {
  checkpointValues.value[checkpointId] = value;
}

function updateCallDetails(details) {
  fetch(`/manager/calls/${props.call.id}/details`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
    body: JSON.stringify(details),
  });
}

function swapSpeakers() {
  fetch(`/manager/calls/${props.call.id}/swap-speakers`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
  }).then(() => {
    router.reload({ only: ['call'] });
  });
}

function openNoteModal(utterance, index) {
  selectedUtterance.value = utterance;
  showNoteModal.value = true;
}

function jumpToTimestamp(note) {
  // This would seek the audio player to the note's timestamp
  currentTime.value = note.timestamp_start;
}

function saveNote(noteData) {
  fetch(`/manager/calls/${props.call.id}/notes`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
    body: JSON.stringify(noteData),
  }).then(response => response.json()).then(data => {
    if (data.note) {
      notes.value.push(data.note);
    }
    showNoteModal.value = false;
  });
}

function calculatePlaybackSeconds() {
  return Math.floor((Date.now() - playbackStartTime.value) / 1000);
}

function saveDraft() {
  submitGradeData('draft');
}

function submitGrade() {
  submitGradeData('submitted');
}

function submitGradeData(status) {
  const data = {
    category_scores: categoryScores.value,
    checkpoint_responses: checkpointValues.value,
    appointment_quality: null,
    playback_seconds: calculatePlaybackSeconds(),
    status: status,
  };

  fetch(`/manager/calls/${props.call.id}/grade`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
    body: JSON.stringify(data),
  }).then(response => response.json()).then(result => {
    if (result.redirect) {
      window.location.href = result.redirect;
    }
  });
}
</script>
