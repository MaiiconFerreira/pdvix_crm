class DashboardCharts {
  constructor(canvasId, chartType = 'bar', options = {}) {
    this.canvasId = canvasId;
    this.chartType = chartType;
    this.options = options;
    this.chart = null; // referência ao gráfico Chart.js
  }

  // Método para buscar dados do backend
  async fetchData(url) {
    try {
      const response = await fetch(url);
      if (!response.ok) throw new Error("Erro ao buscar dados");
      return await response.json();
    } catch (error) {
      console.error("Erro no fetch:", error);
      return null;
    }
  }

  // Método para renderizar o gráfico
  render(data) {
    const ctx = document.getElementById(this.canvasId);

    // destruir se já existir
    if (this.chart) {
      this.chart.destroy();
    }

    this.chart = new Chart(ctx, {
      type: this.chartType,
      data: data,
      options: this.options
    });
  }

  // Método para buscar + renderizar em um passo só
  async loadFromUrl(url, formatCallback) {
    const rawData = await this.fetchData(url);
    if (!rawData) return;

    // formatCallback transforma JSON do backend no formato Chart.js
    const chartData = formatCallback(rawData);

    this.render(chartData);
  }
}
