<?php
$assistantProvider = (string) ($assistantBootstrap['provider'] ?? 'OpenRouter');
$assistantModel = (string) ($assistantBootstrap['model'] ?? 'openai/gpt-5.2');
$assistantCommandUrl = (string) ($assistantBootstrap['api']['command'] ?? '../../index.php?action=assistant_command');
$assistantStatusUrl = (string) ($assistantBootstrap['api']['status'] ?? '../../index.php?action=assistant_status');
?>

<section class="profile-ai-assistant-shell fade-in-section" aria-label="AI Assistant">
  <div class="glass-card ai-assistant-card" data-assistant-panel
       data-assistant-provider="<?= htmlspecialchars($assistantProvider) ?>"
       data-assistant-model="<?= htmlspecialchars($assistantModel) ?>">
    <div class="ai-assistant-glow"></div>

    <div class="ai-assistant-header">
      <div class="ai-assistant-heading">
        <span class="ai-assistant-kicker">Autonomous profile ops</span>
        <h2 class="ai-assistant-title">AI Assistant</h2>
        <p class="ai-assistant-subtitle">
          Voice, WhatsApp, and browser tasks powered by <?= htmlspecialchars($assistantProvider) ?>.
        </p>
      </div>

      <div class="ai-assistant-toolbar">
        <span class="ai-assistant-status" data-assistant-status>
          <span class="ai-assistant-status-dot" data-assistant-status-dot></span>
          <span data-assistant-status-label>Online</span>
        </span>

        <button type="button" class="ai-icon-btn" data-assistant-voice-toggle title="Toggle voice input" aria-label="Toggle voice input">
          <i data-lucide="mic" class="w-4 h-4"></i>
        </button>
        <button type="button" class="ai-icon-btn" data-assistant-settings-toggle title="Open assistant settings" aria-label="Open assistant settings">
          <i data-lucide="settings-2" class="w-4 h-4"></i>
        </button>
      </div>
    </div>

    <div class="ai-assistant-layout">
      <div class="ai-assistant-chat-column">
        <div class="ai-chat-surface">
          <div class="ai-chat-log" data-assistant-chat-log role="log" aria-live="polite" aria-label="Assistant conversation">
            <div class="ai-chat-empty" data-assistant-empty>
              Start with a command like "message Ali hello bro" or "open my messages and draft a reply".
            </div>
            <div class="ai-chat-typing" data-assistant-typing hidden>
              <span></span><span></span><span></span>
            </div>
          </div>
        </div>

        <div class="ai-composer-card">
          <label class="sr-only" for="assistantCommandInput">Assistant command</label>
          <textarea
            id="assistantCommandInput"
            class="ai-command-input"
            data-assistant-input
            rows="2"
            placeholder="Tell the assistant what to do..."
            autocomplete="off"
            spellcheck="false"></textarea>

          <div class="ai-composer-actions">
            <button type="button" class="ai-compose-btn ai-compose-btn-secondary" data-assistant-voice-input title="Start voice input">
              <i data-lucide="audio-lines" class="w-4 h-4"></i>
              Voice
            </button>
            <button type="button" class="ai-compose-btn ai-compose-btn-primary" data-assistant-send>
              <i data-lucide="send" class="w-4 h-4"></i>
              Send
            </button>
          </div>
        </div>
      </div>

      <aside class="ai-assistant-side">
        <div class="ai-execution-card">
          <div class="ai-execution-head">
            <div>
              <span class="ai-execution-kicker">Live Execution</span>
              <strong class="ai-execution-title" data-assistant-task-label>Idle</strong>
            </div>
            <div class="ai-execution-pulse" data-assistant-execution-indicator aria-hidden="true"></div>
          </div>
          <div class="ai-execution-summary" data-assistant-execution-summary>
            Waiting for a command.
          </div>
          <div class="ai-step-list" data-assistant-steps>
            <div class="ai-step-empty">No steps yet.</div>
          </div>
        </div>

        <div class="ai-system-card">
          <div class="ai-system-row">
            <span>Provider</span>
            <strong><?= htmlspecialchars($assistantProvider) ?></strong>
          </div>
          <div class="ai-system-row">
            <span>Model</span>
            <strong><?= htmlspecialchars($assistantModel) ?></strong>
          </div>
          <div class="ai-system-row">
            <span>Command API</span>
            <strong><?= htmlspecialchars($assistantCommandUrl) ?></strong>
          </div>
          <div class="ai-system-row">
            <span>Status API</span>
            <strong><?= htmlspecialchars($assistantStatusUrl) ?></strong>
          </div>
        </div>
      </aside>
    </div>

    <div class="ai-settings-popover" data-assistant-settings hidden>
      <div class="ai-settings-head">
        <div>
          <p class="ai-settings-kicker">Assistant settings</p>
          <h3>Workflow controls</h3>
        </div>
        <button type="button" class="ai-icon-btn" data-assistant-settings-close aria-label="Close settings">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <div class="ai-settings-body">
        <label class="ai-toggle-row">
          <span>Voice feedback</span>
          <input type="checkbox" data-assistant-voice-feedback checked>
        </label>
        <label class="ai-toggle-row">
          <span>Auto speak results</span>
          <input type="checkbox" data-assistant-auto-speak checked>
        </label>
        <label class="ai-toggle-row">
          <span>Live log polling</span>
          <input type="checkbox" data-assistant-live-poll checked>
        </label>
      </div>

      <div class="ai-settings-actions">
        <button type="button" class="ai-compose-btn ai-compose-btn-secondary" data-assistant-refresh>
          <i data-lucide="refresh-cw" class="w-4 h-4"></i>
          Refresh
        </button>
        <button type="button" class="ai-compose-btn ai-compose-btn-secondary" data-assistant-clear>
          <i data-lucide="trash-2" class="w-4 h-4"></i>
          Clear chat
        </button>
      </div>
    </div>
  </div>
</section>
