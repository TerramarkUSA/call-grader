# Slice 8: Three Libraries + Navigation

## Objective
Build the three library views for managers (Graded Calls, Coaching Notes, Objections) and implement proper navigation across the manager portal.

## Prerequisites
- **Slice 6 complete**: Grading UI working
- **Slice 7 complete**: Coaching notes and objection flagging working

## What This Slice Builds

1. **Manager Navigation** - Persistent nav with Calls, Graded Calls, Notes, Objections tabs
2. **Graded Calls Library** - Browse all calls graded by this manager
3. **Coaching Notes Library** - Browse all notes across all calls
4. **Objections Library** - Browse all flagged objections with outcomes
5. **Filtering and search** across all libraries

---

## File Structure

```
resources/
├── js/
│   ├── Layouts/
│   │   └── ManagerLayout.vue          # Update with navigation
│   └── Pages/Manager/
│       ├── GradedCalls/
│       │   └── Index.vue              # Graded calls library
│       ├── Notes/
│       │   └── Index.vue              # Notes library
│       └── Objections/
│           └── Index.vue              # Objections library
app/
├── Http/Controllers/Manager/
│   ├── GradedCallsController.php
│   ├── NotesLibraryController.php
│   └── ObjectionsLibraryController.php
```

---

## Step 1: Manager Navigation Layout

Update `resources/js/Layouts/ManagerLayout.vue`:

```vue
<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Top Navigation Bar -->
    <nav class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16">
          <!-- Left: Logo + Nav Links -->
          <div class="flex">
            <!-- Logo/Home Link -->
            <Link 
              :href="route('manager.queue')" 
              class="flex items-center px-2 text-xl font-bold text-gray-900 hover:text-blue-600"
            >
              Call Grader
            </Link>

            <!-- Nav Links -->
            <div class="hidden sm:ml-8 sm:flex sm:space-x-1">
              <NavLink 
                :href="route('manager.queue')" 
                :active="isActive('manager.queue', 'manager.grade')"
              >
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                Calls
              </NavLink>

              <NavLink 
                :href="route('manager.graded-calls')" 
                :active="isActive('manager.graded-calls')"
              >
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Graded
              </NavLink>

              <NavLink 
                :href="route('manager.notes-library')" 
                :active="isActive('manager.notes-library')"
              >
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Notes
              </NavLink>

              <NavLink 
                :href="route('manager.objections')" 
                :active="isActive('manager.objections')"
              >
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Objections
              </NavLink>
            </div>
          </div>

          <!-- Right: User Menu -->
          <div class="flex items-center">
            <div class="relative" v-click-outside="() => showUserMenu = false">
              <button 
                @click="showUserMenu = !showUserMenu"
                class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:text-gray-900"
              >
                <span>{{ $page.props.auth.user.name }}</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>

              <!-- Dropdown -->
              <div 
                v-if="showUserMenu"
                class="absolute right-0 mt-1 w-48 bg-white rounded-md shadow-lg py-1 z-50"
              >
                <Link
                  v-if="$page.props.auth.user.role !== 'manager'"
                  href="/admin/accounts"
                  class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                >
                  Admin Panel
                </Link>
                <Link
                  :href="route('logout')"
                  method="post"
                  as="button"
                  class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                >
                  Sign Out
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Mobile Navigation -->
      <div class="sm:hidden border-t">
        <div class="flex justify-around py-2">
          <MobileNavLink :href="route('manager.queue')" :active="isActive('manager.queue')">
            Calls
          </MobileNavLink>
          <MobileNavLink :href="route('manager.graded-calls')" :active="isActive('manager.graded-calls')">
            Graded
          </MobileNavLink>
          <MobileNavLink :href="route('manager.notes-library')" :active="isActive('manager.notes-library')">
            Notes
          </MobileNavLink>
          <MobileNavLink :href="route('manager.objections')" :active="isActive('manager.objections')">
            Objections
          </MobileNavLink>
        </div>
      </div>
    </nav>

    <!-- Page Content -->
    <main>
      <slot />
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const showUserMenu = ref(false);

// NavLink component inline
const NavLink = {
  props: ['href', 'active'],
  template: `
    <Link 
      :href="href" 
      :class="[
        'inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors',
        active 
          ? 'bg-blue-50 text-blue-700' 
          : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
      ]"
    >
      <slot />
    </Link>
  `,
  components: { Link }
};

// MobileNavLink component inline
const MobileNavLink = {
  props: ['href', 'active'],
  template: `
    <Link 
      :href="href" 
      :class="[
        'px-3 py-1 text-xs font-medium rounded transition-colors',
        active 
          ? 'bg-blue-100 text-blue-700' 
          : 'text-gray-600 hover:text-gray-900'
      ]"
    >
      <slot />
    </Link>
  `,
  components: { Link }
};

function isActive(...routeNames) {
  const currentRoute = usePage().props.ziggy?.location || '';
  return routeNames.some(name => {
    try {
      return currentRoute.includes(route(name).replace(window.location.origin, ''));
    } catch {
      return false;
    }
  });
}

// Click outside directive
const vClickOutside = {
  mounted(el, binding) {
    el._clickOutside = (e) => {
      if (!el.contains(e.target)) binding.value();
    };
    document.addEventListener('click', el._clickOutside);
  },
  unmounted(el) {
    document.removeEventListener('click', el._clickOutside);
  }
};
</script>
```

