<!doctype html>
<html lang="pt_br" v="0556546545645102025">
  <!--begin::Head-->
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta charset="UTF-8">
    <title>Início - IDEAL Soluções</title>
    <!--begin::Primary Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="title" content="IDEAL SOLUCOES" />
    <meta name="author" content="Maicon Ferreira" />
    <link rel="icon" type="image/png" href="template/dist/assets/img/favicon.png">
    <link rel="icon" href="template/dist/assets/img/favicon.ico" type="image/x-icon">
	<link rel="manifest" href="/manifest.json?v=05062025">
	<meta name="theme-color" content="#1976d2">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
     <style>
   /* Tela inteira do loading */
  #loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: #ffffff;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.5s ease;
  }

  /* Container para empilhar logo e spinner */
  .loading-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px; /* espaço entre logo e spinner */
  }

  .spinner {
    border: 8px solid #f3f3f3;
    border-top: 8px solid #4d2a6e;
    border-radius: 50%;
    width: 60px;
    height: 60px;
    animation: spin 1s linear infinite;
  }

  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }

  /* Estilo básico do login */
  .login-container {
    max-width: 400px;
    margin: 100px auto;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
    font-family: Arial, sans-serif;
  }

  .logo-wrapper {
    overflow: hidden;
    width: 200px; /* ajuste conforme o tamanho da imagem */
	margin-bottom: 50px;
  }

  .logo {
    width: 100%;
    /* animação de slide para a direita */
    transform: translateX(-100%);
    animation: slideIn 1s ease-out forwards;
  }

  @keyframes slideIn {
    to {
      transform: translateX(0);
    }
  }
  	.top-container {
  	    padding: 0.5rem;
  	}

  	.top-container .dt-buttons {
  	    margin-left: auto;
  	}

  	.top-container .btn {
  	    font-size: 0.8rem;
  	    padding: 0.25rem 0.5rem;
  	}
  	/* Campo de pesquisa */
  .dt-search input {
      width: 260px;   /* largura do campo */
      height: 32px;   /* altura do campo */
      font-size: 14px;
      padding: 10px;
  		margin-left: 2px;
  }
  .dt-search input:focus{
  	outline: none;
  	border:1px solid #000;
  	box-shadow: var(--bs-box-shadow-inset), 0 0 0 0.15rem #2125290f;
  }

  /* Select "Mostrar X registros" */
  .dt-length select {
      width: 80px;    /* largura do select */
      height: 32px;   /* altura do select */
      font-size: 14px;
      padding: 2px 10px;
  }
  .dt-search input,
  .dt-length select {
      display: inline-block;
      vertical-align: middle;
  }
  /* Bootstrap 5+ com DataTables */
  .dt-paging .pagination {
      justify-content: flex-end;
  }
  .notificacao-noread{
    color: var(--bs-dropdown-link-hover-color) !important;
    background-color: var(--bs-dropdown-link-hover-bg) !important;
  }
  .loading {
  position: relative;
  pointer-events: none;
  filter: grayscale(100%) brightness(0.8);
  cursor: wait;
  overflow: hidden;
  animation: fadePulse 3s infinite ease-in-out;
}

/* Efeito de onda diagonal */
.loading::after {
  content: "";
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: linear-gradient(
    135deg,
    rgba(0, 0, 0, 0) 40%,
    rgba(0, 0, 0, 0.1) 50%,
    rgba(0, 0, 0, 0) 60%
  );
  animation: waveShadow 2.5s infinite ease-in-out;
}

/* Animação de fade in/out */
@keyframes fadePulse {
  0%, 100% {
    opacity: 0.3;
  }
  50% {
    opacity: 1;
  }
}

/* Animação da onda */
@keyframes waveShadow {
  0% {
    transform: translateX(-100%) translateY(-100%);
  }
  100% {
    transform: translateX(100%) translateY(100%);
  }
}
.os-scrollbar-track{
  background: rgba(0, 0, 0, 0.2);
}

