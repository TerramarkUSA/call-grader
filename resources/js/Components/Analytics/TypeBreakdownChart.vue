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
    type: 'doughnut',
    data: {
      labels: props.data.map(d => d.label),
      datasets: [{
        data: props.data.map(d => d.count),
        backgroundColor: props.data.map(d => d.color),
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'right',
        },
      },
    },
  });
}
</script>
