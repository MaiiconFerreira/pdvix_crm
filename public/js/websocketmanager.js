class WebSocketManager {
    constructor(url) {
        this.url = url;
        this.ws = null;
        this.eventHandlers = {}; // Armazena eventos e seus handlers
        this.connect();
    }

    connect() {
        this.ws = new WebSocket(this.url);

        this.ws.onopen = () => {
            console.log("✅ WebSocket conectado:", this.url);
        };

        this.ws.onmessage = (message) => {
            try {
                const data = JSON.parse(message.data);
                const { event, payload } = data;

                if (event && this.eventHandlers[event]) {
                    this.eventHandlers[event].forEach(handler => handler(payload));
                } else {
                    console.warn("⚠️ Evento sem handler:", event);
                }
            } catch (e) {
                console.error("Erro ao processar mensagem:", e, message.data);
            }
        };

        this.ws.onclose = () => {
            console.log("❌ WebSocket desconectado. Tentando reconectar...");
            setTimeout(() => this.connect(), 3000);
        };

        this.ws.onerror = (err) => {
            console.error("Erro no WebSocket:", err);
        };
    }

    // Registra handlers para eventos
    on(eventName, callback) {
        if (!this.eventHandlers[eventName]) {
            this.eventHandlers[eventName] = [];
        }
        this.eventHandlers[eventName].push(callback);
    }

    // Envia mensagens
    emit(eventName, payload) {
        const message = JSON.stringify({ event: eventName, payload });
        this.ws.send(message);
    }

    // Remove um handler específico
    off(eventName, callback) {
        if (this.eventHandlers[eventName]) {
            this.eventHandlers[eventName] = this.eventHandlers[eventName].filter(
                handler => handler !== callback
            );
        }
    }
}
