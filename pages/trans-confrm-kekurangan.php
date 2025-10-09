<?php
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('konfirmasi_kekurangan');

$nik = $_SESSION['nik_user'] ?? '';
$username = $_SESSION['username'] ?? '';
$type_scan = $_SESSION['type_scan'] ?? '';
$role_name = $_SESSION['role_name'] ?? '';

// --- filter role aman ---
$where = "WHERE 1=0"; // default aman
if (!empty($role_name)) {
  if (strtoupper($role_name) === 'SUPERADMIN') {
    $where = "WHERE tk.status = 'pending'";
  } else {
    switch (strtoupper($role_name)) {
      case 'SCAN IN VENDOR':
        $where = "WHERE tk.status = 'pending' AND tk.last_gate = 'SCAN_IN_VENDOR'";
        break;
      case 'SCAN OUT VENDOR':
        $where = "WHERE tk.status = 'pending' AND tk.last_gate = 'SCAN_OUT_VENDOR'";
        break;
      case 'SCAN IN INCOMING':
        $where = "WHERE tk.status = 'pending' AND tk.last_gate = 'SCAN_IN_INCOMING'";
        break;
      case 'SCAN CHECK QC':
        $where = "WHERE tk.status = 'pending' AND tk.last_gate = 'SCAN_CHECK_QC'";
        break;
    }
  }
}

// --- query transaksi ---
$query_kekurangan = "
    SELECT 
        tk.id_kekurangan,
        tk.id_trans_asal,
        tk.job_order,
        tk.komponen_qty,
        tk.defect_qty,
        tk.total_kekurangan,
        tk.status,
        tk.last_gate,
        tk.created_at,
        tk.updated_at,
        t.barcode,
        t.created_by
    FROM tbl_transaksi_kekurangan tk
    LEFT JOIN tbl_transaksi t ON tk.id_trans_asal = t.id_trans
    $where
    ORDER BY tk.created_at DESC
";

$res_kekurangan = $conn->query($query_kekurangan);
if (!$res_kekurangan) {
  die("Query gagal: " . $conn->error);
}
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
</style>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>iSubcont - Transactions</title>
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

</head>

