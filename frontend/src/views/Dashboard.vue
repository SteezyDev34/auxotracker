<script setup>
import BestSellingWidget from '@/components/dashboard/BestSellingWidget.vue';
import NotificationsWidget from '@/components/dashboard/NotificationsWidget.vue';
import RecentSalesWidget from '@/components/dashboard/RecentSalesWidget.vue';
import RevenueStreamWidget from '@/components/dashboard/RevenueStreamWidget.vue';
import StatsWidget from '@/components/dashboard/StatsWidget.vue';
import ChartWidget from '@/components/dashboard/ChartWidget.vue';
import BetsHistoryWidget from '@/components/dashboard/BetsHistoryWidget.vue';
import { useLayout } from '@/layout/composables/layout';
import { onMounted, ref, watch } from 'vue';
import CapitalEvolutionChart from '@/components/dashboard/CapitalEvolutionChart.vue';

const { getPrimary, getSurface, isDarkTheme } = useLayout();
const lineData = ref(null);
const lineOptions = ref(null);
onMounted(() => {
    setColorOptions();
});
function setColorOptions() {
    const documentStyle = getComputedStyle(document.documentElement);
    const textColor = documentStyle.getPropertyValue('--text-color');
    const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary');
    const surfaceBorder = documentStyle.getPropertyValue('--surface-border');

    lineData.value = {
        labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July'],
        datasets: [
            {
                label: 'First Dataset',
                data: [65, 59, 80, 81, 56, 55, 40],
                fill: false,
                backgroundColor: documentStyle.getPropertyValue('--p-primary-500'),
                borderColor: documentStyle.getPropertyValue('--p-primary-500'),
                tension: 0.4
            },
            {
                label: 'Second Dataset',
                data: [28, 48, 40, 19, 86, 27, 90],
                fill: false,
                backgroundColor: documentStyle.getPropertyValue('--p-primary-200'),
                borderColor: documentStyle.getPropertyValue('--p-primary-200'),
                tension: 0.4
            }
        ]
    };

    lineOptions.value = {
        plugins: {
            legend: {
                labels: {
                    fontColor: textColor
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    color: textColorSecondary
                },
                grid: {
                    color: surfaceBorder,
                    drawBorder: false
                }
            },
            y: {
                ticks: {
                    color: textColorSecondary
                },
                grid: {
                    color: surfaceBorder,
                    drawBorder: false
                }
            }
        }
    };

}

watch(
    [getPrimary, getSurface, isDarkTheme],
    () => {
        setColorOptions();
    },
    { immediate: true }
);
</script>
<template>
    <div class="grid grid-cols-12 gap-8">
        <div class="col-span-12 xl:col-span-12">
            <CapitalEvolutionChart />
        </div>
        <!-- Widget des statistiques masqué temporairement -->
        <!-- <div class="col-span-12 xl:col-span-12">
        <StatsWidget />
        </div> -->
        <div class="col-span-12 xl:col-span-12">
        <BetsHistoryWidget />
        </div>
    </div>
</template>
