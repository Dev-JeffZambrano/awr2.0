# Modelo entidad-relación de AWR

Esquema `public` de PostgreSQL. Hay 28 tablas. Las líneas representan solo claves foráneas existentes.

Notación:

- `||` exactamente uno
- `o|` cero o uno
- `o{` cero o muchos

## Organización y facturación

![Organización y facturación](er-organizacion-facturacion.png)

```mermaid
erDiagram
    empresa ||--o{ sucursal : tiene
    empresa ||--o| configuracion_facturacion : configura
    empresa ||--o{ factura : emite
    sucursal ||--o{ punto_emision : abre
    sucursal ||--o{ factura : origina
    punto_emision ||--o{ factura : emite
    punto_emision ||--o{ secuencial_comprobante : numera
    tipo_comprobante ||--o{ secuencial_comprobante : numera
    tipo_comprobante ||--o{ factura : clasifica
    cliente ||--o{ factura : recibe
    usuario ||--o{ factura : emite
    usuario |o--o{ factura : anula
    operacion_idempotente ||--o| factura : registra
    operacion_idempotente |o--o| factura : anula
    factura ||--o{ factura_detalle : contiene
    factura ||--o{ pago : cobra
    metodo_pago ||--o{ pago : aplica
    producto ||--o{ factura_detalle : detalla
    impuesto ||--o{ factura_detalle : grava

    empresa {
        int id_empresa PK
        varchar identificacion UK
        varchar nombre
        boolean estado
    }
    sucursal {
        int id_sucursal PK
        int id_empresa FK
        varchar codigo UK
        varchar codigo_establecimiento UK
    }
    configuracion_facturacion {
        int id_configuracion PK
        int id_empresa FK UK
        varchar moneda
        varchar ambiente
    }
    punto_emision {
        int id_punto_emision PK
        int id_sucursal FK
        varchar codigo
    }
    tipo_comprobante {
        int id_tipo_comprobante PK
        varchar codigo UK
    }
    secuencial_comprobante {
        int id_secuencial PK
        int id_punto_emision FK
        int id_tipo_comprobante FK
        int ultimo_numero
    }
    cliente {
        int id_cliente PK
        varchar tipo_identificacion
        varchar identificacion
    }
    factura {
        int id_factura PK
        uuid id_operacion FK UK
        uuid id_operacion_anulacion FK UK
        int id_empresa FK
        int id_sucursal FK
        int id_punto_emision FK
        int id_tipo_comprobante FK
        int id_cliente FK
        int id_usuario FK
        int id_usuario_anulacion FK
        varchar numero_comprobante
        varchar estado
    }
    factura_detalle {
        int id_factura_detalle PK
        int id_factura FK
        int id_producto FK
        int id_impuesto FK
        int cantidad
    }
    pago {
        int id_pago PK
        int id_factura FK
        int id_metodo_pago FK
        numeric monto
    }
    metodo_pago {
        int id_metodo_pago PK
        varchar codigo UK
    }
```

`secuencial_comprobante` admite una sola fila por cada par `id_punto_emision` + `id_tipo_comprobante`. `factura` admite un solo `numero_comprobante` por empresa y un solo `secuencial` por punto, tipo y número.

## Inventario y resiliencia

![Inventario y resiliencia](er-inventario-resiliencia.png)

```mermaid
erDiagram
    categoria ||--o{ producto : agrupa
    proveedor ||--o{ producto : abastece
    impuesto |o--o{ producto : grava
    sucursal ||--o{ inventario_sucursal : almacena
    producto ||--o{ inventario_sucursal : existe
    sucursal ||--o{ movimiento_inventario : mueve
    producto ||--o{ movimiento_inventario : mueve
    usuario ||--o{ movimiento_inventario : registra
    operacion_idempotente ||--o{ movimiento_inventario : agrupa
    factura |o--o{ movimiento_inventario : provoca
    sucursal ||--o{ operacion_idempotente : ejecuta
    usuario ||--o{ operacion_idempotente : ejecuta

    categoria {
        int id_categoria PK
        varchar nombre UK
    }
    proveedor {
        int id_proveedor PK
        varchar identificacion UK
        varchar tipo_identificacion
    }
    impuesto {
        int id_impuesto PK
        varchar codigo UK
        numeric porcentaje
    }
    producto {
        int id_producto PK
        int id_categoria FK
        int id_proveedor FK
        int id_impuesto FK
        varchar codigo UK
        varchar codigo_barras UK
    }
    inventario_sucursal {
        int id_inventario PK
        int id_sucursal FK
        int id_producto FK
        int stock_actual
        int stock_minimo
        int stock_maximo
    }
    movimiento_inventario {
        int id_movimiento PK
        uuid id_operacion FK
        int id_sucursal FK
        int id_producto FK
        int id_usuario FK
        int id_factura FK
        varchar tipo_movimiento
        int cantidad
    }
    operacion_idempotente {
        uuid id_operacion PK
        varchar tipo_operacion
        int id_usuario FK
        int id_sucursal FK
    }
```

