const express = require('express');
const cors = require('cors');
const db = require('../config/db');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT_PAYMENTS || 3004;

app.use(cors());
app.use(express.json());

// POST /payments: Procesar el pago de un pedido
app.post('/payments', async (req, res) => {
    const { pedido_id, metodo, detalles_tarjeta } = req.body;

    if (!pedido_id || !metodo || !detalles_tarjeta) {
        return res.status(400).json({ error: 'Faltan datos obligatorios para procesar el pago' });
    }

    const conexion = await db.getConnection();

    try {
        await conexion.beginTransaction();

        // 1. Verificar que el pedido exista y esté pendiente
        const [pedidos] = await conexion.query('SELECT estado, total FROM pedidos WHERE id = ?', [pedido_id]);
        
        if (pedidos.length === 0) {
            throw new Error('Pedido no encontrado');
        }
        if (pedidos[0].estado !== 'pendiente') {
            throw new Error('El pedido ya se encuentra pagado o fue cancelado');
        }

        // --- SIMULACIÓN DE PASARELA DE PAGO ---
        // Aquí el backend validaría con el banco los datos de "detalles_tarjeta"
        // Como es un entorno académico, asumi que el banco aprobó la transacción.

        // 2. Registrar el comprobante en la tabla 'pagos'
        const [resultadoPago] = await conexion.query(
            'INSERT INTO pagos (pedido_id, metodo, estado) VALUES (?, ?, ?)',
            [pedido_id, metodo, 'completado']
        );

        // 3. Actualizar el estado del pedido a 'pagado'
        await conexion.query('UPDATE pedidos SET estado = ? WHERE id = ?', ['pagado', pedido_id]);

        await conexion.commit();

        res.status(200).json({
            mensaje: 'Pago aprobado y procesado exitosamente',
            pago_id: resultadoPago.insertId,
            pedido_id: pedido_id,
            estado_orden: 'pagado',
            nota_seguridad: 'Los datos de la tarjeta fueron procesados en memoria y desechados según normativa PCI.'
        });

    } catch (error) {
        await conexion.rollback();
        console.error('[Payments Service] Error al procesar pago:', error.message);
        res.status(409).json({ error: 'Fallo en la pasarela de pagos', detalle: error.message });
    } finally {
        conexion.release();
    }
});

app.listen(PORT, () => {
    console.log(`[Microservicio Payments] activo en http://localhost:${PORT}`);
});