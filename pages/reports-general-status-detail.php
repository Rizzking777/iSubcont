<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('general_status'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username']; // Query ringkasan per job_order

$job_order = $_GET['job_order'] ?? '';
if (empty($job_order)) {
  die("<div class='alert alert-danger'>Job Order tidak ditemukan.</div>");
}

// Fungsi normalisasi size
function normalizeSize($s)
{
  return strtoupper(trim($s)); // semua size jadi uppercase dan trim spasi
}

// 🔹 1️⃣ Ambil informasi umum
$infoQuery = "
    SELECT 
        job_order, po_code, po_item, style, model, ncvs, bucket,
        MIN(date_updated) AS doc_date
    FROM tbl_master_data
     WHERE job_order = '$job_order'
    GROUP BY job_order
";
$info = $conn->query($infoQuery)->fetch_assoc();


// ===============================
// 🔹 2️⃣ Ambil summary progress (AKUMULATIF + VALIDASI ACTION)
// ===============================
$summary = [
  'total_order' => 0,
  'scan_in' => 0,
  'scan_out' => 0,
  'kekurangan' => 0
];

// Total order dari master
$totalOrderQuery = "SELECT SUM(qty) AS total_order FROM tbl_master_data WHERE job_order = '$job_order'";
$totalOrder = $conn->query($totalOrderQuery)->fetch_assoc();
$summary['total_order'] = (float)($totalOrder['total_order'] ?? 0);

// Ambil semua log untuk job_order ini
$logQuery = "
  SELECT action_type, new_data
  FROM tlog_transaksi
  WHERE JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.job_order')) = '$job_order'
  AND action_type IN ('SCAN_IN_WAREHOUSE', 'SCAN_OUT_TO_PRODUCTION')
";
$logResult = $conn->query($logQuery);

while ($log = $logResult->fetch_assoc()) {
  $action = strtoupper(trim($log['action_type']));
  $data = json_decode($log['new_data'], true);

  if (!is_array($data)) continue;

  // Decode komponen_qty (string JSON di dalam JSON)
  $komponenList = $data['komponen_qty'] ?? [];
  if (is_string($komponenList)) {
    $komponenList = json_decode($komponenList, true);
  }

  if (!is_array($komponenList)) continue;

  // Loop semua komponen dan akumulasi qty
  foreach ($komponenList as $item) {
    $qty = (float)($item['qty'] ?? 0);

    if ($action === 'SCAN_IN_WAREHOUSE') {
      $summary['scan_in'] += $qty;
    } elseif ($action === 'SCAN_OUT_TO_PRODUCTION') {
      $summary['scan_out'] += $qty;
    }
  }
}

// Ambil total kekurangan
$kekuranganQuery = "
  SELECT SUM(total_kekurangan) AS kekurangan
  FROM tbl_transaksi_kekurangan
  WHERE job_order = '$job_order' AND status = 'PENDING'
";
$kekurangan = $conn->query($kekuranganQuery)->fetch_assoc();
$summary['kekurangan'] = (float)($kekurangan['kekurangan'] ?? 0);


// ===============================
// 🔹 3️⃣ Ambil pivot LOT-SIZE
// ===============================
$pivotData = [];

$query = "
  SELECT action_type, new_data
  FROM tlog_transaksi
  WHERE action_type IN ('SCAN_IN_WAREHOUSE', 'SCAN_OUT_TO_PRODUCTION')
  AND JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.job_order')) = '$job_order'
";

$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
  $action = $row['action_type'];
  $data = json_decode($row['new_data'], true);

  if (!is_array($data)) continue;

  // Decode lot (bisa berupa string "[1,2]" atau array langsung)
  $lots = $data['lot'] ?? [];
  if (is_string($lots)) {
    $lots = json_decode($lots, true);
  }
  if (!is_array($lots)) $lots = [$lots];

  // Decode komponen_qty (string JSON)
  $komponenList = $data['komponen_qty'] ?? [];
  if (is_string($komponenList)) {
    $komponenList = json_decode($komponenList, true);
  }

  if (!is_array($komponenList)) continue;

  // Loop LOT dan isi pivot
  foreach ($lots as $lot) {
    if (!isset($pivotData[$lot])) $pivotData[$lot] = ['sizes' => []];

    foreach ($komponenList as $item) {
      $size = normalizeSize($item['size'] ?? '');
      $qty  = (float)($item['qty'] ?? 0);
      if (!$size) continue;

      if (!isset($pivotData[$lot]['sizes'][$size])) {
        $pivotData[$lot]['sizes'][$size] = ['scan_in' => 0, 'scan_out' => 0];
      }

      if ($action === 'SCAN_IN_WAREHOUSE') {
        $pivotData[$lot]['sizes'][$size]['scan_in'] += $qty;
      } elseif ($action === 'SCAN_OUT_TO_PRODUCTION') {
        $pivotData[$lot]['sizes'][$size]['scan_out'] += $qty;
      }
    }
  }
}

