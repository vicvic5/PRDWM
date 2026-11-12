const express = require('express');
const cors = require('cors');
const proxy = require('express-http-proxy');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT_GATEWAY || 3000;

app.use(cors());
app.use(express.json());

// Logger de solicitudes en consola
app.use((req, res, next) => {
    console.log(`[Gateway] ${req.method} ${req.url} -> ${new Date().toLocaleTimeString()}`);
    next();
});

// Middleware de autenticación en la frontera (Frontera Pública)
const verificarAuth = (req, res, next) => {
    const authHeader = req.headers['authorization'];

    // Si no viene la cabecera o no empieza con "Bearer ", rechaza inmediatamente con 401
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
        return res.status(401).json({
            error: "Autenticación requerida",
            codigo: "UNAUTHORIZED",
            mensaje: "No se proporcionó un token Bearer válido en la cabecera Authorization"
        });
    }

    const token = authHeader.split(' ')[1];
    if (!token || token === 'null' || token === 'undefined' || token === '') {
        return res.status(401).json({
            error: "Token ausente o inválido",
            codigo: "UNAUTHORIZED"
        });
    }

    // Opcional: verificación de firma criptográfica con JWT_SECRET
    try {
        const jwt = require('jsonwebtoken');
        const decoded = jwt.verify(token, process.env.JWT_SECRET || 'secret');
        req.user = decoded;
    } catch (err) {
        if (err.name === 'JsonWebTokenError' || err.name === 'TokenExpiredError') {
            return res.status(401).json({
                error: "Token inválido o expirado",
                codigo: "UNAUTHORIZED"
            });
        }
    }

    next();
};

// Redirección hacia microservicio de Inventario (Público - Puerto 3001)
const INVENTORY_URL = `http://localhost:${process.env.PORT_INVENTORY || 3001}`;

app.use('/api/v1/products', proxy(INVENTORY_URL, {
    proxyReqPathResolver: (req) => {
        return `/products${req.url}`;
    }
}));

// Redirección hacia microservicio de Clientes/Auth (Público para login/registro - Puerto 3002)
const CLIENTS_URL = `http://localhost:${process.env.PORT_CLIENTS || 3002}`;

app.use('/api/v1/users', proxy(CLIENTS_URL, {
    proxyReqPathResolver: (req) => `/users${req.url}`
}));

app.use('/api/v1/auth', proxy(CLIENTS_URL, {
    proxyReqPathResolver: (req) => `/auth${req.url}`
}));

// Redirección protegida hacia microservicio de Pedidos (Puerto 3003)
const ORDERS_URL = `http://localhost:${process.env.PORT_ORDERS || 3003}`;

// Se aplica verificarAuth antes de derivar al proxy
app.use('/api/v1/orders', verificarAuth, proxy(ORDERS_URL, {
    proxyReqPathResolver: (req) => `/orders${req.url}`
}));

// Redirección protegida hacia microservicio de Pagos (Puerto 3004)
const PAYMENTS_URL = `http://localhost:${process.env.PORT_PAYMENTS || 3004}`;

app.use('/api/v1/payments', verificarAuth, proxy(PAYMENTS_URL, {
    proxyReqPathResolver: (req) => `/payments${req.url}`
}));

// Endpoint base de chequeo
app.get('/', (req, res) => {
    res.json({
        gateway: 'Urban Style API Gateway',
        estado: 'Online',
        version: '1.0.0',
        rutas: ['/api/v1/products', '/api/v1/orders', '/api/v1/payments', '/api/v1/users']
    });
});

app.listen(PORT, () => {
    console.log(`[API Gateway] activo en http://localhost:${PORT}`);
});