const express = require('express');
const bodyParser = require('body-parser');
const redis = require('redis');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 3000;
const REDIS_HOST = process.env.REDIS_HOST || 'localhost';
const REDIS_PORT = process.env.REDIS_PORT || 6379;

// Redis client
const client = redis.createClient({
    socket: {
        host: REDIS_HOST,
        port: REDIS_PORT
    }
});
client.on('error', (err) => console.log('Redis Client Error', err));

// Middleware
app.use(bodyParser.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, 'public')));
app.set('view engine', 'ejs');

// Routes
app.get('/', async (req, res) => {
    const keys = await client.keys('*');
    const keyValues = [];

    for (const key of keys) {
        const value = await client.get(key);
        keyValues.push({ key, value });
    }
    res.render('index', { keyValues });
});

app.post('/add-key', async (req, res) => {
    const { apiKey } = req.body;
    if (!apiKey) return res.redirect('/');
    
    await client.set(apiKey, 'active');
    res.redirect('/');
});

app.post('/delete-key', async (req, res) => {
    const { apiKey } = req.body;
    if (!apiKey) return res.redirect('/');
    
    await client.del(apiKey);
    res.redirect('/');
});

app.post('/toggle-status', async (req, res) => {
    const { apiKey } = req.body;
    if (!apiKey) return res.redirect('/');
    
    const currentStatus = await client.get(apiKey);
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    await client.set(apiKey, newStatus);
    res.redirect('/');
});

// Start server
app.listen(PORT, async () => {
    await client.connect();
    console.log(`Server running at http://localhost:${PORT}`);
});
