# Slice 7: Coaching Notes + Objection Flagging

## Objective
Add the ability for managers to create coaching notes tied to specific transcript moments, optionally tag them with rubric categories, and flag objections with outcomes. Also add the "Why No Appointment?" quick capture for failed calls.

## Prerequisites
- **Slice 6 complete**: Grading UI working with audio player and transcript

## What This Slice Builds

1. **Click-to-add-note on transcript** - Click any transcript line to add a coaching note
2. **Category tagging** - Optionally tag notes with rubric categories
3. **Multi-line selection** - Select a range of transcript lines for context
4. **Objection flagging** - Flag notes as objections with type and outcome
5. **Why No Appointment** - Quick capture when call outcome = no appointment
6. **Notes sidebar** - View all notes for current call

---

## File Structure

```
app/
├── Http/Controllers/Manager/
│   ├── CoachingNoteController.php     # Note CRUD
│   └── ObjectionController.php        # Objection types management
├── Models/
│   ├── CoachingNote.php               # (should exist from Slice 1)
│   └── ObjectionType.php              # (should exist from Slice 1)
database/
├── seeders/
│   └── ObjectionTypeSeeder.php        # Seed common objection types
resources/
├── js/
│   └── Components/
│       ├── AddNoteModal.vue           # Modal for creating notes
│       ├── NotesSidebar.vue           # List of notes for call
│       ├── TranscriptSelection.vue    # Multi-line selection UI
│       └── WhyNoAppointmentModal.vue  # Quick capture modal
```

---

## Step 1: Objection Type Seeder

Create `database/seeders/ObjectionTypeSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ObjectionType;

class ObjectionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Price/Budget', 'sort_order' => 1],
            ['name' => 'Timing/Not Ready', 'sort_order' => 2],
            ['name' => 'Need to Talk to Spouse', 'sort_order' => 3],
            ['name' => 'Distance/Location', 'sort_order' => 4],
            ['name' => 'Already Own Property', 'sort_order' => 5],
            ['name' => 'Bad Experience Before', 'sort_order' => 6],
            ['name' => 'Just Looking/Not Serious', 'sort_order' => 7],
            ['name' => 'Health/Age Concerns', 'sort_order' => 8],
            ['name' => 'Scheduling Conflict', 'sort_order' => 9],
            ['name' => 'Want More Information First', 'sort_order' => 10],
            ['name' => 'Other', 'sort_order' => 99],
        ];

        foreach ($types as $type) {
            ObjectionType::updateOrCreate(
                ['name' => $type['name']],
                ['sort_order' => $type['sort_order'], 'is_active' => true]
            );
        }
    }
}
```

Run it:
```bash
php artisan db:seed --class=ObjectionTypeSeeder
```

---

## Step 2: Coaching Note Controller

Create `app/Http/Controllers/Manager/CoachingNoteController.php`:

