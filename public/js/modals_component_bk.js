class ModalManager {
    constructor() {
        this.modalId = 'dynamicModal';
    }

    /**
     * Cria o modal no DOM caso não exista
     */
    createModalStructure() {
        if (document.getElementById(this.modalId)) return;

        const modalHtml = `
        <div class="modal fade" id="${this.modalId}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body"></div>
                    <div class="modal-footer"></div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    /**
     * Abre modal de formulário dinâmico
     * @param {Object} options - Configurações do modal
     */
    openFormModal(options) {
        this.createModalStructure();

        const modalEl = document.getElementById(this.modalId);
        let modalObj = new bootstrap.Modal(modalEl);
        const modalTitle = modalEl.querySelector('.modal-title');
        const modalBody = modalEl.querySelector('.modal-body');
        const modalFooter = modalEl.querySelector('.modal-footer');

        // Limpa conteúdo antigo
        modalBody.innerHTML = '';
        modalFooter.innerHTML = '';
        modalTitle.textContent = options.title || 'Formulário';

        // Cria formulário
        const form = document.createElement('form');
        form.method = options.method || 'POST';
        form.action = options.action || '#';

        // Cria inputs dinamicamente
        if (options.inputs) {
      options.inputs.forEach(input => {
          let field;

          // Cria o campo
          if (input.type === 'textarea') {
              field = document.createElement('textarea');
              field.className = 'form-control';
          }else if (input.type === 'select2') {
            field = document.createElement('select');
            field.name = input.name || '';
            field.className = 'form-control';

            // insere select vazio para o Select2 preencher
            const placeholder = input.placeholder || 'Selecione...';
            const option = document.createElement('option');
            option.value = '';
            option.textContent = placeholder;
            field.appendChild(option);

            setTimeout(() => {
    $(field).select2({
        width: "100%",
        placeholder: placeholder,
        dropdownParent: $(modalEl), // 👈 força ficar dentro do modal
        ajax: {
            url: input.fetchURI,
            dataType: "json",
            delay: 250,
            processResults: function (response) {
                if (response.status === "success" && Array.isArray(response.data)) {
                    return {
                        results: response.data.map(item => {
                            if (typeof input.processItem === "function") {
                                return input.processItem(item);
                            }
                            return {
                                id: item.id,
                                text: item.razao_social || item.nome || "Sem nome"
                            };
                        })
                    };
                }
                return { results: [] };
            }
        }
    });
}, 150);
        } else {
              field = document.createElement('input');
              field.type = input.type || 'text';
              field.className = 'form-control';
          }
          field.name = input.name || '';
          field.value = input.value || '';

          // Se for hidden, não cria label nem div
          if (input.type === 'hidden') {
              form.appendChild(field);
          } else {
              const div = document.createElement('div');
              div.className = 'mb-3';

              const label = document.createElement('label');
              label.className = 'form-label';
              label.textContent = input.label || '';
              if (input.name) label.htmlFor = input.name;

              div.appendChild(label);
              div.appendChild(field);
              form.appendChild(div);
          }
      });
  }


        // Botões
        const submitBtn = document.createElement('button');
        submitBtn.type = 'submit';
        submitBtn.className = 'btn btn-primary';
        submitBtn.textContent = options.submitText || 'Enviar';
        submitBtn.onclick = ()=>{
          if (typeof options.onSubmit === 'function') {
              options.onSubmit(new FormData(form), form, modalObj);
          }
        };

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-secondary';
        cancelBtn.setAttribute('data-bs-dismiss', 'modal');
        cancelBtn.textContent = 'Cancelar';

        modalFooter.appendChild(cancelBtn);
        modalFooter.appendChild(submitBtn);
        modalBody.appendChild(form);

        // Listener de envio
        form.onsubmit = (e) => {
            e.preventDefault();
            if (typeof options.onSubmit === 'function') {
                options.onSubmit(new FormData(form), form, modalObj);
            }
        };

        modalObj.show();
    }

    /**
     * Abre modal de confirmação GET
     * @param {Object} options - Configurações do modal
     */
    openConfirmationModal(options) {
        this.createModalStructure();

        const modalEl = document.getElementById(this.modalId);
        let modalObj = new bootstrap.Modal(modalEl);
        const modalTitle = modalEl.querySelector('.modal-title');
        const modalBody = modalEl.querySelector('.modal-body');
        const modalFooter = modalEl.querySelector('.modal-footer');

        // Limpa conteúdo antigo
        modalBody.innerHTML = '';
        modalFooter.innerHTML = '';
        modalTitle.textContent = options.title || 'Confirmação';

        // Ícone charmoso azul
        const icon = document.createElement('div');
        icon.className = 'text-center mb-3';
        icon.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#0d6efd" class="bi bi-question-circle" viewBox="0 0 16 16">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0-1A6 6 0 1 0 8 2a6 6 0 0 0 0 12z"/>
                <path d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.505-1.206 1.05-1.168 2.037l.003.217a.25.25 0 0 0 .25.25h.819a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927.896-1.371.654-.472 1.376-.977 1.376-2.073C10.5 4.012 9.494 3 8.134 3c-1.355 0-2.3.824-2.506 1.787zm1.293 5.696c0 .356.287.643.643.643.356 0 .643-.287.643-.643a.643.643 0 1 0-1.286 0z"/>
            </svg>
        `;

        // Texto de confirmação
        const text = document.createElement('p');
        text.className = 'text-center';
        text.textContent = options.message || 'Você tem certeza?';

        modalBody.appendChild(icon);
        modalBody.appendChild(text);

        // Botões
        const confirmBtn = document.createElement('button');
        confirmBtn.type = 'button';
        confirmBtn.className = 'btn btn-primary';
        confirmBtn.textContent = options.confirmText || 'Confirmar';
        confirmBtn.onclick = () => {
            if (typeof options.onConfirm === 'function') {
                options.onConfirm(modalObj);
            }
        };

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-secondary';
        cancelBtn.setAttribute('data-bs-dismiss', 'modal');
        cancelBtn.textContent = 'Cancelar';

        modalFooter.appendChild(cancelBtn);
        modalFooter.appendChild(confirmBtn);

        modalObj.show();
    }
}
