# 📋 Ejemplos de Stored Procedures - Casos Comunes

Este archivo contiene ejemplos adicionales de stored procedures para diferentes necesidades.

## 🔍 Variantes de Consultas

### 1. Técnicos con Filtro de Fecha

Obtener técnicos que tienen rutas asignadas en un rango de fechas:

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_technicians_by_date_range(
    IN start_date DATE,
    IN end_date DATE
)
BEGIN
    SELECT DISTINCT
        t.id,
        t.code,
        t.name,
        t.email,
        t.phone,
        t.department,
        t.position,
        t.status,
        COUNT(tr.route_id) AS total_routes
    FROM technicians t
    INNER JOIN technician_routes tr ON t.id = tr.technician_id
    WHERE tr.assigned_date BETWEEN start_date AND end_date
    GROUP BY t.id, t.code, t.name, t.email, t.phone, t.department, t.position, t.status
    ORDER BY t.name ASC;
END$$

DELIMITER ;
```

### 2. Estadísticas de Técnico

Obtener estadísticas detalladas de un técnico:

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_technician_stats(IN tech_id INT)
BEGIN
    SELECT 
        t.id,
        t.code,
        t.name,
        COUNT(DISTINCT tr.route_id) AS total_routes,
        COUNT(CASE WHEN tr.status = 'active' THEN 1 END) AS active_routes,
        COUNT(CASE WHEN tr.status = 'pending' THEN 1 END) AS pending_routes,
        MIN(tr.assigned_date) AS first_assignment,
        MAX(tr.assigned_date) AS last_assignment
    FROM technicians t
    LEFT JOIN technician_routes tr ON t.id = tr.technician_id
    WHERE t.id = tech_id
    GROUP BY t.id, t.code, t.name;
END$$

DELIMITER ;
```

### 3. Técnicos Disponibles (Sin Ruta Específica)

Encontrar técnicos que NO tienen asignada una ruta específica:

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_available_technicians_for_route(IN route_id INT)
BEGIN
    SELECT 
        t.id,
        t.code,
        t.name,
        t.email,
        t.phone,
        t.department,
        t.position,
        t.status
    FROM technicians t
    WHERE t.status = 'active'
    AND t.id NOT IN (
        SELECT technician_id 
        FROM technician_routes 
        WHERE route_id = route_id 
        AND status IN ('active', 'pending')
    )
    ORDER BY t.name ASC;
END$$

DELIMITER ;
```

### 4. Búsqueda con Paginación

Técnicos con búsqueda y paginación:

```sql
DELIMITER $$

CREATE PROCEDURE sp_search_technicians(
    IN search_term VARCHAR(255),
    IN page_offset INT,
    IN page_limit INT
)
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
    WHERE (
        t.name LIKE CONCAT('%', search_term, '%')
        OR t.code LIKE CONCAT('%', search_term, '%')
        OR t.email LIKE CONCAT('%', search_term, '%')
        OR t.department LIKE CONCAT('%', search_term, '%')
    )
    ORDER BY t.name ASC
    LIMIT page_limit OFFSET page_offset;
    
    -- También retornar el total para paginación
    SELECT COUNT(*) AS total
    FROM technicians t
    WHERE (
        t.name LIKE CONCAT('%', search_term, '%')
        OR t.code LIKE CONCAT('%', search_term, '%')
        OR t.email LIKE CONCAT('%', search_term, '%')
        OR t.department LIKE CONCAT('%', search_term, '%')
    );
END$$

DELIMITER ;
```

### 5. Rutas con Carga de Trabajo

Rutas ordenadas por cantidad de técnicos asignados:

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_routes_workload()
BEGIN
    SELECT 
        r.id,
        r.code,
        r.name,
        r.description,
        r.status,
        COUNT(tr.technician_id) AS assigned_technicians,
        GROUP_CONCAT(
            CONCAT(t.code, ' - ', t.name) 
            ORDER BY t.name 
            SEPARATOR ', '
        ) AS technician_list
    FROM routes r
    LEFT JOIN technician_routes tr ON r.id = tr.route_id AND tr.status = 'active'
    LEFT JOIN technicians t ON tr.technician_id = t.id
    GROUP BY r.id, r.code, r.name, r.description, r.status
    ORDER BY assigned_technicians DESC, r.name ASC;
END$$

DELIMITER ;
```

### 6. Técnicos con Múltiples Rutas

Encontrar técnicos que tienen más de X rutas asignadas:

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_technicians_with_multiple_routes(IN min_routes INT)
BEGIN
    SELECT 
        t.id,
        t.code,
        t.name,
        t.department,
        t.status,
        COUNT(tr.route_id) AS total_routes,
        GROUP_CONCAT(
            r.name 
            ORDER BY r.name 
            SEPARATOR ', '
        ) AS route_list
    FROM technicians t
    INNER JOIN technician_routes tr ON t.id = tr.technician_id AND tr.status = 'active'
    INNER JOIN routes r ON tr.route_id = r.id
    GROUP BY t.id, t.code, t.name, t.department, t.status
    HAVING COUNT(tr.route_id) >= min_routes
    ORDER BY total_routes DESC, t.name ASC;
END$$

