$.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
    const criteriaRows = document.querySelectorAll('.filter-criteria-row');
    if (criteriaRows.length === 0) return true;

    let isVisible = true;

    criteriaRows.forEach(row => {
        const colIndex = parseInt(row.querySelector('.filter-col').value);
        const condition = row.querySelector('.filter-cond').value;
        const rawInput = row.querySelector('.filter-val').value;
        const targetValue = parseFloat(rawInput);

        // Se o campo estiver vazio, ignoramos este critério específico
        if (rawInput === "" || isNaN(targetValue)) return;

        // Limpa o valor da célula (remove % ou ícones) para comparar números
        let cellValue = data[colIndex] || "0";
        cellValue = parseFloat(cellValue.replace(/[^\d.-]/g, '')) || 0;

        if (condition === 'gt' && !(cellValue > targetValue)) isVisible = false;
        if (condition === 'lt' && !(cellValue < targetValue)) isVisible = false;
        if (condition === 'eq' && !(cellValue === targetValue)) isVisible = false;
    });

    return isVisible;
});

class Table {
	constructor(selector, columns, fetchUrl, options = {}) {

		function createIconImage(isCheck) {
		    const canvas = document.createElement('canvas');
		    canvas.width = 12;
		    canvas.height = 12;
		    const ctx = canvas.getContext('2d');

		    ctx.clearRect(0, 0, canvas.width, canvas.height);
		    ctx.fillStyle = isCheck ? 'green' : 'red';
		    ctx.font = '12px Arial';
		    ctx.textAlign = 'center';
		    ctx.textBaseline = 'middle';
		    ctx.fillText(isCheck ? '✔' : '✖', canvas.width / 2, canvas.height / 2);
		    return canvas.toDataURL();
		}

		let buttonsDefault = [
			{
				extend: 'excel',
				text: '<i class="bi bi-file-earmark-excel"></i> Exportar Excel',
				className: 'btn btn-sm btn-success',
				title: options.titleFile !== undefined ? options.titleFile : null,
				filename: options.filename || (Date.now().toString(36) + Math.random().toString(36).substring(2)),
				exportOptions: {
					columns: ':visible:not(.no-export)',
					format: {
						body: function(data) {
							if (typeof data === 'string' && data.includes('bi-check-circle-fill')) return 'OK';
							if (typeof data === 'string' && data.includes('bi-x-circle-fill')) return 'Falta';
							return data;
						}
					}
				}
			},
			{
				extend: 'colvis',
				text: '<i class="bi bi-eye"></i> Colunas',
				className: 'btn btn-sm btn-secondary'
			},
			{
				extend: 'pdfHtml5',
				text: 'Exportar PDF',
				className: 'btn btn-sm btn-danger',
				title: options.titleFile || 'Relatório de Usuários',
				filename: options.filename || (Date.now().toString(36) + Math.random().toString(36).substring(2)),
				orientation: options.exportOrientation || 'landscape',
				pageSize: 'A4',
				exportOptions: {
					columns: ':visible',
					format: {
						body: function(data) {
							if (typeof data === 'string' && data.includes('bi-check-circle-fill')) return '__CHECK__';
							if (typeof data === 'string' && data.includes('bi-x-circle-fill')) return '__X__';
							return data;
						}
					}
				},
				customize: function(doc) {
					var body = doc.content[1].table.body;
					body.forEach(function(row) {
						row.forEach(function(cell, colIndex) {
							if (cell.text === '__CHECK__') {
								row[colIndex] = { image: createIconImage(true), width: 12, alignment: 'center' };
							}
							if (cell.text === '__X__') {
								row[colIndex] = { image: createIconImage(false), width: 12, alignment: 'center' };
							}
						});
					});
				}
			}
		];

		let buttonsOptions;

		if (options.mergeButtons && Array.isArray(options.buttons)) {

		  // 🔹 Mescla os botões padrões com os da página
		  buttonsOptions = [...buttonsDefault, ...options.buttons];

		} else if (Array.isArray(options.buttons)) {
		  // 🔹 Substitui completamente os botões padrões
		  buttonsOptions = options.buttons;
		} else {
		  // 🔹 Usa apenas os padrões
		  buttonsOptions = buttonsDefault;
		}

		this.selector = selector;
		this.columns = columns;
		this.fetchUrl = fetchUrl;
		this.options = options;

		this.instance = new DataTable(selector, {
			data: [],
			columns: this.columns,
			...this.options,
			dom: options.dom ||
				"<'row'<'col-sm-6'f><'col-sm-6 text-end'B>>" +
				"rt" +
				"<'row'<'col-sm-6'l><'col-sm-6 text-end'p>>",
			buttons: buttonsOptions,
			language: {
				url: '/template/datatables_pt-BR.json'
			},
		});

		$(selector).on('draw.dt', function () {
		    $('[data-toggle="tooltip"]').tooltip();
		});


	}

