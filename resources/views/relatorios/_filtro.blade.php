<form method="GET" class="row g-2 align-items-end mb-4 d-print-none">
    <div class="col-auto">
        <label class="form-label small fw-semibold mb-1">De</label>
        <input type="date" name="inicio" value="{{ $inicio->format('Y-m-d') }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <label class="form-label small fw-semibold mb-1">Até</label>
        <input type="date" name="fim" value="{{ $fim->format('Y-m-d') }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <button class="btn btn-sm btn-primary">
            <i class="bi bi-funnel-fill me-1"></i>Filtrar
        </button>
    </div>
    <div class="col-auto ms-auto">
        <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer-fill me-1"></i>Imprimir / PDF
        </button>
    </div>
</form>