DELIMITER ;
```

### 7. Asignaciones Recientes

Obtener las últimas asignaciones de rutas:

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_recent_assignments(IN days_ago INT)
BEGIN
    SELECT 
        tr.id AS assignment_id,
        t.code AS technician_code,
        t.name AS technician_name,
        r.code AS route_code,
        r.name AS route_name,
        tr.assigned_date,
        tr.status,
        tr.notes,
        tr.created_at
    FROM technician_routes tr
    INNER JOIN technicians t ON tr.technician_id = t.id
    INNER JOIN routes r ON tr.route_id = r.id
    WHERE tr.assigned_date >= DATE_SUB(CURDATE(), INTERVAL days_ago DAY)
    ORDER BY tr.assigned_date DESC, tr.created_at DESC;
END$$

DELIMITER ;
```

### 8. Rutas sin Técnicos Asignados

Encontrar rutas que no tienen técnicos activos:

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_unassigned_routes()
BEGIN
    SELECT 
        r.id,
        r.code,
        r.name,
        r.description,
        r.status,
        r.created_at
    FROM routes r
    WHERE r.status = 'active'
    AND r.id NOT IN (
        SELECT DISTINCT route_id 
        FROM technician_routes 
        WHERE status = 'active'
    )
    ORDER BY r.name ASC;
END$$

DELIMITER ;
```

### 9. Técnicos por Posición con Conteo

Agrupar técnicos por posición/cargo:

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_technicians_by_position()
BEGIN
    SELECT 
        t.position,
        t.department,
        COUNT(*) AS total_technicians,
        COUNT(CASE WHEN t.status = 'active' THEN 1 END) AS active_count,
        COUNT(CASE WHEN t.status = 'inactive' THEN 1 END) AS inactive_count,
        GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') AS technician_names
    FROM technicians t
    GROUP BY t.position, t.department
    ORDER BY total_technicians DESC, t.position ASC;
END$$

DELIMITER ;
```

### 10. Historial de Cambios de Estado

Si tienes una tabla de auditoría:

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_technician_history(IN tech_id INT, IN limit_records INT)
BEGIN
    -- Asumiendo que tienes una tabla de auditoría
    SELECT 
        th.id,
        th.technician_id,
        th.field_changed,
        th.old_value,
        th.new_value,
        th.changed_by,
        th.changed_at
    FROM technician_history th
    WHERE th.technician_id = tech_id
    ORDER BY th.changed_at DESC
    LIMIT limit_records;
END$$

DELIMITER ;
```

## 🔧 Cómo Implementar estos SPs en Laravel

### 1. Agregar método al Repository Interface

```php
// app/Repositories/TechnicianRepositoryInterface.php
public function getTechniciansByDateRange(string $startDate, string $endDate): Collection;
```

### 2. Implementar en el Repository

```php
// app/Repositories/DbTechnicianRepository.php
public function getTechniciansByDateRange(string $startDate, string $endDate): Collection
{
    try {
        $results = DB::connection(self::DB_CONNECTION)
            ->select('CALL sp_get_technicians_by_date_range(?, ?)', [$startDate, $endDate]);

        return collect($results);
    } catch (\Exception $e) {
        Log::error('Error al buscar técnicos por rango de fechas: ' . $e->getMessage());
        throw new \RuntimeException('Error al buscar técnicos: ' . $e->getMessage());
    }
}
```

### 3. Agregar al Service

```php
// app/Services/TechnicianService.php
public function getTechniciansByDateRange(string $startDate, string $endDate): Collection
{
    $cacheKey = "technicians.date_range.{$startDate}.{$endDate}";
    
    return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($startDate, $endDate) {
        return $this->repository->getTechniciansByDateRange($startDate, $endDate);
    });
}
```

### 4. Crear endpoint en el Controller

```php
// app/Http/Controllers/Api/TechnicianController.php
public function byDateRange(Request $request): AnonymousResourceCollection|JsonResponse
{
    $request->validate([
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date'
    ]);

    try {
        $technicians = $this->service->getTechniciansByDateRange(
            $request->start_date,
            $request->end_date
        );

        return TechnicianResource::collection($technicians);
    } catch (\Exception $e) {
        return $this->errorResponse('Error: ' . $e->getMessage(), 500);
    }
}
```

### 5. Agregar ruta

```php
// routes/api.php
Route::get('/technicians/date-range', [TechnicianController::class, 'byDateRange']);
```

## 📊 Mejores Prácticas

1. **Índices**: Asegúrate de tener índices en campos usados en WHERE y JOIN
2. **LIMIT**: Usa siempre LIMIT para consultas que pueden retornar muchos registros
3. **Parámetros**: Valida todos los parámetros en el SP
4. **Error Handling**: Usa DECLARE ... HANDLER para errores
5. **Documentación**: Comenta cada SP con su propósito y parámetros
6. **Testing**: Prueba cada SP directamente antes de usarlo en Laravel
7. **Performance**: Usa EXPLAIN para verificar el plan de ejecución
8. **Caché**: Implementa caché en Laravel para SPs costosos

## 🧪 Testing de SPs

```sql
-- Probar SP directamente
CALL sp_get_technicians_by_date_range('2025-01-01', '2025-12-31');

-- Ver plan de ejecución
EXPLAIN SELECT * FROM technicians WHERE ...;

-- Verificar índices
SHOW INDEX FROM technicians;

-- Tiempo de ejecución
SET profiling = 1;
CALL sp_get_technicians_with_routes();
SHOW PROFILES;
```

## 💡 Tips Adicionales

- Usa transacciones si modificas datos
- Implementa versionado de SPs
- Mantén un registro de cambios
- Prueba con datos de producción (anonimizados)
- Monitorea el rendimiento en producción