---

## Step 2: Graded Calls Controller

Create `app/Http/Controllers/Manager/GradedCallsController.php`:

```php
<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Call;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class GradedCallsController extends Controller
{
    public function index(Request $request)
    {
        $query = Grade::where('graded_by', Auth::id())
            ->where('status', 'submitted')
            ->with(['call:id,rep_name,project_name,call_date,duration_seconds,outcome_status'])
            ->orderBy('submitted_at', 'desc');

        // Filters
        if ($request->filled('rep')) {
            $query->whereHas('call', fn($q) => $q->where('rep_name', $request->rep));
        }

        if ($request->filled('project')) {
            $query->whereHas('call', fn($q) => $q->where('project_name', $request->project));
        }

        if ($request->filled('score_min')) {
            $query->where('weighted_score', '>=', $request->score_min);
        }

        if ($request->filled('score_max')) {
            $query->where('weighted_score', '<=', $request->score_max);
        }

        if ($request->filled('date_from')) {
            $query->where('submitted_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('submitted_at', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('appointment_quality')) {
            $query->where('appointment_quality', $request->appointment_quality);
        }

        $grades = $query->paginate(25)->withQueryString();

        // Get filter options
        $reps = Call::whereIn('id', Grade::where('graded_by', Auth::id())->pluck('call_id'))
            ->distinct()
            ->pluck('rep_name')
            ->sort()
            ->values();

        $projects = Call::whereIn('id', Grade::where('graded_by', Auth::id())->pluck('call_id'))
            ->distinct()
            ->pluck('project_name')
            ->sort()
            ->values();

        return Inertia::render('Manager/GradedCalls/Index', [
            'grades' => $grades,
            'filters' => $request->only(['rep', 'project', 'score_min', 'score_max', 'date_from', 'date_to', 'appointment_quality']),
            'filterOptions' => [
                'reps' => $reps,
                'projects' => $projects,
            ],
            'stats' => [
                'total_graded' => Grade::where('graded_by', Auth::id())->where('status', 'submitted')->count(),
                'avg_score' => round(Grade::where('graded_by', Auth::id())->where('status', 'submitted')->avg('weighted_score') ?? 0, 1),
                'this_week' => Grade::where('graded_by', Auth::id())
                    ->where('status', 'submitted')
                    ->where('submitted_at', '>=', now()->startOfWeek())
                    ->count(),
            ],
        ]);
    }
}
```

---

## Step 3: Notes Library Controller

Create `app/Http/Controllers/Manager/NotesLibraryController.php`:

