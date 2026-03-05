// bia-chat.js
class BiaChat {
    constructor(config = {}) {
        this.userName = config.userName || "Usuário";

        // Estado
        this.conversations = []; // { realId: number|null, title: string, messages: [], createdAt }
        this.currentConversationIndex = 0;
        this.chatOpened = false;

        // Estado de Hint (Balão)
        this.hintDismissedThisSession = false;
        this.hintAutoCloseTimer = null;

        // --- DOM REFERENCES ---
        this.btn = document.getElementById("bia-floating-btn");
        this.windowChat = document.getElementById("bia-chat-window");
        
        // Botões de Janela
        this.minimizeBtn = document.getElementById("biaMinimizeBtn");
        this.maximizeBtn = document.getElementById("biaMaximizeBtn"); // Novo
        
        // Sidebar e Título
        this.sidebar = document.getElementById("biaSidebar");
        this.sidebarToggle = document.getElementById("biaSidebarToggle");
        this.chatTitle = document.getElementById("biaChatTitle"); // Novo
        
        // Inputs e Mensagens
        this.input = document.getElementById("biaInput");
        this.sendBtn = document.getElementById("biaSendBtn");
        this.messages = document.getElementById("biaMessages");
        
        // Listas e Hint
        this.listContainer = document.getElementById("biaConversationsList");
        this.newChatBtn = document.getElementById("biaNewChatBtn");
        this.hint = document.getElementById("biaHint");

        
        // Socket manager (assume window.websocketServer global)
        this.socket = window.websocketServer;
        this.typingIndicator = null;

        this.bindEvents();
        this.registerSocketHandlers();
        this.showHint();
    }

