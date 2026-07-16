#!/usr/bin/env node
import qrcode from 'qrcode-terminal';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { makeWASocket, DisconnectReason, useMultiFileAuthState, jidNormalizedUser, fetchLatestBaileysVersion } from 'baileys';
import yargs from 'yargs';
import { hideBin } from 'yargs/helpers';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

let globalTimeoutId = null;

class WhatsAppConsole {
  socket = null;
  isConnected = false;
  authDir = path.join(__dirname, 'auth_info_baileys');
  statusFile = null;
  mode = 'interactive';
  loggingOut = false;
  connectedPromise = null;
  connectedResolve = null;

  constructor(mode = 'interactive') {
    this.mode = mode;

    if (mode === 'link') {
      this.statusFile = path.join(this.authDir, 'status.json');
    }

    this.connectedPromise = new Promise((resolve) => {
      this.connectedResolve = resolve;
    });
    this.ensureAuthDir();
  }

  ensureAuthDir() {
    if (!fs.existsSync(this.authDir)) {
      fs.mkdirSync(this.authDir, { recursive: true });
    }
  }

  writeStatus(patch) {
    if (!this.statusFile) return;
    let current = {};
    try {
      if (fs.existsSync(this.statusFile)) {
        current = JSON.parse(fs.readFileSync(this.statusFile, 'utf8'));
      }
    } catch (e) {
      current = {};
    }
    const next = { ...current, ...patch, updated_at: new Date().toISOString() };
    fs.writeFileSync(this.statusFile, JSON.stringify(next));
  }

  async start() {
    try {
      console.log('Starting WhatsApp...');
      const { state, saveCreds } = await useMultiFileAuthState(this.authDir);
      const { version, isLatest } = await fetchLatestBaileysVersion();
      console.log(`Using WA version ${version.join('.')}, isLatest: ${isLatest}`);

      this.socket = makeWASocket({
        auth: state,
        printQRInTerminal: false,
        version,
        markOnlineOnConnect: false,
        shouldIgnoreJid: () => false,
        shouldSyncHistoryMessage: () => true,
        logger: {
          level: 'silent',
          child: function () { return this; },
          trace: () => {},
          debug: () => {},
          info: () => {},
          warn: () => {},
          error: () => {},
        },
        browser: ['ManbaCenter', 'Desktop', '1.0.0'],
        generateHighQualityLinkPreview: false,
        defaultQueryTimeoutMs: 60000,
      });

      this.setupEventHandlers(saveCreds);
    } catch (error) {
      console.error('Failed to start WhatsApp:', error);
      if (this.mode === 'link') this.writeStatus({ status: 'error' });
      if (globalTimeoutId) clearTimeout(globalTimeoutId);
      process.exit(1);
    }
  }

  setupEventHandlers(saveCreds) {
    this.socket.ev.on('connection.update', async (update) => {
      const { connection, lastDisconnect, qr } = update;

      if (qr) {
        if (this.mode === 'link') {
          try {
            const { default: QRCode } = await import('qrcode');
            const dataUri = await QRCode.toDataURL(qr, { margin: 1, width: 300 });
            this.writeStatus({ status: 'qr_ready', qr_code: dataUri });
          } catch (e) {
            console.error('Failed to render QR:', e?.message || e);
            this.writeStatus({ status: 'error' });
          }
        } else {
          qrcode.generate(qr, { small: true });
          console.log('\nWaiting for QR code scan...\n');
        }
      }

      if (connection === 'close') {
        if (this.loggingOut) return;

        const statusCode = lastDisconnect?.error?.output?.statusCode;
        const shouldReconnect = statusCode !== DisconnectReason.loggedOut && statusCode !== 401;
        console.log(`Connection closed (statusCode=${statusCode ?? 'unknown'}): ${lastDisconnect?.error?.message || 'no message'}`);

        if (shouldReconnect) {
          console.log('Reconnecting...');
          await this.start();
        } else {
          console.log('Device logged out.');
          if (this.mode === 'link') this.writeStatus({ status: 'disconnected' });
          if (globalTimeoutId) clearTimeout(globalTimeoutId);
          process.exit(1);
        }
      } else if (connection === 'open') {
        console.log('Connected to WhatsApp!');

        const me = this.socket.user;
        const jid = jidNormalizedUser(me.id);

        if (this.mode === 'link') {
          const phone = String(me.id).split(':')[0].split('@')[0];
          const displayName = me?.name || me?.pushName || null;

          let picture = null;
          try {
            picture = await this.socket.profilePictureUrl(jid, 'image');
          } catch (e) {
            picture = null;
          }

          this.writeStatus({
            status: 'ready',
            qr_code: null,
            phone_number: phone,
            name: displayName,
            profile_picture_path: picture,
            connected_at: new Date().toISOString(),
          });

          this.isConnected = true;
          if (typeof this.connectedResolve === 'function') this.connectedResolve(true);
          console.log('Initialization complete.');
          if (globalTimeoutId) clearTimeout(globalTimeoutId);
          process.exit(0);
        }

        this.isConnected = true;
        if (typeof this.connectedResolve === 'function') this.connectedResolve(true);

        if (this.mode === 'init') {
          console.log('Initialization complete.');
          if (globalTimeoutId) clearTimeout(globalTimeoutId);
          process.exit(0);
        }
      }
    });

    this.socket.ev.on('creds.update', saveCreds);
  }
}

