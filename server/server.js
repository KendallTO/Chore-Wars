import express from 'express';
import cors from 'cors';
import fs from 'fs';
import fsp from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || 3000;

// Paths
const rootDir = path.resolve(__dirname, '..');
const staticDir = path.join(rootDir, 'chore_wars');
const dataDir = path.join(__dirname, 'data');
const usersFile = path.join(dataDir, 'users.txt');

// Ensure data dir and file exist
if (!fs.existsSync(dataDir)) {
  fs.mkdirSync(dataDir, { recursive: true });
}
if (!fs.existsSync(usersFile)) {
  fs.writeFileSync(usersFile, '');
}

// Middleware
app.use(cors());
app.use(express.json());

// Helper: read all users into memory (array of objects)
async function readUsers() {
  try {
    const content = await fsp.readFile(usersFile, 'utf8');
    const lines = content.split(/\r?\n/).filter(Boolean);
    const users = lines.map(line => {
      try { return JSON.parse(line); } catch { return null; }
    }).filter(Boolean);
    return users;
  } catch (e) {
    return [];
  }
}

// Helper: append a single user as a JSON line
async function appendUser(user) {
  const line = JSON.stringify(user) + '\n';
  await fsp.appendFile(usersFile, line, 'utf8');
}

// API: Register
// Body: { username, email, password }
app.post('/api/register', async (req, res) => {
  const { username, email = '', password } = req.body || {};
  if (!username || !password) {
    return res.status(400).json({ ok: false, error: 'Username and password required' });
  }
  const uname = String(username).trim();
  const unameLower = uname.toLowerCase();

  try {
    const users = await readUsers();
    const exists = users.some(u => (u.usernameLower || '').toLowerCase() === unameLower);
    if (exists) {
      return res.status(409).json({ ok: false, error: 'Username already exists' });
    }

    // NOTE: Password stored in plain text per request (hash later)
    const user = {
      username: uname,
      usernameLower: unameLower,
      email: String(email || ''),
      password: String(password),
      created: Date.now()
    };

    await appendUser(user);
    return res.json({ ok: true, username: user.username, email: user.email });
  } catch (e) {
    console.error('Register error:', e);
    return res.status(500).json({ ok: false, error: 'Server error' });
  }
});

// API: Login
// Body: { username, password }
app.post('/api/login', async (req, res) => {
  const { username, password } = req.body || {};
  if (!username || !password) {
    return res.status(400).json({ ok: false, error: 'Username and password required' });
  }
  const unameLower = String(username).trim().toLowerCase();
  try {
    const users = await readUsers();
    const user = users.find(u => (u.usernameLower || '').toLowerCase() === unameLower);
    if (!user) return res.status(404).json({ ok: false, error: 'Username not found' });
    if (String(user.password) !== String(password)) {
      return res.status(401).json({ ok: false, error: 'Incorrect password' });
    }
    return res.json({ ok: true, username: user.username, email: user.email || '' });
  } catch (e) {
    console.error('Login error:', e);
    return res.status(500).json({ ok: false, error: 'Server error' });
  }
});

// Serve static front-end
app.use(express.static(staticDir));

// Fallback to index/login routes if needed
app.get('/', (req, res) => {
  res.sendFile(path.join(staticDir, 'login.html'));
});

app.listen(PORT, () => {
  console.log(`Chore-Wars server running on http://localhost:${PORT}`);
});
