const express = require('express');
const cors = require('cors');
const bcrypt = require('bcrypt');
const jwt = require('jsonwebtoken');
const db = require('../config/db');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT_CLIENTS || 3002;
const SECRET = process.env.JWT_SECRET || 'clave_fallback';

app.use(cors());
app.use(express.json());

// POST /users: Registro de un nuevo usuario
app.post('/users', async (req, res) => {
    try {
        const { nombre, correo, password } = req.body;

        if (!nombre || !correo || !password) {
            return res.status(400).json({ error: 'Faltan datos obligatorios' });
        }

        // Verificar si el correo ya existe
        const [existentes] = await db.query('SELECT id FROM usuarios WHERE correo = ?', [correo]);
        if (existentes.length > 0) {
            return res.status(409).json({ error: 'El correo ya está registrado' });
        }

        // Hashear la contraseña con 10 rondas de sal
        const hash = await bcrypt.hash(password, 10);

        const [resultado] = await db.query(
            'INSERT INTO usuarios (nombre, correo, password_hash) VALUES (?, ?, ?)',
            [nombre, correo, hash]
        );

        res.status(201).json({ 
            mensaje: 'Usuario creado exitosamente',
            usuario_id: resultado.insertId 
        });
    } catch (error) {
        console.error('[Clients Service] Error al registrar usuario:', error);
        res.status(500).json({ error: 'Error interno al crear el usuario' });
    }
});

// POST /auth/login: Inicio de sesión y generación de JWT
app.post('/auth/login', async (req, res) => {
    try {
        const { correo, password } = req.body;

        if (!correo || !password) {
            return res.status(400).json({ error: 'Correo y contraseña son obligatorios' });
        }

        // Buscar al usuario por correo
        const [usuarios] = await db.query('SELECT * FROM usuarios WHERE correo = ?', [correo]);
        if (usuarios.length === 0) {
            return res.status(401).json({ error: 'Credenciales inválidas' });
        }

        const usuario = usuarios[0];

        // Comparar la contraseña ingresada con el hash de la base de datos
        const esValida = await bcrypt.compare(password, usuario.password_hash);
        if (!esValida) {
            return res.status(401).json({ error: 'Credenciales inválidas' });
        }

        // Generar token JWT válido por 2 horas
        const token = jwt.sign(
            { id: usuario.id, rol: usuario.rol, correo: usuario.correo },
            SECRET,
            { expiresIn: '2h' }
        );

        res.status(200).json({
            mensaje: 'Inicio de sesión exitoso',
            token: token,
            usuario: {
                id: usuario.id,
                nombre: usuario.nombre,
                rol: usuario.rol
            }
        });
    } catch (error) {
        console.error('[Clients Service] Error en login:', error);
        res.status(500).json({ error: 'Error interno en la autenticación' });
    }
});

app.listen(PORT, () => {
    console.log(`[Microservicio Clients] activo en http://localhost:${PORT}`);
});