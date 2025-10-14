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
$komponen_map = [];
$res_all_komponen = $conn->query("SELECT id_komponen, nama_komponen FROM tbl_komponen WHERE is_deleted = 0");
while ($k = $res_all_komponen->fetch_assoc()) {
  $komponen_map[$k['id_komponen']] = $k['nama_komponen'];
}

// Ambil data kekurangan + join tbl_transaksi
$query_kekurangan = "
    SELECT 
        tk.id_kekurangan,
        tk.id_trans_asal,
        tk.job_order,
        tk.komponen_qty AS tk_komponen_qty,
        tk.total_kekurangan,
        tk.status AS tk_status,
        t.ncvs,
        t.bucket,
        t.po_code,
        t.po_item,
        t.model,
        t.style
    FROM tbl_transaksi_kekurangan tk
    LEFT JOIN tbl_transaksi t ON tk.id_trans_asal = t.id_trans
    $where
    ORDER BY tk.created_at DESC
";
$res_kekurangan = $conn->query($query_kekurangan);
if (!$res_kekurangan) die("Query gagal: " . $conn->error);

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
              <div class="table-responsive" style="overflow-x: auto;">
                <table id="tbl_kekurangan" class="table table-bordered table-striped text-center align-middle nowrap" style="width:100%">
                  <thead class="table-light">
                    <tr>
                      <th style="text-align: center;">No</th>
                      <th style="text-align: center;">Job Order</th>
                      <th style="text-align: center;">NCVS</th>
                      <th style="text-align: center;">Bucket</th>
                      <th style="text-align: center;">Po Code</th>
                      <th style="text-align: center;">Po Item</th>
                      <th style="text-align: center;">Model</th>
                      <th style="text-align: center;">Style</th>
                      <th style="text-align: center;">Komponen</th>
                      <th style="text-align: center;">Total Kurang</th>
                      <th style="text-align: center;">Status</th>
                      <th style="text-align: center;">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $no = 1;

                    // Debug session & filter
                    echo "<!-- DEBUG SESSION: role_name={$_SESSION['role_name']}, nik={$_SESSION['nik_user']}, type_scan={$_SESSION['type_scan']} -->";
                    echo "<!-- DEBUG WHERE: {$where} -->";

                    if (!$res_kekurangan || $res_kekurangan->num_rows == 0) {
                      echo "<tr>";
                      for ($i = 0; $i < 12; $i++) {
                        if ($i == 1) {
                          echo "<td class='text-center text-muted'>Tidak ada data kekurangan yang perlu dikonfirmasi.</td>";
                        } else {
                          echo "<td>&nbsp;</td>";
                        }
                      }
                      echo "</tr>";
                    } else {
                      while ($row = $res_kekurangan->fetch_assoc()) {
                        $job_order = htmlspecialchars($row['job_order'] ?? '-', ENT_QUOTES, 'UTF-8');
                        $ncvs      = htmlspecialchars($row['ncvs'] ?? '-', ENT_QUOTES, 'UTF-8');
                        $bucket    = htmlspecialchars($row['bucket'] ?? '-', ENT_QUOTES, 'UTF-8');
                        $po_code   = htmlspecialchars($row['po_code'] ?? '-', ENT_QUOTES, 'UTF-8');
                        $po_item   = htmlspecialchars($row['po_item'] ?? '-', ENT_QUOTES, 'UTF-8');
                        $model     = htmlspecialchars($row['model'] ?? '-', ENT_QUOTES, 'UTF-8');
                        $style     = htmlspecialchars($row['style'] ?? '-', ENT_QUOTES, 'UTF-8');
                        $total_kekurangan = intval($row['total_kekurangan'] ?? 0);
                        $status = strtolower(trim($row['tk_status'] ?? ''));
                        $status_badge = $status === 'pending'
                          ? "<span class='badge bg-warning text-dark'>Pending</span>"
                          : "<span class='badge bg-success'>Confirmed</span>";

                        // Proses komponen JSON
                        $komponen_display = [];

                        if (!empty($row['tk_komponen_qty'])) {
                          $komponen_list = json_decode($row['tk_komponen_qty'], true);
                          if (is_array($komponen_list)) {
                            $grouped = []; // tampung per komponen

                            // Loop data dan kelompokkan berdasarkan id_komponen
                            foreach ($komponen_list as $item) {
                              $id_komponen = intval($item['komponen'] ?? 0);
                              $size = htmlspecialchars($item['size'] ?? '-', ENT_QUOTES, 'UTF-8');

                              // Ambil nilai kekurangan, fallback ke qty kalau kekurangan tidak ada
                              $kekurangan = isset($item['kekurangan'])
                                ? intval($item['kekurangan'])
                                : intval($item['qty'] ?? 0);

                              if (!isset($grouped[$id_komponen])) {
                                $grouped[$id_komponen] = [];
                              }

                              // Simpan tiap size dengan format "size (qty)"
                              $grouped[$id_komponen][] = "{$size} ({$kekurangan})";
                            }

                            // Bangun teks tampilan
                            foreach ($grouped as $komp_id => $sizes) {
                              $nama_komponen = $komponen_map[$komp_id] ?? "Komponen {$komp_id}";
                              $size_str = implode(', ', $sizes);
                              $komponen_display[] = "{$nama_komponen} : {$size_str}";
                            }
                          }
                        }

                        $komponen_html = implode('<br>', $komponen_display) ?: '-';

                        // Debug per row
                        echo "<!-- DEBUG ROW: " . htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') . " -->";

                        echo "<tr>";
                        echo "<td class='text-center'>{$no}</td>";
                        echo "<td>{$job_order}</td>";
                        echo "<td>{$ncvs}</td>";
                        echo "<td>{$bucket}</td>";
                        echo "<td>{$po_code}</td>";
                        echo "<td>{$po_item}</td>";
                        echo "<td>{$model}</td>";
                        echo "<td>{$style}</td>";
                        echo "<td>{$komponen_html}</td>";
                        echo "<td class='text-center'>{$total_kekurangan}</td>";
                        echo "<td class='text-center'>{$status_badge}</td>";
                        echo "<td class='text-center'>";
                        if ($status === 'pending') {
                          echo '<form action="../config/function.php" method="POST" style="display:inline;">
            <input type="hidden" name="action" value="confirm_kekurangan">
            <input type="hidden" name="id_kekurangan" value="' . intval($row['id_kekurangan']) . '">
            <button 
                class="btn btn-success btn-sm" 
                type="submit"
                onclick="return confirm(\'Yakin ingin mengonfirmasi kekurangan ini?\')" 
                data-bs-toggle="tooltip" 
                title="Konfirmasi kekurangan">
                <i class="bi bi-check-circle"></i>
            </button>
          </form>';
                        } else {
                          echo '<button 
            class="btn btn-secondary btn-sm" 
            disabled 
            data-bs-toggle="tooltip" 
            title="Sudah dikonfirmasi">
            <i class="bi bi-check2-all"></i>
          </button>';
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
        autoWidth: false,
        scrollX: true, // scroll horizontal
        responsive: false, // matikan responsive supaya td tidak pecah
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

  <!-- <script>
    document.addEventListener("DOMContentLoaded", function() {
      document.querySelectorAll(".confirmBtn").forEach(btn => {
        btn.addEventListener("click", function() {
          const id = this.dataset.id;

          if (!confirm("Yakin ingin mengonfirmasi kekurangan ini?")) return;

          fetch("./../config//function.php", {
              method: "POST",
              headers: {
                "Content-Type": "application/x-www-form-urlencoded"
              },
              body: "action=confirm_kekurangan&id_kekurangan=" + encodeURIComponent(id)
            })
            .then(res => res.text()) // ganti dari res.json() ke res.text()
            .then(text => {
              try {
                const data = JSON.parse(text);
                alert(data.message);
                if (data.status === "success") location.reload();
              } catch (e) {
                // Kalau JSON gagal parse (misal HTML error), tampilkan isinya
                alert("❌ Server tidak mengembalikan JSON:\n" + text);
              }
            })
            .catch(err => alert("❌ Fetch error: " + err));
        });
      });
    });
  </script> -->

</body>

</html>