/* Animação de abertura */
@keyframes biaChatOpen {
    from {
        opacity: 0;
        transform: translateY(20px) scale(.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Animação de fechamento */
@keyframes biaChatClose {
    from {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    to {
        opacity: 0;
        transform: translateY(20px) scale(.95);
    }
}

/* Classe aplicada ao abrir */
.bia-open {
    display: flex !important;
    animation: biaChatOpen .25s ease forwards;
}

/* Classe aplicada ao fechar */
.bia-close {
    animation: biaChatClose .20s ease forwards;
}

/* BOTÃO FLUTUANTE */
#bia-floating-btn {
    position: fixed;
    bottom: 25px;
    right: 25px;
    width: 65px;
    height: 65px;
    background: #4e73df;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
    z-index: 9999;
    transition: transform .2s;
    animation: biaPulse 2.5s infinite ease-in-out;
}

#bia-floating-btn:hover {
    transform: scale(1.07);
}

.bia-floating-avatar {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    object-fit: cover;
    object-position: top; /* foca no rosto */
    border: 3px solid #fff;
}

/* JANELA GERAL */
#bia-chat-window {
    position: fixed;
    bottom: 100px;
    right: 22px;
    width: 520px;
    height: 650px;
    display: none;
    flex-direction: row;
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,.25);
    z-index: 9999;
}

/* SIDEBAR */
.bia-sidebar {
    width: 0;
    background: #1f2838;
    color: white;
    overflow: hidden;
    transition: width .25s;
    display: flex;
    flex-direction: column;
}
.bia-sidebar.open {
    width: 160px;
}
.bia-sidebar-header {
    padding: 14px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.bia-conversations-list {
    overflow-y: auto;
    flex: 1;
}
.bia-conversation-item {
    padding: 10px;
    cursor: pointer;
}
.bia-conversation-item:hover {
    background: rgba(255,255,255,0.15);
}

/* CHAT BODY */
.bia-chat-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #f4f6f9;
}

/* HEADER */
.bia-chat-header {
    height: 65px;
    padding: 10px 15px;
    background: #9b32a7;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #fff;
}

/* Sidebar Toggle */
.bia-sidebar-toggle {
    font-size: 26px;
    cursor: pointer;
}

/* Avatar e nome */
.bia-header-profile {
    display: flex;
    align-items: center;
    gap: 10px;
}
.bia-avatar-header {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    border: 2px solid white;
    object-fit: cover;
    object-position: 100% 50%;
    border: 3px solid #b8caff;
    box-shadow: 0 3px 10px rgba(78, 115, 223, 0.25);
}

.bia-header-text {
    line-height: 15px;
    font-size: 14px;
}

/* Minimizar */
.bia-minimize-btn {
    font-size: 22px;
    cursor: pointer;
}

/* MENSAGENS */
.bia-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
}

/* Item de mensagem */
.bia-message-wrapper {
    display: flex;
    margin-bottom: 18px;
    gap: 8px;
    animation: biaMsgAppear .25s ease;
}
.bia-message {
    max-width: 75%;
    padding: 10px 14px;
    border-radius: 12px;
}

/* Avatar da IA nas mensagens */
.bia-avatar-msg {
    width: 52px;            /* maior = mais rosto visível */
    height: 52px;
    border-radius: 50%;
    object-fit: cover;      /* recorta perfeitamente */
    object-position: top;   /* foco no rosto */
    border: 3px solid #9b32a7; /* moldura mais forte */
    box-shadow: 0 3px 10px rgba(78,115,223,0.25); /* destaque */
    background-color: #fff; /* garante borda perfeita */
}


/* Estilos de mensagens */
.bia-user-msg .bia-message {
    background: #4e73df;
    color: white;
    margin-left: auto;
}

.bia-ai-msg .bia-message {
    background: white;
    border: 1px solid #ddd;
    color: #333;
}
.bia-avatar-header{
    object-fit: cover;
    object-position: center;
}

