# Assistant Services

This folder contains the autonomous assistant service layer used by the profile page.

## What it does

- Polls queued assistant tasks from `storage/assistant/tasks.json`
- Executes browser steps with Puppeteer
- Listens for WhatsApp commands that begin with `AI:`
- Stores task memory, delivery state, and execution logs under `storage/assistant/`

## Required app settings

- `OPENROUTER_API_KEY` in root `.env`
- `OPENROUTER_MODEL` in root `.env`
- `APP_URL` in root `.env` or `ASSISTANT_APP_URL` in the Node process

## Install

```bash
cd services/assistant
npm install
```

## Run the browser worker

```bash
npm run worker
```

## Run the WhatsApp bridge

```bash
npm run whatsapp
```

## Notes

- The worker uses a persistent Chrome profile in `storage/assistant/browser-profile/`
- WhatsApp login is handled by `whatsapp-web.js` via QR code the first time
- Browser actions rely on stable selectors such as `data-*` attributes and page ids
