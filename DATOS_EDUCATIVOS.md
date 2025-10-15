# Datos Educativos y de Salud - Instituto

Este proyecto incluye un sistema completo de gestión de inventario para un instituto educativo con especialización en carreras de salud.

## 🏥 Áreas Cubiertas

### Carreras de Salud
- **Laboratorio Clínico**: Análisis clínicos y bioquímicos
- **Hemoterapia**: Banco de sangre y hemoderivados  
- **Radiología**: Diagnóstico por imágenes
- **Instrumentación Quirúrgica**: Equipos y materiales quirúrgicos

### Productos Incluidos

#### Equipos de Laboratorio
- Microscopio Óptico Binocular
- Centrífuga de Laboratorio
- Autoclave de Laboratorio

#### Reactivos y Soluciones
- Reactivo para Glucosa
- Solución Salina Fisiológica
- Reactivo para Hemoglobina

#### Material Descartable
- Jeringas Descartables 5ml
- Guantes de Látex
- Tubos de Ensayo Estériles

#### Equipos de Radiología
- Equipo de Rayos X Digital
- Delantal Plomado

#### Instrumentos Quirúrgicos
- Bisturí N°11
- Pinzas de Disección

#### Equipos de Hemoterapia
- Aguja para Flebotomía 21G
- Bolsas para Sangre 450ml

#### Material Educativo
- Modelo Anatómico de Corazón
- Esqueleto Humano Didáctico

## 🚀 Cómo Usar

### Opción 1: Comando Personalizado (Recomendado)
```bash
# Limpiar y generar datos (mantiene tabla users)
php artisan db:reset-educational

# Limpiar completamente y regenerar (incluye migraciones frescas)
php artisan db:reset-educational --fresh
```

### Opción 2: Seeders Individuales
```bash
# Solo limpiar datos (excepto users)
php artisan db:seed --class=CleanDatabaseSeeder

# Solo generar datos educativos
php artisan db:seed --class=EducationalHealthDataSeeder

# Ejecutar todo el proceso
php artisan db:seed
```

## 📊 Datos Generados

### Proveedores (5) con Sectores Asignados
- **MedEquip S.A.** - Equipos médicos y laboratorio
  - Laboratorio Clínico, Educación
- **LabSupply Argentina** - Insumos de laboratorio
  - Laboratorio Clínico, Hemoterapia
- **Quirúrgica del Sur** - Material quirúrgico
  - Instrumentación Quirúrgica, Educación
- **RadTech Solutions** - Equipos de radiología
  - Radiología, Educación
- **EduMed Supplies** - Material educativo
  - Educación, Laboratorio Clínico

### Ubicaciones (10)
- Laboratorio Principal
- Laboratorio de Microbiología
- Sala de Radiología
- Quirófano 1 y 2
- Banco de Sangre
- Aula de Prácticas
- Depósito Central
- Sala de Instrumentación
- Consultorio Médico

### Órdenes y Transacciones
- **5 Órdenes de Compra** con diferentes estados
- **6 Detalles de Órdenes de Compra** (oc_details)
- **4 Órdenes de Pago** con diferentes estados
- **6 Detalles de Órdenes de Pago** (op_details) con conceptos variados

### Datos Adicionales
- ✅ **Niveles de stock** actualizados con ubicaciones y costos
- ✅ **Órdenes de compra** (5 órdenes) con detalles completos
- ✅ **Detalles de órdenes de compra** (oc_details) vinculados a insumos
- ✅ **Órdenes de pago** (4 órdenes) con diferentes estados
- ✅ **Detalles de órdenes de pago** (op_details) con conceptos y métodos
- ✅ **Aplicaciones** y usos de productos en prácticas
- ✅ **Movimientos de inventario** (entradas y salidas)
- ✅ **Recepciones** y devoluciones de productos
- ✅ **Cotizaciones** y tasas de mercado
- ✅ **Relación proveedores-sectores** (suppliers_sectors)
- ✅ **Detalles de solicitud** para reposición de stock

## 🔧 Estructura de Archivos

```
database/seeders/
├── CleanDatabaseSeeder.php          # Limpia datos (excepto users)
├── EducationalHealthDataSeeder.php  # Genera datos educativos
└── DatabaseSeeder.php               # Configuración principal

app/Console/Commands/
└── ResetEducationalData.php         # Comando personalizado
```

## ⚠️ Importante

- **La tabla `users` NO se modifica** - Se mantienen todos los usuarios existentes
- Los datos generados son **realistas** para un instituto educativo de salud
- Se incluyen **fechas de vencimiento** apropiadas para productos perecederos
- Los **porcentajes de utilización** reflejan el uso típico en instituciones educativas

## 🎯 Casos de Uso

Este sistema es ideal para:
- Institutos de educación superior en salud
- Escuelas de medicina y enfermería
- Laboratorios de enseñanza
- Centros de formación técnica en salud
- Instituciones con programas de radiología y hemoterapia
