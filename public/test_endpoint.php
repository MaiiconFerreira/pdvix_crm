<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Testador de Requisições</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    input, button, textarea, select { padding: 10px; margin: 5px 0; font-size: 16px; width: 100%; }
    textarea { height: 120px; }
    .par { display: flex; gap: 10px; }
    .par input { flex: 1; }
    .lista-pares { margin: 10px 0; }
    .item-par { background: #fff; padding: 10px; border: 1px solid #ccc; margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center; }
    .item-par span { word-break: break-all; }
    .item-par button { padding: 5px 10px; font-size: 14px; }
    pre { background: #eee; padding: 10px; }
  </style>
</head>
<body>

  <h2>Testador de Requisições</h2>

  <label>URL do endpoint:</label>
  <input type="text" id="url" placeholder="http://localhost/seu-endpoint.php">

  <label>Método:</label>
  <select id="metodo">
    <option value="POST" selected>POST</option>
    <option value="GET">GET</option>
  </select>

  <h3>Adicionar dados:</h3>
  <div class="par">
    <input type="text" id="chave" placeholder="Chave">
    <input type="text" id="valor" placeholder="Valor">
  </div>
  <button onclick="adicionarPar()">Adicionar ao JSON</button>
  <button onclick="resetarJSON()" style="background: #f44336; color: white;">Resetar JSON</button>

  <div class="lista-pares" id="listaPares"></div>

  <label>Corpo da requisição (JSON):</label>
  <textarea id="json" readonly>{}</textarea>

  <button onclick="enviarRequisicao()">Enviar Requisição</button>

  <h3>Resposta:</h3>
  <pre id="resposta">A resposta aparecerá aqui...</pre>

  <script>
    let dadosJson = {};

    function atualizarJSON() {
      document.getElementById("json").value = JSON.stringify(dadosJson, null, 2);
      atualizarListaPares();
    }

    function adicionarPar() {
      const chave = document.getElementById("chave").value.trim();
      const valor = document.getElementById("valor").value.trim();

      if (!chave) {
        alert("Informe a chave.");
        return;
      }

      dadosJson[chave] = valor;
      document.getElementById("chave").value = '';
      document.getElementById("valor").value = '';
      atualizarJSON();
    }

    function removerPar(chave) {
      delete dadosJson[chave];
      atualizarJSON();
    }

    function resetarJSON() {
      if (confirm("Tem certeza que deseja apagar todos os dados?")) {
        dadosJson = {};
        atualizarJSON();
      }
    }

    function atualizarListaPares() {
      const lista = document.getElementById("listaPares");
      lista.innerHTML = '';

      Object.entries(dadosJson).forEach(([chave, valor]) => {
        const div = document.createElement("div");
        div.className = "item-par";
        div.innerHTML = `<span><strong>${chave}</strong>: ${valor}</span> <button onclick="removerPar('${chave}')">Remover</button>`;
        lista.appendChild(div);
      });
    }

    async function enviarRequisicao() {
      const url = document.getElementById("url").value;
      const metodo = document.getElementById("metodo").value;
      const respostaElemento = document.getElementById("resposta");

      try {
        let finalUrl = url;
        let fetchOptions = { method: metodo, headers: {} };

        if (metodo === "POST") {
          fetchOptions.headers["Content-Type"] = "application/json";
          fetchOptions.body = JSON.stringify(dadosJson);
        } else if (metodo === "GET") {
          const params = new URLSearchParams(dadosJson).toString();
          finalUrl += (url.includes("?") ? "&" : "?") + params;
        }

        const resposta = await fetch(finalUrl, fetchOptions);
        const texto = await resposta.text();
        respostaElemento.textContent = texto;
      } catch (erro) {
        respostaElemento.textContent = "Erro: " + erro.message;
      }
    }
  </script>

</body>
</html>
