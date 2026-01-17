<template>
  <div class="overflow-x-auto">
    <table class="w-full text-xs">
      <thead>
        <tr>
          <th class="text-left py-2 text-gray-500 font-medium w-16"></th>
          <th v-for="day in days" :key="day.value" class="text-center py-2 text-gray-500 font-medium w-12">
            {{ day.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="hour in hours" :key="hour">
          <td class="py-1 text-gray-500">{{ formatHour(hour) }}</td>
          <td
            v-for="day in days"
            :key="`${hour}-${day.value}`"
            class="p-1"
          >
            <div
              class="w-full h-6 rounded"
              :style="{ backgroundColor: getCellColor(hour, day.value) }"
              :title="`${day.label} ${formatHour(hour)}: ${getCellCount(hour, day.value)} calls`"
            ></div>
          </td>
        </tr>
      </tbody>
    </table>
    <div class="flex items-center justify-end gap-4 mt-4 text-xs text-gray-500">
      <div class="flex items-center gap-1">
        <div class="w-4 h-4 rounded bg-gray-100"></div>
        <span>Low</span>
      </div>
      <div class="flex items-center gap-1">
        <div class="w-4 h-4 rounded bg-blue-300"></div>
        <span>Medium</span>
      </div>
      <div class="flex items-center gap-1">
        <div class="w-4 h-4 rounded bg-blue-600"></div>
        <span>High</span>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  data: Array,
  maxCount: Number,
});

const days = [
  { value: 1, label: 'Sun' },
  { value: 2, label: 'Mon' },
  { value: 3, label: 'Tue' },
  { value: 4, label: 'Wed' },
  { value: 5, label: 'Thu' },
  { value: 6, label: 'Fri' },
  { value: 7, label: 'Sat' },
];

const hours = [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18];

function formatHour(hour) {
  if (hour === 12) return '12pm';
  return hour < 12 ? `${hour}am` : `${hour - 12}pm`;
}

function getCellData(hour, day) {
  return props.data.find(d => d.hour === hour && d.day === day);
}

function getCellCount(hour, day) {
  return getCellData(hour, day)?.count ?? 0;
}

function getCellColor(hour, day) {
  const cell = getCellData(hour, day);
  if (!cell || cell.count === 0) return '#f3f4f6';

  const intensity = cell.intensity;
  if (intensity < 0.33) return '#dbeafe';
  if (intensity < 0.66) return '#93c5fd';
  return '#2563eb';
}
</script>