// ===============================
// 🔹 4️⃣ Ambil data kekurangan detail
// ===============================
$kekuranganQuery = "
  SELECT 
      job_order,
      last_gate AS lot,
      JSON_UNQUOTE(JSON_EXTRACT(komponen_qty, '$[0].size')) AS size,
      total_kekurangan,
      status
  FROM tbl_transaksi_kekurangan
  WHERE job_order = '$job_order'
";
$kekurangan = $conn->query($kekuranganQuery);


?>

<style>
  .toast-progress {
    height: 4px;
    width: 100%;
    overflow: hidden;
    border-radius: 0 0 0.375rem 0.375rem;
  }

  .toast-progress-bar {
    height: 100%;
    animation: progressBar 3s linear forwards;
  }

  @keyframes progressBar {
    0% {
      width: 100%;
    }

    100% {
      width: 0%;
    }
  }

  .select2-container {
    width: 100% !important;
  }

  .select2-selection {
    min-height: 38px;
    /* biar seragam sama form-control bootstrap */
    display: flex;
    align-items: center;
  }

  #addKomponenBtn {
    margin-top: 0px;
    /* atau sesuai kebutuhan */
    margin-bottom: 5px;
  }

  .komponen-row .form-label {
    display: block;
  }

  .komponen-row .form-control {
    width: 100%;
  }

  .qr-center {
    text-align: center;
    margin-top: 10px;
  }

  .match-height {
    height: calc(1.5em + 0.75rem + 2px);
    /* Cocokkan dengan .form-control Bootstrap */
    display: flex;
    justify-content: center;
    align-items: center;
  }

  @media print {
    @page {
      size: 50mm auto;
      /* Lebar 50mm, tinggi otomatis */
      margin: 0;
      /* Hilangkan margin default browser */
    }

    body {
      width: 50mm;
      font-size: 10px;
      /* Bisa kecilkan font supaya pas */
    }

    /* Hanya print konten modal */
    body * {
      visibility: hidden;
    }

    #barcodeContent<?= $row['id_trans']; ?>,
    #barcodeContent<?= $row['id_trans']; ?>* {
      visibility: visible;
    }

    #barcodeContent<?= $row['id_trans']; ?> {
      position: absolute;
      left: 0;
      top: 0;
    }
  }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>iSubcont - Reports</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="../assets/img/Logo-Stg.png" rel="icon">
  <link href="../assets/img/Logo-Stg.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="../assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="../assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="../assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="../assets/css/style.css" rel="stylesheet">

  <!-- Tambahkan pustaka Select2 di bagian <head> -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- Pastikan jQuery ada -->

  <!-- Select2 CSS & JS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <!-- Datatables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">

  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>

</head>

