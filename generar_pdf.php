<?php

use Dompdf\Dompdf;
use Dompdf\Options;

require __DIR__.'/vendor/autoload.php';

// ── Datos de la factura (mismos que factura.php) ──────────────────────────────

$emisor = [
    'ruc'       => '20123456789',
    'razon'     => 'GREEN SAC',
    'comercial' => 'GREEN',
    'direccion' => 'Av. Villa Nueva 221',
    'distrito'  => 'LIMA',
    'provincia' => 'LIMA',
    'dpto'      => 'LIMA',
];

$cliente = [
    'num_doc' => '20000000001',
    'razon'   => 'EMPRESA X',
];

$factura = [
    'serie'       => 'F001',
    'correlativo' => '1',
    'fecha'       => '2020-08-24',
    'moneda'      => 'PEN',
    'tipo_doc'    => 'FACTURA ELECTRÓNICA',
];

$items = [
    [
        'cod'         => 'P001',
        'descripcion' => 'PRODUCTO 1',
        'unidad'      => 'NIU',
        'cantidad'    => 2,
        'p_unitario'  => 59.00,
        'valor_venta' => 100.00,
        'igv'         => 18.00,
        'total'       => 118.00,
    ],
];

$totales = [
    'op_gravadas' => 100.00,
    'igv'         => 18.00,
    'importe'     => 118.00,
];

$leyenda = 'SON DOSCIENTOS TREINTA Y SEIS CON 00/100 SOLES';

// ── Preparar valores formateados ──────────────────────────────────────────────

$simbolo = $factura['moneda'] === 'PEN' ? 'S/' : '$';

$filas_items = '';
foreach ($items as $it) {
    $filas_items .= '<tr>'
        .'<td>'.$it['cod'].'</td>'
        .'<td>'.$it['descripcion'].'</td>'
        .'<td style="text-align:center">'.$it['unidad'].'</td>'
        .'<td style="text-align:right">'.$it['cantidad'].'</td>'
        .'<td style="text-align:right">'.number_format($it['p_unitario'], 2).'</td>'
        .'<td style="text-align:right">'.number_format($it['valor_venta'], 2).'</td>'
        .'<td style="text-align:right">'.number_format($it['igv'], 2).'</td>'
        .'<td style="text-align:right">'.number_format($it['total'], 2).'</td>'
        .'</tr>';
}

$fmt_gravadas = number_format($totales['op_gravadas'], 2);
$fmt_igv      = number_format($totales['igv'], 2);
$fmt_importe  = number_format($totales['importe'], 2);

// ── HTML de la factura ────────────────────────────────────────────────────────