```php
<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\CoachingNote;
use App\Models\RubricCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NotesLibraryController extends Controller
{
    public function index(Request $request)
    {
        $query = CoachingNote::where('author_id', Auth::id())
            ->with([
                'category:id,name',
                'objectionType:id,name',
                'call:id,rep_name,project_name,call_date',
            ])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('category')) {
            if ($request->category === 'uncategorized') {
                $query->whereNull('category_id');
            } else {
                $query->where('category_id', $request->category);
            }
        }

        if ($request->filled('rep')) {
            $query->whereHas('call', fn($q) => $q->where('rep_name', $request->rep));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('note_text', 'like', "%{$search}%")
                  ->orWhere('transcript_text', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_objection')) {
            $query->where('is_objection', $request->is_objection === 'true');
        }

        $notes = $query->paginate(25)->withQueryString();

        // Get filter options
        $categories = RubricCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        $reps = CoachingNote::where('author_id', Auth::id())
            ->join('calls', 'coaching_notes.call_id', '=', 'calls.id')
            ->distinct()
            ->pluck('calls.rep_name')
            ->sort()
            ->values();

        return Inertia::render('Manager/Notes/Index', [
            'notes' => $notes,
            'filters' => $request->only(['category', 'rep', 'search', 'is_objection']),
            'filterOptions' => [
                'categories' => $categories,
                'reps' => $reps,
            ],
            'stats' => [
                'total_notes' => CoachingNote::where('author_id', Auth::id())->count(),
                'with_category' => CoachingNote::where('author_id', Auth::id())->whereNotNull('category_id')->count(),
                'objections' => CoachingNote::where('author_id', Auth::id())->where('is_objection', true)->count(),
            ],
        ]);
    }
}
```

---

## Step 4: Objections Library Controller

Create `app/Http/Controllers/Manager/ObjectionsLibraryController.php`:

```php
<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\CoachingNote;
use App\Models\ObjectionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ObjectionsLibraryController extends Controller
{
    public function index(Request $request)
    {
        $query = CoachingNote::where('author_id', Auth::id())
            ->where('is_objection', true)
            ->with([
                'objectionType:id,name',
                'category:id,name',
                'call:id,rep_name,project_name,call_date',
            ])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('objection_type')) {
            $query->where('objection_type_id', $request->objection_type);
        }

        if ($request->filled('outcome')) {
            $query->where('objection_outcome', $request->outcome);
        }

        if ($request->filled('rep')) {
            $query->whereHas('call', fn($q) => $q->where('rep_name', $request->rep));
        }

        if ($request->filled('project')) {
            $query->whereHas('call', fn($q) => $q->where('project_name', $request->project));
        }

        $objections = $query->paginate(25)->withQueryString();

        // Get filter options
        $objectionTypes = ObjectionType::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        $reps = CoachingNote::where('author_id', Auth::id())
            ->where('is_objection', true)
            ->join('calls', 'coaching_notes.call_id', '=', 'calls.id')
            ->distinct()
            ->pluck('calls.rep_name')
            ->sort()
            ->values();

        $projects = CoachingNote::where('author_id', Auth::id())
            ->where('is_objection', true)
            ->join('calls', 'coaching_notes.call_id', '=', 'calls.id')
            ->distinct()
            ->pluck('calls.project_name')
            ->sort()
            ->values();

        // Stats by objection type
        $statsByType = CoachingNote::where('author_id', Auth::id())
            ->where('is_objection', true)
            ->select('objection_type_id', 'objection_outcome', DB::raw('count(*) as count'))
            ->groupBy('objection_type_id', 'objection_outcome')
            ->get()
            ->groupBy('objection_type_id')
            ->map(function($items) {
                return [
                    'overcame' => $items->where('objection_outcome', 'overcame')->sum('count'),
                    'failed' => $items->where('objection_outcome', 'failed')->sum('count'),
                ];
            });

        return Inertia::render('Manager/Objections/Index', [
            'objections' => $objections,
            'filters' => $request->only(['objection_type', 'outcome', 'rep', 'project']),
            'filterOptions' => [
                'objectionTypes' => $objectionTypes,
                'reps' => $reps,
                'projects' => $projects,
            ],
            'stats' => [
                'total' => CoachingNote::where('author_id', Auth::id())->where('is_objection', true)->count(),
                'overcame' => CoachingNote::where('author_id', Auth::id())->where('is_objection', true)->where('objection_outcome', 'overcame')->count(),
                'failed' => CoachingNote::where('author_id', Auth::id())->where('is_objection', true)->where('objection_outcome', 'failed')->count(),
                'by_type' => $statsByType,
            ],
        ]);
    }
}
```