<body>

  <!-- Header -->
  <?php
  $page = 'general_status';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Details General Status
      </h1>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">

              <div class="container mt-4">

                <!-- Info Job Order -->
                <div class="row g-2">
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Job Order</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['job_order'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Bucket</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['bucket'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">PO Code</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['po_code'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">PO Item</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['po_item'] ?>">
                  </div>
                </div>

                <div class="row g-2 mt-2">
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Model</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['model'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Style</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['style'] ?>">
                  </div>
                  <div class="col-md-6">
                    <label style="font-weight: bold;">NCVS</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['ncvs'] ?>">
                  </div>
                </div>
              </div>

              <!-- ==========================
                    SUMMARY PROGRESS CHART
                ========================== -->
              <div class="card mt-3">
                <div class="card-body">
                  <h5 class="card-title mb-3">Summary Progress</h5>
                  <canvas id="summaryChart" height="120"></canvas>
                </div>
              </div>

              <!-- ==========================
                  DETAIL KOMPONEN (PIVOT)
              ========================== -->
              <hr>
                <p class="text-success">Description data per cell</p>
                <p class="text-success fs-4">[ BALANCE IN | BALANCE OUT ]</p>
              <div class="card mt-3">
                <div class="card-body table-responsive">
                  <h5 class="card-title mb-3">Detail Size</h5>
                  <table id="tablePivot" class="table table-bordered text-center align-middle nowrap" style="width:100%">
                    <thead class="table-light" id="pivotHeader"></thead>
                    <tbody id="pivotBody"></tbody>
                  </table>
                </div>
              </div>

              <!-- ==========================
                  DETAIL KEKURANGAN
              ========================== -->
              <hr>
              <div class="card mt-3">
                <div class="card-body table-responsive">
                  <h5 class="card-title mb-3">Detail Kekurangan</h5>
                  <table id="tableKekurangan" class="table table-bordered text-center align-middle nowrap" style="width:100%">
                    <thead class="table-light">
                      <tr>
                        <th>Status</th>
                        <th>Size</th>
                        <th>Total Kekurangan</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody id="kekuranganBody"></tbody>
                  </table>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>
    </section>

  </main><!-- End #main -->

  <!-- ======= Footer ======= -->
  <?php include_once __DIR__ . '/../includes/footer.php' ?>
  <!-- End Footer -->

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <script src="../assets/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/vendor/chart.js/chart.umd.js"></script>
  <script src="../assets/vendor/echarts/echarts.min.js"></script>
  <script src="../assets/vendor/quill/quill.min.js"></script>
  <script src="../assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="../assets/vendor/tinymce/tinymce.min.js"></script>
  <script src="../assets/vendor/php-email-form/validate.js"></script>

  <!-- Template Main JS File -->
  <script src="../assets/js/main.js"></script>

  <!-- Select2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- Select2 JS -->
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <!-- DataTables core -->
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

  <!-- Responsive extension -->
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

  <!-- Buttons extension -->
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

  <script>
    $(function() {
      $("#example1").DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": false,
        // "buttons": ["copy", "excel"]
      }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
      $('#example2').DataTable({
        "paging": true,
        "lengthChange": false,
        "searching": false,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
      });
    });
  </script>

  <script>
    $(document).ready(function() {
      $('#example1').DataTable({
        scrollX: true,
        destroy: true // biar gak error reinit
      });
    });
  </script>

  <?php include_once __DIR__ . '/../includes/notification.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const toastEl = document.getElementById('liveToast');
      if (toastEl) {
        const toast = new bootstrap.Toast(toastEl, {
          delay: 5000
        });
        toast.show();
      }
    });
  </script>

  <script>
    function loadSummaryChart(data) {
      const ctx = document.getElementById("summaryChart").getContext("2d");

      // Ambil data
      const totalOrder = data.total_order || 0;
      const scanIn = data.scan_in || 0;
      const scanOut = data.scan_out || 0;
      const kekurangan = data.kekurangan || 0;

      // Hitung progress
      const progressIn = totalOrder > 0 ? scanIn / totalOrder : 0;
      const progressOut = totalOrder > 0 ? scanOut / totalOrder : 0;

      // Gradient hijau lembut untuk "total order"
      const gradientGreen = ctx.createLinearGradient(0, 0, 0, 200);
      gradientGreen.addColorStop(0, "#6ee7b7"); // hijau muda atas
      gradientGreen.addColorStop(1, "#10b981"); // hijau bawah

      // Warna dasar
      let colorTotal = gradientGreen;
      let colorIn = "#17a2b8"; // biru default
      let colorOut = "#28a745"; // hijau normal
      let colorKekurangan = "#f28b82"; // merah lembut

      // Warna kuning lembut (belum 50%)
      const softYellow = "#facc15"; // tailwind yellow-400

      // Threshold logika
      const goodThreshold = 0.95;
      const warningThreshold = 0.5;

      // Logika warna SCAN IN
      if (progressIn >= goodThreshold) {
        colorIn = gradientGreen; // full hijau
      } else if (progressIn >= warningThreshold) {
        colorIn = "#17a2b8"; // biru lembut
      } else {
        colorIn = softYellow; // kuning lembut
      }

      // Logika warna SCAN OUT
      if (progressOut >= goodThreshold) {
        colorOut = gradientGreen;
      } else if (progressOut >= warningThreshold) {
        colorOut = "#28a745"; // hijau normal
      } else {
        colorOut = softYellow; // kuning lembut
      }

      new Chart(ctx, {
        type: "bar",
        data: {
          labels: ["Total Order", "Scan In", "Scan Out", "Kekurangan"],
          datasets: [{
            label: "Jumlah",
            data: [totalOrder, scanIn, scanOut, kekurangan],
            backgroundColor: [colorTotal, colorIn, colorOut, colorKekurangan],
            borderRadius: 8,
            barThickness: 28,
            borderSkipped: false,
            categoryPercentage: 0, // makin kecil → jarak antar bar makin rapat
            barPercentage: 0 // makin kecil → batang makin ramping

          }]
        },
        options: {
          indexAxis: "y",
          responsive: true,
          scales: {
            x: {
              beginAtZero: true,
              grid: {
                display: false
              },
              ticks: {
                color: "#555",
                font: {
                  size: 13
                }
              }
            },
            y: {
              grid: {
                display: false
              },
              ticks: {
                color: "#333",
                font: {
                  weight: "bold"
                }
              }
            }
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: "#fff",
              titleColor: "#333",
              bodyColor: "#000",
              borderColor: "#ddd",
              borderWidth: 1,
              callbacks: {
                label: ctx => `${ctx.parsed.x}`
              }
            }
          }
        }
      });
    }
  </script>


  <script>
    // --- Data summary dari PHP ---
    const summaryData = <?= json_encode($summary) ?>;

    // --- Panggil chart biar langsung tampil ---
    loadSummaryChart(summaryData);
  </script>

  <script>
    function renderPivotTable(pivotData) {
      if (!pivotData || pivotData.length === 0) return;

      const sizes = [...new Set(pivotData.flatMap(row => Object.keys(row.sizes)))];
      let headerHtml = `<tr><th>LOT</th>${sizes.map(size => `<th>${size}</th>`).join("")}</tr>`;
      let bodyHtml = "";

      pivotData.forEach(row => {
        let rowHtml = `<tr><td>${row.lot}</td>`;
        sizes.forEach(size => {
          const val = row.sizes[size] ? `${row.sizes[size].scan_in} | ${row.sizes[size].scan_out}` : "-";
          rowHtml += `<td>${val}</td>`;
        });
        rowHtml += "</tr>";
        bodyHtml += rowHtml;
      });

      document.getElementById("pivotHeader").innerHTML = headerHtml;
      document.getElementById("pivotBody").innerHTML = bodyHtml;
    }
  </script>

  <script>
    function renderKekuranganTable(data) {
      let html = "";
      data.forEach(row => {
        let badgeClass = "";
        let badgeText = row.status;

        if (row.status === "pending") {
          badgeClass = "bg-warning text-dark"; // kuning
        } else if (row.status === "confirmed" || row.status === "DONE") {
          badgeClass = "bg-success"; // hijau
        } else {
          badgeClass = "bg-secondary"; // fallback
        }

        html += `
        <tr>
          <td>${row.lot}</td>
          <td>${row.size}</td>
          <td>${row.total_kekurangan}</td>
          <td><span class="badge rounded-pill ${badgeClass}">${badgeText}</span></td>
        </tr>`;
      });

      document.getElementById("kekuranganBody").innerHTML = html;
    }
  </script>


  <script>
    // --- Pivot Table Data ---
    const pivotData = <?= json_encode(array_map(function ($lot, $data) {
                        return [
                          'lot' => $lot,
                          'sizes' => $data['sizes']
                        ];
                      }, array_keys($pivotData), $pivotData)) ?>;

    // Panggil fungsi renderPivotTable
    renderPivotTable(pivotData);

    // --- Kekurangan Data ---
    const kekuranganData = <?= json_encode($kekurangan->fetch_all(MYSQLI_ASSOC)) ?>;

    // Panggil fungsi renderKekuranganTable
    renderKekuranganTable(kekuranganData);
  </script>
</body>

</html>