import makeWASocket, { 
    DisconnectReason, 
    useMultiFileAuthState,
    fetchLatestBaileysVersion,
    makeCacheableSignalKeyStore
} from '@whiskeysockets/baileys';
import qrcode from 'qrcode-terminal';
import { Boom } from '@hapi/boom';
import express from 'express';
import pino from 'pino';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const logger = pino({ level: 'silent' });
const app = express();
app.use(express.json());

const PORT = process.env.WHATSAPP_PORT || 3000;

// Set ADMIN_WHATSAPP_NUMBER in Railway env vars (e.g. 27821234567 - no + or spaces)
const PHONE_NUMBER = process.env.ADMIN_WHATSAPP_NUMBER ? process.env.ADMIN_WHATSAPP_NUMBER.replace(/\D/g, '') : null;

let sock;
let isConnected = false;
let pairingCodeRequested = false;
let welcomeSent = false;

async function connectToWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState(path.join(__dirname, 'storage/whatsapp-session'));
    const { version, isLatest } = await fetchLatestBaileysVersion();
    console.log(`using WA v${version.join('.')}, isLatest: ${isLatest}`);
    
    sock = makeWASocket({
        auth: {
            creds: state.creds,
            keys: makeCacheableSignalKeyStore(state.keys, logger),
        },
        version: version,
        browser: ['Chrome (Mac)', 'Chrome', '110.0.5481.177'],
        syncFullHistory: false,
        logger: logger,
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;
        
        // Use pairing code instead of QR if phone number is set
        if (qr && PHONE_NUMBER && !pairingCodeRequested) {
            pairingCodeRequested = true;
            try {
                // Small delay to let the socket register first
                await new Promise(resolve => setTimeout(resolve, 3000));
                const code = await sock.requestPairingCode(PHONE_NUMBER);
                console.log('\n========================================');
                console.log(`   YOUR WHATSAPP PAIRING CODE: ${code}`);
                console.log('========================================');
                console.log('Go to WhatsApp > Settings > Linked Devices > Link a Device');
                console.log('Tap "Link with phone number" and enter the code above.\n');
            } catch (err) {
                console.error('Failed to request pairing code:', err.message);
            }
        } else if (qr && !PHONE_NUMBER) {
            // Fallback: print QR if no phone number is configured
            console.log('\n--- SCAN THIS QR CODE WITH WHATSAPP ---');
            qrcode.generate(qr, { small: true });
            console.log('(Set ADMIN_WHATSAPP_NUMBER env var to use pairing code instead)');
        }

        if (connection === 'close') {
            isConnected = false;
            pairingCodeRequested = false;
            const reason = (lastDisconnect?.error instanceof Boom) 
                ? lastDisconnect.error.output.statusCode 
                : 'unknown';
            
            console.log(`Connection closed. Reason: ${reason}. Reconnecting: ${shouldReconnect}`);
            if (shouldReconnect) connectToWhatsApp();
        } else if (connection === 'open') {
            isConnected = true;
            console.log('✅ WhatsApp Bridge: Connected!');
            // Send confirmation message to admin on connect (only once per restart)
            if (PHONE_NUMBER && !welcomeSent) {
                // Wait 10 seconds for the session to fully stabilize before sending
                setTimeout(async () => {
                    try {
                        const jid = `${PHONE_NUMBER}@s.whatsapp.net`;
                        await sock.sendMessage(jid, { 
                            text: '🚀 Welcome to Radar! Your WhatsApp bridge is now live and connected.' 
                        });
                        welcomeSent = true;
                        console.log(`✅ Confirmation message sent to ${PHONE_NUMBER}`);
                    } catch (err) {
                        console.error('Failed to send confirmation message:', err.message);
                    }
                }, 10000);
            }
        }
    });
}

app.post('/send', async (req, res) => {
    const { phone, message } = req.body;
    if (!isConnected) return res.status(503).json({ error: 'WhatsApp not connected' });
    if (!phone || !message) return res.status(400).json({ error: 'Phone and message are required' });

    try {
        const jid = phone.includes('@s.whatsapp.net') ? phone : `${phone.replace(/\D/g, '')}@s.whatsapp.net`;
        await sock.sendMessage(jid, { text: message });
        res.json({ success: true });
    } catch (error) {
        console.error('Failed to send message:', error);
        res.status(500).json({ error: 'Failed to send message' });
    }
});

app.get('/health', (req, res) => {
    res.json({ status: isConnected ? 'connected' : 'connecting' });
});

app.listen(PORT, () => {
    console.log(`🚀 WhatsApp Bridge running on port ${PORT}`);
    connectToWhatsApp();
});