/* INPUT */
.bia-chat-input {
    display: flex;
    padding: 10px;
    background: white;
    border-top: 1px solid #ddd;
    gap: 8px;
}
.bia-chat-input input {
    flex: 1;
    border-radius: 8px;
    border: 1px solid #ccc;
    padding: 10px;
}
.bia-chat-input button {
    background: #4e73df;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0 14px;
    cursor: pointer;
}
.bia-new-chat {
    padding: 12px;
    cursor: pointer;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.bia-new-chat:hover {
    background: rgba(255,255,255,0.1);
}

.bia-hint {
    position: absolute;
    right: 70px;
    bottom: 20px;
    background: #ffffff;
    padding: 8px 14px;
    border-radius: 12px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.15);
    font-size: 14px;
    color: #333;
    opacity: 0;
    pointer-events: none;
    transition: all .3s ease;
    white-space: nowrap;
}

.bia-hint.show {
    opacity: 1;
    right: 80px;
}
.bia-hint::after {
    content: "";
    position: absolute;
    right: -6px;
    top: 50%;
    transform: translateY(-50%);
    border-left: 6px solid #fff;
    border-top: 6px solid transparent;
    border-bottom: 6px solid transparent;
}
/* Ajustes para mobile */
@media (max-width: 768px) {
    #bia-chat-window {
        width: 90%;        /* ocupa quase toda a tela */
        height: 80%;       /* um pouco menor para caber */
        bottom: 70px;      /* ajustar distância do botão */
        right: 5%;         /* centraliza mais ou deixa na margem */
        border-radius: 12px;
    }

    #bia-floating-btn {
        width: 55px;
        height: 55px;
        bottom: 15px;
        right: 15px;
    }

    .bia-floating-avatar {
        width: 45px;
        height: 45px;
    }

    .bia-avatar-header,
    .bia-avatar-msg {
        width: 42px;
        height: 42px;
    }

    .bia-chat-header {
        height: 55px;
        padding: 8px 12px;
    }

    .bia-header-text {
        font-size: 12px;
        line-height: 14px;
    }

    .bia-chat-input input {
        padding: 8px;
    }

    .bia-chat-input button {
        padding: 0 10px;
    }

    .bia-message-wrapper {
        gap: 6px;
    }

    .bia-message {
        padding: 8px 10px;
        border-radius: 10px;
    }
}

/* Estilo para o Negrito */
.bia-message strong {
    font-weight: 700;
    color: #2c3e50; /* Ou uma cor de destaque levemente mais escura */
}

/* Estilo para Listas (Bullet Points) */
.bia-message ul {
    margin: 5px 0;
    padding-left: 20px; /* Espaço para a bolinha */
    list-style-type: disc;
}

.bia-message li {
    margin-bottom: 4px;
    line-height: 1.4;
}

/* Ajuste para garantir que parágrafos fiquem legíveis */
.bia-message {
    line-height: 1.5;
    /* white-space: pre-wrap; <- REMOVER se existir, pois agora controlamos as quebras com <br> e <ul> */
}
/* --- ADICIONAR NO SEU CSS EXISTENTE --- */

/* Estado Maximizado */
.bia-maximized {
    width: 100% !important;
    height: 100% !important;
    bottom: 0 !important;
    right: 0 !important;
    border-radius: 0 !important;
}

/* Ícone de maximizar (ajuste de cursor) */
.bia-maximize-btn {
    font-size: 18px;
    cursor: pointer;
    margin-right: 10px; /* Espaço entre o maximizar e o minimizar */
}

/* Container de botões de sugestão (<option>) */
.bia-options-container {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
    padding-left: 5px;
}

/* Estilo do Botão de Sugestão */
.bia-suggestion-chip {
    background-color: #e8f0fe;
    color: #1967d2;
    border: 1px solid #d2e3fc;
    border-radius: 16px;
    padding: 6px 14px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
}