`inventario_sucursal` tiene una fila como máximo por cada par sucursal y producto. `id_factura` en el movimiento puede quedar vacío. `id_impuesto` en `producto` también puede quedar vacío.

## Facturación resiliente

La imagen comienza cuando el usuario confirma una factura y separa claramente sus dos caminos:

- **Sin latencia:** la factura se guarda inmediatamente en PostgreSQL.
- **Con latencia:** la factura entra en Redpanda; cuando la latencia vuelve a ser aceptable, el consumidor la guarda en PostgreSQL.

Los dos caminos llegan a las mismas tablas. `id_operacion` evita guardar dos veces la misma factura. Mientras el mensaje espera en Redpanda todavía no existe una fila en `factura`. El stock se descuenta cuando la factura finalmente se guarda en PostgreSQL.

`FACTURA_CONFIRMADA` representa el evento inicial y Redpanda representa el sistema externo; no son tablas de PostgreSQL. Las demás entidades sí corresponden al modelo transaccional existente.

![Modelo entidad-relación de facturación resiliente](er-facturacion-dos-caminos.png)

```mermaid
erDiagram
    FACTURA_CONFIRMADA ||--o| operacion_idempotente : "SIN LATENCIA guarda directo"
    FACTURA_CONFIRMADA ||--o| REDPANDA : "CON LATENCIA publica"
    REDPANDA ||--o| operacion_idempotente : "AL RECUPERARSE guarda"
    operacion_idempotente ||--o| factura : registra
    factura ||--|{ factura_detalle : contiene
    factura ||--|{ pago : cobra
    operacion_idempotente ||--o{ movimiento_inventario : agrupa
    factura ||--o{ movimiento_inventario : provoca
    sucursal ||--o{ inventario_sucursal : almacena
    producto ||--o{ inventario_sucursal : existe
    movimiento_inventario }o--|| inventario_sucursal : actualiza

    FACTURA_CONFIRMADA {
        uuid id_operacion
        varchar ruta_seleccionada
    }
    REDPANDA {
        varchar topic
        uuid id_operacion
        json factura_pendiente
    }
    operacion_idempotente {
        uuid id_operacion PK
        varchar tipo_operacion
        int id_usuario FK
        int id_sucursal FK
    }
    factura {
        int id_factura PK
        uuid id_operacion UK
        int id_sucursal FK
        int id_cliente FK
        int id_usuario FK
        varchar numero_comprobante
        varchar estado
    }
    factura_detalle {
        int id_factura_detalle PK
        int id_factura FK
        int id_producto FK
        int cantidad
    }
    pago {
        int id_pago PK
        int id_factura FK
        int id_metodo_pago FK
        numeric monto
    }
    movimiento_inventario {
        int id_movimiento PK
        uuid id_operacion FK
        int id_factura FK
        int id_producto FK
        int id_sucursal FK
        varchar tipo_movimiento
        int cantidad
    }
    inventario_sucursal {
        int id_inventario PK
        int id_sucursal FK
        int id_producto FK
        int stock_actual
        int stock_minimo
    }
    sucursal {
        int id_sucursal PK
    }
    producto {
        int id_producto PK
    }
```

## Seguridad y roles

![Seguridad y roles](er-seguridad.png)

```mermaid
erDiagram
    usuario ||--o{ usuario_rol : recibe
    rol ||--o{ usuario_rol : asigna
    rol ||--o{ rol_permiso : concede
    permiso ||--o{ rol_permiso : concede
    usuario ||--o{ usuario_sucursal : trabaja
    sucursal ||--o{ usuario_sucursal : admite

    usuario {
        int id_usuario PK
        varchar username UK
        varchar password_hash
        boolean estado
        timestamptz ultimo_acceso
    }
    rol {
        int id_rol PK
        varchar nombre UK
        boolean estado
    }
    permiso {
        int id_permiso PK
        varchar codigo UK
        boolean estado
    }
    usuario_rol {
        int id_usuario PK
        int id_rol PK
        boolean estado
        timestamptz fecha_asignacion
    }
    rol_permiso {
        int id_rol PK
        int id_permiso PK
        timestamptz fecha_asignacion
    }
    usuario_sucursal {
        int id_usuario PK
        int id_sucursal PK
        boolean estado
        timestamptz fecha_asignacion
    }
    sucursal {
        int id_sucursal PK
        int id_empresa FK
    }
    auditoria_login {
        int id_auditoria PK
        varchar username
        varchar resultado
        timestamptz fecha
    }
```

`usuario_rol`, `usuario_sucursal` y `rol_permiso` son las tres tablas puente. Un usuario puede tener varios roles y varias sucursales, o ninguno. `auditoria_login` no tiene clave foránea.

## Fuera del modelo de negocio

`knex_migrations` y `knex_migrations_lock` solo registran migraciones y no apuntan a ninguna tabla de AWR.

## Relación probable no confirmada por FK

`auditoria_login.username` coincide con `usuario.username`, pero PostgreSQL no declara esa clave.
