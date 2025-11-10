# 🏗️ Arquitectura del Módulo de Técnicos

## Diagrama de Flujo de Datos

```
┌─────────────────────────────────────────────────────────────────┐
│                         CLIENTE (Frontend)                       │
│                     Axios / Fetch / HTTP Client                  │
└───────────────────────────┬─────────────────────────────────────┘
                            │ HTTP Request
                            │ Authorization: Bearer {token}
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                      LARAVEL MIDDLEWARE                          │
│                    auth:sanctum (Protección)                     │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    ROUTES (routes/api.php)                       │
│                                                                  │
│  GET  /api/technicians                                           │
│  GET  /api/technicians/active                                    │
│  GET  /api/technicians/{id}                                      │
│  GET  /api/technicians/{id}/routes                               │
│  GET  /api/technicians/department/{dept}                         │
│  POST /api/technicians/cache/clear                               │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│              CONTROLLER (TechnicianController)                   │
│                                                                  │
│  • Recibe Request                                                │
│  • Valida parámetros                                             │
│  • Llama al Service                                              │
│  • Retorna Resource/JSON                                         │
│  • Maneja excepciones                                            │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                SERVICE (TechnicianService)                       │
│                                                                  │
│  • Lógica de negocio                                             │
│  • Implementa caché (60 min)                                     │
│  • Transforma datos                                              │
│  • Parseado de rutas                                             │
│  • Manejo de errores                                             │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│           REPOSITORY (DbTechnicianRepository)                    │
│                                                                  │
│  • Abstracción de datos                                          │
│  • Llama Stored Procedures                                       │
│  • Usa DB::connection('mysql_external')                          │
│  • Logging de errores                                            │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│              BASE DE DATOS MYSQL EXTERNA                         │
│                                                                  │
│  Stored Procedures:                                              │
│  • sp_get_technicians_with_routes()                              │
│  • sp_get_technician_by_id(?)                                    │
│  • sp_get_technicians_by_department(?)                           │
│  • sp_get_active_technicians()                                   │
│  • sp_get_technician_routes(?)                                   │
│                                                                  │
│  Tablas:                                                         │
│  • technicians                                                   │
│  • routes                                                        │
│  • technician_routes                                             │
└─────────────────────────────────────────────────────────────────┘
```

## Flujo de Datos Detallado

### 1. Request → Controller

```
GET /api/technicians/{id}
├── Middleware auth:sanctum valida token
├── Route binding captura {id}
└── Llama TechnicianController@show(int $id)
```

### 2. Controller → Service

```
TechnicianController@show(1)
├── try {
│   ├── $technician = $this->service->getTechnicianById(1)
│   ├── if (!$technician) return 404
│   └── return TechnicianResource
└── } catch (\Exception $e) return 500
```

### 3. Service → Repository

```
TechnicianService@getTechnicianById(1)
├── Verifica caché: "technicians.1"
├── Si no existe en caché:
│   ├── $this->repository->findById(1)
│   ├── Transforma datos
│   └── Guarda en caché (60 min)
└── Retorna datos
```

### 4. Repository → Database

```
DbTechnicianRepository@findById(1)
├── try {
│   ├── DB::connection('mysql_external')
│   ├──   ->select('CALL sp_get_technician_by_id(?)', [1])
│   └── Retorna resultado
└── } catch (\Exception $e) {
    ├── Log::error(...)
    └── throw RuntimeException
}
```

## Patrón de Diseño: Dependency Injection

```php
// AppServiceProvider.php
register() {
    // Cuando se pida TechnicianRepositoryInterface,
    // Laravel automáticamente inyecta DbTechnicianRepository
    $this->app->bind(
        TechnicianRepositoryInterface::class,
        DbTechnicianRepository::class
    );
    
    $this->app->bind(
        TechnicianServiceInterface::class,
        TechnicianService::class
    );
}

// Controller recibe las dependencias automáticamente
public function __construct(
    private TechnicianServiceInterface $service
) {}
```

## Estructura de Directorios

```
app/
├── DataTransferObjects/
│   └── TechnicianData.php              # DTO para transferencia de datos
│
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── TechnicianController.php # Controlador REST
│   │
│   └── Resources/
│       └── TechnicianResource.php       # Transformación JSON
│
├── Repositories/
│   ├── TechnicianRepositoryInterface.php    # Contrato
│   └── DbTechnicianRepository.php           # Implementación
│
├── Services/
│   ├── TechnicianServiceInterface.php   # Contrato
│   └── TechnicianService.php            # Lógica de negocio
│
└── Providers/
    └── AppServiceProvider.php           # Registro de bindings

config/
└── database.php                         # Conexión mysql_external

routes/
└── api.php                              # Rutas API

database/
└── external_db_example.sql              # Script de ejemplo

docs/
├── TECHNICIANS_EXTERNAL_DB.md           # Documentación completa
└── STORED_PROCEDURES_EXAMPLES.md        # Ejemplos de SPs
```