---

## Step 5: Routes

Add to `routes/manager.php`:

```php
use App\Http\Controllers\Manager\GradedCallsController;
use App\Http\Controllers\Manager\NotesLibraryController;
use App\Http\Controllers\Manager\ObjectionsLibraryController;

Route::middleware(['auth', 'role:manager'])->prefix('manager')->name('manager.')->group(function () {
    // ... existing routes ...

    // Libraries
    Route::get('/graded-calls', [GradedCallsController::class, 'index'])->name('graded-calls');
    Route::get('/notes', [NotesLibraryController::class, 'index'])->name('notes-library');
    Route::get('/objections', [ObjectionsLibraryController::class, 'index'])->name('objections');
});
```

---

## Step 6: Graded Calls Page

Create `resources/js/Pages/Manager/GradedCalls/Index.vue`:

```vue
<template>
  <ManagerLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Graded Calls</h1>
        <p class="text-gray-600">Review calls you've graded</p>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Total Graded</p>
          <p class="text-2xl font-bold text-gray-900">{{ stats.total_graded }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Average Score</p>
          <p class="text-2xl font-bold" :class="scoreColor(stats.avg_score)">
            {{ stats.avg_score }}%
          </p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">This Week</p>
          <p class="text-2xl font-bold text-gray-900">{{ stats.this_week }}</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
          <select v-model="localFilters.rep" class="border rounded px-3 py-2 text-sm">
            <option value="">All Reps</option>
            <option v-for="rep in filterOptions.reps" :key="rep" :value="rep">
              {{ rep }}
            </option>
          </select>

          <select v-model="localFilters.project" class="border rounded px-3 py-2 text-sm">
            <option value="">All Projects</option>
            <option v-for="project in filterOptions.projects" :key="project" :value="project">
              {{ project }}
            </option>
          </select>

          <select v-model="localFilters.appointment_quality" class="border rounded px-3 py-2 text-sm">
            <option value="">All Quality</option>
            <option value="solid">Solid</option>
            <option value="tentative">Tentative</option>
            <option value="backed_in">Backed-in</option>
          </select>

          <input 
            type="date" 
            v-model="localFilters.date_from" 
            class="border rounded px-3 py-2 text-sm"
            placeholder="From"
          />

          <input 
            type="date" 
            v-model="localFilters.date_to" 
            class="border rounded px-3 py-2 text-sm"
            placeholder="To"
          />

          <button 
            @click="applyFilters"
            class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700"
          >
            Apply
          </button>
        </div>
      </div>

      <!-- Results Table -->
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rep</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Call Date</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quality</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Graded</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="grade in grades.data" :key="grade.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm text-gray-900">{{ grade.call.rep_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ grade.call.project_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(grade.call.call_date) }}</td>
              <td class="px-4 py-3">
                <span 
                  :class="['text-sm font-medium', scoreColor(grade.weighted_score)]"
                >
                  {{ grade.weighted_score }}%
                </span>
              </td>
              <td class="px-4 py-3">
                <span 
                  v-if="grade.appointment_quality"
                  :class="[
                    'text-xs px-2 py-0.5 rounded',
                    qualityClass(grade.appointment_quality)
                  ]"
                >
                  {{ formatQuality(grade.appointment_quality) }}
                </span>
                <span v-else class="text-xs text-gray-400">—</span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(grade.submitted_at) }}</td>
              <td class="px-4 py-3">
                <Link 
                  :href="route('manager.grade', grade.call_id)"
                  class="text-blue-600 hover:text-blue-800 text-sm"
                >
                  View
                </Link>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Empty state -->
        <div v-if="grades.data.length === 0" class="p-8 text-center text-gray-500">
          No graded calls found.
        </div>

        <!-- Pagination -->
        <div v-if="grades.last_page > 1" class="px-4 py-3 border-t flex justify-between items-center">
          <p class="text-sm text-gray-600">
            Showing {{ grades.from }} to {{ grades.to }} of {{ grades.total }}
          </p>
          <div class="flex gap-1">
            <Link
              v-for="link in grades.links"
              :key="link.label"
              :href="link.url"
              :class="[
                'px-3 py-1 text-sm rounded',
                link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700',
                !link.url ? 'opacity-50 cursor-not-allowed' : ''
              ]"
              v-html="link.label"
            />
          </div>
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';

const props = defineProps({
  grades: Object,
  filters: Object,
  filterOptions: Object,
  stats: Object,
});

const localFilters = ref({ ...props.filters });

function applyFilters() {
  router.get(route('manager.graded-calls'), localFilters.value, {
    preserveState: true,
  });
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function scoreColor(score) {
  if (score >= 85) return 'text-green-600';
  if (score >= 70) return 'text-blue-600';
  if (score >= 50) return 'text-orange-500';
  return 'text-red-600';
}

function qualityClass(quality) {
  return {
    solid: 'bg-green-100 text-green-800',
    tentative: 'bg-yellow-100 text-yellow-800',
    backed_in: 'bg-orange-100 text-orange-800',
  }[quality] || 'bg-gray-100 text-gray-800';
}

function formatQuality(quality) {
  return {
    solid: 'Solid',
    tentative: 'Tentative',
    backed_in: 'Backed-in',
  }[quality] || quality;
}
</script>
```

