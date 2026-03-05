class ApexChartComponent {
    constructor({ elementId, url, method = 'GET', payload = null }) {
        this.elementId = elementId;
        this.url = url;
        this.method = method;
        this.payload = payload;
        this.chart = null;
    }

    async load(filters = {}) {
        try {
            // Build URL with filter params
            const urlObj = new URL(this.url, window.location.origin);
            Object.entries(filters).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') {
                    urlObj.searchParams.set(key, value);
                } else {
                    urlObj.searchParams.delete(key);
                }
            });

            const response = await fetch(urlObj.toString(), {
                method: this.method,
                headers: { 'Content-Type': 'application/json' },
                body: this.method !== 'GET' ? JSON.stringify(this.payload) : null
            });

            const result = await response.json();

            if (result.status !== 'success') {
                throw new Error('Erro ao carregar dados do gráfico');
            }

            this.render(result.data);

        } catch (error) {
            console.error('ApexChartComponent:', error);
            const el = document.getElementById(this.elementId);
            if (el) el.innerHTML = '<p class="text-danger small p-2"><i class="bi bi-exclamation-circle"></i> Erro ao carregar gráfico</p>';
        }
    }

    render(chartData) {
        const defaultOptions = {
            chart: {
                type: chartData.type,
                height: chartData.height || 300,
                toolbar: { show: false }
            },
            title: {
                text: chartData.title || '',
                align: 'left'
            },
            series: chartData.series
        };

        const options = {
            ...defaultOptions,
            ...chartData.options
        };

        if (this.chart) {
            this.chart.updateOptions(options);
            this.chart.updateSeries(chartData.series);
        } else {
            this.chart = new ApexCharts(
                document.querySelector(`#${this.elementId}`),
                options
            );
            this.chart.render();
        }
    }

    destroy() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    }
}
