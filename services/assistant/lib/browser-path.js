import fs from 'node:fs/promises';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import puppeteer from 'puppeteer';

const safeText = (value) => String(value ?? '').trim();

const unique = (values = []) => Array.from(new Set(values.map(safeText).filter(Boolean)));

const fileExists = async (candidate) => {
  const resolved = safeText(candidate);
  if (!resolved) {
    return false;
  }

  try {
    await fs.access(resolved);
    return true;
  } catch (_error) {
    return false;
  }
};

const collectWindowsCandidates = () => {
  const programFiles = safeText(process.env.ProgramFiles);
  const programFilesX86 = safeText(process.env['ProgramFiles(x86)']);
  const localAppData = safeText(process.env.LOCALAPPDATA);

  return [
    path.join(programFiles, 'Google', 'Chrome', 'Application', 'chrome.exe'),
    path.join(programFilesX86, 'Google', 'Chrome', 'Application', 'chrome.exe'),
    path.join(programFiles, 'Google', 'Chrome SxS', 'Application', 'chrome.exe'),
    path.join(programFiles, 'Microsoft', 'Edge', 'Application', 'msedge.exe'),
    path.join(programFilesX86, 'Microsoft', 'Edge', 'Application', 'msedge.exe'),
    path.join(localAppData, 'Google', 'Chrome', 'Application', 'chrome.exe'),
    path.join(localAppData, 'Chromium', 'Application', 'chrome.exe'),
    path.join(localAppData, 'Microsoft', 'Edge', 'Application', 'msedge.exe'),
  ];
};

const collectDarwinCandidates = () => [
  '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  '/Applications/Google Chrome Canary.app/Contents/MacOS/Google Chrome Canary',
  '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
  '/Applications/Chromium.app/Contents/MacOS/Chromium',
];

const collectLinuxCandidates = () => [
  '/usr/bin/google-chrome',
  '/usr/bin/google-chrome-stable',
  '/usr/bin/chromium',
  '/usr/bin/chromium-browser',
  '/usr/bin/chrome',
  '/usr/bin/msedge',
  '/snap/bin/chromium',
];

const collectShellCandidates = () => {
  const commands = process.platform === 'win32'
    ? [
        ['where', ['chrome']],
        ['where', ['msedge']],
        ['where', ['chromium']],
      ]
    : [
        ['which', ['google-chrome']],
        ['which', ['google-chrome-stable']],
        ['which', ['chromium']],
        ['which', ['chromium-browser']],
        ['which', ['chrome']],
        ['which', ['msedge']],
    ];

  const results = [];
  for (const [command, args] of commands) {
    try {
      const output = execFileSync(command, args, {
        encoding: 'utf8',
        windowsHide: true,
        stdio: ['ignore', 'pipe', 'ignore'],
      });
      results.push(
        ...String(output)
          .split(/\r?\n/)
          .map((line) => safeText(line))
          .filter(Boolean)
      );
    } catch (_error) {
    }
  }

  return results;
};

const collectBundledCandidates = () => {
  try {
    const bundled = typeof puppeteer.executablePath === 'function' ? puppeteer.executablePath() : '';
    return safeText(bundled) ? [bundled] : [];
  } catch (_error) {
    return [];
  }
};

export async function resolveChromeExecutablePath(extraCandidates = []) {
  const candidatePool = unique([
    ...extraCandidates,
    process.env.ASSISTANT_CHROME_EXECUTABLE_PATH,
    process.env.CHROME_EXECUTABLE_PATH,
    process.env.PUPPETEER_EXECUTABLE_PATH,
    ...(process.platform === 'win32' ? collectWindowsCandidates() : []),
    ...(process.platform === 'darwin' ? collectDarwinCandidates() : []),
    ...(process.platform === 'linux' ? collectLinuxCandidates() : []),
    ...collectShellCandidates(),
    ...collectBundledCandidates(),
  ]);

  for (const candidate of candidatePool) {
    if (await fileExists(candidate)) {
      return candidate;
    }
  }

  return '';
}