```php
<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\CoachingNote;
use App\Models\Grade;
use App\Models\ObjectionType;
use App\Models\RubricCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CoachingNoteController extends Controller
{
    /**
     * Get all notes for a call by current manager
     */
    public function index(Call $call)
    {
        $notes = CoachingNote::where('call_id', $call->id)
            ->where('author_id', Auth::id())
            ->with(['category:id,name', 'objectionType:id,name'])
            ->orderBy('line_index_start')
            ->get();

        return response()->json($notes);
    }

    /**
     * Store a new coaching note
     */
    public function store(Request $request, Call $call)
    {
        $validated = $request->validate([
            'grade_id' => 'nullable|exists:grades,id',
            'line_index_start' => 'required|integer|min:0',
            'line_index_end' => 'nullable|integer|min:0',
            'timestamp_start' => 'required|numeric|min:0',
            'timestamp_end' => 'nullable|numeric|min:0',
            'transcript_text' => 'required|string|max:2000',
            'note_text' => 'required|string|max:2000',
            'category_id' => 'nullable|exists:rubric_categories,id',
            'is_objection' => 'boolean',
            'objection_type_id' => 'nullable|required_if:is_objection,true|exists:objection_types,id',
            'objection_outcome' => 'nullable|required_if:is_objection,true|in:overcame,failed',
        ]);

        // Find or get the current grade for this call by this manager
        $grade = null;
        if ($validated['grade_id']) {
            $grade = Grade::find($validated['grade_id']);
        } else {
            $grade = Grade::where('call_id', $call->id)
                ->where('graded_by', Auth::id())
                ->first();
        }

        $note = CoachingNote::create([
            'call_id' => $call->id,
            'grade_id' => $grade?->id,
            'author_id' => Auth::id(),
            'line_index_start' => $validated['line_index_start'],
            'line_index_end' => $validated['line_index_end'] ?? $validated['line_index_start'],
            'timestamp_start' => $validated['timestamp_start'],
            'timestamp_end' => $validated['timestamp_end'] ?? $validated['timestamp_start'],
            'transcript_text' => $validated['transcript_text'],
            'note_text' => $validated['note_text'],
            'category_id' => $validated['category_id'],
            'is_objection' => $validated['is_objection'] ?? false,
            'objection_type_id' => $validated['objection_type_id'] ?? null,
            'objection_outcome' => $validated['objection_outcome'] ?? null,
        ]);

        $note->load(['category:id,name', 'objectionType:id,name']);

        return response()->json($note, 201);
    }

    /**
     * Update a coaching note
     */
    public function update(Request $request, CoachingNote $note)
    {
        // Ensure user owns this note
        if ($note->author_id !== Auth::id()) {
            abort(403, 'You can only edit your own notes.');
        }

        $validated = $request->validate([
            'note_text' => 'sometimes|required|string|max:2000',
            'category_id' => 'nullable|exists:rubric_categories,id',
            'is_objection' => 'boolean',
            'objection_type_id' => 'nullable|exists:objection_types,id',
            'objection_outcome' => 'nullable|in:overcame,failed',
        ]);

        $note->update($validated);
        $note->load(['category:id,name', 'objectionType:id,name']);

        return response()->json($note);
    }

    /**
     * Delete a coaching note
     */
    public function destroy(CoachingNote $note)
    {
        if ($note->author_id !== Auth::id()) {
            abort(403, 'You can only delete your own notes.');
        }

        $note->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get form data (categories and objection types)
     */
    public function formData()
    {
        return response()->json([
            'categories' => RubricCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']),
            'objectionTypes' => ObjectionType::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']),
        ]);
    }
}
```

---

## Step 3: Why No Appointment Controller

Add to `app/Http/Controllers/Manager/GradingController.php`:

```php
/**
 * Save "Why No Appointment" data
 */
public function saveNoAppointmentReason(Request $request, Call $call)
{
    $validated = $request->validate([
        'objection_type_ids' => 'required|array|min:1',
        'objection_type_ids.*' => 'exists:objection_types,id',
        'notes' => 'nullable|string|max:1000',
    ]);

    // Find or create the grade
    $grade = Grade::firstOrCreate(
        [
            'call_id' => $call->id,
            'graded_by' => Auth::id(),
        ],
        [
            'status' => 'draft',
            'weighted_score' => 0,
        ]
    );

    // Store as JSON on the grade
    $grade->update([
        'no_appointment_reasons' => [
            'objection_type_ids' => $validated['objection_type_ids'],
            'notes' => $validated['notes'],
        ],
    ]);

    return response()->json(['success' => true]);
}
```

**Note:** Add `no_appointment_reasons` as a JSON column to grades table if not already there:

```php
// In a new migration
$table->json('no_appointment_reasons')->nullable();
```

---

## Step 4: Routes

Add to `routes/manager.php`:

```php
use App\Http\Controllers\Manager\CoachingNoteController;

Route::middleware(['auth', 'role:manager'])->prefix('manager')->name('manager.')->group(function () {
    // ... existing routes ...

    // Coaching Notes
    Route::get('/notes/form-data', [CoachingNoteController::class, 'formData'])->name('notes.form-data');
    Route::get('/calls/{call}/notes', [CoachingNoteController::class, 'index'])->name('notes.index');
    Route::post('/calls/{call}/notes', [CoachingNoteController::class, 'store'])->name('notes.store');
    Route::patch('/notes/{note}', [CoachingNoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [CoachingNoteController::class, 'destroy'])->name('notes.destroy');

    // Why No Appointment
    Route::post('/grade/{call}/no-appointment', [GradingController::class, 'saveNoAppointmentReason'])
        ->name('grade.no-appointment');
});
```

---

## Step 5: Vue Components

### AddNoteModal.vue

Create `resources/js/Components/AddNoteModal.vue`:

