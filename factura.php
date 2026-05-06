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

$see = require __DIR__.'/config.php';

// Cliente
$client = (new Client())
    ->setTipoDoc('6')
    ->setNumDoc('20000000001')
    ->setRznSocial('EMPRESA X');

// Emisor
$address = (new Address())
    ->setUbigueo('150101')
    ->setDepartamento('LIMA')
    ->setProvincia('LIMA')
    ->setDistrito('LIMA')
    ->setUrbanizacion('-')
    ->setDireccion('Av. Villa Nueva 221')
    ->setCodLocal('0000'); // Codigo de establecimiento asignado por SUNAT, 0000 por defecto.

$company = (new Company())
    ->setRuc('20123456789')
    ->setRazonSocial('GREEN SAC')
    ->setNombreComercial('GREEN')
    ->setAddress($address);

// Venta
$invoice = (new Invoice())
    ->setUblVersion('2.1')
    ->setTipoOperacion('0101') // Venta - Catalog. 51
    ->setTipoDoc('01') // Factura - Catalog. 01 
    ->setSerie('F001') // FA001, FB001, FC001, etc. - Serie de factura
    ->setCorrelativo('1') // 1, 2, 3, etc. - Correlativo de factura
    ->setFechaEmision(new DateTime('2020-08-24 13:05:00-05:00')) // Zona horaria: Lima
    ->setFormaPago(new FormaPagoContado()) // FormaPago: Contado
    ->setTipoMoneda('PEN') // Sol - Catalog. 02 // USD - Catalog. 01
    ->setCompany($company)
    ->setClient($client)
    ->setMtoOperGravadas(100.00)
    ->setMtoIGV(18.00)
    ->setTotalImpuestos(18.00)
    ->setValorVenta(100.00)
    ->setSubTotal(118.00)
    ->setMtoImpVenta(118.00)
    ;

$item = (new SaleDetail())
    ->setCodProducto('P001')
    ->setUnidad('NIU') // Unidad - Catalog. 03
    ->setCantidad(2)
    ->setMtoValorUnitario(50.00)
    ->setDescripcion('PRODUCTO 1')
    ->setMtoBaseIgv(100)
    ->setPorcentajeIgv(18.00) // 18%
    ->setIgv(18.00)
    ->setTipAfeIgv('10') // Gravado Op. Onerosa - Catalog. 07
    ->setTotalImpuestos(18.00) // Suma de impuestos en el detalle
    ->setMtoValorVenta(100.00)
    ->setMtoPrecioUnitario(59.00)
    ;

$legend = (new Legend())
    ->setCode('1000') // Monto en letras - Catalog. 52
    ->setValue('SON DOSCIENTOS TREINTA Y SEIS CON 00/100 SOLES');

$invoice->setDetails([$item])
        ->setLegends([$legend]);


$result = $see->send($invoice);

// Guardar XML firmado digitalmente.
file_put_contents($invoice->getName().'.xml',
                  $see->getFactory()->getLastXml());

// Verificamos que la conexión con SUNAT fue exitosa.
if (!$result->isSuccess()) {
    // Mostrar error al conectarse a SUNAT.
    echo 'Codigo Error: '.$result->getError()->getCode();
    echo 'Mensaje Error: '.$result->getError()->getMessage();
    exit();
}

// Guardamos el CDR
file_put_contents('R-'.$invoice->getName().'.zip', $result->getCdrZip());



$cdr = $result->getCdrResponse();

$code = (int)$cdr->getCode();

if ($code === 0) {
    echo 'ESTADO: ACEPTADA'.PHP_EOL;
    if (count($cdr->getNotes()) > 0) {
        echo 'OBSERVACIONES:'.PHP_EOL;
        // Corregir estas observaciones en siguientes emisiones.
        var_dump($cdr->getNotes());
    }  
} else if ($code >= 2000 && $code <= 3999) {
    echo 'ESTADO: RECHAZADA'.PHP_EOL;
} else {
    /* Esto no debería darse, pero si ocurre, es un CDR inválido que debería tratarse como un error-excepción. */
    /*code: 0100 a 1999 */
    echo 'Excepción';
}

echo $cdr->getDescription().PHP_EOL;

