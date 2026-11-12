const express = require('express');
const cors = require('cors');
const db = require('../config/db');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT_INVENTORY || 3001;

app.use(cors());
app.use(express.json());

// Listar todos los productos o filtrar por coincidencia de búsqueda (?q=nombre)
app.get('/products', async (req, res) => {
    try {
        const { q } = req.query;
        let query = 'SELECT id, nombre, precio, img, es_accesorio, stock, agotado FROM productos';
        let params = [];

        if (q) {
            query += ' WHERE nombre LIKE ?';
            params.push(`%${q}%`);
        }

        const [rows] = await db.query(query, params);

        const productos = rows.map(prod => ({
            ...prod,
            es_accesorio: Boolean(prod.es_accesorio),
            agotado: Boolean(prod.agotado)
        }));

        res.status(200).json(productos);
    } catch (error) {
        console.error('[Inventory Service] Error al obtener productos:', error);
        res.status(500).json({ error: 'Error interno del servidor de inventario' });
    }
});

// Detalle de un producto individual por ID
app.get('/products/:id', async (req, res) => {
    try {
        const { id } = req.params;
        const [rows] = await db.query('SELECT * FROM productos WHERE id = ?', [id]);

        if (rows.length === 0) {
            return res.status(404).json({ error: 'Producto no encontrado' });
        }

        const producto = {
            ...rows[0],
            es_accesorio: Boolean(rows[0].es_accesorio),
            agotado: Boolean(rows[0].agotado)
        };

        res.status(200).json(producto);
    } catch (error) {
        console.error('[Inventory Service] Error al consultar producto:', error);
        res.status(500).json({ error: 'Error interno al consultar el producto' });
    }
});

// Actualización de stock tras venta (utilizado por el microservicio de Orders)
app.patch('/products/:id/stock', async (req, res) => {
    try {
        const { id } = req.params;
        const { cantidad } = req.body;

        if (!cantidad || cantidad <= 0) {
            return res.status(400).json({ error: 'Cantidad inválida' });
        }

        const [rows] = await db.query('SELECT stock FROM productos WHERE id = ?', [id]);
        if (rows.length === 0) {
            return res.status(404).json({ error: 'Producto no encontrado' });
        }

        const stockActual = rows[0].stock;
        if (stockActual < cantidad) {
            return res.status(409).json({ error: 'Stock insuficiente' });
        }

        const nuevoStock = stockActual - cantidad;
        const nuevoAgotado = nuevoStock === 0 ? 1 : 0;

        await db.query('UPDATE productos SET stock = ?, agotado = ? WHERE id = ?', [nuevoStock, nuevoAgotado, id]);

        res.status(200).json({
            mensaje: 'Stock actualizado con éxito',
            stockRestante: nuevoStock,
            agotado: Boolean(nuevoAgotado)
        });
    } catch (error) {
        console.error('[Inventory Service] Error al modificar stock:', error);
        res.status(500).json({ error: 'Error interno al procesar el inventario' });
    }
});

app.listen(PORT, () => {
    console.log(`[Microservicio Inventory] activo en http://localhost:${PORT}`);
});