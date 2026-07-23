let chart;

$wire.dataset().then(data => {
	chart = new Chart(document.getElementById('chart'), {
    type: 'pie',
    data: {
        labels: data.labels,
        datasets: [{
            data: data.values,
            backgroundColor: [
                '#c2956a',
                '#d4a843',
                '#6ba3be',
                '#5fb8a5',
                '#7c82b5',
                '#c26b6b',
                '#6b9e6b',
                '#9ca3af',
            ],
        }],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false,
            },
        },
    },
	});
})

$wire.$on('chart-update', ({ data }) => {
    if (!(chart && data.values.length > 0)) {
		return;
    }

    chart.data.labels = data.labels;
    chart.data.datasets[0].data = data.values;
    chart.update();
});