---

## Step 7: Notes Library Page

Create `resources/js/Pages/Manager/Notes/Index.vue`:

```vue
<template>
  <ManagerLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Coaching Notes</h1>
        <p class="text-gray-600">Browse all your coaching notes</p>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Total Notes</p>
          <p class="text-2xl font-bold text-gray-900">{{ stats.total_notes }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Categorized</p>
          <p class="text-2xl font-bold text-blue-600">{{ stats.with_category }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Objections</p>
          <p class="text-2xl font-bold text-orange-600">{{ stats.objections }}</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
          <select v-model="localFilters.category" class="border rounded px-3 py-2 text-sm">
            <option value="">All Categories</option>
            <option value="uncategorized">Uncategorized</option>
            <option v-for="cat in filterOptions.categories" :key="cat.id" :value="cat.id">
              {{ cat.name }}
            </option>
          </select>

          <select v-model="localFilters.rep" class="border rounded px-3 py-2 text-sm">
            <option value="">All Reps</option>
            <option v-for="rep in filterOptions.reps" :key="rep" :value="rep">
              {{ rep }}
            </option>
          </select>

          <select v-model="localFilters.is_objection" class="border rounded px-3 py-2 text-sm">
            <option value="">All Notes</option>
            <option value="true">Objections Only</option>
            <option value="false">Non-Objections</option>
          </select>

          <input 
            type="text" 
            v-model="localFilters.search" 
            class="border rounded px-3 py-2 text-sm"
            placeholder="Search notes..."
          />

          <button 
            @click="applyFilters"
            class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700"
          >
            Apply
          </button>
        </div>
      </div>

      <!-- Notes Grid -->
      <div class="grid gap-4">
        <div 
          v-for="note in notes.data" 
          :key="note.id"
          class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow"
        >
          <div class="flex items-start justify-between mb-2">
            <!-- Call info -->
            <div class="text-sm text-gray-500">
              <span class="font-medium text-gray-700">{{ note.call.rep_name }}</span>
              • {{ note.call.project_name }}
              • {{ formatDate(note.call.call_date) }}
            </div>

            <!-- View call link -->
            <Link 
              :href="route('manager.grade', note.call_id)"
              class="text-blue-600 hover:text-blue-800 text-sm"
            >
              View Call →
            </Link>
          </div>

          <!-- Transcript excerpt -->
          <p class="text-sm text-gray-500 italic mb-2 line-clamp-2">
            "{{ note.transcript_text }}"
          </p>

          <!-- Note text -->
          <p class="text-gray-900 mb-3">{{ note.note_text }}</p>

          <!-- Tags -->
          <div class="flex items-center gap-2 flex-wrap">
            <span 
              v-if="note.category"
              class="text-xs px-2 py-0.5 rounded bg-blue-100 text-blue-800"
            >
              {{ note.category.name }}
            </span>
            <span 
              v-else
              class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600"
            >
              Uncategorized
            </span>

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

            <span class="text-xs text-gray-400 ml-auto">
              {{ formatTimestamp(note.timestamp_start) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="notes.data.length === 0" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        No notes found. Add notes while grading calls.
      </div>

      <!-- Pagination -->
      <div v-if="notes.last_page > 1" class="mt-6 flex justify-center gap-1">
        <Link
          v-for="link in notes.links"
          :key="link.label"
          :href="link.url"
          :class="[
            'px-3 py-1 text-sm rounded',
            link.active ? 'bg-blue-600 text-white' : 'bg-white shadow hover:bg-gray-50 text-gray-700',
            !link.url ? 'opacity-50 cursor-not-allowed' : ''
          ]"
          v-html="link.label"
        />
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';

const props = defineProps({
  notes: Object,
  filters: Object,
  filterOptions: Object,
  stats: Object,
});

const localFilters = ref({ ...props.filters });

function applyFilters() {
  router.get(route('manager.notes-library'), localFilters.value, {
    preserveState: true,
  });
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
  });
}

function formatTimestamp(seconds) {
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>
```

