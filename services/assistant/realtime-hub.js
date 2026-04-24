import { WebSocketServer, WebSocket } from 'ws';

const nowIso = () => new Date().toISOString();
const safeText = (value) => String(value ?? '').trim();

export class AssistantRealtimeHub {
  constructor(options = {}) {
    this.port = Number(options.port || process.env.ASSISTANT_WS_PORT || 8787);
    this.host = safeText(options.host || process.env.ASSISTANT_WS_HOST || '127.0.0.1');
    this.path = safeText(options.path || process.env.ASSISTANT_WS_PATH || '/assistant');
    this.server = null;
  }

  async start() {
    if (this.server) {
      return this;
    }

    this.server = new WebSocketServer({
      port: this.port,
      host: this.host,
      path: this.path,
    });

    this.server.on('connection', (socket) => {
      try {
        socket.send(JSON.stringify({
          type: 'ready',
          timestamp: nowIso(),
          message: 'Assistant realtime hub connected.',
        }));
      } catch (_error) {
      }
    });

    return this;
  }

  broadcast(payload = {}) {
    if (!this.server) {
      return;
    }

    const message = JSON.stringify({
      timestamp: nowIso(),
      ...payload,
    });

    for (const client of this.server.clients) {
      if (client.readyState === WebSocket.OPEN) {
        client.send(message);
      }
    }
  }

  async close() {
    if (!this.server) {
      return;
    }

    const server = this.server;
    this.server = null;
    await new Promise((resolve) => {
      server.close(resolve);
    });
  }
}