```vue
<template>
  <div 
    v-if="isOpen" 
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    @click.self="close"
  >
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
      <!-- Header -->
      <div class="px-4 py-3 border-b flex items-center justify-between">
        <h3 class="font-medium text-gray-900">Add Coaching Note</h3>
        <button @click="close" class="text-gray-400 hover:text-gray-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Selected transcript text -->
      <div class="px-4 py-3 bg-gray-50 border-b">
        <p class="text-xs text-gray-500 mb-1">Selected transcript:</p>
        <p class="text-sm text-gray-700 italic">"{{ selection.text }}"</p>
        <p class="text-xs text-gray-400 mt-1">
          {{ formatTimestamp(selection.timestampStart) }}
          <span v-if="selection.timestampEnd !== selection.timestampStart">
            – {{ formatTimestamp(selection.timestampEnd) }}
          </span>
        </p>
      </div>

      <!-- Form -->
      <div class="px-4 py-4 space-y-4">
        <!-- Note text -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Coaching Note
          </label>
          <textarea
            v-model="form.noteText"
            rows="3"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="What should the rep have done differently? What did they do well?"
          />
        </div>

        <!-- Category tag (optional) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Category Tag <span class="text-gray-400 font-normal">(optional)</span>
          </label>
          <select
            v-model="form.categoryId"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
          >
            <option :value="null">No category</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
              {{ cat.name }}
            </option>
          </select>
        </div>

        <!-- Objection flag -->
        <div class="border rounded-lg p-3 bg-gray-50">
          <label class="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              v-model="form.isObjection"
              class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <span class="text-sm font-medium text-gray-700">This is an objection</span>
          </label>

          <!-- Objection details (shown when checked) -->
          <div v-if="form.isObjection" class="mt-3 space-y-3 pl-6">
            <div>
              <label class="block text-sm text-gray-600 mb-1">Objection Type</label>
              <select
                v-model="form.objectionTypeId"
                class="w-full border rounded px-3 py-2 text-sm"
                required
              >
                <option :value="null" disabled>Select type...</option>
                <option v-for="type in objectionTypes" :key="type.id" :value="type.id">
                  {{ type.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm text-gray-600 mb-1">Outcome</label>
              <div class="flex gap-2">
                <button
                  type="button"
                  @click="form.objectionOutcome = 'overcame'"
                  :class="[
                    'flex-1 py-2 px-3 rounded text-sm font-medium transition-colors',
                    form.objectionOutcome === 'overcame'
                      ? 'bg-green-600 text-white'
                      : 'bg-white border hover:bg-gray-50 text-gray-700'
                  ]"
                >
                  ✓ Overcame
                </button>
                <button
                  type="button"
                  @click="form.objectionOutcome = 'failed'"
                  :class="[
                    'flex-1 py-2 px-3 rounded text-sm font-medium transition-colors',
                    form.objectionOutcome === 'failed'
                      ? 'bg-red-600 text-white'
                      : 'bg-white border hover:bg-gray-50 text-gray-700'
                  ]"
                >
                  ✗ Failed
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-4 py-3 border-t flex justify-end gap-2">
        <button
          @click="close"
          class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded"
        >
          Cancel
        </button>
        <button
          @click="save"
          :disabled="!isValid || saving"
          class="px-4 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
        >
          {{ saving ? 'Saving...' : 'Save Note' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  callId: { type: Number, required: true },
  gradeId: { type: Number, default: null },
  selection: {
    type: Object,
    default: () => ({
      lineIndexStart: 0,
      lineIndexEnd: 0,
      timestampStart: 0,
      timestampEnd: 0,
      text: '',
    }),
  },
  categories: { type: Array, default: () => [] },
  objectionTypes: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const form = ref({
  noteText: '',
  categoryId: null,
  isObjection: false,
  objectionTypeId: null,
  objectionOutcome: null,
});

// Reset form when modal opens
watch(() => props.isOpen, (isOpen) => {
  if (isOpen) {
    form.value = {
      noteText: '',
      categoryId: null,
      isObjection: false,
      objectionTypeId: null,
      objectionOutcome: null,
    };
  }
});

const isValid = computed(() => {
  if (!form.value.noteText.trim()) return false;
  if (form.value.isObjection) {
    if (!form.value.objectionTypeId || !form.value.objectionOutcome) return false;
  }
  return true;
});

function formatTimestamp(seconds) {
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function close() {
  emit('close');
}

async function save() {
  if (!isValid.value) return;

  saving.value = true;
  try {
    const response = await axios.post(`/manager/calls/${props.callId}/notes`, {
      grade_id: props.gradeId,
      line_index_start: props.selection.lineIndexStart,
      line_index_end: props.selection.lineIndexEnd,
      timestamp_start: props.selection.timestampStart,
      timestamp_end: props.selection.timestampEnd,
      transcript_text: props.selection.text,
      note_text: form.value.noteText,
      category_id: form.value.categoryId,
      is_objection: form.value.isObjection,
      objection_type_id: form.value.objectionTypeId,
      objection_outcome: form.value.objectionOutcome,
    });

    emit('saved', response.data);
    close();
  } catch (error) {
    console.error('Error saving note:', error);
    alert('Failed to save note. Please try again.');
  } finally {
    saving.value = false;
  }
}
</script>
```