$html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
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
  .box-doc .ruc-label{ font-size:10px; margin:4px 0 2px; }
  .box-doc .ruc-num { font-size:13px; font-weight:bold; }
  .box-doc .serie   { font-size:12px; margin-top:6px; font-weight:bold; }

  .seccion       { margin-bottom:14px; }
  .seccion-titulo{ background:#1a5276; color:#fff; font-weight:bold; padding:3px 8px; font-size:10px; margin-bottom:6px; }
  .grid-2        { display:table; width:100%; }
  .col           { display:table-cell; width:50%; padding:2px 4px; }
  .label         { color:#555; font-size:9px; }
  .value         { font-size:10px; font-weight:bold; }

  table.items    { width:100%; border-collapse:collapse; margin-bottom:10px; }
  table.items th { background:#1a5276; color:#fff; padding:5px 4px; font-size:9px; text-align:center; }
  table.items td { padding:4px; border-bottom:1px solid #ddd; font-size:10px; }
  table.items tr:nth-child(even) td { background:#f4f6f9; }

  .totales-wrap  { display:table; width:100%; margin-top:8px; }
  .leyenda-col   { display:table-cell; width:60%; vertical-align:bottom; font-style:italic; font-size:9px; color:#444; padding-right:10px; }
  .totales-col   { display:table-cell; width:40%; }
  .totales-col table { width:100%; border-collapse:collapse; }
  .totales-col td { padding:3px 6px; font-size:10px; }
  .totales-col .t-label { color:#555; }
  .totales-col .t-value { text-align:right; font-weight:bold; }
  .totales-col .t-total td { background:#1a5276; color:#fff; font-weight:bold; font-size:11px; }

  .footer { margin-top:30px; font-size:9px; color:#888; text-align:center; border-top:1px solid #ccc; padding-top:8px; }
</style>
</head>
<body>
<div class="page">

  <div class="header">
    <div class="header-left">
      <div class="emisor-nombre">'.$emisor['razon'].'</div>
      <div class="emisor-comercial">'.$emisor['comercial'].'</div>
      <div class="emisor-info">
        RUC: '.$emisor['ruc'].'<br>
        '.$emisor['direccion'].'<br>
        '.$emisor['distrito'].' - '.$emisor['provincia'].' - '.$emisor['dpto'].'
      </div>
    </div>
    <div class="header-right">
      <div class="box-doc">
        <div class="tipo">'.$factura['tipo_doc'].'</div>
        <div class="ruc-label">R.U.C.</div>
        <div class="ruc-num">'.$emisor['ruc'].'</div>
        <div class="serie">'.$factura['serie'].'-'.str_pad($factura['correlativo'], 8, '0', STR_PAD_LEFT).'</div>
      </div>
    </div>
  </div>

  <div class="seccion">
    <div class="seccion-titulo">DATOS DEL CLIENTE</div>
    <div class="grid-2">
      <div class="col">
        <div class="label">Razón Social</div>
        <div class="value">'.$cliente['razon'].'</div>
      </div>
      <div class="col">
        <div class="label">RUC</div>
        <div class="value">'.$cliente['num_doc'].'</div>
      </div>
    </div>
    <div class="grid-2" style="margin-top:6px">
      <div class="col">
        <div class="label">Fecha de Emisión</div>
        <div class="value">'.$factura['fecha'].'</div>
      </div>
      <div class="col">
        <div class="label">Moneda</div>
        <div class="value">'.$factura['moneda'].'</div>
      </div>
    </div>
  </div>

  <div class="seccion">
    <div class="seccion-titulo">DETALLE</div>
    <table class="items">
      <thead>
        <tr>
          <th>Código</th>
          <th>Descripción</th>
          <th>Unidad</th>
          <th>Cantidad</th>
          <th>P. Unitario</th>
          <th>Valor Venta</th>
          <th>IGV</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>'.$filas_items.'</tbody>
    </table>
  </div>

  <div class="totales-wrap">
    <div class="leyenda-col">
      <strong>Son:</strong> '.$leyenda.'
    </div>
    <div class="totales-col">
      <table>
        <tr>
          <td class="t-label">Op. Gravadas ('.$simbolo.')</td>
          <td class="t-value">'.$fmt_gravadas.'</td>
        </tr>
        <tr>
          <td class="t-label">IGV 18% ('.$simbolo.')</td>
          <td class="t-value">'.$fmt_igv.'</td>
        </tr>
        <tr class="t-total">
          <td>IMPORTE TOTAL ('.$simbolo.')</td>
          <td class="t-value">'.$fmt_importe.'</td>
        </tr>
      </table>
    </div>
  </div>

  <div class="footer">
    Representación impresa de la '.$factura['tipo_doc'].' &mdash; Autorizado mediante Resolución de Superintendencia N.° 300-2014/SUNAT
  </div>

</div>
</body>
</html>';

// ── Generar PDF con Dompdf ────────────────────────────────────────────────────

$options = new Options();
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nombre = $emisor['ruc'].'-01-'.$factura['serie'].'-'.$factura['correlativo'].'.pdf';
file_put_contents(__DIR__.'/'.$nombre, $dompdf->output());

echo 'PDF generado: '.$nombre.PHP_EOL;
