<!doctype html>
<html lang="pt-BR">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>Login | PDV + CRM</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="author" content="Maicon Ferreira" />
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#13164a">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous" />
  <link rel="stylesheet" href="template/dist/css/adminlte.css" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      min-height: 100vh; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      font-family: 'Source Sans 3', sans-serif;
      background: linear-gradient(145deg, #000000 0%, #13164a 55%, #0c85a3 100%);
      position: relative; overflow: hidden;
    }
    body::before, body::after {
      content: ''; position: fixed; border-radius: 50%; opacity: .06; pointer-events: none;
    }
    body::before { width:600px; height:600px; background:#0c85a3; top:-200px; right:-200px; }
    body::after  { width:500px; height:500px; background:#13164a; bottom:-200px; left:-150px; }
    #loading-overlay {
      position:fixed; inset:0; background:#080a1e; z-index:9999;
      display:flex; align-items:center; justify-content:center;
      transition: opacity .5s ease;
    }
    .loading-content { display:flex; flex-direction:column; align-items:center; gap:24px; }
    .spinner {
      width:48px; height:48px;
      border:5px solid rgba(255,255,255,.15); border-top-color:#0c85a3;
      border-radius:50%; animation: spin .9s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .login-wrapper {
      width:100%; max-width:400px; padding:0 20px;
      display:flex; flex-direction:column; align-items:center; gap:28px;
      position:relative; z-index:1;
    }
    .logo-area img { width:160px; filter: drop-shadow(0 4px 24px rgba(12,133,163,.45)); animation: fadeDown .7s ease both; }
    @keyframes fadeDown { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
    .login-card {
      width:100%; background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.10);
      border-radius:16px; padding:36px 32px; backdrop-filter: blur(20px);
      box-shadow: 0 24px 64px rgba(0,0,0,.5); animation: fadeUp .6s .15s ease both;
    }
    @keyframes fadeUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
    .login-card h2 { font-size:1.1rem; font-weight:600; color:rgba(255,255,255,.7); text-align:center; margin-bottom:28px; letter-spacing:.3px; }
    .field { position:relative; margin-bottom:16px; }
    .field input {
      width:100%; padding:14px 44px 14px 16px; background: rgba(255,255,255,.07);
      border:1px solid rgba(255,255,255,.14); border-radius:10px;
      color:#fff; font-size:1rem; outline:none;
      transition: border-color .25s, background .25s, box-shadow .25s;
    }
    .field input::placeholder { color:rgba(255,255,255,.35); }
    .field input:focus { border-color:#0c85a3; background: rgba(12,133,163,.12); box-shadow: 0 0 0 3px rgba(12,133,163,.20); }
    .field .icon { position:absolute; right:14px; top:50%; transform:translateY(-50%); color:rgba(255,255,255,.35); font-size:1.05rem; pointer-events:none; }
    #step-senha { display:none; }
    .btn-login {
      width:100%; padding:14px; background: linear-gradient(135deg, #0c85a3, #13164a);
      border:none; border-radius:10px; color:#fff; font-size:1rem; font-weight:600;
      letter-spacing:.5px; cursor:pointer; transition: opacity .2s, transform .15s; margin-top:6px;
    }
    .btn-login:hover { opacity:.88; } .btn-login:active { transform:scale(.98); } .btn-login:disabled { opacity:.55; cursor:not-allowed; }
    .btn-login .btn-spinner {
      display:inline-block; width:18px; height:18px;
      border:3px solid rgba(255,255,255,.3); border-top-color:#fff;
      border-radius:50%; animation: spin .8s linear infinite; vertical-align:middle; margin-right:6px;
    }
    .error-box {
      display:none; background: rgba(220,53,69,.18); border:1px solid rgba(220,53,69,.4);
      border-radius:8px; padding:10px 14px; color:#ff8a8a; font-size:.9rem; margin-bottom:16px; text-align:center;
    }
    .forgot-link { display:block; text-align:center; margin-top:18px; color:rgba(255,255,255,.4); font-size:.85rem; text-decoration:none; transition: color .2s; }
    .forgot-link:hover { color:#0c85a3; }
    .step-indicator { display:flex; gap:6px; justify-content:center; margin-bottom:20px; }
    .step-dot { width:8px; height:8px; border-radius:50%; background: rgba(255,255,255,.2); transition: background .3s, transform .3s; }
    .step-dot.active { background:#0c85a3; transform:scale(1.25); }
    .login-footer { text-align:center; color:rgba(255,255,255,.22); font-size:.78rem; line-height:1.6; animation: fadeUp .6s .3s ease both; }
  </style>
</head>
<body>
  <div id="loading-overlay">
    <div class="loading-content">
      <img src="template/dist/assets/img/logo_login.png" alt="Logo" width="130" style="opacity:.85;">
      <div class="spinner"></div>
    </div>
  </div>

  <div class="login-wrapper">
    <div class="logo-area">
      <img src="template/dist/assets/img/logo_login.png" alt="Logo">
    </div>

    <div class="login-card">
      <h2>Acesse sua conta</h2>

      <div class="step-indicator">
        <div class="step-dot active" id="dot-1"></div>
        <div class="step-dot"        id="dot-2"></div>
      </div>

      <div class="error-box" id="error-box">
        <i class="bi bi-exclamation-circle me-1"></i>
        <span id="error-msg"></span>
      </div>

      <form id="loginForm" autocomplete="off">
        <!-- name="login" → AuthController valida campo 'login' -->
        <div class="field" id="step-login">
          <input type="text" id="inputLogin" name="login" placeholder="Login" autocomplete="username" />
          <span class="icon"><i class="bi bi-person-fill"></i></span>
        </div>

        <div class="field" id="step-senha">
          <input type="password" id="inputSenha" name="password" placeholder="Senha" autocomplete="current-password" />
          <span class="icon"><i class="bi bi-lock-fill"></i></span>
        </div>

        <button type="submit" class="btn-login" id="btnLogin">
          <span id="btnText">Continuar</span>
        </button>
      </form>

      <a href="/esqueci_minha_senha" class="forgot-link">
        <i class="bi bi-key me-1"></i>Esqueci minha senha
      </a>
    </div>

    <div class="login-footer">
      <div>v<?php echo defined('APPLICATION_VERSION') ? APPLICATION_VERSION : '1.0.0'; ?></div>
      <div>Desenvolvido por Maicon Ferreira</div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
  <script src="template/dist/js/adminlte.js"></script>

  <script>
    const form       = document.getElementById('loginForm');
    const inputLogin = document.getElementById('inputLogin');
    const inputSenha = document.getElementById('inputSenha');
    const stepSenha  = document.getElementById('step-senha');
    const btnLogin   = document.getElementById('btnLogin');
    const btnText    = document.getElementById('btnText');
    const errorBox   = document.getElementById('error-box');
    const errorMsg   = document.getElementById('error-msg');
    const dot1       = document.getElementById('dot-1');
    const dot2       = document.getElementById('dot-2');

    let etapa = 1;

    window.addEventListener('load', () => {
      const overlay = document.getElementById('loading-overlay');
      overlay.style.opacity = '0';
      setTimeout(() => overlay.style.display = 'none', 500);
      inputLogin.focus();
    });

    function showError(msg) { errorMsg.textContent = msg; errorBox.style.display = 'block'; }
    function hideError()    { errorBox.style.display = 'none'; }

    function setLoading(on) {
      btnLogin.disabled = on;
      btnText.innerHTML = on
        ? '<span class="btn-spinner"></span>Aguarde...'
        : (etapa === 1 ? 'Continuar' : 'Entrar');
    }

    function avancarParaSenha() {
      if (!inputLogin.value.trim()) { showError('Informe seu login para continuar.'); inputLogin.focus(); return; }
      hideError(); etapa = 2;
      stepSenha.style.display = 'block';
      stepSenha.style.animation = 'fadeDown .35s ease both';
      dot1.classList.remove('active');
      dot2.classList.add('active');
      btnText.textContent = 'Entrar';
      inputSenha.focus();
    }

    inputLogin.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); if (etapa === 1) avancarParaSenha(); }
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (etapa === 1) { avancarParaSenha(); return; }
      if (!inputSenha.value) { showError('Informe sua senha.'); inputSenha.focus(); return; }

      hideError();
      setLoading(true);

      let uuid = localStorage.getItem('uuid_v4');
      if (!uuid) { uuid = gerarUUID(); localStorage.setItem('uuid_v4', uuid); }

      const formData = new FormData(form);
      formData.append('uuid_v4', uuid);

      try {
        const res  = await fetch('/auth', { method: 'POST', body: formData });
        const json = await res.json();

        if (json.status === 'success') { window.location.href = '/'; return; }

        showError(json.message || 'Credenciais inválidas.');
        setLoading(false);
        inputSenha.value = '';
        inputSenha.focus();
      } catch {
        showError('Erro de conexão. Tente novamente.');
        setLoading(false);
      }
    });

    function gerarUUID() {
      return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = Math.random() * 16 | 0;
        return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
      });
    }

    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('service-worker.js').catch(e => console.warn('SW:', e));
    }
  </script>
</body>
</html>