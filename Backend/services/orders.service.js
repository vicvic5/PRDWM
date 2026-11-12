const express = require('express');
const cors = require('cors');
const db = require('../config/db');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT_ORDERS || 3003;
const INVENTORY_URL = `http://localhost:${process.env.PORT_INVENTORY || 3001}`;

app.use(cors());
app.use(express.json());

// POST /orders: Crear un nuevo pedido
app.post('/orders', async (req, res) => {
    const { usuario_id, carrito, datos_entrega } = req.body;

    if (!usuario_id || !carrito || carrito.length === 0 || !datos_entrega) {
        return res.status(400).json({ error: 'Faltan datos obligatorios para crear el pedido' });
    }

    const conexion = await db.getConnection();

    try {
        // Iniciar transacción de base de datos
        await conexion.beginTransaction();

        let totalGeneral = 0;

        // 1. Comunicarse con el microservicio de Inventario para restar stock
        for (const item of carrito) {
            totalGeneral += item.subtotal;

            const response = await fetch(`${INVENTORY_URL}/products/${item.producto_id}/stock`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cantidad: item.cantidad })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(`Error en producto ${item.producto_id}: ${errorData.error}`);
            }
        }

        // 2. Guardar el pedido principal en la tabla `pedidos`
        const [resultadoPedido] = await conexion.query(
            `INSERT INTO pedidos (usuario_id, total, estado, rut_envio, direccion_envio, comuna_envio) 
             VALUES (?, ?, 'pendiente', ?, ?, ?)`,
            [usuario_id, totalGeneral, datos_entrega.rut, datos_entrega.direccion, datos_entrega.comuna]
        );
        
        const pedidoId = resultadoPedido.insertId;

        // 3. Guardar cada producto en `detalles_pedido`
        for (const item of carrito) {
            await conexion.query(
                `INSERT INTO detalles_pedido (pedido_id, producto_id, cantidad, talla, subtotal) 
                 VALUES (?, ?, ?, ?, ?)`,
                [pedidoId, item.producto_id, item.cantidad, item.talla || null, item.subtotal]
            );
        }

        // Si todo salió bien, confirmar los cambios en la base de datos
        await conexion.commit();

        res.status(201).json({
            mensaje: 'Pedido generado con éxito',
            pedido_id: pedidoId,
            total: totalGeneral,
            estado: 'pendiente'
        });

    } catch (error) {
        // Si hay cualquier error (ej: falta de stock), revertir todos los cambios
        await conexion.rollback();
        console.error('[Orders Service] Error al procesar pedido:', error.message);
        res.status(409).json({ error: 'No se pudo completar el pedido', detalle: error.message });
    } finally {
        conexion.release();
    }
});

// GET /orders/:id: Obtener el resumen de un pedido
app.get('/orders/:id', async (req, res) => {
    try {
        const { id } = req.params;
        const [pedidos] = await db.query('SELECT * FROM pedidos WHERE id = ?', [id]);

        if (pedidos.length === 0) {
            return res.status(404).json({ error: 'Pedido no encontrado' });
        }

        const [detalles] = await db.query('SELECT * FROM detalles_pedido WHERE pedido_id = ?', [id]);

        res.status(200).json({
            pedido: pedidos[0],
            productos: detalles
        });
    } catch (error) {
        console.error('[Orders Service] Error al obtener pedido:', error);
        res.status(500).json({ error: 'Error interno del servidor' });
    }
});

app.listen(PORT, () => {
    console.log(`[Microservicio Orders] activo en http://localhost:${PORT}`);
});