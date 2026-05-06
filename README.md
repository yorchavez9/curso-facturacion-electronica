# Facturación Electrónica con Greenter

Emisión de facturas electrónicas hacia SUNAT (Perú) usando [Greenter](https://greenter.dev/), con generación automática de PDF en formato A4 y ticket 80mm.

---

## Requisitos

- PHP 8.0 o superior
- [Composer](https://getcomposer.org/)
- Certificado digital `.pem` (proporcionado por tu proveedor de certificados)
- Credenciales SOL de SUNAT

---

## Instalación

**1. Clonar o descargar el proyecto**

```bash
git clone <url-del-repositorio>
cd curso-fac
```

**2. Instalar dependencias**

```bash
composer install
```

**3. Agregar el certificado digital**

Coloca tu archivo de certificado en la raíz del proyecto con el nombre:

```
certificate.pem
```

**4. Configurar credenciales SOL**

Abre `config.php` y ajusta los datos de tu empresa:

```php
$see->setCertificate(file_get_contents(__DIR__.'/certificate.pem'));
$see->setService(SunatEndpoints::FE_BETA);        // cambiar a FE_PRODUCCION en producción
$see->setClaveSOL('RUC', 'USUARIO_SOL', 'CLAVE_SOL');
```

---

## Uso

Edita `factura.php` con los datos de tu emisor, cliente e ítems, luego ejecuta:

```bash
php factura.php
```

Al terminar se generan automáticamente en la carpeta `facturas/`:

| Archivo | Descripción |
|---|---|
| `RUC-01-SERIE-CORRELATIVO.xml` | XML firmado digitalmente |
| `R-RUC-01-SERIE-CORRELATIVO.zip` | CDR de respuesta de SUNAT |
| `RUC-01-SERIE-CORRELATIVO_A4.pdf` | PDF tamaño A4 |
| `RUC-01-SERIE-CORRELATIVO_80mm.pdf` | PDF ticket térmico 80mm |

---

## Endpoints SUNAT

| Constante | Entorno |
|---|---|
| `SunatEndpoints::FE_BETA` | Pruebas (SUNAT Beta) |
| `SunatEndpoints::FE_PRODUCCION` | Producción |

---

## Dependencias principales

| Paquete | Versión | Uso |
|---|---|---|
| `greenter/lite` | ^5.2 | Firma y envío de comprobantes a SUNAT |
| `dompdf/dompdf` | ^3.1 | Generación de PDFs |