<body>

  <!-- Header -->
  <?php
  $page = 'konfirmasi_kekurangan';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Konfirmasi Kekurangan Komponen
      </h1>
    </div>

    <!-- DEBUG: cek role dan filter SQL -->
    <?php
    echo "<!-- DEBUG ROLE: " . htmlspecialchars($role_name, ENT_QUOTES, 'UTF-8') .
      ", WHERE: " . htmlspecialchars($where, ENT_QUOTES, 'UTF-8') . " -->";
    ?>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body" style="margin-top: 10px;">
              <div class="table-responsive">
                <table id="tbl_kekurangan" class="table table-bordered table-striped text-center align-middle nowrap" style="width:100%">
                  <thead class="table-light">
                    <tr>
                      <th class="text-center">No</th>
                      <th class="text-center">Job Order</th>
                      <th class="text-center">Barcode</th>
                      <th class="text-center">Total Kekurangan</th>
                      <th class="text-center">Gate Asal</th>
                      <th class="text-center">Status</th>
                      <th class="text-center">Dibuat</th>
                      <th class="text-center">Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $no = 1;

                    // Debug session & filter
                    echo "<!-- DEBUG SESSION: role_name={$_SESSION['role_name']}, nik={$_SESSION['nik_user']}, type_scan={$_SESSION['type_scan']} -->";
                    echo "<!-- DEBUG WHERE: {$where} -->";

                    if (!$res_kekurangan || $res_kekurangan->num_rows == 0) {
                      // Row dummy aman: 8 td tanpa colspan
                      echo "<tr>";
                      echo "<td class='text-center'>{$no}</td>";
                      echo "<td class='text-center text-muted'>Tidak ada data kekurangan yang perlu dikonfirmasi.</td>";
                      echo "<td>&nbsp;</td>";
                      echo "<td>&nbsp;</td>";
                      echo "<td>&nbsp;</td>";
                      echo "<td>&nbsp;</td>";
                      echo "<td>&nbsp;</td>";
                      echo "<td class='text-center'>&nbsp;</td>";
                      echo "</tr>";
                    } else {
                      while ($row = $res_kekurangan->fetch_assoc()) {
                        $job_order = htmlspecialchars($row['job_order'] ?? '-', ENT_QUOTES, 'UTF-8');
                        $barcode = htmlspecialchars($row['barcode'] ?? '-', ENT_QUOTES, 'UTF-8');
                        $total_kekurangan = intval($row['total_kekurangan'] ?? 0);
                        $last_gate = htmlspecialchars($row['last_gate'] ?? '-', ENT_QUOTES, 'UTF-8');
                        $status = strtolower(trim($row['status'] ?? ''));
                        $status_badge = $status === 'pending'
                          ? "<span class='badge bg-warning text-dark'>Pending</span>"
                          : "<span class='badge bg-success'>Confirmed</span>";
                        $created_at = !empty($row['created_at']) ? date('d M Y H:i', strtotime($row['created_at'])) : '-';

                        // Debug per row
                        echo "<!-- DEBUG ROW: " . htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') . " -->";

                        echo "<tr>";
                        echo "<td class='text-center'>{$no}</td>";
                        echo "<td>{$job_order}</td>";
                        echo "<td>{$barcode}</td>";
                        echo "<td class='text-center'>{$total_kekurangan}</td>";
                        echo "<td class='text-center'>{$last_gate}</td>";
                        echo "<td class='text-center'>{$status_badge}</td>";
                        echo "<td class='text-center'>{$created_at}</td>";
                        echo "<td class='text-center'>";
                        if ($status === 'pending') {
                          echo "<button class='btn btn-success btn-sm confirmBtn' data-id='" . intval($row['id_kekurangan']) . "'>
                                    <i class='bi bi-check-circle'></i> Konfirmasi
                                  </button>";
                        } else {
                          echo "<button class='btn btn-secondary btn-sm' disabled>
                                    <i class='bi bi-check2-all'></i> Selesai
                                  </button>";
                        }
                        echo "</td>";
                        echo "</tr>";

                        $no++;
                      }
                    }
                    ?>
                  </tbody>
                </table>
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

  <!-- <script>
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
  </script> -->

  <!-- <script>
    $(document).ready(function() {
      $('#example1').DataTable({
        scrollX: true,
        destroy: true // biar gak error reinit
      });
    });
  </script> -->

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

  <!-- Jangan buat inisialisasi DataTable ganda. Pastikan ini hanya ada 1x di halaman (setelah table) -->
  <script>
    $(document).ready(function() {
      $('#tbl_kekurangan').DataTable({
        pageLength: 10,
        lengthChange: false,
        order: [
          [0, 'asc']
        ],
        responsive: true,
        autoWidth: false, // penting supaya jumlah kolom sesuai
        language: {
          search: "Cari:",
          zeroRecords: "Data tidak ditemukan",
          info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
          infoEmpty: "Tidak ada data",
          paginate: {
            first: "Awal",
            last: "Akhir",
            next: "›",
            previous: "‹"
          }
        }
      });

      // tombol konfirmasi
      $(document).on('click', '.confirmBtn', function() {
        const id = $(this).data('id');
        if (!confirm('Konfirmasi kekurangan ini?')) return;
        $.post('ajax/confirm_kekurangan.php', {
          id_kekurangan: id
        }, function(res) {
          try {
            const data = typeof res === 'string' ? JSON.parse(res) : res;
            if (data.success) {
              alert('Berhasil dikonfirmasi.');
              location.reload();
            } else {
              alert('Gagal: ' + (data.message || 'unknown'));
            }
          } catch (e) {
            console.error(res, e);
            alert('Terjadi kesalahan sistem.');
          }
        });
      });
    });
  </script>

</body>

</html>