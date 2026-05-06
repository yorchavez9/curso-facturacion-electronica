<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Emisión de Factura — GREEN SAC</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --brand: #1a3c6e;
      --brand-light: #2255a4;
      --accent: #f0c040;
    }

    body {
      background: #eef1f7;
      font-family: 'Segoe UI', sans-serif;
    }

    /* ── Navbar ── */
    .topbar {
      background: var(--brand);
      padding: 14px 0;
      text-align: center;
    }
    .topbar-title {
      font-size: 1.35rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: .5px;
    }
    .topbar-sub {
      font-size: .78rem;
      color: rgba(255,255,255,.55);
      margin-top: 1px;
    }

    /* ── Wrapper centrado ── */
    .wrapper {
      max-width: 780px;
      margin: 0 auto;
      padding: 28px 16px 60px;
    }

    /* ── Cards ── */
    .panel {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,.07);
      margin-bottom: 20px;
      overflow: hidden;
    }
    .panel-head {
      background: var(--brand);
      color: #fff;
      padding: 12px 20px;
      font-size: .82rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .8px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .panel-head .num {
      background: var(--accent);
      color: var(--brand);
      width: 22px; height: 22px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .75rem; font-weight: 900;
      flex-shrink: 0;
    }
    .panel-body { padding: 20px; }

    /* ── Labels y controles ── */
    .form-label {
      font-size: .75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .4px;
      color: #555;
      margin-bottom: 4px;
    }
    .form-control, .form-select {
      border-radius: 8px;
      border: 1.5px solid #d0d7e3;
      font-size: .95rem;
      padding: 9px 12px;
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--brand-light);
      box-shadow: 0 0 0 3px rgba(34,85,164,.15);
    }

    /* ── Tabla ítems ── */
    #tablaItems thead th {
      background: var(--brand);
      color: #fff;
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .4px;
      padding: 10px 8px;
      border: none;
    }
    #tablaItems tbody td { vertical-align: middle; padding: 6px 6px; }
    #tablaItems tbody tr:nth-child(even) { background: #f7f9fc; }
    .form-control-sm, .form-select-sm { border-radius: 6px; font-size: .85rem; }

    /* ── Total box ── */
    .total-panel {
      background: var(--brand);
      border-radius: 12px;
      padding: 20px 24px;
      color: #fff;
    }
    .total-row-item {
      display: flex;
      justify-content: space-between;
      font-size: .9rem;
      padding: 4px 0;
      color: rgba(255,255,255,.75);
    }
    .total-row-item span:last-child { font-weight: 600; color: #fff; }
    .total-divider { border-color: rgba(255,255,255,.2); margin: 10px 0; }
    .total-final-label { font-size: .85rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
    .total-final-value { font-size: 2rem; font-weight: 900; color: var(--accent); line-height: 1; }

    /* ── Botón emitir ── */
    .btn-emitir {
      background: var(--accent);
      color: var(--brand);
      font-weight: 800;
      font-size: 1.05rem;
      border: none;
      border-radius: 10px;
      padding: 13px;
      width: 100%;
      margin-top: 14px;
      letter-spacing: .3px;
      transition: opacity .15s;
    }
    .btn-emitir:hover { opacity: .88; color: var(--brand); }
    .btn-emitir:disabled { opacity: .6; }

    /* ── Btn agregar ítem ── */
    .btn-add {
      background: #e8f5e9; color: #1b5e20;
      border: 1.5px solid #a5d6a7;
      border-radius: 8px;
      font-size: .8rem; font-weight: 700;
      padding: 5px 12px;
    }
    .btn-add:hover { background: #c8e6c9; color: #1b5e20; }

    .sin-items {
      padding: 32px;
      text-align: center;
      color: #aaa;
    }
    .sin-items i { font-size: 2.2rem; }

    /* ── Alerta error ── */
    .alerta-error {
      background: #fdecea;
      border-left: 4px solid #e53935;
      border-radius: 8px;
      padding: 12px 16px;
      font-size: .88rem;
      color: #b71c1c;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>

<div class="topbar">
  <div class="topbar-title"><i class="bi bi-receipt-cutoff me-2"></i>GREEN SAC — Emisión de Facturas</div>
  <div class="topbar-sub">RUC 20123456789 &nbsp;|&nbsp; Sistema de Facturación Electrónica</div>
</div>

<div class="wrapper">

  <?php if (!empty($_GET['error'])): ?>
  <div class="alerta-error">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <?= htmlspecialchars($_GET['error']) ?>
  </div>
  <?php endif; ?>

  <form action="emitir.php" method="POST" id="formFactura" novalidate>

    <!-- 1. Datos de la factura -->
    <div class="panel">
      <div class="panel-head"><span class="num">1</span>Datos de la Factura</div>
      <div class="panel-body">
        <div class="row g-3">
          <div class="col-6 col-sm-3">
            <label class="form-label">Serie</label>
            <input type="text" name="serie" class="form-control" value="F001" required>
          </div>
          <div class="col-6 col-sm-3">
            <label class="form-label">Correlativo</label>
            <input type="number" name="correlativo" class="form-control" value="1" min="1" required>
          </div>
          <div class="col-6 col-sm-3">
            <label class="form-label">Fecha de Emisión</label>
            <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-6 col-sm-3">
            <label class="form-label">Moneda</label>
            <select name="moneda" class="form-select">
              <option value="PEN">Soles (PEN)</option>
              <option value="USD">Dólares (USD)</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- 2. Datos del cliente -->
    <div class="panel">
      <div class="panel-head"><span class="num">2</span>Datos del Cliente</div>
      <div class="panel-body">
        <div class="row g-3">
          <div class="col-6 col-sm-2">
            <label class="form-label">Tipo Doc.</label>
            <select name="cli_tipo_doc" class="form-select">
              <option value="6">RUC</option>
              <option value="1">DNI</option>
              <option value="4">C. Ext.</option>
              <option value="7">Pasaporte</option>
            </select>
          </div>
          <div class="col-6 col-sm-4">
            <label class="form-label">N° Documento</label>
            <input type="text" name="cli_num_doc" class="form-control"
                   placeholder="20000000001" maxlength="15" required>
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label">Razón Social / Nombre</label>
            <input type="text" name="cli_razon" class="form-control text-uppercase"
                   placeholder="EMPRESA EJEMPLO S.A.C." required>
          </div>
        </div>
      </div>
    </div>

    <!-- 3. Detalle de ítems -->
    <div class="panel">
      <div class="panel-head">
        <span class="num">3</span>Detalle de Ítems
        <button type="button" class="btn-add ms-auto" onclick="agregarFila()">
          <i class="bi bi-plus-lg"></i> Agregar ítem
        </button>
      </div>
      <div class="panel-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0" id="tablaItems">
            <thead>
              <tr>
                <th class="ps-3" style="width:90px">Código</th>
                <th style="min-width:180px">Descripción</th>
                <th style="width:120px">Unidad</th>
                <th style="width:80px">Cant.</th>
                <th style="width:120px">P.Unit s/IGV</th>
                <th style="width:70px">IGV%</th>
                <th style="width:110px">Total</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody id="itemsBody"></tbody>
          </table>
        </div>
        <div id="sinItems" class="sin-items">
          <i class="bi bi-inbox d-block mb-2"></i>
          Haz clic en <strong>Agregar ítem</strong> para comenzar
        </div>
      </div>
    </div>

    <!-- Totales + emitir -->
    <div class="row g-3 align-items-start">
      <div class="col-sm-5 d-flex align-items-center">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="limpiarItems()">
          <i class="bi bi-arrow-counterclockwise"></i> Limpiar ítems
        </button>
      </div>
      <div class="col-sm-7">
        <div class="total-panel">
          <div class="total-row-item">
            <span>Op. Gravadas</span>
            <span id="resGravadas">0.00</span>
          </div>
          <div class="total-row-item">
            <span>IGV (18%)</span>
            <span id="resIgv">0.00</span>
          </div>
          <hr class="total-divider">
          <div class="d-flex justify-content-between align-items-center">
            <span class="total-final-label">Total a Pagar</span>
            <span id="resTotal" class="total-final-value">0.00</span>
          </div>
          <button type="submit" class="btn-emitir mt-3" id="btnEmitir">
            <i class="bi bi-send-fill me-2"></i>Emitir Factura
          </button>
        </div>
      </div>
    </div>

  </form>
</div><!-- /wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let idx = 0;

const UNIDADES = [
  ['NIU','Unidad'],['ZZ','Servicio'],['KGM','Kg'],
  ['MTR','Metro'],['LTR','Litro'],['BX','Caja'],
];

function optsUnidad(sel) {
  return UNIDADES.map(([v,t]) =>
    `<option value="${v}"${v===sel?' selected':''}>${t} (${v})</option>`
  ).join('');
}

function agregarFila(cod='', desc='', uni='NIU', cant=1, precio='', igvPct=18) {
  const i = idx++;
  document.getElementById('sinItems').style.display = 'none';

  const tr = document.createElement('tr');
  tr.id = `fila_${i}`;
  tr.innerHTML = `
    <td class="ps-3">
      <input type="text" name="items[${i}][cod]" class="form-control form-control-sm"
             value="${cod}" placeholder="P001">
    </td>
    <td>
      <input type="text" name="items[${i}][descripcion]" class="form-control form-control-sm text-uppercase"
             value="${desc}" placeholder="Descripción" required>
    </td>
    <td>
      <select name="items[${i}][unidad]" class="form-select form-select-sm">
        ${optsUnidad(uni)}
      </select>
    </td>
    <td>
      <input type="number" name="items[${i}][cantidad]" class="form-control form-control-sm item-num"
             value="${cant}" min="0.001" step="0.001" required>
    </td>
    <td>
      <input type="number" name="items[${i}][precio]" class="form-control form-control-sm item-num"
             value="${precio}" min="0.01" step="0.01" placeholder="0.00" required>
    </td>
    <td>
      <input type="number" name="items[${i}][igv_pct]" class="form-control form-control-sm item-num"
             value="${igvPct}" min="0" max="100" step="0.01">
    </td>
    <td>
      <input type="text" id="tot_${i}" class="form-control form-control-sm text-end fw-bold bg-light"
             readonly value="0.00">
    </td>
    <td>
      <button type="button" class="btn btn-sm btn-outline-danger px-2" onclick="eliminarFila(${i})">
        <i class="bi bi-trash3"></i>
      </button>
    </td>`;

  document.getElementById('itemsBody').appendChild(tr);
  tr.querySelectorAll('.item-num').forEach(el => el.addEventListener('input', () => recalcFila(i)));
  recalcFila(i);
}

function eliminarFila(i) {
  document.getElementById(`fila_${i}`)?.remove();
  if (!document.querySelectorAll('#itemsBody tr').length)
    document.getElementById('sinItems').style.display = '';
  recalcTotales();
}

function limpiarItems() {
  document.getElementById('itemsBody').innerHTML = '';
  document.getElementById('sinItems').style.display = '';
  recalcTotales();
}

function recalcFila(i) {
  const g = n => parseFloat(document.querySelector(`[name="items[${i}][${n}]"]`)?.value) || 0;
  const total = g('cantidad') * g('precio') * (1 + g('igv_pct') / 100);
  const el = document.getElementById(`tot_${i}`);
  if (el) el.value = total.toFixed(2);
  recalcTotales();
}

function recalcTotales() {
  let grav = 0, igvSum = 0;
  document.querySelectorAll('#itemsBody tr').forEach(tr => {
    const i = tr.id.replace('fila_', '');
    const g = n => parseFloat(tr.querySelector(`[name="items[${i}][${n}]"]`)?.value) || 0;
    const venta = g('cantidad') * g('precio');
    grav   += venta;
    igvSum += venta * g('igv_pct') / 100;
  });
  document.getElementById('resGravadas').textContent = grav.toFixed(2);
  document.getElementById('resIgv').textContent      = igvSum.toFixed(2);
  document.getElementById('resTotal').textContent    = (grav + igvSum).toFixed(2);
}

document.getElementById('formFactura').addEventListener('submit', function(e) {
  if (!document.querySelectorAll('#itemsBody tr').length) {
    e.preventDefault();
    alert('Debe agregar al menos un ítem antes de emitir.');
    return;
  }
  const btn = document.getElementById('btnEmitir');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando a SUNAT…';
});

agregarFila();
</script>
</body>
</html>