## Capas de Responsabilidad

### 🎨 Resource Layer (Presentación)
**Responsabilidad**: Formatear respuestas JSON

```php
TechnicianResource::toArray()
├── Transforma objeto a array
├── Formatea fechas (ISO8601)
├── Incluye/excluye campos
└── Retorna estructura consistente
```

### 🎮 Controller Layer (Interfaz)
**Responsabilidad**: Manejar HTTP requests/responses

```php
TechnicianController
├── Validar input
├── Llamar Service
├── Retornar Resource/JSON
└── Manejar errores HTTP
```

### 💼 Service Layer (Lógica de Negocio)
**Responsabilidad**: Implementar reglas de negocio

```php
TechnicianService
├── Caché de datos
├── Transformación de datos
├── Parseo de rutas
├── Validaciones de negocio
└── Limpieza de caché
```

### 💾 Repository Layer (Acceso a Datos)
**Responsabilidad**: Comunicación con base de datos

```php
DbTechnicianRepository
├── Ejecutar Stored Procedures
├── Usar conexión externa
├── Logging de errores
└── Retornar Collections
```

### 🗄️ Database Layer (Almacenamiento)
**Responsabilidad**: Lógica de datos y consultas

```sql
Stored Procedures
├── Consultas optimizadas
├── Joins complejos
├── Agregaciones
└── Filtros y ordenamiento
```

## Ventajas de esta Arquitectura

### ✅ Separación de Responsabilidades
Cada capa tiene una función específica y bien definida.

### ✅ Testeable
Fácil hacer testing unitario de cada capa por separado.

### ✅ Mantenible
Los cambios en una capa no afectan a las demás.

### ✅ Escalable
Fácil agregar nuevas funcionalidades siguiendo el patrón.

### ✅ Flexible
Se puede cambiar la implementación sin cambiar el contrato.

### ✅ Reutilizable
Los servicios y repositorios pueden usarse en diferentes contextos.

## Ejemplo de Extensión

### Agregar nuevo endpoint "búsqueda"

**1. Repository Interface**
```php
public function searchTechnicians(string $term): Collection;
```

**2. Repository**
```php
public function searchTechnicians(string $term): Collection
{
    return collect(
        DB::connection('mysql_external')
            ->select('CALL sp_search_technicians(?)', [$term])
    );
}
```

**3. Service Interface**
```php
public function search(string $term): Collection;
```

**4. Service**
```php
public function search(string $term): Collection
{
    return Cache::remember("technicians.search.{$term}", 60, 
        fn() => $this->repository->searchTechnicians($term)
    );
}
```

**5. Controller**
```php
public function search(Request $request)
{
    $technicians = $this->service->search($request->q);
    return TechnicianResource::collection($technicians);
}
```

**6. Route**
```php
Route::get('/technicians/search', [TechnicianController::class, 'search']);
```

## Caché Strategy

```
Request → Service
           │
           ├─ Cache::has("technicians.{id}")?
           │  ├─ YES → Retorna datos de caché (rápido)
           │  └─ NO  → Consulta Repository
           │           │
           │           └─ Guarda en caché por 60 min
           │              │
           │              └─ Retorna datos
```

### Gestión de Caché

```php
// Caché por entidad
"technicians.{id}"                    // GET /technicians/1
"technicians.all.with_routes"         // GET /technicians
"technicians.active"                  // GET /technicians/active
"technicians.department.{dept}"       // GET /technicians/department/IT
"technicians.{id}.routes"             // GET /technicians/1/routes

// TTL: 60 minutos
// Invalidación: Manual via POST /technicians/cache/clear
```

## Error Handling Flow

```
Exception en Repository
     │
     ├─ Log::error(...)
     │
     └─ throw RuntimeException
              │
              ▼
         Service catch
              │
              └─ throw Exception
                     │
                     ▼
                Controller catch
                     │
                     └─ return errorResponse(500)
                              │
                              ▼
                         JSON Error Response
```

## Principios SOLID Aplicados

### Single Responsibility
- Controller: Solo HTTP
- Service: Solo lógica de negocio
- Repository: Solo acceso a datos

### Open/Closed
- Extender funcionalidad sin modificar código existente
- Agregar nuevos métodos en nuevas clases

### Liskov Substitution
- Cualquier implementación de Repository es intercambiable

### Interface Segregation
- Interfaces pequeñas y específicas

### Dependency Inversion
- Dependemos de abstracciones (Interfaces), no de implementaciones

---

Esta arquitectura sigue las mejores prácticas de Laravel y es fácilmente mantenible y escalable. 🚀
