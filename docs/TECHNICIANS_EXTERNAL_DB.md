# Configuración de Técnicos - Base de Datos Externa

## 📋 Descripción General

Esta implementación permite consumir información de técnicos y sus rutas desde una **base de datos MySQL externa** usando **stored procedures (SP)**, siguiendo las mejores prácticas de Laravel con patrón **Repository** y **Service**.

## 🏗️ Arquitectura

```
Controller (TechnicianController)
    ↓
Service (TechnicianService) → Implementa caché
    ↓
Repository (DbTechnicianRepository) → Llama a Stored Procedures
    ↓
Base de Datos Externa MySQL
```

## ⚙️ Configuración

### 1. Variables de Entorno (.env)

Agregar las siguientes variables a tu archivo `.env`:

```env
# Base de datos externa MySQL (Técnicos)
DB_EXTERNAL_HOST=127.0.0.1
DB_EXTERNAL_PORT=3306
DB_EXTERNAL_DATABASE=external_technicians_db
DB_EXTERNAL_USERNAME=root
DB_EXTERNAL_PASSWORD=your_password
```

### 2. Conexión de Base de Datos

La conexión `mysql_external` ya está configurada en `config/database.php`.

## 📊 Stored Procedures Requeridos

Crear los siguientes stored procedures en tu base de datos MySQL externa:

### SP 1: Obtener todos los técnicos con rutas

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_technicians_with_routes()
BEGIN
    SELECT 
        t.id,
        t.code AS technician_code,
        t.name AS technician_name,
        t.email,
        t.phone,
        t.department,
        t.position,
        t.status,
        JSON_ARRAYAGG(
            JSON_OBJECT(
                'route_id', r.id,
                'route_name', r.name,
                'route_code', r.code
            )
        ) AS routes,
        t.created_at,
        t.updated_at
    FROM technicians t
    LEFT JOIN technician_routes tr ON t.id = tr.technician_id
    LEFT JOIN routes r ON tr.route_id = r.id
    GROUP BY t.id
    ORDER BY t.name ASC;
END$$

DELIMITER ;
```

### SP 2: Obtener técnico por ID

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_technician_by_id(IN tech_id INT)
BEGIN
    SELECT 
        t.id,
        t.code,
        t.name,
        t.email,
        t.phone,
        t.department,
        t.position,
        t.status,
        JSON_ARRAYAGG(
            JSON_OBJECT(
                'route_id', r.id,
                'route_name', r.name,
                'route_code', r.code
            )
        ) AS routes,
        t.created_at,
        t.updated_at
    FROM technicians t
    LEFT JOIN technician_routes tr ON t.id = tr.technician_id
    LEFT JOIN routes r ON tr.route_id = r.id
    WHERE t.id = tech_id
    GROUP BY t.id;
END$$

DELIMITER ;
```

### SP 3: Obtener técnicos por departamento

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_technicians_by_department(IN dept_name VARCHAR(100))
BEGIN
    SELECT 
        t.id,
        t.code,
        t.name,
        t.email,
        t.phone,
        t.department,
        t.position,
        t.status,
        t.created_at,
        t.updated_at
    FROM technicians t
    WHERE t.department = dept_name
    ORDER BY t.name ASC;
END$$

DELIMITER ;
```

### SP 4: Obtener técnicos activos

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_active_technicians()
BEGIN
    SELECT 
        t.id,
        t.code,
        t.name,
        t.email,
        t.phone,
        t.department,
        t.position,
        t.status,
        t.created_at,
        t.updated_at
    FROM technicians t
    WHERE t.status = 'active'
    ORDER BY t.name ASC;
END$$

DELIMITER ;
```

### SP 5: Obtener rutas de un técnico

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_technician_routes(IN tech_id INT)
BEGIN
    SELECT 
        r.id,
        r.code,
        r.name,
        r.description,
        tr.assigned_date,
        tr.status AS assignment_status
    FROM routes r
    INNER JOIN technician_routes tr ON r.id = tr.route_id
    WHERE tr.technician_id = tech_id
    ORDER BY r.name ASC;
END$$

DELIMITER ;
```

## 🔌 Endpoints de API

Todas las rutas están protegidas con `auth:sanctum`:

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/technicians` | Obtener todos los técnicos con rutas |
| GET | `/api/technicians/active` | Obtener solo técnicos activos |
| GET | `/api/technicians/{id}` | Obtener un técnico específico |
| GET | `/api/technicians/{id}/routes` | Obtener rutas de un técnico |
| GET | `/api/technicians/department/{department}` | Obtener técnicos por departamento |
| POST | `/api/technicians/cache/clear` | Limpiar caché de técnicos |