.bia-suggestion-chip:hover {
    background-color: #d2e3fc;
    transform: translateY(-1px);
}

.bia-suggestion-chip:active {
    transform: translateY(1px);
}
/* Classe base para o dropdown */
.dropdown-custom22 {
  width: 400px;
  max-width: 450px;
  max-height: 700px;
  overflow-y: auto;
  border-radius: 5px;
}

/* Ajustes para telas menores (mobile) */
@media (max-width: 576px) {
  .dropdown-custom22 {
    width: 100%;          /* ocupa toda a largura da tela */
    max-width: 100%;      /* remove limite de largura */
    max-height: 300px;    /* reduz altura para caber melhor */
    border-radius: 0;     /* opcional: remove arredondamento */
  }
}

.gender-card {
    border: 1px solid #dee2e6;
}

.gender-card.selected {
    border-color: #0d6efd !important; /* Cor primária do Bootstrap */
    background-color: #eaf3ff;
    box-shadow: 0 0 5px rgba(13, 110, 253, 0.5);
}

/* Spinner Moderno */
.modern-loader {
    width: 80px;
    height: 80px;
    border: 5px solid rgba(0, 123, 255, 0.2);
    border-left-color: #007bff;
    border-radius: 50%;
    display: inline-block;
    animation: premium-spin 1s linear infinite;
}

@keyframes premium-spin {
    to { transform: rotate(360deg); }
}

.bounce-in {
    animation: bounceIn 0.5s ease;
}

@keyframes bounceIn {
    0% { opacity: 0; transform: scale(.3); }
    50% { opacity: 1; transform: scale(1.05); }
    70% { transform: scale(.9); }
    100% { transform: scale(1); }
}

   </style>
    <!--end::Primary Meta Tags-->
    <!--begin::Fonts-->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css"
      integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q="
      crossorigin="anonymous"
    />
    <!--end::Fonts-->
    <!--begin::Third Party Plugin(OverlayScrollbars)-->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css"
      integrity="sha256-tZHrRjVqNSRyWg2wbppGnT833E/Ys0DHWGwT04GiqQg="
      crossorigin="anonymous"
    />
    <!--end::Third Party Plugin(OverlayScrollbars)-->
    <!--begin::Third Party Plugin(Bootstrap Icons)-->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
      integrity="sha256-9kPW/n5nn53j4WMRYAxe9c1rCY96Oogo/MKSVdKzPmI="
      crossorigin="anonymous"
    />

    <!--end::Third Party Plugin(Bootstrap Icons)-->
    <!--begin::Required Plugin(AdminLTE)-->
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../template/dist/css/adminlte.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/rowgroup/1.4.1/css/rowGroup.dataTables.min.css">
     <script src="https://cdn.onesignal.com/sdks/OneSignalSDK.js" async=""></script>
     <link href="https://cdnjs.cloudflare.com/ajax/libs/gridstack.js/7.2.3/gridstack.min.css" rel="stylesheet" />
     <link rel="stylesheet" href="/template/dist/css/erp-design-system.css">
     
    <!--end::Required Plugin(AdminLTE)-->

    <style>
    .grid-stack-item-content {
        background: #fff;
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        display: flex;
        flex-direction: column;
    }
    .widget-header {
        padding: 10px;
        cursor: move; /* Indica que pode arrastar */
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .widget-body {
        flex-grow: 1;
        padding: 10px;
        overflow: hidden;
        position: relative;
    }
    .btn-remove-widget { color: #dc3545; cursor: pointer; }
    .grid-stack { background: #f4f6f9; min-height: 400px; }
</style>
  </head>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
  <div id="loading-overlay">
  <div class="loading-content">
    <div class="logo-wrapper">
      <img src="/template/dist/assets/img/logo_login.png" alt="Logo" class="logo">
    </div>
    <div><div class="spinner"></div></div>
  </div>
</div>

<!--begin::App Wrapper-->
    <div class="app-wrapper">