    /* ===================== BIND EVENTS ===================== */
    bindEvents() {
        // Abrir/Fechar
        if (this.btn) this.btn.addEventListener("click", () => this.openChat());
        if (this.minimizeBtn) this.minimizeBtn.addEventListener("click", () => this.closeChat());

        // Maximizar (Novo)
        if (this.maximizeBtn) {
            this.maximizeBtn.addEventListener("click", () => {
                this.windowChat.classList.toggle("bia-maximized");
                const isMax = this.windowChat.classList.contains("bia-maximized");
                // Alterna ícone se estiver usando Bootstrap Icons ou similar
                this.maximizeBtn.classList.toggle("bi-arrows-fullscreen", !isMax);
                this.maximizeBtn.classList.toggle("bi-fullscreen-exit", isMax);
            });
        }

        // Sidebar
        if (this.sidebarToggle) this.sidebarToggle.addEventListener("click", () => this.sidebar.classList.toggle("open"));

        // Enviar Mensagem
        if (this.sendBtn) this.sendBtn.addEventListener("click", () => this.sendMessage());
        if (this.input) this.input.addEventListener("keypress", e => {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Hint (clique)
        if (this.hint) {
            this.hint.addEventListener('click', () => this.openChat());
        }

        // Nova conversa
        if (this.newChatBtn) this.newChatBtn.addEventListener("click", () => {
            this.createConversationLocal();
            this.windowChat.style.display = "flex";
            if (this.hint) this.hint.style.display = "none";
            // Fecha sidebar no mobile ao clicar em novo chat
            if (this.sidebar) this.sidebar.classList.remove("open");
        });
    }

    /* ===================== SOCKET HANDLERS (CORRIGIDO) ===================== */
    registerSocketHandlers() {
        if (!this.socket) {
            console.warn("BiaChat: window.websocketServer não encontrado. Certifique-se de instanciar WebSocketManager antes.");
            return;
        }

        // Recebe resposta da IA
        this.socket.on("ai_response", (payload) => {
            // payload: { conversation_id, text, chat_title, frontend_action, ... }
            this.handleAIResponse(payload);
        });

        // Evento de conexão (opcional)
        this.socket.on("connection_ok", (p) => {
            // console.log('Socket OK', p);
        });

    
        // Registrar Handlers
        this.socket.on('list_conversations', (payload) => this.handleListConversations(payload));
        this.socket.on('history_data', (payload) => this.handleHistoryData(payload));

        const checkReady = setInterval(() => {
            if (this.socket.ws && this.socket.ws.readyState === WebSocket.OPEN) {
                this.socket.emit('list_conversations', {});
                clearInterval(checkReady);
            }
        }, 500);
    }

    sendSocketEvent(eventName, payload) {
        if (!this.socket) {
            console.warn("WebSocket não inicializado");
            // Fallback visual se desconectado
            this.addAIMessage("⚠️ Erro de conexão. O WebSocket não está pronto.");
            return;
        }
        try {
            // Usa .emit() conforme a implementação original do seu Wrapper
            this.socket.emit(eventName, payload);
        } catch (err) {
            console.error("Falha ao enviar via socket:", err);
        }
    }

    /* ===================== UI: open / close / hint ===================== */
    openChat() {
        this.windowChat.classList.remove("bia-close");
        this.windowChat.classList.add("bia-open");
        this.windowChat.style.display = "flex";

        this.dismissHint();

        if (!this.chatOpened) {
            // Se não tem conversas ainda, cria a primeira
            if (this.conversations.length === 0) {
                this.createConversationLocal();
            }
            this.chatOpened = true;
        }
        
        // Foca no input
        setTimeout(() => { if(this.input) this.input.focus(); }, 300);
    }

    closeChat() {
        this.windowChat.classList.remove("bia-open");
        this.windowChat.classList.add("bia-close");
        setTimeout(() => {
            this.windowChat.style.display = "none";
        }, 200);
    }

    /* ===================== HINT LOGIC ===================== */
    showHint() {
        if (!this.hint || this.hintDismissedThisSession) return;

        setTimeout(() => {
            if (this.chatOpened || this.hintDismissedThisSession) return;
            this.hint.classList.add("show");
            this.hintAutoCloseTimer = setTimeout(() => {
                this.dismissHint();
            }, 10000); 
        }, 2000);
    }

    dismissHint() {
        if (!this.hint) return;
        this.hint.classList.remove("show");
        this.hintDismissedThisSession = true;
        if (this.hintAutoCloseTimer) {
            clearTimeout(this.hintAutoCloseTimer);
            this.hintAutoCloseTimer = null;
        }
    }

    /* ===================== CONVERSA LOCAL ===================== */
    createConversationLocal() {
        const conv = {
            realId: null,
            title: "Nova conversa",
            messages: [],
            createdAt: new Date()
        };

        // Saudação inicial
        conv.messages.push({
            from: "ai",
            text: `Olá, ${this.userName}!\n Tudo bem? 😊\nEu sou a Bia. Como posso te ajudar hoje?`,
            time: new Date(),
            hasOptions: true // Flag para indicar que podemos renderizar sugestões padrões
        });

        this.conversations.push(conv);
        this.currentConversationIndex = this.conversations.length - 1;

        this.updateChatHeaderTitle("Nova conversa");
        this.renderConversationsList();
        this.renderCurrentConversation();
    }

    /* ===================== RENDER LISTA E HEADER ===================== */
    renderConversationsList() {
        if (!this.listContainer) return;
        this.listContainer.innerHTML = "";
        this.conversations.forEach((conv, idx) => {
            const item = document.createElement("div");
            item.classList.add("bia-conversation-item");
            if (idx === this.currentConversationIndex) item.classList.add("active"); // Opcional: estilizar ativo
            
            const title = conv.title + (conv.realId ? ` (#${conv.realId})` : "");
            item.innerText = title;

            item.addEventListener("click", () => {
                this.currentConversationIndex = idx;
                this.loadConversationIndex(idx);
                if (this.sidebar) this.sidebar.classList.remove("open");
            });

            this.listContainer.appendChild(item);
        });
    }

    loadConversationIndex(index) {
        this.currentConversationIndex = index;
        const conv = this.conversations[index];
        if (conv) this.updateChatHeaderTitle(conv.title);
        this.renderCurrentConversation();
    }

    updateChatHeaderTitle(title) {
        if (this.chatTitle) {
            this.chatTitle.innerText = title;
        }
    }

    renderCurrentConversation() {
        if (!this.messages) return;
        this.messages.innerHTML = "";

        const conv = this.conversations[this.currentConversationIndex];
        if (!conv) return;

        conv.messages.forEach(msg => {
            if (msg.from === "user") {
                this.addUserMessage(msg.text, false);
            } else {
                // Ao recarregar histórico, processamos o texto novamente
                // Se quiser salvar as options no histórico, precisaria mudar a estrutura de dados.
                // Aqui vamos re-processar o texto cru.
                this.addAIMessage(msg.text, false);
            }
        });

        this.messages.scrollTop = this.messages.scrollHeight;
    }

    /* ===================== ENVIO MENSAGEM ===================== */
    sendMessage() {
        if (!this.input) return;
        const text = this.input.value.trim();
        if (!text) return;

        const conv = this.conversations[this.currentConversationIndex];
        if (!conv) return;

        // Salva localmente
        conv.messages.push({ from: "user", text, time: new Date() });
        this.addUserMessage(text);

        // Limpar input
        this.input.value = "";

        // Payload
        const payload = {
            message: text,
            conversation_id: conv.realId, 
            user_name: this.userName
        };

        this.showTypingIndicator(true);
        // Usa o método de evento (emit) correto
        this.sendSocketEvent("user_message", payload);
    }

    /* ===================== RESPOSTA DA IA ===================== */
    handleAIResponse(payload) {
        if (!payload) return;
        
        const realId = payload.conversation_id ?? null;
        
        // 1. Processamento de Texto
        let text = String(
            (payload.resposta_humana === null ? payload.text : payload.resposta_humana) 
            ?? "Desculpe, não entendi."
        );

        // 2. Localizar conversa
        let conv = null;
        if (realId !== null) {
            conv = this.conversations.find(c => c.realId === realId);
        }
        if (!conv) {
            conv = this.conversations.find(c => c.realId === null);
        }
        if (!conv) {
            // Fallback: cria nova se perdeu a referência
            this.createConversationLocal();
            conv = this.conversations[this.currentConversationIndex];
        }

        // 3. Atualizar ID real se necessário
        if (!conv.realId && realId !== null) {
            conv.realId = realId;
        }

        // 4. ATUALIZAÇÃO DE TÍTULO (NOVO)
        // Se o backend mandou um título novo (ex: chat_title ou title)
        const newTitle = payload.chat_title || payload.title;
        if (newTitle) {
            conv.title = newTitle;
            // Se for a conversa atual, atualiza o DOM
            const visibleConv = this.conversations[this.currentConversationIndex];
            if (conv === visibleConv) {
                this.updateChatHeaderTitle(newTitle);
            }
            this.renderConversationsList();
        }

        // 5. Salvar e Renderizar
        conv.messages.push({ from: "ai", text: text, time: new Date() });
        
        const visibleConv = this.conversations[this.currentConversationIndex];
        if (conv === visibleConv) {
            this.addAIMessage(text, true);
        }

        // 6. Frontend Action
        const frontendAction = payload.frontend_action;
        if (frontendAction && frontendAction.intencao && frontendAction.parametros) {
            this.handleFrontendAction(frontendAction.intencao, frontendAction.parametros);
        }

        this.showTypingIndicator(false);
        this.renderConversationsList();
    }

    /* ===================== UI: ADICIONAR MENSAGENS E OPÇÕES ===================== */
    
    // Extrai <option>Value</option> do texto
    extractOptions(htmlString) {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = htmlString;
        
        const options = [];
        const optionTags = tempDiv.getElementsByTagName('option');

        // Itera removendo as tags do HTML e salvando o texto
        while (optionTags.length > 0) {
            options.push(optionTags[0].innerText);
            optionTags[0].remove();
        }

        return {
            cleanHTML: tempDiv.innerHTML, // Texto sem as tags <option>
            options: options
        };
    }

    addUserMessage(text, animate = true) {
        if (!this.messages) return;
        const wrapper = document.createElement("div");
        wrapper.classList.add("bia-message-wrapper", "bia-user-msg");
        
        if(animate) this.setAnimation(wrapper);

        const message = document.createElement("div");
        message.className = "bia-message";
        message.textContent = text; 

        wrapper.appendChild(message);
        this.messages.appendChild(wrapper);
        this.finalizeMessageAdd(wrapper, animate);
    }

    addAIMessage(text, animate = true) {
        if (!this.messages) return;

        const wrapper = document.createElement("div");
        wrapper.classList.add("bia-message-wrapper", "bia-ai-msg");

        if(animate) this.setAnimation(wrapper);

        const avatar = document.createElement("img");
        avatar.src = "/template/dist/icons/bia.png"; 
        avatar.className = "bia-avatar-msg";

        const message = document.createElement("div");
        message.className = "bia-message";

        // --- NOVO: Extrair Options ---
        const extracted = this.extractOptions(text);
        
        // --- Parser Markdown no texto limpo ---
        message.innerHTML = this.parseSimpleMarkdown(extracted.cleanHTML);

        wrapper.appendChild(avatar);
        wrapper.appendChild(message);
        this.messages.appendChild(wrapper);

        // --- NOVO: Renderizar Chips ---
        if (extracted.options.length > 0) {
            this.renderSuggestionChips(extracted.options);
        } else if (text.includes("Como posso te ajudar hoje?") && this.conversations[this.currentConversationIndex].messages.length <= 1) {
            // Sugestões padrão se for a mensagem de boas vindas e não vieram options do backend
            this.renderSuggestionChips(["Tem vaga para Operador?", "Qual o Fill Rate?", "Resumo da loja"]);
        }

        this.finalizeMessageAdd(wrapper, animate);
    }

    renderSuggestionChips(suggestionsList) {
        const container = document.createElement("div");
        container.className = "bia-options-container"; // Certifique-se de ter CSS para isso (ex: display: flex, gap: 5px, flex-wrap: wrap, margin-left: 50px)
        
        // Estilo inline básico caso não tenha CSS ainda
        container.style.display = "flex";
        container.style.flexWrap = "wrap";
        container.style.gap = "8px";
        container.style.marginTop = "5px";
        container.style.marginBottom = "10px";
        container.style.marginLeft = "48px"; // alinhado com o texto da IA

        suggestionsList.forEach(suggestion => {
            const chip = document.createElement("button");
            chip.className = "bia-suggestion-chip";
            chip.innerText = suggestion;
            chip.type = "button";
            
            // Estilo inline básico
            chip.style.padding = "6px 12px";
            chip.style.borderRadius = "15px";
            chip.style.border = "1px solid #ddd";
            chip.style.backgroundColor = "#fff";
            chip.style.cursor = "pointer";
            chip.style.fontSize = "0.85rem";
            
            // Hover effect via JS events ou use CSS externo
            chip.onmouseover = () => chip.style.backgroundColor = "#f0f0f0";
            chip.onmouseout = () => chip.style.backgroundColor = "#fff";

            // Ação ao clicar: Preenche o input e foca
            chip.onclick = () => {
                if(this.input) {
                    this.input.value = suggestion;
                    this.input.focus();
                }
            };
            container.appendChild(chip);
        });

        this.messages.appendChild(container);
    }

    /* ===================== HELPERS ===================== */
    setAnimation(element) {
        element.style.opacity = "0";
        element.style.transform = "translateY(10px)";
        element.style.transition = "all .22s ease";
    }

    finalizeMessageAdd(element, animate) {
        if (animate) {
            setTimeout(() => {
                element.style.opacity = "1";
                element.style.transform = "translateY(0)";
            }, 30);
        }
        this.messages.scrollTop = this.messages.scrollHeight;
    }

    parseSimpleMarkdown(text) {
        let safeText = text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");

        // Negrito
        safeText = safeText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // Listas simples
        const lines = safeText.split('\n');
        let html = '';
        let inList = false;

        lines.forEach((line, index) => {
            const trimmed = line.trim();
            if (trimmed.startsWith('- ')) {
                if (!inList) { html += '<ul>'; inList = true; }
                html += `<li>${trimmed.substring(2)}</li>`;
            } else {
                if (inList) { html += '</ul>'; inList = false; }
                if (trimmed.length === 0) {
                    html += '<br>';
                } else {
                    html += line + (index < lines.length - 1 ? '<br>' : '');
                }
            }
        });
        if (inList) html += '</ul>';
        return html;
    }

    showTypingIndicator(show = true) {
        if (!this.messages) return;
        if (show) {
            if (this.typingIndicator) return;
            const wrapper = document.createElement("div");
            wrapper.classList.add("bia-message-wrapper", "bia-ai-msg", "bia-typing");
            // Estilos de animação
            wrapper.style.opacity = "0";
            wrapper.style.transform = "translateY(6px)";
            wrapper.style.transition = "all .18s ease";

            const avatar = document.createElement("img");
            avatar.src = "/template/dist/icons/bia.png";
            avatar.className = "bia-avatar-msg";

            const bubble = document.createElement("div");
            bubble.className = "bia-message";
            bubble.textContent = "Bia está digitando...";

            wrapper.appendChild(avatar);
            wrapper.appendChild(bubble);
            this.messages.appendChild(wrapper);
            this.typingIndicator = wrapper;

            setTimeout(() => {
                wrapper.style.opacity = "1";
                wrapper.style.transform = "translateY(0)";
            }, 30);
            this.messages.scrollTop = this.messages.scrollHeight;
        } else {
            if (!this.typingIndicator) return;
            this.typingIndicator.remove();
            this.typingIndicator = null;
        }
    }

    /* ===================== AÇÕES DE FRONTEND (NAV) ===================== */
    handleFrontendAction(intencao, params = {}) {
        console.log(`Ação: ${intencao}`, params);
        // Minimiza o chat para ver a tela
        this.closeChat();

        switch (intencao) {
            case 'cancelar_agendamento':
                if (window.navigateToScreen) {
                    window.navigateToScreen('agendamentos', {
                        id: params.id,
                        action: 'cancel'
                    });
                }
                if (window.triggerCancelFunction) {
                     window.triggerCancelFunction(params.id);
                }
                break;

            case 'ver_escalas':
                if (window.navigateToScreen) {
                    window.navigateToScreen('escalas', params);
                }
                break;

            default:
                console.warn(`Ação '${intencao}' não mapeada.`);
        }
    }

    handleListConversations(payload) {
        if (!payload.conversations) return;
        
        this.conversations = payload.conversations.map(c => ({
            realId: c.id,
            title: c.user_name || `Conversa #${c.id}`,
            messages: [],
            createdAt: c.created_at
        }));
        
        this.renderConversationsList();
    }

    // Atualize o loadConversationIndex para garantir que pede o histórico com ID real
    loadConversationIndex(index) {
        this.currentConversationIndex = index;
        const conv = this.conversations[index];
        
        if (conv) {
            this.updateChatHeaderTitle(conv.title);
            if (conv.realId && conv.messages.length === 0) {
                this.showTypingIndicator(true);
                // Solicita histórico ao backend
                this.socket.emit("get_history", { conversation_id: conv.realId });
            } else {
                this.renderCurrentConversation();
            }
        }
    }

    handleHistoryData(payload) {
        const conv = this.conversations.find(c => c.realId == payload.conversation_id);
        if (conv) {
            // Mapeia o formato do banco para o formato do chat
            conv.messages = payload.messages.map(m => ({
                from: m.sender === 'user' ? 'user' : 'ai',
                text: m.message,
                time: new Date()
            }));
            
            // Se ainda for a conversa ativa, renderiza
            if (this.conversations[this.currentConversationIndex].realId == payload.conversation_id) {
                this.renderCurrentConversation();
            }
        }
        this.showTypingIndicator(false);
    }
}