## 📝 Ejemplos de Uso

### Obtener todos los técnicos

```bash
GET /api/technicians
Authorization: Bearer {token}
```

**Respuesta:**
```json
{
    "data": [
        {
            "id": 1,
            "code": "TEC001",
            "name": "Juan Pérez",
            "email": "juan@example.com",
            "phone": "+1234567890",
            "department": "Mantenimiento",
            "position": "Técnico Senior",
            "status": "active",
            "routes": [
                {
                    "route_id": 1,
                    "route_name": "Ruta Norte",
                    "route_code": "RN001"
                }
            ]
        }
    ],
    "meta": {
        "total": 1,
        "source": "external_database"
    }
}
```

### Obtener técnicos por departamento

```bash
GET /api/technicians/department/Mantenimiento
Authorization: Bearer {token}
```

### Obtener rutas de un técnico

```bash
GET /api/technicians/1/routes
Authorization: Bearer {token}
```

## 🚀 Características Implementadas

✅ **Patrón Repository**: Separación de la lógica de acceso a datos  
✅ **Patrón Service**: Lógica de negocio centralizada  
✅ **Data Transfer Objects (DTO)**: Transferencia de datos tipada  
✅ **API Resources**: Transformación consistente de respuestas  
✅ **Caché**: Implementado con TTL de 60 minutos  
✅ **Manejo de Errores**: Try-catch con logging  
✅ **Inyección de Dependencias**: Usando Service Provider  
✅ **Stored Procedures**: Consultas optimizadas en DB externa  
✅ **Documentación**: Código comentado y tipado  

## 🔧 Personalización

### Cambiar tiempo de caché

Edita `app/Services/TechnicianService.php`:

```php
private const CACHE_TTL = 60; // minutos
```

### Modificar nombre de Stored Procedures

Edita `app/Repositories/DbTechnicianRepository.php` y cambia los nombres de los SP en las llamadas:

```php
DB::connection(self::DB_CONNECTION)
    ->select('CALL tu_nombre_de_sp()');
```

### Agregar más métodos

1. Agregar método a la interfaz: `TechnicianRepositoryInterface.php`
2. Implementar en el repositorio: `DbTechnicianRepository.php`
3. Agregar al servicio: `TechnicianService.php` y `TechnicianServiceInterface.php`
4. Crear endpoint en el controlador: `TechnicianController.php`
5. Agregar ruta en: `routes/api.php`

## 📦 Estructura de Archivos Creados

```
app/
├── DataTransferObjects/
│   └── TechnicianData.php
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── TechnicianController.php
│   └── Resources/
│       └── TechnicianResource.php
├── Repositories/
│   ├── TechnicianRepositoryInterface.php
│   └── DbTechnicianRepository.php
├── Services/
│   ├── TechnicianServiceInterface.php
│   └── TechnicianService.php
└── Providers/
    └── AppServiceProvider.php (modificado)

config/
└── database.php (modificado)

routes/
└── api.php (modificado)
```

## 🧪 Testing

Para probar la conexión:

```bash
php artisan tinker
```

```php
// Probar conexión
DB::connection('mysql_external')->getPdo();

// Probar SP
DB::connection('mysql_external')->select('CALL sp_get_active_technicians()');
```

## 🛡️ Seguridad

- Todas las rutas están protegidas con Sanctum
- Los parámetros de SP usan binding para prevenir SQL injection
- Las excepciones se capturan y se registran en logs
- No se exponen detalles internos en las respuestas de error en producción

## 📚 Mejores Prácticas Implementadas

1. **SOLID Principles**: Separación de responsabilidades
2. **Dependency Injection**: Usando interfaces
3. **Repository Pattern**: Abstracción de datos
4. **Service Layer**: Lógica de negocio
5. **Caching**: Reducción de llamadas a BD
6. **Error Handling**: Try-catch con logging
7. **Type Hinting**: PHP 8.2+ features
8. **API Resources**: Respuestas consistentes
9. **Documentation**: Código autodocumentado
10. **PSR-12**: Code standards

## 🐛 Troubleshooting

### Error de conexión a BD externa

Verifica las credenciales en `.env` y prueba la conexión:

```bash
php artisan tinker
DB::connection('mysql_external')->getPdo();
```

### Stored Procedure no existe

```sql
-- Listar SPs disponibles
SHOW PROCEDURE STATUS WHERE Db = 'tu_base_datos';
```

### Limpiar caché

```bash
# Via API
POST /api/technicians/cache/clear

# Via Artisan
php artisan cache:clear
```
