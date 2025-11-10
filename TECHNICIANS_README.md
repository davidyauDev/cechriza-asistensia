# 🔧 Módulo de Técnicos - Conexión a Base de Datos Externa

## ⚡ Inicio Rápido

### 1. Configurar Variables de Entorno

Agregar a tu `.env`:

```env
DB_EXTERNAL_HOST=127.0.0.1
DB_EXTERNAL_PORT=3306
DB_EXTERNAL_DATABASE=tu_base_datos_externa
DB_EXTERNAL_USERNAME=root
DB_EXTERNAL_PASSWORD=tu_contraseña
```

### 2. Crear el Stored Procedure en tu Base de Datos Externa

El sistema espera un stored procedure llamado `sp_get_rutas_tecnicos_dia` que recibe `emp_code` como parámetro.

Ver ejemplo en `database/external_db_example.sql`

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_rutas_tecnicos_dia(IN p_emp_code VARCHAR(50))
BEGIN
    -- Tu consulta personalizada aquí
    SELECT 
        orden_trabajo_id,
        fecha_programada,
        numero_orden,
        fecha_inicio,
        fecha_fin,
        estado_id,
        tecnico_nombre,
        tecnico_apellido,
        cliente_telefono,
        codigo_equipo,
        equipo_modelo
    FROM tu_tabla
    WHERE emp_code = p_emp_code
    AND DATE(fecha_programada) = CURDATE();
END$$

DELIMITER ;
```

### 3. Probar Conexión

```bash
php artisan tinker
```

```php
DB::connection('mysql_external')->getPdo();
// Si no hay error, la conexión funciona

// Probar el SP
DB::connection('mysql_external')->select('CALL sp_get_rutas_tecnicos_dia(?)', ['TEC001']);
```

### 4. Usar la API

#### Endpoint

```
GET /api/technicians/rutas-dia?emp_code={codigo}
```

#### Ejemplo de Petición

```bash
curl -X GET "http://localhost:8000/api/technicians/rutas-dia?emp_code=TEC001" \
     -H "Authorization: Bearer tu_token_sanctum" \
     -H "Accept: application/json"
```

#### Respuesta Ejemplo

```json
{
  "success": true,
  "data": [
    {
      "orden_trabajo_id": 75261,
      "fecha_programada": "2025-11-10 15:00:00",
      "numero_orden": "OS0075262",
      "fecha_inicio": "2025-11-10",
      "fecha_fin": "2025-11-10",
      "estado_id": 14,
      "tecnico_nombre": "Alexander Alfredo",
      "tecnico_apellido": "Ari Flores",
      "cliente_telefono": "72902960",
      "codigo_equipo": "K5A(K21K09-A05086)",
      "equipo_modelo": "MG2000(V2403-0510)"
    },
    {
      "orden_trabajo_id": 75262,
      "fecha_programada": "2025-11-10 15:00:00",
      "numero_orden": "OS0075263",
      "fecha_inicio": "2025-11-10",
      "fecha_fin": "2025-11-10",
      "estado_id": 14,
      "tecnico_nombre": "Alexander Alfredo",
      "tecnico_apellido": "Ari Flores",
      "cliente_telefono": "72902960",
      "codigo_equipo": "MG2000(V2403-0510)",
      "equipo_modelo": "MG3000"
    }
  ],
  "meta": {
    "emp_code": "TEC001",
    "total_rutas": 2,
    "fecha_consulta": "2025-11-10 16:30:45"
  }
}
```

## 📂 Archivos Creados

```
app/
├── Http/
│   └── Controllers/Api/TechnicianController.php  # Controlador con 1 método
├── Repositories/
│   ├── TechnicianRepositoryInterface.php         # Contrato
│   └── DbTechnicianRepository.php                # Llamada al SP
└── Services/
    ├── TechnicianServiceInterface.php            # Contrato
    └── TechnicianService.php                     # Lógica simple

config/database.php                               # Conexión mysql_external
routes/api.php                                    # Ruta GET /technicians/rutas-dia
database/external_db_example.sql                  # Ejemplo de SP y tablas
```

## 🎯 Características

- ✅ **Un solo endpoint**: Simple y directo
- ✅ **Stored Procedure**: Consulta optimizada en BD externa
- ✅ **Patrón Repository**: Separación de datos
- ✅ **Patrón Service**: Lógica de negocio
- ✅ **Error Handling**: Try-catch con logging
- ✅ **Validación**: Requiere emp_code
- ✅ **SOLID Principles**: Código mantenible

## 🔐 Seguridad

- Ruta protegida con Sanctum
- SQL injection prevenido con bindings
- Excepciones manejadas y logueadas
- Validación de parámetros obligatorios

## 🧪 Testing Rápido

```bash
# Verificar conexión
php artisan tinker
DB::connection('mysql_external')->getPdo();

# Probar SP directamente
DB::connection('mysql_external')->select('CALL sp_get_rutas_tecnicos_dia(?)', ['TEC001']);

# Probar servicio
app(\App\Services\TechnicianServiceInterface::class)->getRutasTecnicosDia('TEC001');
```

## � Personalización

### Cambiar nombre del Stored Procedure

Edita `app/Repositories/DbTechnicianRepository.php`:

```php
$results = DB::connection(self::DB_CONNECTION)
    ->select('CALL tu_nombre_de_sp(?)', [$empCode]);
```

### Cambiar nombre de la ruta

Edita `routes/api.php`:

```php
Route::get('/tu-ruta-personalizada', [TechnicianController::class, 'getRutasTecnicosDia']);
```

## 🐛 Troubleshooting

### Error de conexión a BD externa

Verifica las credenciales en `.env`:

```bash
php artisan tinker
DB::connection('mysql_external')->getPdo();
```

### Stored Procedure no existe

```sql
-- Listar SPs disponibles
SHOW PROCEDURE STATUS WHERE Db = 'tu_base_datos';

-- Ver definición del SP
SHOW CREATE PROCEDURE sp_get_rutas_tecnicos_dia;
```

### Ver logs de error

```powershell
Get-Content storage/logs/laravel.log -Tail 50
```

---

**Arquitectura simple:** Request → Controller → Service → Repository → Stored Procedure → Respuesta JSON
