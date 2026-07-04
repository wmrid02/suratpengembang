<?php
require_once 'includes/auth_check.php';
require_once 'config/database.php';

$base_url = '';
$page_title = 'Dashboard';

// Statistik ringkas
$totalSurat   = $pdo->query("SELECT COUNT(*) FROM surat")->fetchColumn();
$totalTtd     = $pdo->query("SELECT COUNT(*) FROM surat WHERE status_ttd = 'Sudah TTD'")->fetchColumn();
$totalBelum   = $pdo->query("SELECT COUNT(*) FROM surat WHERE status_ttd = 'Belum TTD'")->fetchColumn();

$tahunSekarang = date('Y');
// filter tahun (opsional lewat query string)
$tahun = isset($_GET['tahun']) && ctype_digit($_GET['tahun']) ? $_GET['tahun'] : $tahunSekarang;

// Daftar tahun yang tersedia dari data
$tahunList = $pdo->query("SELECT DISTINCT YEAR(tanggal_ttd) AS thn FROM surat WHERE tanggal_ttd IS NOT NULL ORDER BY thn DESC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($tahunList)) $tahunList = [$tahunSekarang];

// Rekap surat SUDAH TTD per bulan untuk tahun terpilih
$stmt = $pdo->prepare("
    SELECT MONTH(tanggal_ttd) AS bulan, COUNT(*) AS jumlah
    FROM surat
    WHERE status_ttd = 'Sudah TTD' AND YEAR(tanggal_ttd) = ?
    GROUP BY MONTH(tanggal_ttd)
    ORDER BY bulan
");
$stmt->execute([$tahun]);
$rekapRaw = $stmt->fetchAll();

$namaBulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$dataPerBulan = array_fill(1, 12, 0);
foreach ($rekapRaw as $row) {
    $dataPerBulan[(int)$row['bulan']] = (int)$row['jumlah'];
}
$chartLabels = $namaBulan;
$chartValues = array_values($dataPerBulan);

// Surat terbaru
$suratTerbaru = $pdo->query("SELECT * FROM surat ORDER BY created_at DESC LIMIT 5")->fetchAll();

include 'includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card stat-primary">
            <div class="stat-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
            <div>
                <div class="stat-value"><?= $totalSurat ?></div>
                <div class="stat-label">Total Surat</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-success">
            <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="stat-value"><?= $totalTtd ?></div>
                <div class="stat-label">Sudah TTD</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-warning">
            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-value"><?= $totalBelum ?></div>
                <div class="stat-label">Belum TTD</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-bar-chart-fill me-1"></i> Rekapan Surat Sudah TTD per Bulan</span>
        <form method="GET" class="d-flex align-items-center gap-2">
            <label class="mb-0 small text-muted">Tahun:</label>
            <select name="tahun" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <?php foreach ($tahunList as $t): ?>
                    <option value="<?= $t ?>" <?= $t == $tahun ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="card-body">
        <canvas id="chartSurat" height="90"></canvas>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        <i class="bi bi-clock-history me-1"></i> Surat Terbaru Ditambahkan
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Jenis Surat</th>
                        <th>Nomor Surat</th>
                        <th>Tanggal TTD</th>
                        <th>Pejabat TTD</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($suratTerbaru)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data surat.</td></tr>
                <?php else: $no=1; foreach ($suratTerbaru as $s): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($s['jenis_surat']) ?></td>
                        <td><?= htmlspecialchars($s['nomor_surat']) ?></td>
                        <td><?= $s['tanggal_ttd'] ? date('d-m-Y', strtotime($s['tanggal_ttd'])) : '-' ?></td>
                        <td><?= htmlspecialchars($s['pejabat_ttd']) ?></td>
                        <td>
                            <?php if ($s['status_ttd'] === 'Sudah TTD'): ?>
                                <span class="badge badge-success">Sudah TTD</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Belum TTD</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('chartSurat');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Jumlah Surat Sudah TTD',
            data: <?= json_encode($chartValues) ?>,
            backgroundColor: '#4f6ef7',
            borderRadius: 6,
            maxBarThickness: 40
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
