class ModalManager {
    constructor() {
        this.modalId = 'dynamicModal';
        this.modalIdAlert = 'dynamicModalAlert';
    }

    /**
     * Cria o modal no DOM caso não exista
     * @param {string} size - sm, md ou lg
     */
    createModalStructure(size = "md", close = true, modalIdContainer = null) {

        const sizeClass = {
            sm: "modal-sm",
            md: "",
            lg: "modal-lg",
            xl: "modal-xl"
        }[size] || "";

        if (document.getElementById(modalIdContainer ? modalIdContainer : this.modalId)){
          document.getElementById(modalIdContainer ? modalIdContainer : this.modalId).children[0].className = `modal-dialog ${sizeClass}`;
          return;
        }

        const modalHtml = `
        <div class="modal fade" id="${modalIdContainer ? modalIdContainer : this.modalId}" tabindex="-1">
            <div class="modal-dialog ${sizeClass}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"></h5>
                        ${ (close === true) ? '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' : ''}
                    </div>
                    <div class="modal-body"></div>
                    <div class="modal-footer"></div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    /**
     * Cria campos dinamicamente (reutilizado em forms normais e abas)
     */
    createFields(inputs, form, modalEl, optionsAjaxSelect2 = {}) {
        inputs.forEach(input => {
            let field;

            if (input.type === 'textarea') {
                field = document.createElement('textarea');
                field.className = 'form-control';
                if (input.disabled) field.disabled = input.disabled;
            } else if (input.type === 'select2') {
                field = document.createElement('select');
                field.name = input.name || '';
                field.className = 'form-control';


                // 💡 ADICIONAR SUPORTE MULTIPLE AQUI
                  if (input.multiple) {
                      field.setAttribute('multiple', 'multiple');
                      // O Select2 usa o atributo 'name' para enviar múltiplos valores,
                      // que deve terminar em '[]' se o backend for PHP, mas deixaremos a cargo do 'input.name'
                      // pois o FormData do JS já trata múltiplos valores se o 'multiple' estiver presente.
                  }

                  const placeholder = input.placeholder || 'Selecione...';

                  // 💡 O Option de Placeholder só é necessário/útil para Single Selection
                  if (!input.multiple) {
                      const option = document.createElement('option');
                      option.value = '';
                      option.textContent = placeholder;
                      field.appendChild(option);
                  }

                setTimeout(() => {
                    $(field).select2({
                        width: "100%",
                        placeholder: placeholder,
                        dropdownParent: $(modalEl),
                        ajax: {
                            url: input.fetchURI,
                            dataType: "json",
                            delay: 250,
                            processResults: function (response) {
                                  if (response.status === "success" && Array.isArray(response.data)) {
                                      let results = response.data.map(item => {
                                          if (typeof input.processItem === "function") {
                                              return input.processItem(item);
                                          }
                                          return {
                                              id: item.id,
                                              text: item.razao_social || item.nome || "Sem nome"
                                          };
                                      });
                                    //console.log(input);
                                    // 🔹 Aqui seleciona o item se o id bater
                                    if (input.selectedId) {
                                        let selected = results.find(r => r.id == input.selectedId);
                                        if (selected) {
                                            $(field).append(new Option(selected.text, selected.id, true, true)).trigger("change");
                                        }
                                    }

                                    return { results };
                                }
                                return { results: [] };
                            },
                            ...optionsAjaxSelect2
                        }
                    });

                    if(typeof input.onchange === "function"){
                      $(field).on("change", input.onchange);
                    }
                    if(typeof input.onselect === "function"){
                      $(field).on("select2:select", input.onselect);
                    }

                    if(typeof input.disabled === "boolean"){
                      $(field).prop("disabled", true);
                    }

                    if(typeof input.autoload !== "undefined"){
                      $(field).select2('open');
                      $(field).select2('close');
                    }
                }, 150);
            }  else if (input.type === 'select') {
            field = document.createElement('select');
            field.name = input.name || '';
            field.className = 'form-control';

            // Placeholder padrão
            const placeholder = input.placeholder || 'Selecione...';
            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = placeholder;
            field.appendChild(placeholderOption);

            // Adiciona options a partir de input.values
            if (input.values && typeof input.values === 'object') {
                Object.entries(input.values).forEach(([key, val]) => {
                    const option = document.createElement('option');
                    option.value = key;
                    option.textContent = val;

                    // Marca como selecionado se input.selectedId bater
                    if (input.selectedId && input.selectedId == key) {
                        option.selected = true;
                    }



                    field.appendChild(option);
                });
            }
          }else if (input.type === 'file') {
              // Criar wrapper para organizar botão + input file
              field = document.createElement('div');
              field.className = 'd-flex flex-column gap-2';

              // 🔹 Botão "Visualizar" se tiver base64
              if (input.base64) {
                  const btnView = document.createElement('button');
                  btnView.type = 'button';
                  btnView.textContent = 'Visualizar documento anexado';
                  btnView.className = 'btn btn-sm btn-primary';

                  btnView.addEventListener('click', () => {
                      try {
                          const byteCharacters = atob(input.base64);
                          const byteNumbers = new Array(byteCharacters.length);
                          for (let i = 0; i < byteCharacters.length; i++) {
                              byteNumbers[i] = byteCharacters.charCodeAt(i);
                          }
                          const byteArray = new Uint8Array(byteNumbers);

                          const blob = new Blob([byteArray], { type: input.mimeType || 'application/octet-stream' });
                          const url = URL.createObjectURL(blob);
                          window.open(url, '_blank');
                      } catch (e) {
                          console.error("Erro ao abrir arquivo base64:", e);
                      }
                  });

                  field.appendChild(btnView);
              }

              // 🔹 Input file
              const fileInput = document.createElement('input');
              fileInput.type = 'file';
              fileInput.name = input.name || '';
              fileInput.className = input.className || 'form-control';
              field.appendChild(fileInput);

              // 🔹 Input hidden p/ base64
              const hiddenInput = document.createElement('input');
              hiddenInput.type = 'hidden';
              hiddenInput.name = input.name + "_base64"; // ex: "arquivo_base64"
              field.appendChild(hiddenInput);

              // 🔹 Preview da imagem
              const preview = document.createElement('img');
              preview.style.maxWidth = '200px';
              preview.style.maxHeight = '200px';
              preview.style.marginTop = '5px';
              field.appendChild(preview);

              // 🔹 Evento para gerar base64 e mostrar preview
              fileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) {
                    hiddenInput.value = ''; // envia vazio
                    preview.src = '';       // limpa preview
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(event) {
                    const dataUrl = event.target.result;
                    hiddenInput.value = dataUrl.split(',')[1]; // base64 puro
                    if (file.type.startsWith('image/')) {
                        preview.src = dataUrl; // mostra preview
                    } else {
                        preview.src = ''; // limpa preview se não for imagem
                    }
                };
                reader.readAsDataURL(file);
            });

          }
          else if (input.type === 'signature') {
              // wrapper
              field = document.createElement('div');
              field.className = 'd-flex flex-column gap-2';

              // canvas
              const canvas = document.createElement('canvas');
              canvas.width = input.width || 400;
              canvas.height = input.height || 200;
              canvas.style.border = '1px solid #ccc';
              canvas.style.borderRadius = '4px';
              canvas.style.cursor = 'crosshair';

              // hidden input p/ salvar assinatura em base64
              const hiddenInput = document.createElement('input');
              hiddenInput.type = 'hidden';
              hiddenInput.name = input.name || 'signature';

              // botões auxiliares
              const btnClear = document.createElement('button');
              btnClear.type = 'button';
              btnClear.textContent = 'Limpar';
              btnClear.className = 'btn btn-sm btn-secondary mt-1';

              const ctx = canvas.getContext('2d');
              let drawing = false;

              const startDrawing = (e) => {
                  drawing = true;
                  ctx.beginPath();
                  ctx.moveTo(
                      e.offsetX ?? e.touches[0].clientX - canvas.getBoundingClientRect().left,
                      e.offsetY ?? e.touches[0].clientY - canvas.getBoundingClientRect().top
                  );
              };

              const draw = (e) => {
                  if (!drawing) return;
                  ctx.lineWidth = 2;
                  ctx.lineCap = "round";
                  ctx.strokeStyle = "#000";

                  ctx.lineTo(
                      e.offsetX ?? e.touches[0].clientX - canvas.getBoundingClientRect().left,
                      e.offsetY ?? e.touches[0].clientY - canvas.getBoundingClientRect().top
                  );
                  ctx.stroke();
              };

              const stopDrawing = () => {
                  drawing = false;
                  hiddenInput.value = canvas.toDataURL("image/png").split(",")[1]; // só base64 sem prefixo
              };

              // eventos mouse
              canvas.addEventListener("mousedown", startDrawing);
              canvas.addEventListener("mousemove", draw);
              canvas.addEventListener("mouseup", stopDrawing);
              canvas.addEventListener("mouseleave", stopDrawing);

              // eventos touch (mobile)
              canvas.addEventListener("touchstart", startDrawing);
              canvas.addEventListener("touchmove", (e) => {
                  e.preventDefault(); // evita rolagem
                  draw(e);
              });
              canvas.addEventListener("touchend", stopDrawing);

              // limpar assinatura
              btnClear.addEventListener("click", () => {
                  ctx.clearRect(0, 0, canvas.width, canvas.height);
                  hiddenInput.value = "";
              });

              field.appendChild(canvas);
              field.appendChild(btnClear);
              field.appendChild(hiddenInput);
          }
          else if (input.type === 'map') {
              const wrapper = document.createElement('div');
              wrapper.classList.add('map-wrapper');

              // Search input
              const search = document.createElement('input');
              search.id = `search_${input.nameLat}`;
              search.placeholder = 'Pesquisar loja ou endereço';
              search.style.cssText = "width:100%;padding:8px;";
              search.value = input.enderecoSelected || '';
              wrapper.appendChild(search);

              // Results list
              const results = document.createElement('ul');
              results.id = `results_${input.nameLat}`;
              results.style.cssText = "list-style:none;padding:0;border:1px solid #ccc;display:none;position:absolute;background:#fff;z-index:1000;";
              wrapper.appendChild(results);

              // Map container
              const mapDiv = document.createElement('div');
              mapDiv.id = `map_${input.nameLat}`;
              mapDiv.style.height = '400px';
              mapDiv.style.marginTop = '5px';
              wrapper.appendChild(mapDiv);

              // Hidden inputs para coordenadas
              const latInput = document.createElement('input');
              latInput.type = 'hidden';
              latInput.name = input.nameLat || 'latitude';
              latInput.id = `lat_input_${input.nameLat}`;
              wrapper.appendChild(latInput);

              const lngInput = document.createElement('input');
              lngInput.type = 'hidden';
              lngInput.name = input.nameLng || 'longitude';
              lngInput.id = `lng_input_${input.nameLat}`;
              wrapper.appendChild(lngInput);

              // Input para tolerância
              const tolWrapper = document.createElement('div');
              tolWrapper.style.marginTop = '10px';
              const tolLabel = document.createElement('label');
              tolLabel.textContent = 'Tolerância (metros):';
              tolWrapper.appendChild(tolLabel);

              const tolInput = document.createElement('input');
              tolInput.type = 'number';
              tolInput.min = '0';
              tolInput.step = '1';
              tolInput.name = 'tolerancia_metros';
              tolInput.value = input.toleranciaSelected ?? '300'; // padrão 300 metros
              tolInput.style.cssText = "width:100%;padding:6px;margin-top:3px;";
              tolWrapper.appendChild(tolInput);

              wrapper.appendChild(tolWrapper);

              field = wrapper;

              field.initMap = function() {
                    // Se o mapa já existe, não inicializa novamente
                    if (field.map) return;

                    const initialLat = input.latitudeSelected ?? -15.7797;
                    const initialLng = input.longitudeSelected ?? -47.9297;
                    const initialTol = parseInt(input.toleranciaSelected ?? tolInput.value);

                    // Inicializa o mapa
                    const map = L.map(mapDiv.id).setView([initialLat, initialLng], 16);
                    field.map = map; // armazenar referência para invalidateSize

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap'
                    }).addTo(map);

                    const marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);
                    field.marker = marker; // armazenar referência

                    // círculo de tolerância
                    const toleranceCircle = L.circle([initialLat, initialLng], {
                        radius: initialTol,
                        color: 'blue',
                        fillColor: '#cce5ff',
                        fillOpacity: 0.3
                    }).addTo(map);
                    field.toleranceCircle = toleranceCircle; // armazenar referência

                    function setFields(lat, lng) {
                        latInput.value = lat;
                        lngInput.value = lng;
                        toleranceCircle.setLatLng([lat, lng]);
                    }
                    setFields(initialLat, initialLng);

                    // atualizar círculo quando alterar tolerância
                    tolInput.addEventListener('input', function() {
                        const val = parseInt(this.value) || 0;
                        toleranceCircle.setRadius(val);
                    });

                    marker.on('dragend', function() {
                        const pos = marker.getLatLng();
                        setFields(pos.lat, pos.lng);
                    });

                    // Search autocomplete
                    let timer = null;
                    const API_KEY = "pk.a3ae2fea50b8a014260bdbb6efecd854";

                    search.addEventListener('input', function() {
                        clearTimeout(timer);
                        const q = this.value.trim();
                        if (!q) {
                            results.style.display = "none";
                            results.innerHTML = "";
                            return;
                        }

                        timer = setTimeout(function() {
                            fetch(`https://us1.locationiq.com/v1/autocomplete.php?key=${API_KEY}&q=${encodeURIComponent(q)}&limit=5&dedupe=1`)
                                .then(r => r.json())
                                .then(data => {
                                    results.innerHTML = "";
                                    if (data && data.length > 0) {
                                        data.forEach(f => {
                                            const li = document.createElement("li");
                                            li.textContent = f.display_name;
                                            li.style.cursor = "pointer";
                                            li.style.padding = "5px";
                                            li.onclick = function() {
                                                const lat = parseFloat(f.lat), lng = parseFloat(f.lon);
                                                map.setView([lat, lng], 16);
                                                marker.setLatLng([lat, lng]);
                                                setFields(lat, lng);
                                                search.value = li.textContent;
                                                results.style.display = "none";
                                                results.innerHTML = "";
                                            };
                                            results.appendChild(li);
                                        });
                                        results.style.display = "block";
                                    } else {
                                        results.style.display = "none";
                                    }
                                });
                        }, 400);
                    });
                }

              // Evento para garantir renderização correta do mapa ao abrir modal
              modalEl.addEventListener('shown.bs.modal', function () {
                  if (field.initMap) field.initMap();
                  // Força recalcular tamanho do mapa após abertura do modal
                  setTimeout(() => {
                      if (field.map) field.map.invalidateSize();
                  }, 1000);
              });

          }else if (input.type === 'camera') {
    // Wrapper
    field = document.createElement('div');
    field.className = 'd-flex flex-column gap-3';

    // 🔹 Informativo com ícones
    const info = document.createElement('div');
    info.innerHTML = `
        <p><b>Antes de continuar:</b></p>
        <p style="text-align:center;width:100%">
        <svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
<!-- Cabeça -->
<circle cx="100" cy="80" r="40" fill="#fcd19c" stroke="#333" stroke-width="4"/>

<!-- Chapéu -->
<rect x="60" y="30" width="80" height="20" rx="5" fill="#333"/>
<rect x="50" y="50" width="100" height="10" rx="5" fill="#333"/>

<!-- Óculos -->
<circle cx="75" cy="80" r="10" stroke="#333" stroke-width="4" fill="none"/>
<circle cx="125" cy="80" r="10" stroke="#333" stroke-width="4" fill="none"/>
<line x1="85" y1="80" x2="115" y2="80" stroke="#333" stroke-width="4"/>

<!-- X de remoção -->
<line x1="50" y1="50" x2="150" y2="150" stroke="#e63946" stroke-width="10" stroke-linecap="round"/>
<line x1="150" y1="50" x2="50" y2="150" stroke="#e63946" stroke-width="10" stroke-linecap="round"/>

</svg>
        </p>
        <p>Mantenha-se em um fundo neutro e bem iluminado.</p>
    `;
    field.appendChild(info);

    // Botão para iniciar câmera
    const btnStart = document.createElement('button');
    btnStart.type = 'button';
    btnStart.textContent = 'Iniciar Câmera';
    btnStart.className = 'btn btn-primary';
    field.appendChild(btnStart);

    // Video (maior para celular)
    const video = document.createElement('video');
    video.autoplay = true;
    video.playsInline = true;
    video.style.width = '100%';     // ocupa toda largura disponível
    video.style.maxWidth = '500px'; // limite para desktop
    video.style.borderRadius = '8px';
    video.style.display = 'none';
    field.appendChild(video);

    // Canvas oculto (usado só na captura)
    const canvas = document.createElement('canvas');
    canvas.style.display = 'none';
    field.appendChild(canvas);

    // Botão Capturar
    const btnCapture = document.createElement('button');
    btnCapture.type = 'button';
    btnCapture.textContent = 'Capturar Foto';
    btnCapture.className = 'btn btn-success mt-2';
    btnCapture.style.display = 'none';
    field.appendChild(btnCapture);

    // Preview
    const preview = document.createElement('img');
    preview.style.maxWidth = '100%';
    preview.style.marginTop = '10px';
    preview.style.border = '1px solid #ccc';
    preview.style.borderRadius = '8px';
    field.appendChild(preview);

    // Hidden input base64
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = input.name + "_base64";
    field.appendChild(hiddenInput);

    let stream;
    btnStart.addEventListener('click', async () => {
      info.innerHTML = '';
      const constraints = { video: true };

        try {
            // Verifica se a API moderna está disponível
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                const stream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = stream;
            } else {
                // Fallback para navegadores antigos
                const getUserMedia = navigator.getUserMedia ||
                                     navigator.webkitGetUserMedia ||
                                     navigator.mozGetUserMedia ||
                                     navigator.msGetUserMedia;

                if (getUserMedia) {
                    getUserMedia.call(navigator, constraints, function(stream) {
                        video.src = window.URL.createObjectURL(stream);
                    }, function(err) {
                        throw err;
                    });
                } else {
                    throw new Error('getUserMedia não é suportado neste navegador.');
                }
            }

            video.style.display = 'block';
            btnCapture.style.display = 'inline-block';
            btnStart.style.display = 'none';

        } catch (err) {
            console.error('Erro ao acessar câmera:', err);
            alert('Não foi possível acessar a câmera.');
        }
    });

    btnCapture.addEventListener('click', () => {
        // Ajusta o canvas para proporção real do vídeo
        const ratioWidth = video.videoWidth;
        const ratioHeight = video.videoHeight;
        canvas.width = ratioWidth;
        canvas.height = ratioHeight;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, ratioWidth, ratioHeight);

        const dataUrl = canvas.toDataURL('image/png');
        preview.src = dataUrl;
        hiddenInput.value = dataUrl.split(',')[1]; // base64 puro

        // Para a câmera
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        video.style.display = 'none';
        btnCapture.style.display = 'none';
    });
} else if (input.type === 'checkbox') {
    // Cria checkbox simples com descrição abaixo
    const divCheck = document.createElement('div');
    divCheck.style.margin = '0';
    divCheck.style.padding = '0';

    field = document.createElement('input');
    field.type = 'checkbox';
    field.name = input.name || '';
    field.checked = input.checked || false;
    field.style.margin = '0 0 0 5px';
    field.style.padding = '0';
    field.className = "checkbox-custom";

    const span = document.createElement('span');
    span.textContent = input.description || '';
    span.style.display = 'block';
    span.style.fontSize = '0.875rem'; // opcional, para ficar menor
    span.style.margin = '2px 0 0 0';   // pouco espaço acima do span

    divCheck.appendChild(field);
    divCheck.appendChild(span);
    form.appendChild(divCheck);
}else if (input.type === "rating") {
  // Container visual
  const starsWrapper = document.createElement("div");
  starsWrapper.classList.add("rating-stars");

  // Hidden que vai pro form
  const hiddenInput = document.createElement("input");
  hiddenInput.type = "hidden";
  hiddenInput.name = input.name;
  hiddenInput.value = input.value || 0;

  // Cria 5 estrelas
  for (let i = 1; i <= 5; i++) {
    const star = document.createElement("span");
    star.innerHTML = "&#9733;"; // ★
    star.classList.add("star");
    if (i <= (input.value || 0)) star.classList.add("selected");

    star.addEventListener("click", () => {
      if(input.disabled) return;
      hiddenInput.value = i;
      starsWrapper.querySelectorAll(".star").forEach((s, index) => {
        s.classList.toggle("selected", index < i);
      });
    });

    starsWrapper.appendChild(star);
  }

  // junta UI + hidden dentro de um container
  const container = document.createElement("div");
  container.appendChild(starsWrapper);
  container.appendChild(hiddenInput);

  // aqui está o truque:
  // field = hiddenInput (tem type e name válidos pro form)
  // mas vamos guardar o container no ._ui pra poder renderizar
  hiddenInput._ui = container;
  field = hiddenInput;
} else if (input.type === 'date-split') {
              // Wrapper para Day/Month/Year
              field = document.createElement('div');
              field.className = 'd-flex gap-2';
              field.style.maxWidth = '300px'; // Limita a largura do grupo

              const defaultValue = input.value ? new Date(input.value) : null;

              // 🔹 Input Dia
              const dayInput = document.createElement('input');
              dayInput.type = 'number';
              dayInput.name = input.name + '_dia';
              dayInput.placeholder = 'Dia';
              dayInput.className = 'form-control text-center';
              dayInput.min = '1';
              dayInput.max = '31';
              if (defaultValue) dayInput.value = defaultValue.getDate().toString().padStart(2, '0');
              field.appendChild(dayInput);

              // 🔹 Input Mês
              const monthInput = document.createElement('input');
              monthInput.type = 'number';
              monthInput.name = input.name + '_mes';
              monthInput.placeholder = 'Mês';
              monthInput.className = 'form-control text-center';
              monthInput.min = '1';
              monthInput.max = '12';
              if (defaultValue) monthInput.value = (defaultValue.getMonth() + 1).toString().padStart(2, '0');
              field.appendChild(monthInput);

              // 🔹 Input Ano
              const yearInput = document.createElement('input');
              yearInput.type = 'number';
              yearInput.name = input.name + '_ano';
              yearInput.placeholder = 'Ano';
              yearInput.className = 'form-control text-center';
              yearInput.min = '1900';
              yearInput.max = new Date().getFullYear().toString();
              if (defaultValue) yearInput.value = defaultValue.getFullYear().toString();
              field.appendChild(yearInput);

              // Validação básica para preenchimento:
              const validateDate = () => {
                const day = dayInput.value;
                const month = monthInput.value;
                const year = yearInput.value;
                const dateString = `${year}-${month}-${day}`;
                const isValid = day && month && year && !isNaN(new Date(dateString));

                // Adiciona um atributo especial para validar no form
                if(isValid) {
                  field.setAttribute('data-date-valid', 'true');
                } else {
                  field.removeAttribute('data-date-valid');
                }

                // O form.onsubmit precisará fazer a validação e formatação final.
              };

              dayInput.addEventListener('input', validateDate);
              monthInput.addEventListener('input', validateDate);
              yearInput.addEventListener('input', validateDate);

} else if (input.type === 'gender-grid') {
              // Wrapper do grid
              field = document.createElement('div');
              field.className = 'd-flex justify-content-start gap-4';

              // Hidden input para salvar o valor selecionado
              const hiddenInput = document.createElement('input');
              hiddenInput.type = 'hidden';
              hiddenInput.name = input.name || 'genero';
              hiddenInput.value = input.value || '';
              field.appendChild(hiddenInput);

              // Opções de Gênero
              const options = [
                  { value: 'masculino', label: 'Masculino', icon: '👨' },
                  { value: 'feminino', label: 'Feminino', icon: '👩' },
              ];

              const selectGender = (selectedCard) => {
                  // Limpa a seleção de todos
                  field.querySelectorAll('.gender-card').forEach(card => card.classList.remove('selected'));
                  // Seleciona o atual
                  selectedCard.classList.add('selected');
                  // Atualiza o hidden input
                  hiddenInput.value = selectedCard.getAttribute('data-value');
              };

              options.forEach(option => {
                  const card = document.createElement('div');
                  card.className = 'gender-card border text-center p-3 rounded';
                  card.style.cssText = 'width:100px; cursor:pointer; transition: all 0.2s;';
                  card.setAttribute('data-value', option.value);
                  card.innerHTML = `
                      <div style="font-size: 30px;">${option.icon}</div>
                      <small>${option.label}</small>
                  `;

                  card.addEventListener('click', () => selectGender(card));

                  if (hiddenInput.value === option.value) {
                      card.classList.add('selected');
                  }

                  field.appendChild(card);
              });

              // Estilos CSS para o card (pode ser injetado via <style> ou classe CSS externa)
              // Aqui estamos adicionando a classe, o CSS precisará ser adicionado no projeto
              // Exemplo de CSS externo (sugestão):
              /*
              .gender-card.selected {
                  border-color: #0d6efd !important; // Cor primária do Bootstrap
                  box-shadow: 0 0 5px rgba(13, 110, 253, 0.5);
              }
              */

}else if(input.type === 'biometria'){
    field = document.createElement('div');
    // Garante centralização do wrapper
    field.style.display = 'flex';
    field.style.flexDirection = 'column';
    field.style.alignItems = 'center';
    field.style.width = '100%';

    // INSERÇÃO COM ESTILOS INLINE PARA FORÇAR O TAMANHO
    field.innerHTML = `
    <style>
  /* Força o modal a não ter paddings estranhos que atrapalhem o vídeo */
.modal-body {
    overflow-x: hidden; /* Evita scroll horizontal se algo passar */
}

/* MÁSCARA OVAL - A Lógica da Borda Gigante */
.face-oval {
    position: absolute;
    z-index: 10;
    top: 50%;
    left: 50%;
    /* Centralização exata com correção de renderização iOS */
    transform: translate(-50%, -50%) translateZ(0);
    
    /* Dimensões do "buraco" (Mais estreito para parecer mais oval) */
    width: 210px;  /* Reduzido de 190 para 170 */
    height: 300px; /* Proporção ~1.5x */
    
    /* Border-radius específico para formato de ovo/rosto */
    border-radius: 100%;
    
    /* Máscara escura ao redor */
    /* Aumentamos a opacidade para 0.8 para dar mais destaque ao centro */
    border: 2000px solid rgba(0, 0, 0, 0.8);
    
    /* Linha guia interna mais fina e elegante */
    box-shadow: inset 0 0 0 2px #0d6efd, 0 0 15px rgba(0,0,0,0.5);
    
    pointer-events: none;
    box-sizing: content-box;
}

/* Opcional: Efeito de pulsação suave na borda azul para indicar que está "escaneando" */
.face-oval {
    animation: pulse-blue 2s infinite;
}

@keyframes pulse-blue {
    0% { box-shadow: inset 0 0 0 2px #0d6efd; }
    50% { box-shadow: inset 0 0 0 4px #0d6efd; }
    100% { box-shadow: inset 0 0 0 2px #0d6efd; }
}

/* Painel de erro */
.face-error {
    color: #fff;
    background: #dc3545;
    padding: 10px;
    font-size: 14px;
    margin-top: 10px;
    border-radius: 6px;
    display: none;
    width: 280px;
    text-align: center;
}
    </style>
    <div id="container-scan" class="face-scan-wrapper" style="width: 100%; display: flex; flex-direction: column; align-items: center;">
        
        <div class="face-scan-container" style="position: relative; width: 280px; height: 380px; background: #000; overflow: hidden; border-radius: 24px; margin: 10px auto; flex-shrink: 0; border: 1px solid #333;">
            
            <video id="video-preview" autoplay muted playsinline 
                   style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); z-index: 1;">
            </video>
            
            <div class="face-oval"></div>
            
            <div id="loader-scan" style="display:none; position: absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.95); z-index: 30; flex-direction:column; align-items:center; justify-content:center;">
                <div class="spinner-ring" style="width: 50px; height: 50px; border: 5px solid #e9ecef; border-top: 5px solid #0d6efd; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                <p style="margin-top:15px; font-weight:bold; color:#0d6efd;">Processando...</p>
            </div>

        </div>

        <div id="face-error" class="face-error"></div>
    </div>

    <canvas id="canvas-capture" style="display:none;"></canvas>
    <input type="hidden" name="${input.name}_base64" id="face_data_base64">
    
    <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
    `;
} else {
                field = document.createElement('input');
                field.type = input.type || 'text';
                field.className = input.className || 'form-control';

            }

            field.name = input.name || '';
            field.value = input.value || '';

            // 🌟 NOVO CÓDIGO AQUI: Adiciona atributos personalizados se existirem
              if (input.attributesRender && typeof input.attributesRender === 'object') {
                  for (const attr in input.attributesRender) {
                      if (input.attributesRender.hasOwnProperty(attr)) {
                          // Usa setAttribute para adicionar o atributo e seu valor (pode ser vazio, como em required, ou surveyResponse)
                          field.setAttribute(attr, input.attributesRender[attr]);
                      }
                  }
              }
              // ----------------------------------------------------

            if (input.type === "hidden") {
  form.appendChild(field);
} else {
  const div = document.createElement("div");
  div.className = (input.type === 'rating') ? '' :  "mb-3";

  const label = document.createElement("label");
  label.className = "form-label";
  label.textContent = input.label || "";
  if (input.name) label.htmlFor = input.name;

  div.appendChild(label);

  // se o field tiver UI especial, usa ele
  div.appendChild(field._ui || field);

  form.appendChild(div);
}
        });
    }

    /**
     * Abre um modal para exibir informações genéricas (tabelas, mensagens longas).
     * @param {object} options - { title: string, message: string (HTML), buttonText: string, size: string }
     */
    openContentModal(options) {
        this.createModalStructure(options.size || "md", true); // Cria a estrutura, permite fechar

        const modalEl = document.getElementById(this.modalId);
        const modalObj = new bootstrap.Modal(modalEl);
        const modalTitle = modalEl.querySelector('.modal-title');
        const modalBody = modalEl.querySelector('.modal-body');
        const modalFooter = modalEl.querySelector('.modal-footer');

        // Limpa e configura o título e o corpo
        modalTitle.textContent = options.title || 'Informação';
        modalBody.innerHTML = options.message || '';

        // Limpa o rodapé e adiciona o botão de fechar
        modalFooter.innerHTML = '';
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'btn btn-secondary';
        closeBtn.setAttribute('data-bs-dismiss', 'modal');
        closeBtn.textContent = options.buttonText || 'Fechar';
        modalFooter.appendChild(closeBtn);

        modalObj.show();

        return modalObj; // Retorna o objeto modal para controle externo
    }
    /**
     * Abre modal de formulário dinâmico simples
     */
    openFormModal(options) {
        this.createModalStructure(options.size, options.close);

        const modalEl = document.getElementById(this.modalId);
        let modalObj = new bootstrap.Modal(modalEl);
        const modalTitle = modalEl.querySelector('.modal-title');
        const modalBody = modalEl.querySelector('.modal-body');
        const modalFooter = modalEl.querySelector('.modal-footer');

        // Limpa conteúdo antigo
        modalBody.innerHTML = options.body || '';
        modalFooter.innerHTML = '';
        modalTitle.textContent = options.title || 'Formulário';

        // Cria formulário
        const form = document.createElement('form');
        form.method = options.method || 'POST';
        form.action = options.action || '#';

        if (options.inputs) {
            this.createFields(options.inputs, form, modalEl);
        }

        // Botões
        const submitBtn = document.createElement('button');
        submitBtn.type = 'submit';
        submitBtn.className = 'btn btn-primary';
        submitBtn.setAttribute('data-export', false);
        submitBtn.textContent = options.submitText || 'Enviar';
        submitBtn.onclick = () => {
            if (typeof options.onSubmit === 'function') {
                options.onSubmit(new FormData(form), form, modalObj);
            }
        };

        if(options.close === true){
          const cancelBtn = document.createElement('button');
          cancelBtn.type = 'button';
          cancelBtn.className = 'btn btn-secondary';
          cancelBtn.setAttribute('data-bs-dismiss', 'modal');
          cancelBtn.textContent = 'Cancelar';

          modalFooter.appendChild(cancelBtn);
        }

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

        // Remove ouvintes anteriores se existirem
          if (modalEl._onOpenListener) {
              modalEl.removeEventListener('shown.bs.modal', modalEl._onOpenListener);
          }
          if (modalEl._onCloseListener) {
              modalEl.removeEventListener('hidden.bs.modal', modalEl._onCloseListener);
          }

          // Define novas funções e salva como propriedades do elemento
          modalEl._onOpenListener = function () {
              if (typeof options.onOpen === 'function') {
                  options.onOpen(modalEl, form, modalObj);
              }
          };

          modalEl._onCloseListener = function () {
              if (typeof options.onClose === 'function') {
                  options.onClose(modalEl, form, modalObj);
              }
          };

            // Adiciona os novos ouvintes
            modalEl.addEventListener('shown.bs.modal', modalEl._onOpenListener);
            modalEl.addEventListener('hidden.bs.modal', modalEl._onCloseListener);

    }

    openGridFormModal(options) {
      this.createModalStructure(options.size, options.close);

      const modalEl = document.getElementById(this.modalId);
      let modalObj = new bootstrap.Modal(modalEl);
      const modalTitle = modalEl.querySelector('.modal-title');
      const modalBody = modalEl.querySelector('.modal-body');
      const modalFooter = modalEl.querySelector('.modal-footer');

      // Limpa conteúdo antigo
      modalBody.innerHTML = options.body || '';
      modalFooter.innerHTML = '';
      modalTitle.textContent = options.title || 'Formulário';

      // Cria formulário
      const form = document.createElement('form');
      form.method = options.method || 'POST';
      form.action = options.action || '#';

      if (options.inputs) {
          // Define o tamanho da coluna (Padrão 3 colunas = col-md-4)
          const numCols = options.cols || 3;
          const colClass = {
              1: "col-12",
              2: "col-md-6",
              3: "col-md-4",
              4: "col-md-3"
          }[numCols] || "col-md-4";

          // Cria a Row do Bootstrap
          const row = document.createElement('div');
          row.className = 'row g-3'; // g-3 adiciona espaçamento entre os campos

          options.inputs.forEach(input => {
              if (input.type === 'hidden') {
                  this.createFields([input], form, modalEl);
              } else {
                  const col = document.createElement('div');
                  col.className = colClass;

                  // Criamos um container interno para o field para manter o padrão do seu componente
                  this.createFields([input], col, modalEl);
                  row.appendChild(col);
              }
          });
          form.appendChild(row);
      }

      // Botões
      const submitBtn = document.createElement('button');
      submitBtn.type = 'submit';
      submitBtn.className = 'btn btn-primary';
      submitBtn.setAttribute('data-export', false);
      submitBtn.textContent = options.submitText || 'Enviar';
      submitBtn.onclick = () => {
          if (typeof options.onSubmit === 'function') {
              options.onSubmit(new FormData(form), form, modalObj);
          }
      };

      if (options.close === true) {
          const cancelBtn = document.createElement('button');
          cancelBtn.type = 'button';
          cancelBtn.className = 'btn btn-secondary';
          cancelBtn.setAttribute('data-bs-dismiss', 'modal');
          cancelBtn.textContent = 'Cancelar';
          modalFooter.appendChild(cancelBtn);
      }

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

      // Lógica de Eventos (Identêntica à original)
      if (modalEl._onOpenListener) {
          modalEl.removeEventListener('shown.bs.modal', modalEl._onOpenListener);
      }
      if (modalEl._onCloseListener) {
          modalEl.removeEventListener('hidden.bs.modal', modalEl._onCloseListener);
      }

      modalEl._onOpenListener = function () {
          if (typeof options.onOpen === 'function') {
              options.onOpen(modalEl, form, modalObj);
          }
      };

      modalEl._onCloseListener = function () {
          if (typeof options.onClose === 'function') {
              options.onClose(modalEl, form, modalObj);
          }
      };

      modalEl.addEventListener('shown.bs.modal', modalEl._onOpenListener);
      modalEl.addEventListener('hidden.bs.modal', modalEl._onCloseListener);
  }

    /**
     * Abre modal com abas, cada aba contendo um form
     */
    openTabbedModal(options) {
        this.createModalStructure(options.size);

        const modalEl = document.getElementById(this.modalId);
        const modalObj = new bootstrap.Modal(modalEl);
        const modalTitle = modalEl.querySelector('.modal-title');
        const modalBody = modalEl.querySelector('.modal-body');
        const modalFooter = modalEl.querySelector('.modal-footer');

        // Reset
        modalTitle.textContent = options.title || 'Formulário';
        modalBody.innerHTML = '';
        modalFooter.innerHTML = '';

        // Cria nav
        const nav = document.createElement('ul');
        nav.className = 'nav nav-tabs';
        nav.role = 'tablist';

        const tabContent = document.createElement('div');
        tabContent.className = 'tab-content mt-3';

        options.tabs.forEach((tab, index) => {
            const tabId = `${this.modalId}-tab-${index}`;

            // Aba
            const navItem = document.createElement('li');
            navItem.className = 'nav-item';
            navItem.innerHTML = `
                <button class="nav-link ${index === 0 ? "active" : ""}"
                        id="${tabId}-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#${tabId}"
                        type="button" role="tab">
                    ${tab.label}
                </button>`;
            nav.appendChild(navItem);

            // Conteúdo da aba
            const tabPane = document.createElement('div');
            tabPane.className = `tab-pane fade ${index === 0 ? "show active" : ""}`;
            tabPane.id = tabId;
            tabPane.role = 'tabpanel';
            tabPane.innerHTML = tab.body || '';

            const form = document.createElement('form');
            form.method = tab.method || 'POST';
            form.action = tab.action || '#';

            this.createFields(tab.inputs, form, modalEl);

            form.onsubmit = (e) => {
                e.preventDefault();
                if (typeof tab.onSubmit === 'function') {
                    tab.onSubmit(new FormData(form), form, modalObj);
                }
            };

            // Remove ouvintes anteriores se existirem
            if (modalEl._onOpenListener) {
                modalEl.removeEventListener('shown.bs.modal', modalEl._onOpenListener);
            }
            if (modalEl._onCloseListener) {
                modalEl.removeEventListener('hidden.bs.modal', modalEl._onCloseListener);
            }

            // Define novas funções e salva como propriedades do elemento
            modalEl._onOpenListener = function () {
                if (typeof options.onOpen === 'function') {
                    options.onOpen(modalEl, form, modalObj);
                }
            };

            modalEl._onCloseListener = function () {
                if (typeof options.onClose === 'function') {
                    options.onClose(modalEl, form, modalObj);
                }
            };

            // Adiciona os novos ouvintes
            modalEl.addEventListener('shown.bs.modal', modalEl._onOpenListener);
            modalEl.addEventListener('hidden.bs.modal', modalEl._onCloseListener);

            tabPane.appendChild(form);
            tabContent.appendChild(tabPane);
        });

        modalBody.appendChild(nav);
        modalBody.appendChild(tabContent);

        // Footer comum
        const closeBtn = document.createElement("button");
        closeBtn.type = "button";
        closeBtn.className = "btn btn-secondary";
        closeBtn.setAttribute("data-bs-dismiss", "modal");
        closeBtn.textContent = options.closeText || "Fechar";

        modalFooter.appendChild(closeBtn);

        modalObj.show();


    }

    /**
     * Abre modal de confirmação
     */
    openConfirmationModal(options) {
        this.createModalStructure(options.size);

        const modalEl = document.getElementById(this.modalId);
        let modalObj = new bootstrap.Modal(modalEl);
        const modalTitle = modalEl.querySelector('.modal-title');
        const modalBody = modalEl.querySelector('.modal-body');
        const modalFooter = modalEl.querySelector('.modal-footer');

        // Limpa conteúdo antigo
        modalBody.innerHTML = '';
        modalFooter.innerHTML = '';
        modalTitle.textContent = options.title || 'Confirmação';

        // Ícone
        const icon = document.createElement('div');
        icon.className = 'text-center mb-3';
        icon.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#0d6efd" class="bi bi-question-circle" viewBox="0 0 16 16">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0-1A6 6 0 1 0 8 2a6 6 0 0 0 0 12z"/>
                <path d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.505-1.206 1.05-1.168 2.037l.003.217a.25.25 0 0 0 .25.25h.819a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927.896-1.371.654-.472 1.376-.977 1.376-2.073C10.5 4.012 9.494 3 8.134 3c-1.355 0-2.3.824-2.506 1.787zm1.293 5.696c0 .356.287.643.643.643.356 0 .643-.287.643-.643a.643.643 0 1 0-1.286 0z"/>
            </svg>`;

        const text = document.createElement('p');
        text.className = 'text-center';
        if(options.bodyHTML){
          text.innerHTML = options.message || 'Você tem certeza?';
        }else{
          text.textContent = options.message || 'Você tem certeza?';
        }

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

        // Remove ouvintes anteriores se existirem
  if (modalEl._onOpenListener) {
      modalEl.removeEventListener('shown.bs.modal', modalEl._onOpenListener);
  }
  if (modalEl._onCloseListener) {
      modalEl.removeEventListener('hidden.bs.modal', modalEl._onCloseListener);
  }

  // Define novas funções e salva como propriedades do elemento
  modalEl._onOpenListener = function () {
      if (typeof options.onOpen === 'function') {
          options.onOpen(modalEl, form, modalObj);
      }
  };

  modalEl._onCloseListener = function () {
      if (typeof options.onClose === 'function') {
          options.onClose(modalEl, form, modalObj);
      }
  };

  // Adiciona os novos ouvintes
  modalEl.addEventListener('shown.bs.modal', modalEl._onOpenListener);
  modalEl.addEventListener('hidden.bs.modal', modalEl._onCloseListener);
    }

   /**
     * Abre um modal de status ampliado, moderno e centralizado
     * @param {object} options - { type, title, subtitle, confirmText, onConfirm }
     */
    openAlertModal(options) {
        this.createModalStructure("md", options.type !== 'loading', this.modalIdAlert); 

        const modalEl = document.getElementById(this.modalIdAlert);
        const modalDialog = modalEl.querySelector('.modal-dialog');
        
        // CORREÇÃO AQUI: Usar getOrCreateInstance evita criar múltiplas instâncias no mesmo elemento
        const modalObj = bootstrap.Modal.getOrCreateInstance(modalEl, { 
            backdrop: options.type === 'loading' ? 'static' : true,
            keyboard: options.type !== 'loading'
        });

        // Garantir que a cortina suma se o usuário clicar fora (backdrop)
        // Adicionamos um evento que limpa qualquer resquício ao esconder,
        // MAS só remove a cortina se não houver outro modal aberto
        modalEl.addEventListener('hidden.bs.modal', () => {
            const openModals = document.querySelectorAll('.modal.show');
            if (openModals.length === 0) {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(b => b.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
        }, { once: true }); // Executa apenas uma vez por abertura

        modalDialog.classList.add('modal-dialog-centered');
        
        const modalHeader = modalEl.querySelector('.modal-header');
        const modalBody = modalEl.querySelector('.modal-body');
        const modalFooter = modalEl.querySelector('.modal-footer');

        if (modalHeader) modalHeader.style.display = 'none';
        //modalFooter.style.display = 'none';

        const config = {
            success: { color: '#28a745', icon: '<i class="bi bi-check2-circle" style="font-size: 80px;"></i>' },
            error:   { color: '#dc3545', icon: '<i class="bi bi-x-circle" style="font-size: 80px;"></i>' },
            warning: { color: '#ffc107', icon: '<i class="bi bi-exclamation-triangle" style="font-size: 80px;"></i>' },
            info:    { color: '#17a2b8', icon: '<i class="bi bi-info-circle" style="font-size: 80px;"></i>' },
            loading: { color: '#007bff', icon: '<div class="modern-loader"></div>' }
        }[options.type || 'info'];

        modalBody.innerHTML = `
            <div class="text-center p-5 bounce-in">
                <div class="mb-4" style="color: ${config.color};">
                    ${config.icon}
                </div>
                ${options.title ? `<h2 class="fw-bold mb-2">${options.title}</h2>` : ''}
                ${options.subtitle ? `<p class="text-muted lead">${options.subtitle}</p>` : ''}
            </div>
        `;

        if (options.type !== 'loading') {
            modalFooter.style.display = 'flex';
            modalFooter.style.justifyContent = 'center';
            modalFooter.style.borderTop = 'none';
            modalFooter.style.paddingBottom = '3rem';

            const btn = document.createElement('button');
            btn.className = `btn btn-lg px-5 shadow btn-${options.type === 'warning' ? 'dark' : (options.type === 'error' ? 'danger' : 'primary')}`;
            btn.style.borderRadius = '50px';
            btn.textContent = options.confirmText || 'Entendido';
            
            btn.onclick = () => {
                if (typeof options.onConfirm === 'function') {
                    options.onConfirm(modalObj);
                } else {
                    modalObj.hide();
                }
            };
            
            modalFooter.innerHTML = "";
            modalFooter.appendChild(btn);
        }

        modalObj.show();
        return modalObj;
    }

    hideModal() {
        const modalEl = document.getElementById(this.modalIdAlert);
        if (modalEl) {
            const modalObj = bootstrap.Modal.getInstance(modalEl);
            if (modalObj) {
                modalObj.hide();
            }

            // Força a remoção de qualquer resquício do Bootstrap,
            // MAS só limpa se não houver outro modal ainda aberto (ex: modal de pedido)
            setTimeout(() => {
                const openModals = document.querySelectorAll('.modal.show');
                if (openModals.length === 0) {
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                }
            }, 300); // Aguarda o tempo da animação de fade do Bootstrap
        }
    }
}