# Laravel SOLID Structure

> Generador de arquitectura SOLID para Laravel con Repository Pattern y Service Layer.

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x-red)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

## ✨ Características

- 🏗️ **Arquitectura SOLID** - Repository Pattern con Service Layer
- 🔒 **Validación robusta** - Form Requests automáticos
- 🧪 **Tests incluidos** - Feature tests completos
- ⚡ **Detección inteligente** - Campos y tipos desde tu modelo
- 🎯 **Listo para usar** - Sin configuración adicional

## 📦 Instalación
```bash
composer require amptech/laravel-solid-structure
```

## 🚀 Uso Rápido

### Genera la arquitectura SOLID
```bash
php artisan make:solid Product --test
```

**Crea automáticamente:**
- Controller (REST completo)
- Service (lógica de negocio)
- Repository + Interface
- Form Requests (Store/Update)
- Tests Feature

### Registra las rutas
```php
// routes/api.php
use App\Http\Controllers\ProductController;

Route::apiResource('products', ProductController::class);
```

¡Listo! Ya tienes un CRUD completo funcionando.

## ⚙️ Opciones Avanzadas
```bash
# Con ruta personalizada
php artisan make:solid Product --path=V1/Admin

# Paginación personalizada
php artisan make:solid Product --paginate=20

# Sobrescribir archivos
php artisan make:solid Product --force
```

## 📋 Requisitos

- PHP 8.1+
- Laravel 10.x o 11.x

## 🤝 Contribuir

Las contribuciones son bienvenidas. Por favor, abre un issue o pull request.

## 📄 Licencia

Este proyecto está licenciado bajo la Licencia MIT.

## 👨‍💻 Autor

**Oscar Amperez**
- Email: oamperezp@gmail.com

---

<p align="center">
Si este paquete te fue útil, considera darle una ⭐ en GitHub
</p>