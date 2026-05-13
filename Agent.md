# AI Agent & WhatsApp Testing Guide

To test the AI Agent and its WhatsApp integration, follow these steps:

### 1. Prerequisites
Ensure you have Node.js installed and the main PHP application is running.

### 2. Installation
Navigate to the assistant service directory and install dependencies:
```powershell
cd services/assistant
npm install
```

### 3. Run the Services

#### Option A: Run everything (WhatsApp + Worker)
This starts the WhatsApp bridge (to receive commands via WhatsApp) and the browser worker (to execute actions).
```powershell
cd services/assistant
npm run whatsapp
```
*Note: If it's your first time, you will need to scan the QR code shown in the terminal with your WhatsApp mobile app.*

#### Option B: Run only the Browser Worker
Use this if you only want to test agent actions triggered from the web interface (without WhatsApp).
```powershell
cd services/assistant
npm run worker
```

### 4. Testing Commands
Once the services are running, you can send commands via WhatsApp or use the AI Agent interface on the website.

**Example WhatsApp Commands:**
- `AI: open my profile`
- `AI: search job offers in design`
- `AI: open the live stream page`
- `AI: navigate to the first accepted candidature`

### 5. Troubleshooting
- **QR Code:** If the session expires, delete `storage/assistant/whatsapp-session/` and restart the command.
- **Headless Mode:** To see the browser actions, set `ASSISTANT_HEADLESS=false` in your environment.
- **Backend URL:** Ensure `APP_URL` in your `.env` or root config matches your local server address.
