<?php

use Greenter\Model\Client\Client;
use Greenter\Model\Company\Company;
use Greenter\Model\Company\Address;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Sale\Legend;
use Dompdf\Dompdf;
use Dompdf\Options;

require __DIR__.'/vendor/autoload.php';

// Redirigir si se accede por GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function entero_letras(int $n): string {
    $u = ['','UN','DOS','TRES','CUATRO','CINCO','SEIS','SIETE','OCHO','NUEVE',
          'DIEZ','ONCE','DOCE','TRECE','CATORCE','QUINCE','DIECISÉIS','DIECISIETE','DIECIOCHO','DIECINUEVE'];
    $d = ['','DIEZ','VEINTE','TREINTA','CUARENTA','CINCUENTA','SESENTA','SETENTA','OCHENTA','NOVENTA'];
    $c = ['','CIENTO','DOSCIENTOS','TRESCIENTOS','CUATROCIENTOS','QUINIENTOS',
          'SEISCIENTOS','SETECIENTOS','OCHOCIENTOS','NOVECIENTOS'];
    if ($n === 0)   return 'CERO';
    if ($n < 20)    return $u[$n];
    if ($n === 100) return 'CIEN';
    if ($n < 100)   return $d[(int)($n/10)].($n%10 ? ' Y '.$u[$n%10] : '');
    if ($n < 1000)  return $c[(int)($n/100)].($n%100 ? ' '.entero_letras($n%100) : '');
    if ($n < 2000)  return 'MIL'.($n%1000 ? ' '.entero_letras($n%1000) : '');
    if ($n < 1000000) return entero_letras((int)($n/1000)).' MIL'.($n%1000 ? ' '.entero_letras($n%1000) : '');
    $m = (int)($n/1000000);
    return entero_letras($m).($m===1 ? ' MILLÓN' : ' MILLONES').($n%1000000 ? ' '.entero_letras($n%1000000) : '');
}

function monto_letras(float $monto, string $moneda = 'PEN'): string {
    $entero = (int)floor($monto);
    $cents  = (int)round(($monto - $entero) * 100);
    $mon    = $moneda === 'USD' ? 'DÓLARES' : 'SOLES';
    return 'SON '.entero_letras($entero).' CON '.str_pad($cents, 2, '0', STR_PAD_LEFT).'/100 '.$mon;
}

// ── Leer y validar POST ───────────────────────────────────────────────────────

$serie       = strtoupper(trim($_POST['serie']       ?? 'F001'));
$correlativo = (int)($_POST['correlativo'] ?? 1);
$fecha       = $_POST['fecha']   ?? date('Y-m-d');
$moneda      = $_POST['moneda']  ?? 'PEN';
$cli_tipo    = $_POST['cli_tipo_doc'] ?? '6';
$cli_num     = trim($_POST['cli_num_doc'] ?? '');
$cli_razon   = strtoupper(trim($_POST['cli_razon'] ?? ''));
$items_post  = $_POST['items'] ?? [];

$errores = [];
if (!$cli_num)                  $errores[] = 'El número de documento del cliente es requerido.';
if (!$cli_razon)                $errores[] = 'La razón social / nombre del cliente es requerida.';
if (empty($items_post))         $errores[] = 'Debe agregar al menos un ítem.';

if ($errores) {
    header('Location: index.php?error='.urlencode(implode(' | ', $errores)));
    exit;
}

// ── Emisor (igual que factura.php) ───────────────────────────────────────────

$address = (new Address())
    ->setUbigueo('150101')
    ->setDepartamento('LIMA')
    ->setProvincia('LIMA')
    ->setDistrito('LIMA')
    ->setUrbanizacion('-')
    ->setDireccion('Av. Villa Nueva 221')
    ->setCodLocal('0000');

$company = (new Company())
    ->setRuc('20123456789')
    ->setRazonSocial('GREEN SAC')
    ->setNombreComercial('GREEN')
    ->setAddress($address);