	updateTable(params = '', render = {}) {
		const queryString = new URLSearchParams(params).toString();
		const fullUrl = `${this.fetchUrl}?${queryString}`;

		fetch(fullUrl)
			.then(response => {
				// Se não for ok, enviamos o status para o catch poder tratar
				if (!response.ok) {
					const error = new Error("Erro na requisição");
					error.status = response.status; 
					throw error;
				}
				return response.json();
			})
			.then(data => {
				let originData = data.data || data;
				let tableData = data.data || data;

				if (typeof render.onTransform === 'function') {
					tableData = render.onTransform(tableData);
				}

				this.instance.clear();
				this.instance.rows.add(tableData);

				if (typeof render.keepPage === 'undefined') {
					render.keepPage = false;
				}

				render.keepPage = (render.keepPage === true) ? false : true;
				this.instance.draw(render.keepPage);

				if (typeof render.onUpdate === 'function') {
					render.onUpdate(tableData, originData);
				}

				$(() => $('[data-toggle="tooltip"]').tooltip());
			})
			.catch(error => {
				console.error(error);

				switch(error.status){
					case 403:
					// Caso de Sessão Expirada
					MODAL.openAlertModal({
						type: 'error',
						title: 'Sessão Expirada',
						subtitle: 'Faça o login novamente para continuar.',
						confirmText: 'Recarregar Agora',
						onConfirm: () => window.location.reload()
					});
					break;

					
					case 400:
					// Caso de Sessão Expirada
					MODAL.openAlertModal({
						type: 'error',
						title: 'Campos obrigatórios faltantes',
						subtitle: 'Verifique se informou os campos obrigatórios corretamente.',
						confirmText: 'OK',
						onConfirm: () => MODAL.hideModal()
					});
					break;

					case 404:
					// Caso de Sessão Expirada
					MODAL.openAlertModal({
						type: 'error',
						title: 'Rota não encontrada',
						subtitle: 'Consulte o desenvolvedor.',
						confirmText: 'Ok',
						onConfirm: () => MODAL.hideModal()
					});
					break;
					case 500:
					// Caso de Sessão Expirada
					MODAL.openAlertModal({
						type: 'error',
						title: 'Erro interno no servidor',
						subtitle: 'Consulte o desenvolvedor.',
						confirmText: 'OK',
						onConfirm: () => MODAL.hideModal()
					});
					break;
					case 501:
					// Caso de Sessão Expirada
					MODAL.openAlertModal({
						type: 'error',
						title: 'Sem permissão para acessar este recurso',
						subtitle: 'Consulte o desenvolvedor.',
						confirmText: 'OK',
						onConfirm: () => MODAL.hideModal()
					});
					break;
					default:
					// Caso de Sessão Expirada
					MODAL.openAlertModal({
						type: 'error',
						title: 'Erro desconhecido',
						subtitle: 'Não foi possível identificar a causa do erro. Consulte o desenvolvedor.',
						confirmText: 'OK',
						onConfirm: () => MODAL.hideModal()
					});
					break;
				}
				
			});
	}

	formatDate(dateStr) {
		const [yyyy, mm, dd] = dateStr.split("-");
		return `${dd}/${mm}/${yyyy}`;
	}


	setupCustomFilters(filters) {
        // Registra uma função de busca global no DataTable
      $.fn.dataTable.ext.search.push((settings, data, dataIndex) => {
          // Verifica se o filtro está sendo aplicado a esta tabela específica
          if (settings.nTable.id !== $(this.selector).attr('id')) return true;

          return filters.every(filter => {
              const val = parseFloat(data[filter.columnIndex]) || 0;
              const criteria = $(`#filter-criteria-${filter.id}`).val();
              const threshold = parseFloat($(`#filter-value-${filter.id}`).val());

              if (isNaN(threshold)) return true; // Se não houver valor, não filtra

              if (criteria === 'below') return val < threshold;
              if (criteria === 'above') return val > threshold;
              return true;
          });
      });

      // Adiciona um evento para redesenhar a tabela quando os inputs mudarem
      $(document).on('input change', '.table-custom-filter', () => {
          this.instance.draw();
      });
  }

	updateTableFromServer() { }
}