### NotesSidebar.vue

Create `resources/js/Components/NotesSidebar.vue`:

```vue
<template>
  <div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b flex items-center justify-between">
      <h3 class="font-medium text-gray-900">
        Coaching Notes
        <span v-if="notes.length" class="text-gray-500 font-normal">({{ notes.length }})</span>
      </h3>
      
      <!-- Filter dropdown -->
      <select
        v-if="notes.length > 0"
        v-model="filterCategory"
        class="text-sm border rounded px-2 py-1"
      >
        <option :value="null">All categories</option>
        <option value="uncategorized">Uncategorized</option>
        <option v-for="cat in usedCategories" :key="cat.id" :value="cat.id">
          {{ cat.name }}
        </option>
      </select>
    </div>

    <div class="max-h-[400px] overflow-y-auto">
      <!-- Empty state -->
      <div v-if="filteredNotes.length === 0" class="p-4 text-center text-gray-500 text-sm">
        <p v-if="notes.length === 0">No notes yet.</p>
        <p v-else>No notes match this filter.</p>
        <p class="mt-1 text-xs">Click on transcript text to add a note.</p>
      </div>

      <!-- Notes list -->
      <div v-else class="divide-y">
        <div
          v-for="note in filteredNotes"
          :key="note.id"
          class="p-3 hover:bg-gray-50 cursor-pointer"
          @click="$emit('jump-to', note)"
        >
          <!-- Transcript excerpt -->
          <p class="text-xs text-gray-500 italic line-clamp-1 mb-1">
            "{{ note.transcript_text }}"
          </p>

          <!-- Note text -->
          <p class="text-sm text-gray-900 mb-2">{{ note.note_text }}</p>

          <!-- Tags row -->
          <div class="flex items-center gap-2 flex-wrap">
            <!-- Timestamp -->
            <span class="text-xs text-gray-400">
              {{ formatTimestamp(note.timestamp_start) }}
            </span>

            <!-- Category badge -->
            <span
              v-if="note.category"
              class="text-xs px-2 py-0.5 rounded bg-blue-100 text-blue-800"
            >
              {{ note.category.name }}
            </span>

            <!-- Objection badge -->
            <span
              v-if="note.is_objection"
              :class="[
                'text-xs px-2 py-0.5 rounded',
                note.objection_outcome === 'overcame'
                  ? 'bg-green-100 text-green-800'
                  : 'bg-red-100 text-red-800'
              ]"
            >
              {{ note.objection_type?.name }}
              {{ note.objection_outcome === 'overcame' ? '✓' : '✗' }}
            </span>
          </div>

          <!-- Actions -->
          <div class="mt-2 flex gap-2">
            <button
              @click.stop="$emit('edit', note)"
              class="text-xs text-gray-500 hover:text-gray-700"
            >
              Edit
            </button>
            <button
              @click.stop="confirmDelete(note)"
              class="text-xs text-red-500 hover:text-red-700"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Link to full library -->
    <div v-if="notes.length > 0" class="px-4 py-2 border-t bg-gray-50">
      <a href="/manager/notes" class="text-sm text-blue-600 hover:text-blue-800">
        View all my notes →
      </a>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
  notes: { type: Array, default: () => [] },
});

const emit = defineEmits(['jump-to', 'edit', 'deleted']);

const filterCategory = ref(null);

// Get unique categories from notes
const usedCategories = computed(() => {
  const cats = props.notes
    .filter(n => n.category)
    .map(n => n.category);
  return [...new Map(cats.map(c => [c.id, c])).values()];
});

const filteredNotes = computed(() => {
  if (filterCategory.value === null) {
    return props.notes;
  }
  if (filterCategory.value === 'uncategorized') {
    return props.notes.filter(n => !n.category_id);
  }
  return props.notes.filter(n => n.category_id === filterCategory.value);
});

function formatTimestamp(seconds) {
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins}:${secs.toString().padStart(2, '0')}`;
}

