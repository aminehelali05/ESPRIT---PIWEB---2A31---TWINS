<?php
$signalingUrl = 'http://localhost:3000';
$configPath = __DIR__ . '/../../config/config.php';
if (file_exists($configPath)) {
  include_once $configPath;
  if (defined('SIGNALING_SERVER_URL')) {
    $signalingUrl = (string) SIGNALING_SERVER_URL;
  }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meeting Room - Diversity.is</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'sans-serif']
          }
        }
      }
    };
  </script>

  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdn.socket.io/4.5.4/socket.io.min.js"></script>

  <style>
    body {
      overflow: hidden;
    }

    .video-grid {
      display: grid;
      gap: 0.5rem;
      height: 100%;
    }

    .video-grid.grid-1 {
      grid-template-columns: 1fr;
    }

    .video-grid.grid-2 {
      grid-template-columns: repeat(2, 1fr);
    }

    .video-grid.grid-3,
    .video-grid.grid-4 {
      grid-template-columns: repeat(2, 1fr);
      grid-template-rows: repeat(2, 1fr);
    }

    .video-grid.grid-5,
    .video-grid.grid-6 {
      grid-template-columns: repeat(3, 1fr);
      grid-template-rows: repeat(2, 1fr);
    }

    .video-container {
      position: relative;
      background: #18181b;
      border-radius: 0.5rem;
      overflow: hidden;
      aspect-ratio: 16/9;
    }

    .video-container video {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .video-overlay {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 0.75rem;
      background: linear-gradient(to top, rgba(0, 0, 0, 0.7), transparent);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .control-btn {
      width: 3rem;
      height: 3rem;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      cursor: pointer;
    }

    .control-btn:hover {
      transform: scale(1.1);
    }

    .control-btn.active {
      background: #ef4444;
    }
  </style>
</head>

<body class="bg-zinc-950 text-white h-screen flex flex-col">
  <header class="bg-zinc-900/50 backdrop-blur-xl border-b border-zinc-800 px-6 py-3 flex items-center justify-between">
    <div class="flex items-center gap-4">
      <div class="w-8 h-8 bg-gradient-to-tr from-purple-600 to-pink-500 rounded-lg flex items-center justify-center">
        <i data-lucide="video" class="w-4 h-4"></i>
      </div>
      <div>
        <h1 class="text-sm font-semibold" id="meetingTitle">Meeting Room</h1>
        <p class="text-xs text-zinc-400" id="meetingTime">--:--</p>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <div class="flex items-center gap-2 px-3 py-1.5 bg-zinc-800/50 rounded-lg">
        <i data-lucide="users" class="w-4 h-4 text-zinc-400"></i>
        <span class="text-sm" id="participantCount">1</span>
      </div>

      <button id="copyLinkBtn" class="px-3 py-1.5 bg-zinc-800/50 hover:bg-zinc-800 rounded-lg text-sm transition-all flex items-center gap-2">
        <i data-lucide="link" class="w-4 h-4"></i>
        Copy Link
      </button>

      <button id="settingsBtn" class="p-2 hover:bg-zinc-800/50 rounded-lg transition-all">
        <i data-lucide="settings" class="w-4 h-4"></i>
      </button>
    </div>
  </header>

  <main class="flex-1 p-4 overflow-hidden">
    <div id="videoGrid" class="video-grid grid-1 h-full">
      <div class="video-container">
        <video id="localVideo" autoplay muted playsinline></video>
        <div class="video-overlay">
          <span class="text-sm font-medium">You</span>
          <div class="flex items-center gap-2">
            <span id="localMicStatus" class="w-6 h-6 bg-zinc-800/80 rounded-full flex items-center justify-center">
              <i data-lucide="mic" class="w-3.5 h-3.5"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </main>

  <footer class="bg-zinc-900/50 backdrop-blur-xl border-t border-zinc-800 px-6 py-4">
    <div class="flex items-center justify-center gap-4">
      <button id="micBtn" class="control-btn bg-zinc-800 hover:bg-zinc-700" title="Toggle Microphone">
        <i data-lucide="mic" class="w-5 h-5"></i>
      </button>

      <button id="cameraBtn" class="control-btn bg-zinc-800 hover:bg-zinc-700" title="Toggle Camera">
        <i data-lucide="video" class="w-5 h-5"></i>
      </button>

      <button id="screenBtn" class="control-btn bg-zinc-800 hover:bg-zinc-700" title="Share Screen">
        <i data-lucide="monitor" class="w-5 h-5"></i>
      </button>

      <button id="chatBtn" class="control-btn bg-zinc-800 hover:bg-zinc-700" title="Chat">
        <i data-lucide="message-square" class="w-5 h-5"></i>
      </button>

      <button id="participantsBtn" class="control-btn bg-zinc-800 hover:bg-zinc-700" title="Participants">
        <i data-lucide="users" class="w-5 h-5"></i>
      </button>

      <button id="leaveBtn" class="control-btn bg-red-600 hover:bg-red-500" title="Leave Meeting">
        <i data-lucide="phone-off" class="w-5 h-5"></i>
      </button>
    </div>
  </footer>

  <div id="chatSidebar" class="fixed right-0 top-0 bottom-0 w-80 bg-zinc-900 border-l border-zinc-800 transform translate-x-full transition-transform duration-300 z-50">
    <div class="flex flex-col h-full">
      <div class="p-4 border-b border-zinc-800 flex items-center justify-between">
        <h3 class="font-semibold">Chat</h3>
        <button id="closeChatBtn" class="p-1 hover:bg-zinc-800 rounded">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <div id="chatMessages" class="flex-1 overflow-y-auto p-4 space-y-3"></div>

      <div class="p-4 border-t border-zinc-800">
        <div class="flex gap-2">
          <input type="text" id="chatInput" placeholder="Type a message..." class="flex-1 px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg focus:outline-none focus:border-purple-500 text-sm">
          <button id="sendChatBtn" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 rounded-lg transition-all">
            <i data-lucide="send" class="w-4 h-4"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  <div id="participantsSidebar" class="fixed right-0 top-0 bottom-0 w-80 bg-zinc-900 border-l border-zinc-800 transform translate-x-full transition-transform duration-300 z-50">
    <div class="flex flex-col h-full">
      <div class="p-4 border-b border-zinc-800 flex items-center justify-between">
        <h3 class="font-semibold">Participants</h3>
        <button id="closeParticipantsBtn" class="p-1 hover:bg-zinc-800 rounded">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <div id="participantsList" class="flex-1 overflow-y-auto p-4 space-y-2"></div>
    </div>
  </div>

  <script>
    window.SIGNALING_SERVER_URL = <?php echo json_encode($signalingUrl); ?>;
  </script>
  <script src="../../assets/js/meeting-room.js"></script>
  <script>
    if (window.lucide) {
      window.lucide.createIcons();
    }
  </script>
</body>
</html>
