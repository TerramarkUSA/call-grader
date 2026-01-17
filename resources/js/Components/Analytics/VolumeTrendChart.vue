<template>
  <div class="h-64">
    <canvas ref="chartCanvas"></canvas>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
  data: Array,
});

const chartCanvas = ref(null);
let chart = null;

onMounted(() => {
  renderChart();
});

watch(() => props.data, () => {
  renderChart();
});

function renderChart() {
  if (chart) {
    chart.destroy();
  }

  const ctx = chartCanvas.value.getContext('2d');

  chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: props.data.map(d => formatDate(d.date)),
      datasets: [
        {
          label: 'Total Calls',
          data: props.data.map(d => d.total),
          borderColor: '#6b7280',
          backgroundColor: 'transparent',
          tension: 0.3,
        },
        {
          label: 'Conversations',
          data: props.data.map(d => d.conversations),
          borderColor: '#22c55e',
          backgroundColor: 'transparent',
          tension: 0.3,
        },
        {
          label: 'Appointments',
          data: props.data.map(d => d.appointments),
          borderColor: '#3b82f6',
          backgroundColor: 'transparent',
          tension: 0.3,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
        },
      },
      scales: {
        y: {
          beginAtZero: true,
        },
      },
    },
  });
}

function formatDate(dateStr) {
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
</script>