// ── Cliente ───────────────────────────────────────────────────────────────────

$client = (new Client())
    ->setTipoDoc($cli_tipo)
    ->setNumDoc($cli_num)
    ->setRznSocial($cli_razon);

// ── Construir ítems ───────────────────────────────────────────────────────────

$detalles      = [];
$mto_gravadas  = 0.0;
$mto_igv_total = 0.0;

foreach ($items_post as $it) {
    $cantidad = (float)($it['cantidad'] ?? 0);
    $precio   = (float)($it['precio']   ?? 0);   // valor unitario SIN IGV
    $igv_pct  = (float)($it['igv_pct']  ?? 18);
    $desc     = strtoupper(trim($it['descripcion'] ?? ''));
    $cod      = trim($it['cod'] ?? '') ?: 'P001';
    $unidad   = $it['unidad'] ?? 'NIU';

    if ($cantidad <= 0 || $precio <= 0 || !$desc) continue;

    $valor_venta      = round($precio * $cantidad, 2);
    $igv_item         = round($valor_venta * $igv_pct / 100, 2);
    $precio_con_igv   = round($precio * (1 + $igv_pct / 100), 4);
    $tipo_afe         = $igv_pct > 0 ? '10' : '20';

    $mto_gravadas  += $valor_venta;
    $mto_igv_total += $igv_item;

    $detalles[] = (new SaleDetail())
        ->setCodProducto($cod)
        ->setUnidad($unidad)
        ->setCantidad($cantidad)
        ->setMtoValorUnitario($precio)
        ->setDescripcion($desc)
        ->setMtoBaseIgv($valor_venta)
        ->setPorcentajeIgv($igv_pct)
        ->setIgv($igv_item)
        ->setTipAfeIgv($tipo_afe)
        ->setTotalImpuestos($igv_item)
        ->setMtoValorVenta($valor_venta)
        ->setMtoPrecioUnitario($precio_con_igv);
}

if (!$detalles) {
    header('Location: index.php?error='.urlencode('No se encontraron ítems válidos.'));
    exit;
}

$mto_gravadas  = round($mto_gravadas, 2);
$mto_igv_total = round($mto_igv_total, 2);
$mto_total     = $mto_gravadas + $mto_igv_total;

$legend = (new Legend())
    ->setCode('1000')
    ->setValue(monto_letras($mto_total, $moneda));

// ── Factura ───────────────────────────────────────────────────────────────────

$invoice = (new Invoice())
    ->setUblVersion('2.1')
    ->setTipoOperacion('0101')
    ->setTipoDoc('01')
    ->setSerie($serie)
    ->setCorrelativo((string)$correlativo)
    ->setFechaEmision(new DateTime($fecha.' 08:00:00-05:00'))
    ->setFormaPago(new FormaPagoContado())
    ->setTipoMoneda($moneda)
    ->setCompany($company)
    ->setClient($client)
    ->setMtoOperGravadas($mto_gravadas)
    ->setMtoIGV($mto_igv_total)
    ->setTotalImpuestos($mto_igv_total)
    ->setValorVenta($mto_gravadas)
    ->setSubTotal($mto_total)
    ->setMtoImpVenta($mto_total)
    ->setDetails($detalles)
    ->setLegends([$legend]);

// ── Enviar a SUNAT ────────────────────────────────────────────────────────────

$see = require __DIR__.'/config.php';

$dir_facturas = __DIR__.'/facturas/';
if (!is_dir($dir_facturas)) mkdir($dir_facturas, 0755, true);

$estado      = '';
$descripcion = '';
$notas       = [];
$error_msg   = '';
$success     = false;