async function main() {
  const isLink = process.argv.includes('link');
  // Give plenty of time to scan. Baileys keeps rotating the QR while we are
  // alive, so the page always shows a fresh one. On timeout we mark the
  // session disconnected so the UI prompts a re-link instead of showing a
  // dead (expired) QR code.
  const timeoutMs = isLink ? 180000 : 60000;
  globalTimeoutId = setTimeout(() => {
    console.log(`Timeout after ${timeoutMs / 1000}s`);
    if (isLink) {
      try {
        const sf = path.join(__dirname, 'auth_info_baileys', 'status.json');
        if (fs.existsSync(sf)) {
          const cur = JSON.parse(fs.readFileSync(sf, 'utf8'));
          if (cur.status !== 'ready') {
            fs.writeFileSync(sf, JSON.stringify({
              ...cur, status: 'disconnected', qr_code: null,
              updated_at: new Date().toISOString(),
            }));
          }
        }
      } catch (e) { /* ignore */ }
    }
    process.exit(0);
  }, timeoutMs);
  const timeoutId = globalTimeoutId;

  const argv = yargs(hideBin(process.argv))
    .command('init', 'Initialize WhatsApp (scan QR and exit)')
    .command('link', 'Link WhatsApp account (writes QR + status to disk)')
    .command('logout', 'Remove WhatsApp session')
    .command('send', 'Send a WhatsApp message', {
      phone: { alias: 'p', type: 'string', demandOption: true, describe: 'Recipient phone number' },
      message: { alias: 'm', type: 'string', describe: 'Message text' },
      media: { type: 'string', describe: 'Path to attachment (image/video/PDF)' },
    })
    .help(false)
    .version(false)
    .parse();

  const cmd = argv._[0] || 'interactive';

  if (cmd === 'init') {
    const app = new WhatsAppConsole('init');
    await app.start();
    await app.connectedPromise;
    clearTimeout(timeoutId);
    return;
  }

  if (cmd === 'link') {
    const app = new WhatsAppConsole('link');
    app.writeStatus({ status: 'initializing' });
    await app.start();
    await app.connectedPromise;
    clearTimeout(timeoutId);
    return;
  }

  if (cmd === 'logout') {
    const dir = path.join(__dirname, 'auth_info_baileys');

    try {
      if (fs.existsSync(path.join(dir, 'creds.json'))) {
        const app = new WhatsAppConsole('logout');
        await app.start();
        const opened = await Promise.race([
          app.connectedPromise.then(() => true),
          new Promise((resolve) => setTimeout(() => resolve(false), 20000)),
        ]);
        app.loggingOut = true;
        try {
          await app.socket.logout();
          await new Promise((resolve) => setTimeout(resolve, 3500));
          console.log('Logged out from WhatsApp');
        } catch (e) {
          console.error('Logout call failed:', e?.message || e);
        }
      }
    } catch (e) {
      console.error('Could not connect to log out:', e?.message || e);
    }

    try {
      if (fs.existsSync(dir)) fs.rmSync(dir, { recursive: true, force: true });
      console.log('Session removed');
      clearTimeout(timeoutId);
      process.exit(0);
    } catch (error) {
      console.error('Error removing session:', error?.message || error);
      clearTimeout(timeoutId);
      process.exit(1);
    }
  }

  if (cmd === 'send') {
    const phone = (argv.phone || '').toString().replace(/\D+/g, '');
    const message = (argv.message || '').toString();
    const mediaPath = (argv.media || '').toString();

    if (!phone || (!message && !mediaPath)) {
      console.error('Missing --phone or (--message / --media)');
      if (globalTimeoutId) clearTimeout(globalTimeoutId);
      process.exit(2);
    }

    const app = new WhatsAppConsole('send');
    await app.start();
    await app.connectedPromise;

    try {
      const jid = `${phone}@s.whatsapp.net`;
      const exists = await app.socket.onWhatsApp(jid);
      if (!exists || !exists[0]?.exists) {
        console.error('Phone number does not exist on WhatsApp');
        if (globalTimeoutId) clearTimeout(globalTimeoutId);
        process.exit(1);
      }

      let content;
      if (mediaPath && fs.existsSync(mediaPath)) {
        const buffer = fs.readFileSync(mediaPath);
        const ext = path.extname(mediaPath).toLowerCase().replace('.', '');
        const fileName = path.basename(mediaPath);
        const caption = message || undefined;

        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
          content = { image: buffer, caption };
        } else if (['mp4', 'avi', 'mov', 'wmv', '3gp', 'mkv'].includes(ext)) {
          content = { video: buffer, caption };
        } else {
          content = {
            document: buffer,
            fileName,
            mimetype: ext === 'pdf' ? 'application/pdf' : 'application/octet-stream',
            caption,
          };
        }
      } else {
        content = { text: message };
      }

      await app.socket.sendMessage(jid, content);
      console.log('Message sent successfully');
      clearTimeout(timeoutId);
      process.exit(0);
    } catch (error) {
      console.error('Error sending message:', error?.message || error);
      clearTimeout(timeoutId);
      process.exit(1);
    }
  }

  const app = new WhatsAppConsole('interactive');
  await app.start();
}

process.on('SIGINT', () => {
  if (globalTimeoutId) clearTimeout(globalTimeoutId);
  process.exit(0);
});

process.on('SIGTERM', () => {
  if (globalTimeoutId) clearTimeout(globalTimeoutId);
  process.exit(0);
});

main().catch(console.error);