async function confirmDelete(note) {
  if (!confirm('Delete this note?')) return;

  try {
    await axios.delete(`/manager/notes/${note.id}`);
    emit('deleted', note.id);
  } catch (error) {
    console.error('Error deleting note:', error);
    alert('Failed to delete note.');
  }
}
</script>
```

### WhyNoAppointmentModal.vue

Create `resources/js/Components/WhyNoAppointmentModal.vue`:

```vue
<template>
  <div 
    v-if="isOpen" 
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
  >
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
      <!-- Header -->
      <div class="px-4 py-3 border-b bg-orange-50">
        <h3 class="font-medium text-gray-900">Why No Appointment?</h3>
        <p class="text-sm text-gray-600 mt-1">
          Select the objection(s) that prevented booking. This helps identify patterns.
        </p>
      </div>

      <!-- Objection types (multi-select) -->
      <div class="px-4 py-4 max-h-[300px] overflow-y-auto">
        <div class="space-y-2">
          <label
            v-for="type in objectionTypes"
            :key="type.id"
            class="flex items-center gap-3 p-2 rounded hover:bg-gray-50 cursor-pointer"
          >
            <input
              type="checkbox"
              :value="type.id"
              v-model="selectedTypes"
              class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <span class="text-sm text-gray-700">{{ type.name }}</span>
          </label>
        </div>
      </div>

      <!-- Optional notes -->
      <div class="px-4 pb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Additional notes <span class="text-gray-400 font-normal">(optional)</span>
        </label>
        <textarea
          v-model="notes"
          rows="2"
          class="w-full border rounded-lg px-3 py-2 text-sm"
          placeholder="Any other context..."
        />
      </div>

      <!-- Footer -->
      <div class="px-4 py-3 border-t flex justify-end gap-2">
        <button
          @click="skip"
          class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded"
        >
          Skip
        </button>
        <button
          @click="save"
          :disabled="selectedTypes.length === 0 || saving"
          class="px-4 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
        >
          {{ saving ? 'Saving...' : 'Continue to Submit' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  callId: { type: Number, required: true },
  objectionTypes: { type: Array, default: () => [] },
});

const emit = defineEmits(['saved', 'skipped']);

const selectedTypes = ref([]);
const notes = ref('');
const saving = ref(false);

function skip() {
  emit('skipped');
}

async function save() {
  saving.value = true;
  try {
    await axios.post(`/manager/grade/${props.callId}/no-appointment`, {
      objection_type_ids: selectedTypes.value,
      notes: notes.value,
    });
    emit('saved');
  } catch (error) {
    console.error('Error saving no-appointment reason:', error);
    alert('Failed to save. Please try again.');
  } finally {
    saving.value = false;
  }
}
</script>
```

---

## Step 6: Update Grading Page

Update `resources/js/Pages/Manager/Grading/Show.vue` to integrate notes:

Add these imports and components:

```vue
<script setup>
// Add these imports
import AddNoteModal from '@/Components/AddNoteModal.vue';
import NotesSidebar from '@/Components/NotesSidebar.vue';
import WhyNoAppointmentModal from '@/Components/WhyNoAppointmentModal.vue';
import axios from 'axios';

// Add to props
const props = defineProps({
  // ... existing props ...
  formData: { type: Object, default: () => ({ categories: [], objectionTypes: [] }) },
});

// Add state
const notes = ref([]);
const showAddNoteModal = ref(false);
const showWhyNoAppointmentModal = ref(false);
const selectedTranscript = ref({
  lineIndexStart: 0,
  lineIndexEnd: 0,
  timestampStart: 0,
  timestampEnd: 0,
  text: '',
});

// Load notes on mount
onMounted(async () => {
  await loadNotes();
});

async function loadNotes() {
  try {
    const response = await axios.get(`/manager/calls/${props.call.id}/notes`);
    notes.value = response.data;
  } catch (error) {
    console.error('Error loading notes:', error);
  }
}

// Handle transcript click (single line)
function handleTranscriptClick(utterance, index) {
  selectedTranscript.value = {
    lineIndexStart: index,
    lineIndexEnd: index,
    timestampStart: utterance.start,
    timestampEnd: utterance.end,
    text: utterance.text,
  };
  showAddNoteModal.value = true;
}

// Handle transcript selection (multi-line)
function handleTranscriptSelection(selection) {
  selectedTranscript.value = selection;
  showAddNoteModal.value = true;
}

function handleNoteSaved(note) {
  notes.value.push(note);
  notes.value.sort((a, b) => a.line_index_start - b.line_index_start);
}