try {
    $result = $see->send($invoice);

    file_put_contents($dir_facturas.$invoice->getName().'.xml',
                      $see->getFactory()->getLastXml());

    if (!$result->isSuccess()) {
        $error_msg = 'Error '.$result->getError()->getCode().': '.$result->getError()->getMessage();
    } else {
        file_put_contents($dir_facturas.'R-'.$invoice->getName().'.zip', $result->getCdrZip());
        $cdr   = $result->getCdrResponse();
        $code  = (int)$cdr->getCode();
        $descripcion = $cdr->getDescription();
        $notas = $cdr->getNotes();

        if ($code === 0) {
            $success = true;
            $estado  = 'ACEPTADA';
        } elseif ($code >= 2000 && $code <= 3999) {
            $estado = 'RECHAZADA';
        } else {
            $estado = 'EXCEPCIÓN (cod '.$code.')';
        }
    }
} catch (Exception $e) {
    $error_msg = $e->getMessage();
}

// ── Generar PDFs ──────────────────────────────────────────────────────────────

$addr         = $company->getAddress();
$simbolo      = $moneda === 'PEN' ? 'S/' : '$';
$fecha_fmt    = $invoice->getFechaEmision()->format('Y-m-d');
$serie_fmt    = $invoice->getSerie().'-'.str_pad($invoice->getCorrelativo(), 8, '0', STR_PAD_LEFT);
$fmt_grav     = number_format($mto_gravadas, 2);
$fmt_igv      = number_format($mto_igv_total, 2);
$fmt_total    = number_format($mto_total, 2);
$leyenda_txt  = $legend->getValue();

$filas_a4 = '';
foreach ($invoice->getDetails() as $det) {
    $filas_a4 .= '<tr>'
        .'<td>'.$det->getCodProducto().'</td>'
        .'<td>'.$det->getDescripcion().'</td>'
        .'<td style="text-align:center">'.$det->getUnidad().'</td>'
        .'<td style="text-align:right">'.$det->getCantidad().'</td>'
        .'<td style="text-align:right">'.number_format($det->getMtoPrecioUnitario(), 2).'</td>'
        .'<td style="text-align:right">'.number_format($det->getMtoValorVenta(), 2).'</td>'
        .'<td style="text-align:right">'.number_format($det->getIgv(), 2).'</td>'
        .'<td style="text-align:right">'.number_format($det->getMtoValorVenta() + $det->getIgv(), 2).'</td>'
        .'</tr>';
}