---

## Step 8: Objections Library Page

Create `resources/js/Pages/Manager/Objections/Index.vue`:

```vue
<template>
  <ManagerLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Objections</h1>
        <p class="text-gray-600">Track objections and outcomes</p>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Total Objections</p>
          <p class="text-2xl font-bold text-gray-900">{{ stats.total }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Overcame</p>
          <p class="text-2xl font-bold text-green-600">
            {{ stats.overcame }}
            <span class="text-sm font-normal text-gray-500">
              ({{ stats.total > 0 ? Math.round((stats.overcame / stats.total) * 100) : 0 }}%)
            </span>
          </p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Failed</p>
          <p class="text-2xl font-bold text-red-600">
            {{ stats.failed }}
            <span class="text-sm font-normal text-gray-500">
              ({{ stats.total > 0 ? Math.round((stats.failed / stats.total) * 100) : 0 }}%)
            </span>
          </p>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
          <select v-model="localFilters.objection_type" class="border rounded px-3 py-2 text-sm">
            <option value="">All Types</option>
            <option v-for="type in filterOptions.objectionTypes" :key="type.id" :value="type.id">
              {{ type.name }}
            </option>
          </select>

          <select v-model="localFilters.outcome" class="border rounded px-3 py-2 text-sm">
            <option value="">All Outcomes</option>
            <option value="overcame">Overcame</option>
            <option value="failed">Failed</option>
          </select>

          <select v-model="localFilters.rep" class="border rounded px-3 py-2 text-sm">
            <option value="">All Reps</option>
            <option v-for="rep in filterOptions.reps" :key="rep" :value="rep">
              {{ rep }}
            </option>
          </select>

          <select v-model="localFilters.project" class="border rounded px-3 py-2 text-sm">
            <option value="">All Projects</option>
            <option v-for="project in filterOptions.projects" :key="project" :value="project">
              {{ project }}
            </option>
          </select>

          <button 
            @click="applyFilters"
            class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700"
          >
            Apply
          </button>
        </div>
      </div>

      <!-- Objections List -->
      <div class="space-y-4">
        <div 
          v-for="objection in objections.data" 
          :key="objection.id"
          :class="[
            'bg-white rounded-lg shadow p-4 border-l-4',
            objection.objection_outcome === 'overcame' ? 'border-green-500' : 'border-red-500'
          ]"
        >
          <div class="flex items-start justify-between mb-2">
            <!-- Objection type badge -->
            <span 
              :class="[
                'text-sm font-medium px-2 py-0.5 rounded',
                objection.objection_outcome === 'overcame'
                  ? 'bg-green-100 text-green-800'
                  : 'bg-red-100 text-red-800'
              ]"
            >
              {{ objection.objection_type?.name }}
              {{ objection.objection_outcome === 'overcame' ? '✓ Overcame' : '✗ Failed' }}
            </span>

            <!-- Call info -->
            <div class="text-sm text-gray-500 text-right">
              <span class="font-medium text-gray-700">{{ objection.call.rep_name }}</span>
              <br />
              {{ objection.call.project_name }} • {{ formatDate(objection.call.call_date) }}
            </div>
          </div>

          <!-- Transcript excerpt -->
          <p class="text-sm text-gray-500 italic mb-2">
            "{{ objection.transcript_text }}"
          </p>

          <!-- Coaching note -->
          <p class="text-gray-900 mb-3">{{ objection.note_text }}</p>

          <!-- Footer -->
          <div class="flex items-center justify-between">
            <span class="text-xs text-gray-400">
              {{ formatTimestamp(objection.timestamp_start) }}
            </span>
            <Link 
              :href="route('manager.grade', objection.call_id)"
              class="text-blue-600 hover:text-blue-800 text-sm"
            >
              View Call →
            </Link>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="objections.data.length === 0" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        No objections recorded yet. Flag objections while grading calls.
      </div>

      <!-- Pagination -->
      <div v-if="objections.last_page > 1" class="mt-6 flex justify-center gap-1">
        <Link
          v-for="link in objections.links"
          :key="link.label"
          :href="link.url"
          :class="[
            'px-3 py-1 text-sm rounded',
            link.active ? 'bg-blue-600 text-white' : 'bg-white shadow hover:bg-gray-50 text-gray-700',
            !link.url ? 'opacity-50 cursor-not-allowed' : ''
          ]"
          v-html="link.label"
        />
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';

const props = defineProps({
  objections: Object,
  filters: Object,
  filterOptions: Object,
  stats: Object,
});

const localFilters = ref({ ...props.filters });

function applyFilters() {
  router.get(route('manager.objections'), localFilters.value, {
    preserveState: true,
  });
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
  });
}

function formatTimestamp(seconds) {
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>
```