function handleNoteDeleted(noteId) {
  notes.value = notes.value.filter(n => n.id !== noteId);
}

function handleJumpToNote(note) {
  audioPlayerRef.value?.seekTo(note.timestamp_start);
}

// Modified submit to show WhyNoAppointment modal for failed calls
function submitGrade() {
  // Check if this is a no-appointment call
  if (props.call.outcome_status === 'no_appointment' || props.call.outcome_status === 'no_sale') {
    showWhyNoAppointmentModal.value = true;
    return;
  }
  
  doSubmit();
}

function doSubmit() {
  saving.value = true;
  
  router.post(route('manager.grade.store', props.call.id), {
    ...gradeData.value,
    playback_seconds: playbackSeconds.value,
    status: 'submitted',
  }, {
    onFinish: () => {
      saving.value = false;
    },
  });
}
</script>
```

Add to template (in the right column, after GradingPanel):

```vue
<!-- Notes Sidebar -->
<NotesSidebar
  :notes="notes"
  @jump-to="handleJumpToNote"
  @deleted="handleNoteDeleted"
/>

<!-- Add Note Modal -->
<AddNoteModal
  :is-open="showAddNoteModal"
  :call-id="call.id"
  :grade-id="existingGrade?.id"
  :selection="selectedTranscript"
  :categories="formData.categories"
  :objection-types="formData.objectionTypes"
  @close="showAddNoteModal = false"
  @saved="handleNoteSaved"
/>

<!-- Why No Appointment Modal -->
<WhyNoAppointmentModal
  :is-open="showWhyNoAppointmentModal"
  :call-id="call.id"
  :objection-types="formData.objectionTypes"
  @saved="doSubmit"
  @skipped="doSubmit"
/>
```

---

## Step 7: Update Controller to Pass Form Data

In `GradingController.php`, update the `show` method to pass form data:

```php
use App\Models\ObjectionType;

public function show(Call $call)
{
    // ... existing code ...

    return Inertia::render('Manager/Grading/Show', [
        // ... existing props ...
        'formData' => [
            'categories' => RubricCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']),
            'objectionTypes' => ObjectionType::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']),
        ],
    ]);
}
```

---

## Step 8: Migration for no_appointment_reasons

Create migration if needed:

```bash
php artisan make:migration add_no_appointment_reasons_to_grades_table
```

```php
public function up(): void
{
    Schema::table('grades', function (Blueprint $table) {
        $table->json('no_appointment_reasons')->nullable()->after('appointment_quality');
    });
}

public function down(): void
{
    Schema::table('grades', function (Blueprint $table) {
        $table->dropColumn('no_appointment_reasons');
    });
}
```

---

## Verification Checklist

After implementation:

**Objection Types:**
- [ ] Run seeder: `php artisan db:seed --class=ObjectionTypeSeeder`
- [ ] Verify: `ObjectionType::count()` returns 11

**Add Note Flow:**
- [ ] Click transcript line → modal opens
- [ ] Modal shows selected text and timestamp
- [ ] Can enter note text
- [ ] Can optionally select category
- [ ] Can toggle objection flag
- [ ] When objection checked, type and outcome required
- [ ] Save creates note, appears in sidebar

**Notes Sidebar:**
- [ ] Shows all notes for call
- [ ] Filter by category works
- [ ] Click note jumps audio
- [ ] Delete note works
- [ ] "View all notes" link visible

**Objection Flagging:**
- [ ] Check "This is an objection" shows extra fields
- [ ] Must select type and outcome
- [ ] Saved note shows objection badge

**Why No Appointment:**
- [ ] Submitting a no-appointment call shows modal
- [ ] Can multi-select objection types
- [ ] Can add optional notes
- [ ] Skip bypasses but still submits
- [ ] Data saves to grade record

---

## Test Flow

1. Open grading page for any call
2. Click a transcript line
3. Add note with category tag → verify appears in sidebar
4. Add note flagged as objection → verify badge shows
5. Click note in sidebar → verify audio jumps
6. Delete a note → verify removed
7. Open a "no appointment" call
8. Fill out grading, click Submit
9. Verify "Why No Appointment" modal appears
10. Select objections, click Continue
11. Verify grade submitted with reasons saved

---

## Notes

- Notes are per-manager (each manager has their own notes)
- Notes can exist without a grade (if adding notes before scoring)
- Objection data feeds into the Objections Library (Slice 8)
- "Why No Appointment" is required before submit for failed calls
- Multi-line selection can be added later as enhancement
