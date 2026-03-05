class Alerta {
  constructor(status, title, message, options = {}) {

    // 🎯 Configurações padrão
    toastr.options = {
      "closeButton": false,
      "debug": false,
      "newestOnTop": false,
      "progressBar": true,
      "positionClass": "toast-top-right",
      "preventDuplicates": false,
      "onclick": null,
      "showDuration": "300",
      "hideDuration": "1000",
      "showEasing": "swing",
      "hideEasing": "linear",
      "showMethod": "fadeIn",
      "hideMethod": "fadeOut",
      ...options
    };

		// ⚙️ Ajuste dinâmico por tipo de alerta
				switch (status) {
				  case 'error':
				  case 'warning':
				    toastr.options.timeOut = 10000;           // fecha 10s após mouse sair
				    toastr.options.extendedTimeOut = 5000;   // tempo adicional após hover
				    toastr.options.closeButton = true;       // pode fechar manualmente
				    break;

				  case 'success':
				  case 'info':
				  default:
				    toastr.options.timeOut = 10000;          // 10 segundos na tela
				    toastr.options.extendedTimeOut = 5000;
				    toastr.options.closeButton = false;
				    break;
				}

    // 💬 Exibe a mensagem (com ou sem título)
    if (title) {
      toastr[status](message, title);
    } else {
      toastr[status](message);
    }
  }
}