$html_a4 = '<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, sans-serif; font-size: 11px; color: #222; }
  .page { padding: 30px; }
  .header { display:table; width:100%; margin-bottom:20px; }
  .header-left  { display:table-cell; width:55%; vertical-align:top; }
  .header-right { display:table-cell; width:45%; vertical-align:top; }
  .emisor-nombre    { font-size:18px; font-weight:bold; color:#1a5276; }
  .emisor-comercial { font-size:13px; color:#555; margin-bottom:6px; }
  .emisor-info      { font-size:10px; line-height:1.6; }
  .box-doc          { border:2px solid #1a5276; border-radius:4px; padding:12px 16px; text-align:center; }
  .box-doc .tipo    { font-size:13px; font-weight:bold; color:#1a5276; }
  .box-doc .ruc-label { font-size:10px; margin:4px 0 2px; }
  .box-doc .ruc-num { font-size:13px; font-weight:bold; }
  .box-doc .serie   { font-size:12px; margin-top:6px; font-weight:bold; }
  .seccion        { margin-bottom:14px; }
  .seccion-titulo { background:#1a5276; color:#fff; font-weight:bold; padding:3px 8px; font-size:10px; margin-bottom:6px; }
  .grid-2 { display:table; width:100%; }
  .col    { display:table-cell; width:50%; padding:2px 4px; }
  .label  { color:#555; font-size:9px; }
  .value  { font-size:10px; font-weight:bold; }
  table.items    { width:100%; border-collapse:collapse; margin-bottom:10px; }
  table.items th { background:#1a5276; color:#fff; padding:5px 4px; font-size:9px; text-align:center; }
  table.items td { padding:4px; border-bottom:1px solid #ddd; font-size:10px; }
  table.items tr:nth-child(even) td { background:#f4f6f9; }
  .totales-wrap { display:table; width:100%; margin-top:8px; }
  .leyenda-col  { display:table-cell; width:60%; vertical-align:bottom; font-style:italic; font-size:9px; color:#444; padding-right:10px; }
  .totales-col  { display:table-cell; width:40%; }
  .totales-col table { width:100%; border-collapse:collapse; }
  .totales-col td { padding:3px 6px; font-size:10px; }
  .totales-col .t-label { color:#555; }
  .totales-col .t-value { text-align:right; font-weight:bold; }
  .totales-col .t-total td { background:#1a5276; color:#fff; font-weight:bold; font-size:11px; }
  .footer { margin-top:30px; font-size:9px; color:#888; text-align:center; border-top:1px solid #ccc; padding-top:8px; }
</style></head>
<body><div class="page">
  <div class="header">
    <div class="header-left">
      <div class="emisor-nombre">'.$company->getRazonSocial().'</div>
      <div class="emisor-comercial">'.$company->getNombreComercial().'</div>
      <div class="emisor-info">RUC: '.$company->getRuc().'<br>'.$addr->getDireccion().'<br>'.$addr->getDistrito().' - '.$addr->getProvincia().' - '.$addr->getDepartamento().'</div>
    </div>
    <div class="header-right">
      <div class="box-doc">
        <div class="tipo">FACTURA ELECTRÓNICA</div>
        <div class="ruc-label">R.U.C.</div>
        <div class="ruc-num">'.$company->getRuc().'</div>
        <div class="serie">'.$serie_fmt.'</div>
      </div>
    </div>
  </div>
  <div class="seccion">
    <div class="seccion-titulo">DATOS DEL CLIENTE</div>
    <div class="grid-2">
      <div class="col"><div class="label">Razón Social</div><div class="value">'.$client->getRznSocial().'</div></div>
      <div class="col"><div class="label">RUC / Doc.</div><div class="value">'.$client->getNumDoc().'</div></div>
    </div>
    <div class="grid-2" style="margin-top:6px">
      <div class="col"><div class="label">Fecha de Emisión</div><div class="value">'.$fecha_fmt.'</div></div>
      <div class="col"><div class="label">Moneda</div><div class="value">'.$moneda.'</div></div>
    </div>
  </div>
  <div class="seccion">
    <div class="seccion-titulo">DETALLE</div>
    <table class="items">
      <thead><tr><th>Código</th><th>Descripción</th><th>Unidad</th><th>Cantidad</th><th>P. Unitario</th><th>Valor Venta</th><th>IGV</th><th>Total</th></tr></thead>
      <tbody>'.$filas_a4.'</tbody>
    </table>
  </div>
  <div class="totales-wrap">
    <div class="leyenda-col"><strong>Son:</strong> '.$leyenda_txt.'</div>
    <div class="totales-col">
      <table>
        <tr><td class="t-label">Op. Gravadas ('.$simbolo.')</td><td class="t-value">'.$fmt_grav.'</td></tr>
        <tr><td class="t-label">IGV 18% ('.$simbolo.')</td><td class="t-value">'.$fmt_igv.'</td></tr>
        <tr class="t-total"><td>IMPORTE TOTAL ('.$simbolo.')</td><td class="t-value">'.$fmt_total.'</td></tr>
      </table>
    </div>
  </div>
  <div class="footer">Representación impresa de la Factura Electrónica &mdash; Autorizado mediante Resolución de Superintendencia N.° 300-2014/SUNAT</div>
</div></body></html>';

$filas_ticket = '';
foreach ($invoice->getDetails() as $det) {
    $tot_item = $det->getMtoValorVenta() + $det->getIgv();
    $filas_ticket .= '<tr>'
        .'<td class="desc">'.$det->getDescripcion().'<br>'
        .'<span class="sub">'.$det->getCantidad().' x '.number_format($det->getMtoPrecioUnitario(), 2).'</span></td>'
        .'<td class="monto">'.number_format($tot_item, 2).'</td>'
        .'</tr>';
}

$html_ticket = '<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, sans-serif; font-size: 8px; color: #000; width: 72mm; }
  .page { padding: 4mm 3mm; }
  .center { text-align: center; }
  .emisor-nombre { font-size:11px; font-weight:bold; }
  .emisor-info   { font-size:7px; line-height:1.5; margin-bottom:3mm; }
  .tipo-doc { font-size:9px; font-weight:bold; border:1px solid #000; padding:2px 4px; display:inline-block; margin:2mm 0; }
  .serie    { font-size:9px; font-weight:bold; }
  .sep      { border:none; border-top:1px dashed #000; margin:2mm 0; }
  .datos    { font-size:7px; line-height:1.6; margin-bottom:2mm; }
  .datos .lbl { font-weight:bold; }
  table.items { width:100%; border-collapse:collapse; margin:2mm 0; }
  table.items th { font-size:7px; border-bottom:1px solid #000; padding:1px 2px; text-align:left; }
  table.items th.monto { text-align:right; }
  table.items td { font-size:7px; padding:2px 2px; vertical-align:top; }
  table.items td.desc  { width:75%; }
  table.items td.monto { width:25%; text-align:right; }
  table.items .sub { color:#555; font-size:6.5px; }
  .totales { width:100%; border-collapse:collapse; margin-top:1mm; }
  .totales td { font-size:7px; padding:1px 2px; }
  .totales .lbl { text-align:left; }
  .totales .val { text-align:right; }
  .totales .total-row td { font-weight:bold; font-size:8.5px; border-top:1px solid #000; padding-top:2px; }
  .leyenda { font-size:6.5px; font-style:italic; margin-top:2mm; text-align:center; }
  .footer  { font-size:6px; color:#555; text-align:center; margin-top:3mm; border-top:1px dashed #000; padding-top:2mm; }
</style></head>
<body><div class="page">
  <div class="center">
    <div class="emisor-nombre">'.$company->getRazonSocial().'</div>
    <div class="emisor-info">RUC: '.$company->getRuc().'<br>'.$addr->getDireccion().'<br>'.$addr->getDistrito().' - '.$addr->getProvincia().'</div>
    <div class="tipo-doc">FACTURA ELECTRÓNICA</div><br>
    <div class="serie">'.$serie_fmt.'</div>
  </div>
  <hr class="sep">
  <div class="datos">
    <span class="lbl">Cliente:</span> '.$client->getRznSocial().'<br>
    <span class="lbl">Doc.:</span> '.$client->getNumDoc().'<br>
    <span class="lbl">Fecha:</span> '.$fecha_fmt.'<br>
    <span class="lbl">Moneda:</span> '.$moneda.'
  </div>
  <hr class="sep">
  <table class="items">
    <thead><tr><th>Descripción</th><th class="monto">Total</th></tr></thead>
    <tbody>'.$filas_ticket.'</tbody>
  </table>
  <hr class="sep">
  <table class="totales">
    <tr><td class="lbl">Op. Gravadas</td><td class="val">'.$simbolo.' '.$fmt_grav.'</td></tr>
    <tr><td class="lbl">IGV 18%</td><td class="val">'.$simbolo.' '.$fmt_igv.'</td></tr>
    <tr class="total-row"><td class="lbl">TOTAL A PAGAR</td><td class="val">'.$simbolo.' '.$fmt_total.'</td></tr>
  </table>
  <div class="leyenda">'.$leyenda_txt.'</div>
  <div class="footer">Representación impresa de Factura Electrónica<br>Autorizado mediante R.S. N.° 300-2014/SUNAT</div>
</div></body></html>';

$opts = new Options();
$opts->set('defaultFont', 'Arial');

$pdf_a4 = new Dompdf($opts);
$pdf_a4->loadHtml($html_a4);
$pdf_a4->setPaper('A4', 'portrait');
$pdf_a4->render();
$nombre_a4 = $invoice->getName().'_A4.pdf';
file_put_contents($dir_facturas.$nombre_a4, $pdf_a4->output());

$pdf_ticket = new Dompdf($opts);
$pdf_ticket->loadHtml($html_ticket);
$pdf_ticket->setPaper([0, 0, 226.77, 566.93], 'portrait');
$pdf_ticket->render();
$nombre_ticket = $invoice->getName().'_80mm.pdf';
file_put_contents($dir_facturas.$nombre_ticket, $pdf_ticket->output());

// ── Página de resultado ───────────────────────────────────────────────────────
$nombre_factura = htmlspecialchars($invoice->getName());
$ruta_base      = 'facturas/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Resultado — <?= $nombre_factura ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root { --brand:#1a3c6e; --accent:#f0c040; }
    body  { background:#eef1f7; font-family:'Segoe UI',sans-serif; }

    .topbar { background:var(--brand); padding:14px 0; text-align:center; }
    .topbar-title { font-size:1.25rem; font-weight:800; color:#fff; }
    .topbar-sub   { font-size:.75rem; color:rgba(255,255,255,.5); }

    .wrapper { max-width:700px; margin:0 auto; padding:36px 16px 60px; }

    /* Estado card */
    .estado-card {
      border-radius:16px;
      padding:36px 28px 28px;
      text-align:center;
      margin-bottom:20px;
      box-shadow:0 4px 20px rgba(0,0,0,.1);
    }
    .estado-card.aceptada { background:linear-gradient(135deg,#1b5e20,#388e3c); color:#fff; }
    .estado-card.rechazada{ background:linear-gradient(135deg,#b71c1c,#e53935); color:#fff; }
    .estado-card.error    { background:linear-gradient(135deg,#e65100,#f57c00); color:#fff; }
    .estado-icon  { font-size:4.5rem; line-height:1; margin-bottom:12px; }
    .estado-title { font-size:1.6rem; font-weight:900; margin-bottom:6px; }
    .estado-desc  { font-size:.9rem; opacity:.85; margin-bottom:14px; }
    .estado-badge {
      display:inline-block;
      background:rgba(255,255,255,.2);
      border:1px solid rgba(255,255,255,.4);
      border-radius:20px;
      padding:4px 14px;
      font-size:.82rem;
      font-weight:700;
      letter-spacing:.5px;
    }

    /* Observaciones */
    .obs-box {
      background:#fff8e1; border-left:4px solid #f0c040;
      border-radius:8px; padding:12px 16px;
      font-size:.85rem; color:#5d4037;
      margin-bottom:16px;
    }

    /* Descargas */
    .downloads-title {
      font-size:.72rem; font-weight:800; text-transform:uppercase;
      letter-spacing:.8px; color:#777; margin-bottom:12px;
    }
    .dl-card {
      background:#fff; border-radius:12px;
      box-shadow:0 2px 10px rgba(0,0,0,.07);
      padding:18px 12px; text-align:center;
      text-decoration:none; display:block;
      transition:transform .15s, box-shadow .15s;
      border:2px solid transparent;
    }
    .dl-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(0,0,0,.12); border-color:var(--brand); }
    .dl-card i   { font-size:2.2rem; display:block; margin-bottom:6px; }
    .dl-card .dl-name { font-weight:800; font-size:.88rem; color:#1a3c6e; }
    .dl-card .dl-sub  { font-size:.72rem; color:#999; margin-top:2px; }

    /* Botón nueva factura */
    .btn-nueva {
      background:var(--brand); color:#fff;
      font-weight:800; font-size:1rem;
      border:none; border-radius:10px;
      padding:12px 28px; display:block;
      width:100%; text-align:center;
      text-decoration:none; margin-top:20px;
      transition:opacity .15s;
    }
    .btn-nueva:hover { opacity:.88; color:#fff; }
  </style>
</head>
<body>

<div class="topbar">
  <div class="topbar-title"><i class="bi bi-receipt-cutoff me-2"></i>GREEN SAC — Emisión de Facturas</div>
  <div class="topbar-sub">RUC 20123456789 &nbsp;|&nbsp; Sistema de Facturación Electrónica</div>
</div>

<div class="wrapper">

  <!-- Estado -->
  <?php if ($error_msg): ?>
  <div class="estado-card error">
    <div class="estado-icon"><i class="bi bi-wifi-off"></i></div>
    <div class="estado-title">Error de Conexión con SUNAT</div>
    <div class="estado-desc"><?= htmlspecialchars($error_msg) ?></div>
    <span class="estado-badge"><?= $nombre_factura ?></span>
  </div>
  <div class="obs-box">
    <i class="bi bi-info-circle me-1"></i>
    El XML fue generado localmente. Puedes reintentar el envío manualmente.
  </div>

  <?php elseif ($estado === 'ACEPTADA'): ?>
  <div class="estado-card aceptada">
    <div class="estado-icon"><i class="bi bi-check-circle-fill"></i></div>
    <div class="estado-title">¡Factura Aceptada!</div>
    <div class="estado-desc"><?= htmlspecialchars($descripcion) ?></div>
    <span class="estado-badge"><?= $nombre_factura ?></span>
  </div>
  <?php if ($notas): ?>
  <div class="obs-box">
    <strong><i class="bi bi-exclamation-triangle me-1"></i>Observaciones SUNAT:</strong>
    <ul class="mb-0 mt-1 ps-3">
      <?php foreach ($notas as $nota): ?>
      <li><?= htmlspecialchars($nota) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php elseif ($estado === 'RECHAZADA'): ?>
  <div class="estado-card rechazada">
    <div class="estado-icon"><i class="bi bi-x-circle-fill"></i></div>
    <div class="estado-title">Factura Rechazada</div>
    <div class="estado-desc"><?= htmlspecialchars($descripcion) ?></div>
    <span class="estado-badge"><?= $nombre_factura ?></span>
  </div>

  <?php else: ?>
  <div class="estado-card error">
    <div class="estado-icon"><i class="bi bi-exclamation-circle-fill"></i></div>
    <div class="estado-title"><?= htmlspecialchars($estado) ?></div>
    <div class="estado-desc"><?= htmlspecialchars($descripcion) ?></div>
    <span class="estado-badge"><?= $nombre_factura ?></span>
  </div>
  <?php endif; ?>

  <!-- Descargas -->
  <div class="downloads-title">Archivos Generados</div>
  <div class="row g-3">
    <div class="col-4">
      <a href="<?= $ruta_base.$nombre_a4 ?>" download class="dl-card">
        <i class="bi bi-file-earmark-pdf text-danger"></i>
        <div class="dl-name">PDF A4</div>
        <div class="dl-sub">Factura completa</div>
      </a>
    </div>
    <div class="col-4">
      <a href="<?= $ruta_base.$nombre_ticket ?>" download class="dl-card">
        <i class="bi bi-printer text-danger"></i>
        <div class="dl-name">PDF 80mm</div>
        <div class="dl-sub">Ticket térmico</div>
      </a>
    </div>
    <div class="col-4">
      <a href="<?= $ruta_base.$nombre_factura.'.xml' ?>" download class="dl-card">
        <i class="bi bi-file-earmark-code text-primary"></i>
        <div class="dl-name">XML</div>
        <div class="dl-sub">Firmado digitalmente</div>
      </a>
    </div>
  </div>

  <a href="index.php" class="btn-nueva">
    <i class="bi bi-plus-lg me-2"></i>Nueva Factura
  </a>

</div><!-- /wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