---

## Verification Checklist

After implementation:

**Navigation:**
- [ ] Logo/title "Call Grader" links to call queue
- [ ] "Calls" tab links to `/manager/calls`
- [ ] "Graded" tab links to `/manager/graded-calls`
- [ ] "Notes" tab links to `/manager/notes`
- [ ] "Objections" tab links to `/manager/objections`
- [ ] Active tab is highlighted
- [ ] Mobile nav shows all tabs
- [ ] User dropdown shows name
- [ ] Sign out works

**Graded Calls Library:**
- [ ] Shows list of submitted grades
- [ ] Stats cards show totals
- [ ] Filter by rep works
- [ ] Filter by project works
- [ ] Filter by date range works
- [ ] Filter by appointment quality works
- [ ] "View" link opens grading page
- [ ] Pagination works

**Notes Library:**
- [ ] Shows all notes
- [ ] Stats cards show totals
- [ ] Filter by category works
- [ ] Filter by rep works
- [ ] Filter by objections only works
- [ ] Search notes works
- [ ] "View Call" link opens grading page
- [ ] Category and objection badges display
- [ ] Pagination works

**Objections Library:**
- [ ] Shows only objection-flagged notes
- [ ] Stats show overcame/failed counts and percentages
- [ ] Filter by objection type works
- [ ] Filter by outcome works
- [ ] Filter by rep works
- [ ] Filter by project works
- [ ] Color-coded by outcome (green/red)
- [ ] "View Call" link opens grading page
- [ ] Pagination works

---

## Test Flow

1. Grade a few calls with notes and objections (Slice 6+7)
2. Visit `/manager/graded-calls` → see graded calls
3. Apply filters → results update
4. Visit `/manager/notes` → see all notes
5. Filter by category → only those notes show
6. Search for a word → filtered results
7. Visit `/manager/objections` → see objections only
8. Filter by outcome → only overcame/failed show
9. Click "View Call" → opens grading page
10. Test navigation between all tabs
11. Test mobile nav

---

## Notes

- All libraries are manager-specific (only see your own data)
- Navigation persists across all manager pages
- Logo always returns to call queue
- Stats update in real-time based on filters
- Pagination preserves filter state
