# POST /api/solicitudes/registrar-completa

Endpoint para registrar una solicitud completa desde móvil usando `multipart/form-data`.

## Request

- `id_usuario_solicitante` requerido, entero positivo.
- `justificacion` opcional, texto.
- `es_pedido_compra` requerido, `0` o `1`.
- `prioridad` opcional, `Baja|Media|Alta|Urgente`, default `Media`.
- `fecha_necesaria` opcional, fecha.
- `tipo_entrega_preferida` opcional, `Directo|Delivery`, default `Directo`.
- `id_direccion_entrega` opcional, entero positivo.

Por categoría:

- `id_producto_insumos[]`
- `cantidad_insumos[]`
- `observacion_insumos[]`
- `foto_insumos[]`

- `id_producto_ssgg[]`
- `cantidad_ssgg[]`
- `observacion_ssgg[]`
- `foto_ssgg[]`

- `id_producto_rrhh[]`
- `cantidad_rrhh[]`
- `observacion_rrhh[]`
- `foto_rrhh[]`

Reglas:

- `id_producto_*` es `id_inventario`.
- Se ignoran filas con `id_inventario <= 0` o `cantidad <= 0`.
- No se permiten `id_inventario` duplicados entre categorías.
- Si el producto requiere foto, el archivo debe venir en el mismo índice.

## Response exitoso

```json
{
  "success": true,
  "message": "Solicitud registrada correctamente.",
  "ticket": "SOL-000123",
  "uploaded_files": [
    {
      "id_inventario": 202,
      "path": "uploads/solicitudes/123/sol_123_inv_202_20260413120000_ab12cd34.jpg",
      "original_name": "rrhh.jpg"
    }
  ]
}
```

## Response de error

```json
{
  "success": false,
  "message": "Error: El producto Guantes requiere foto y no se adjuntó archivo en la categoría rrhh."
}
```

## Variables de entorno

- `SOLICITUD_AREA_COMPRAS_ID`: área que se agrega cuando `es_pedido_compra=1`.
- `PEDIDO_COMPRA_NOTIFY_EMAIL`: correo destino para pedidos de compra.
- `SMTP_ALWAYS_CC`: lista separada por comas de correos en CC.