// ── Generar PDF de la factura ─────────────────────────────────────────────────

$addr      = $company->getAddress();
$simbolo   = $invoice->getTipoMoneda() === 'PEN' ? 'S/' : '$';

$filas_items = '';
foreach ($invoice->getDetails() as $det) {
    $filas_items .= '<tr>'
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

$leyendas_texto = '';
foreach ($invoice->getLegends() as $leg) {
    if ($leg->getCode() === '1000') {
        $leyendas_texto = $leg->getValue();
        break;
    }
}

$fmt_gravadas = number_format($invoice->getMtoOperGravadas(), 2);
$fmt_igv      = number_format($invoice->getMtoIGV(), 2);
$fmt_importe  = number_format($invoice->getMtoImpVenta(), 2);
$fecha_emision = $invoice->getFechaEmision()->format('Y-m-d');

$html_pdf = '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8">
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
</style>
</head>
<body><div class="page">

  <div class="header">
    <div class="header-left">
      <div class="emisor-nombre">'.$company->getRazonSocial().'</div>
      <div class="emisor-comercial">'.$company->getNombreComercial().'</div>
      <div class="emisor-info">
        RUC: '.$company->getRuc().'<br>
        '.$addr->getDireccion().'<br>
        '.$addr->getDistrito().' - '.$addr->getProvincia().' - '.$addr->getDepartamento().'
      </div>
    </div>
    <div class="header-right">
      <div class="box-doc">
        <div class="tipo">FACTURA ELECTRÓNICA</div>
        <div class="ruc-label">R.U.C.</div>
        <div class="ruc-num">'.$company->getRuc().'</div>
        <div class="serie">'.$invoice->getSerie().'-'.str_pad($invoice->getCorrelativo(), 8, '0', STR_PAD_LEFT).'</div>
      </div>
    </div>
  </div>

  <div class="seccion">
    <div class="seccion-titulo">DATOS DEL CLIENTE</div>
    <div class="grid-2">
      <div class="col"><div class="label">Razón Social</div><div class="value">'.$client->getRznSocial().'</div></div>
      <div class="col"><div class="label">RUC</div><div class="value">'.$client->getNumDoc().'</div></div>
    </div>
    <div class="grid-2" style="margin-top:6px">
      <div class="col"><div class="label">Fecha de Emisión</div><div class="value">'.$fecha_emision.'</div></div>
      <div class="col"><div class="label">Moneda</div><div class="value">'.$invoice->getTipoMoneda().'</div></div>
    </div>
  </div>

  <div class="seccion">
    <div class="seccion-titulo">DETALLE</div>
    <table class="items">
      <thead><tr>
        <th>Código</th><th>Descripción</th><th>Unidad</th><th>Cantidad</th>
        <th>P. Unitario</th><th>Valor Venta</th><th>IGV</th><th>Total</th>
      </tr></thead>
      <tbody>'.$filas_items.'</tbody>
    </table>
  </div>

  <div class="totales-wrap">
    <div class="leyenda-col"><strong>Son:</strong> '.$leyendas_texto.'</div>
    <div class="totales-col">
      <table>
        <tr><td class="t-label">Op. Gravadas ('.$simbolo.')</td><td class="t-value">'.$fmt_gravadas.'</td></tr>
        <tr><td class="t-label">IGV 18% ('.$simbolo.')</td><td class="t-value">'.$fmt_igv.'</td></tr>
        <tr class="t-total"><td>IMPORTE TOTAL ('.$simbolo.')</td><td class="t-value">'.$fmt_importe.'</td></tr>
      </table>
    </div>
  </div>

  <div class="footer">
    Representación impresa de la Factura Electrónica &mdash; Autorizado mediante Resolución de Superintendencia N.° 300-2014/SUNAT
  </div>

</div></body></html>';

$opts = new Options();
$opts->set('defaultFont', 'Arial');
$pdf = new Dompdf($opts);
$pdf->loadHtml($html_pdf);
$pdf->setPaper('A4', 'portrait');
$pdf->render();

$pdf_nombre = $invoice->getName().'.pdf';
file_put_contents(__DIR__.'/'.$pdf_nombre, $pdf->output());
echo 'PDF generado: '.$pdf_nombre.PHP_EOL;